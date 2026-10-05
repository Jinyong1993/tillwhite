<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAssignment;
use App\Models\Product;
use App\Models\ProductStockLot;
use App\Models\ProductStockMovement;
use App\Models\ProductionBatch;
use App\Models\ProductionConfirmation;
use App\Models\ProductionCorrection;
use App\Models\ProductionDailyClosure;
use App\Models\ProductionLoss;
use App\Models\ProductionLossReason;
use App\Models\ProductionOtherOutflow;
use App\Models\ProductionWaste;
use App\Models\ProductionWasteReason;
use App\Models\Store;
use App\Models\StoreCalendarEvent;
use App\Models\StoreDailyStatus;
use App\Models\StoreDailyWeather;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AccessService;
use App\Services\AuditService;
use App\Services\Production\ProductionDailyService;
use App\Services\Production\ProductionRecommendationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class ProductionManagementController extends Controller
{
    /** 생산 업무의 권한, 일일 계산, 감사, 추천 서비스를 주입합니다. */
    public function __construct(private readonly AccessService $accessService, private readonly AuditService $auditService, private readonly ProductionDailyService $dailyService, private readonly ProductionRecommendationService $recommendationService)
    {
    }

    /** 선택한 날짜의 생산·폐기 업무 화면 전체 데이터를 반환합니다. */
    public function daily(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'store_id' => ['nullable', 'integer', 'exists:stores,id']]);
        $storeId = $this->resolveStoreId($user, $validated['store_id'] ?? null);
        $this->assertStoreReadable($user, $storeId);
        $date = $validated['date'];
        $daily = $this->dailyService->build($storeId, $date, true);
        $daily['date'] = $date;
        $daily['store'] = Store::query()->findOrFail($storeId, ['id', 'name']);
        $daily['events'] = $this->eventsForDate($storeId, $date);
        $daily['weather'] = StoreDailyWeather::query()->where('store_id', $storeId)->whereDate('work_date', $date)->first();
        $daily['blocking_previous_date'] = $this->blockingPreviousDate($storeId, $date);
        return response()->json($daily);
    }

    /** 생산 화면에서 필요한 점포, 제품, 근무자와 사유 선택 목록을 반환합니다. */
    public function options(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');
        $validated = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'store_id' => ['nullable', 'integer', 'exists:stores,id']]);
        $storeId = $this->resolveStoreId($user, $validated['store_id'] ?? null);
        $this->assertStoreReadable($user, $storeId);
        $stores = $this->accessibleStores($user);
        $products = Product::query()->with('category:id,name')->where('store_id', $storeId)->where('is_active', true)->orderBy('product_category_id')->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'product_category_id', 'production_department']);
        $workers = $this->availableWorkers($storeId, $validated['date']);
        return response()->json(['stores' => $stores, 'products' => $products, 'workers' => $workers, 'store_read_only' => $user->isHeadOffice() && $user->role?->code !== 'super_admin', 'loss_reasons' => [['value' => 'production_error', 'title' => '생산 실수'], ['value' => 'shape_failure', 'title' => '모양 불량'], ['value' => 'baking_failure', 'title' => '굽기 불량'], ['value' => 'dough_issue', 'title' => '재료·반죽 문제'], ['value' => 'damage', 'title' => '파손'], ['value' => 'other', 'title' => '기타']], 'waste_reasons' => [['value' => 'unsold', 'title' => '당일 잔여'], ['value' => 'quality', 'title' => '품질 저하'], ['value' => 'storage', 'title' => '보관 문제'], ['value' => 'damage', 'title' => '파손'], ['value' => 'carryover_waste', 'title' => '이월 후 폐기'], ['value' => 'other', 'title' => '기타']], 'zero_reasons' => [['value' => 'no_plan', 'title' => '생산계획 없음'], ['value' => 'material_shortage', 'title' => '재료 부족'], ['value' => 'equipment_issue', 'title' => '설비 문제'], ['value' => 'staffing_issue', 'title' => '인력 문제'], ['value' => 'no_demand', 'title' => '주문·수요 없음'], ['value' => 'other', 'title' => '기타']]]);
    }

    /**
     * 생산 화면에서 제품 관리의 기본 정보와 레시피를 읽기 전용으로 반환합니다.
     * 제품 관리 권한과 별개로 생산 조회 권한 범위 안에서만 조회할 수 있습니다.
     */
    public function productDetail(Request $request, int $productId): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');
        $product = Product::withTrashed()->findOrFail($productId);
        $this->assertStoreReadable($user, (int) $product->store_id);

        $product->load([
            'store:id,name',
            'category:id,store_id,name',
            'prices' => fn ($query) => $query
                ->orderByDesc('effective_from')
                ->orderByDesc('id'),
            'recipes.ingredients',
            'recipes.steps',
        ]);

        return response()->json(['product' => $product]);
    }

    /** 한 번의 생산 배치를 저장하고 생산 당시 레시피와 작업자를 함께 고정합니다. */
    public function storeBatch(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.create');
        $data = $this->validateBatch($request);
        $this->accessService->assertStoreDepartment($user, (int) $data['store_id']);
        $this->assertDateUnlocked((int) $data['store_id'], $data['work_date']);
        $this->assertWorkersScheduled((int) $data['store_id'], $data['work_date'], $data['workers'] ?? []);
        $batch = DB::transaction(function () use ($data, $user) {
            $product = Product::query()->with('recipes.ingredients', 'recipes.steps')->findOrFail($data['product_id']);
            abort_unless($product->store_id === (int) $data['store_id'], 422, '선택한 점포의 제품이 아닙니다.');
            $recipe = $product->recipes()->whereNull('deleted_at')->latest('id')->first();
            $batch = ProductionBatch::create(['store_id' => $data['store_id'], 'product_id' => $data['product_id'], 'work_date' => $data['work_date'], 'quantity' => $data['quantity'], 'recipe_id' => $recipe?->id, 'recipe_snapshot' => $recipe ? $recipe->load('ingredients', 'steps')->toArray() : null, 'recipe_deviated' => (bool) ($data['recipe_deviated'] ?? false), 'recipe_deviation_note' => $data['recipe_deviation_note'] ?? null, 'note' => $data['note'] ?? null, 'created_by' => $user->id]);
            foreach ($data['workers'] ?? [] as $worker) {
                DB::table('production_batch_workers')->insert(['production_batch_id' => $batch->id, 'user_id' => $worker['user_id'], 'process_type' => $worker['process_type'] ?? 'all', 'created_at' => now(), 'updated_at' => now()]);
            }
            ProductStockLot::create(['store_id' => $data['store_id'], 'product_id' => $data['product_id'], 'production_batch_id' => $batch->id, 'origin_production_date' => $data['work_date'], 'initial_quantity' => $data['quantity'], 'remaining_quantity' => $data['quantity'], 'status' => 'active']);
            ProductionConfirmation::updateOrCreate(['store_id' => $data['store_id'], 'product_id' => $data['product_id'], 'work_date' => $data['work_date']], ['production_confirmed' => true, 'zero_production_reason' => null, 'confirmed_by' => $user->id]);
            if ((bool) ($data['recommendation_referenced'] ?? false)) {
                $history = collect();
                $target = Carbon::parse($data['work_date']);
                for ($i = 28; $i >= 1; $i--) {
                    $historyDate = $target->copy()->subDays($i)->toDateString();
                    $daily = $this->dailyService->build((int) $data['store_id'], $historyDate);
                    $row = collect($daily['rows'])->firstWhere('id', (int) $data['product_id']);
                    if ($row && $row['production_confirmed']) {
                        $history->push(['date' => $historyDate, ...$row, 'special_day' => !empty($this->eventsForDate((int) $data['store_id'], $historyDate))]);
                    }
                }
                $targetEvents = collect($this->eventsForDate((int) $data['store_id'], $data['work_date']))->filter(fn(array $event) => empty($event['products']) || collect($event['products'])->contains('id', (int) $data['product_id']))->values()->all();
                $recommendation = $this->recommendationService->recommend($history, $target, $targetEvents);
                $this->recommendationService->snapshot((int) $data['store_id'], (int) $data['product_id'], $data['work_date'], $recommendation, $batch->id, $data['recommendation_deviation_reason'] ?? null);
            }
            return $batch;
        });
        $this->auditService->log($user, 'production', 'create', ProductionBatch::class, $batch->id, null, $batch->toArray(), '생산 기록 등록');
        return response()->json(['message' => '생산 기록을 저장했습니다.', 'batch' => $batch], 201);
    }

    /** 마감 전 생산 배치의 수량·메모를 수정하고 동시 수정 충돌을 검사합니다. */
    public function updateBatch(Request $request, ProductionBatch $batch): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.update');
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100000'], 'note' => ['nullable', 'string', 'max:1000'], 'lock_version' => ['required', 'integer', 'min:1']]);
        $this->accessService->assertStoreDepartment($user, (int) $batch->store_id);
        $this->assertDateUnlocked((int) $batch->store_id, $batch->work_date->toDateString());
        abort_unless((int) $batch->lock_version === (int) $data['lock_version'], 409, '다른 사용자가 먼저 이 생산 기록을 변경했습니다. 최신 내용을 다시 확인해주세요.');
        $lotIds = ProductStockLot::query()->where('production_batch_id', $batch->id)->pluck('id');
        $linkedQuantity = (int) ProductStockMovement::query()->whereIn('stock_lot_id', $lotIds)->where('movement_type', 'carryover_out')->max('quantity');
        abort_if($linkedQuantity > (int) $data['quantity'], 422, '이미 연결된 이월 수량보다 생산량을 작게 수정할 수 없습니다. 연결 기록을 먼저 확인해주세요.');
        $before = $batch->toArray();
        DB::transaction(function () use ($batch, $data, $user) {
            $batch->update(['quantity' => $data['quantity'], 'note' => $data['note'] ?? null, 'updated_by' => $user->id, 'lock_version' => $batch->lock_version + 1]);
            ProductStockLot::query()->where('production_batch_id', $batch->id)->update(['initial_quantity' => $data['quantity']]);
        });
        $this->auditService->log($user, 'production', 'update', ProductionBatch::class, $batch->id, $before, $batch->fresh()->toArray(), '생산 기록 수정');
        return response()->json(['message' => '생산 기록을 수정했습니다.']);
    }

    /** 마감 전 생산 배치를 Soft Delete하고 연결 재고가 이미 이월되었다면 삭제를 차단합니다. */
    public function deleteBatch(Request $request, ProductionBatch $batch): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.delete');
        $data = $request->validate(['memo' => ['nullable', 'string', 'max:1000']]);
        $this->accessService->assertStoreDepartment($user, (int) $batch->store_id);
        $this->assertDateUnlocked((int) $batch->store_id, $batch->work_date->toDateString());
        $lotIds = ProductStockLot::query()->where('production_batch_id', $batch->id)->pluck('id');
        abort_if(ProductStockMovement::query()->whereIn('stock_lot_id', $lotIds)->where('movement_type', 'carryover_in')->exists(), 422, '이미 다음 날짜 이월과 연결된 생산 기록입니다. 연결 기록 수정 기능을 이용해주세요.');
        $before = $batch->toArray();
        DB::transaction(function () use ($batch, $lotIds) {
            ProductStockMovement::query()->whereIn('stock_lot_id', $lotIds)->delete();
            ProductStockLot::query()->whereIn('id', $lotIds)->update(['remaining_quantity' => 0, 'status' => 'deleted']);
            $batch->delete();
        });
        $this->auditService->log($user, 'production', 'delete', ProductionBatch::class, $batch->id, $before, ['memo' => $data['memo'] ?? null], '생산 기록 삭제');
        return response()->json(['message' => '생산 기록을 삭제했습니다.']);
    }

    /** 생산하지 않은 제품을 0개로 명시 확인하고 사유를 저장합니다. */
    public function confirmZeroProduction(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.create');
        $data = $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'product_id' => ['required', 'integer', 'exists:products,id'], 'work_date' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', Rule::in(['no_plan', 'material_shortage', 'equipment_issue', 'staffing_issue', 'no_demand', 'other'])], 'note' => ['nullable', 'string', 'max:1000']]);
        $this->accessService->assertStoreDepartment($user, (int) $data['store_id']);
        $this->assertDateUnlocked((int) $data['store_id'], $data['work_date']);
        ProductionConfirmation::updateOrCreate(['store_id' => $data['store_id'], 'product_id' => $data['product_id'], 'work_date' => $data['work_date']], ['production_confirmed' => true, 'zero_production_reason' => $data['reason'], 'zero_production_note' => $data['note'] ?? null, 'confirmed_by' => $user->id]);
        $this->auditService->log($user, 'production', 'confirm', Product::class, (int) $data['product_id'], null, $data, '생산 0개 확인');
        return response()->json(['message' => '생산 0개를 확인했습니다.']);
    }

    /**
     * 제품의 한 가지 수량 처리 업무만 저장합니다.
     *
     * 화면에서 로스·폐기·기타 출고·이월을 각각 독립적으로 수정하므로
     * 선택하지 않은 업무 기록을 빈 배열로 덮어쓰지 않도록 서버에서도 분리 처리합니다.
     */
    public function saveFlow(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.update');

        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', Rule::in(['loss', 'waste', 'other_outflow', 'carryover'])],
            'reasons' => ['array'],
            'reasons.*.reason_code' => ['required_unless:type,carryover', 'string', 'max:60'],
            'reasons.*.reason_text' => ['nullable', 'string', 'max:120'],
            'reasons.*.quantity' => ['required_unless:type,carryover', 'integer', 'min:1'],
            'quantity' => ['required_if:type,carryover', 'nullable', 'integer', 'min:0'],
            'carryover_source' => ['nullable', Rule::in(['today', 'incoming'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->accessService->assertStoreDepartment($user, (int) $data['store_id']);
        $this->assertDateUnlocked((int) $data['store_id'], $data['work_date']);

        DB::transaction(function () use ($data, $user) {
            $storeId = (int) $data['store_id'];
            $productId = (int) $data['product_id'];
            $date = $data['work_date'];
            $type = $data['type'];
            $reasons = $data['reasons'] ?? [];
            $note = $data['note'] ?? null;

            if ($type === 'loss') {
                $this->replaceLosses($storeId, $productId, $date, $reasons, $note, $user->id);
                $this->confirmFlowField($storeId, $productId, $date, 'loss_confirmed', $user->id);
            } elseif ($type === 'waste') {
                $this->replaceWastes($storeId, $productId, $date, $reasons, $note, $user->id);
                $this->confirmFlowField($storeId, $productId, $date, 'waste_confirmed', $user->id);
            } elseif ($type === 'other_outflow') {
                $this->replaceOutflows($storeId, $productId, $date, $reasons, $note, $user->id);
                $this->confirmFlowField($storeId, $productId, $date, 'disposition_confirmed', $user->id);
            } else {
                $this->replaceCarryover(
                    $storeId,
                    $productId,
                    $date,
                    (int) ($data['quantity'] ?? 0),
                    $data['carryover_source'] ?? 'today',
                    $user->id,
                );
                $this->confirmFlowField($storeId, $productId, $date, 'disposition_confirmed', $user->id);
            }

        });

        $labels = [
            'loss' => '로스',
            'waste' => '폐기',
            'other_outflow' => '기타 출고',
            'carryover' => '이월',
        ];
        $label = $labels[$data['type']];

        $this->auditService->log(
            $user,
            'production',
            'update',
            Product::class,
            (int) $data['product_id'],
            null,
            $data,
            "{$label} 처리 저장",
        );

        return response()->json(['message' => "{$label} 처리를 저장했습니다."]);
    }

    /** 선택한 수량 처리 항목의 확인 상태만 갱신합니다. */
    private function confirmFlowField(
        int $storeId,
        int $productId,
        string $date,
        string $field,
        int $userId,
    ): void {
        ProductionConfirmation::updateOrCreate(
            [
                'store_id' => $storeId,
                'product_id' => $productId,
                'work_date' => $date,
            ],
            [
                $field => true,
                'confirmed_by' => $userId,
            ],
        );
    }

    /** 미입력 로스 또는 폐기만 일괄 0개로 확인하며 기존 입력은 건드리지 않습니다. */
    public function bulkConfirmZero(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.update');
        $data = $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'work_date' => ['required', 'date_format:Y-m-d'], 'type' => ['required', Rule::in(['loss', 'waste'])]]);
        $this->accessService->assertStoreDepartment($user, (int) $data['store_id']);
        $this->assertDateUnlocked((int) $data['store_id'], $data['work_date']);
        $products = Product::query()->where('store_id', $data['store_id'])->where('is_active', true)->whereNull('deleted_at')->pluck('id');
        $field = $data['type'] === 'loss' ? 'loss_confirmed' : 'waste_confirmed';
        $confirmedCount = 0;

        try {
            DB::transaction(function () use ($products, $data, $user, $field, &$confirmedCount) {
                foreach ($products as $productId) {
                    $existing = ProductionConfirmation::query()
                        ->where('store_id', $data['store_id'])
                        ->where('product_id', $productId)
                        ->whereDate('work_date', $data['work_date'])
                        ->first();

                    if ($existing?->{$field}) {
                        continue;
                    }

                    // SQLite/MySQL 모두에서 동일 키 재요청을 안전하게 처리하도록 DB upsert를 사용합니다.
                    DB::table('production_confirmations')->upsert(
                        [[
                            'store_id' => $data['store_id'],
                            'product_id' => $productId,
                            'work_date' => $data['work_date'],
                            $field => true,
                            'confirmed_by' => $user->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]],
                        ['store_id', 'product_id', 'work_date'],
                        [$field, 'confirmed_by', 'updated_at'],
                    );
                    $confirmedCount++;
                }
            });
        } catch (\Throwable $error) {
            report($error);
            abort(500, '일괄 확인 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.');
        }
        $label = $data['type'] === 'loss' ? '로스' : '폐기';
        $this->auditService->log($user, 'production', 'confirm', ProductionConfirmation::class, null, null, $data, "미입력 {$label} 전체 0 확인");
        return response()->json(['message' => "미입력 {$label}를 전체 0개로 확인했습니다."]);
    }

    /** 관리자 권한으로 마감된 날짜를 수정 상태로 열고 필수 수정 사유를 기록합니다. */
    public function openCorrection(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.correct');
        $data = $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'work_date' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'min:2', 'max:1000']]);
        $this->accessService->assertStoreDepartment($user, (int) $data['store_id']);
        $closure = ProductionDailyClosure::query()->where('store_id', $data['store_id'])->whereDate('work_date', $data['work_date'])->first();
        abort_unless($closure?->status === 'closed', 422, '마감 완료된 날짜만 마감 후 수정할 수 있습니다.');
        $before = $this->dailyService->build((int) $data['store_id'], $data['work_date']);
        $affectedDates = ProductStockMovement::query()->where('store_id', $data['store_id'])->whereDate('work_date', '>=', $data['work_date'])->whereIn('stock_lot_id', ProductStockLot::query()->where('store_id', $data['store_id'])->whereDate('origin_production_date', $data['work_date'])->pluck('id'))->orderBy('work_date')->pluck('work_date')->map(fn($date) => Carbon::parse($date)->toDateString())->unique()->values();
        DB::transaction(function () use ($data, $user, $closure, $before) {
            ProductionCorrection::create(['correction_group' => (string) Str::uuid(), 'store_id' => $data['store_id'], 'work_date' => $data['work_date'], 'reason' => $data['reason'], 'before_data' => $before, 'created_by' => $user->id]);
            $closure->update(['status' => 'correction_open', 'updated_by' => $user->id]);
        });
        $this->auditService->log($user, 'production', 'correction_open', ProductionDailyClosure::class, $closure->id, $before, $data, '마감 후 수정 시작');
        return response()->json(['message' => '마감 후 수정이 열렸습니다. 수정 후 다시 마감해주세요.', 'affected_dates' => $affectedDates]);
    }

    /** 마감 전 전체 수량과 미완료 제품을 검증하여 최종 확인 화면 데이터를 반환합니다. */
    public function closePreview(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');
        $data = $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'work_date' => ['required', 'date_format:Y-m-d']]);
        $this->assertStoreReadable($user, (int) $data['store_id']);
        $daily = $this->dailyService->build((int) $data['store_id'], $data['work_date']);
        $incomplete = collect($daily['rows'])->where('is_active', true)->where('complete', false)->values();
        return response()->json(['daily' => $daily, 'can_close' => $incomplete->isEmpty(), 'incomplete' => $incomplete, 'weather_status' => StoreDailyWeather::query()->where('store_id', $data['store_id'])->whereDate('work_date', $data['work_date'])->value('collection_status') ?? 'pending']);
    }

    /** 사용자의 최종 확인 뒤 하루 업무를 마감합니다. 미완료나 수량 불일치가 있으면 서버에서 차단합니다. */
    public function closeDay(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.update');
        $data = $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'work_date' => ['required', 'date_format:Y-m-d'], 'daily_memo' => ['nullable', 'string', 'max:2000']]);
        $this->accessService->assertStoreDepartment($user, (int) $data['store_id']);
        $daily = $this->dailyService->build((int) $data['store_id'], $data['work_date']);
        abort_if(collect($daily['rows'])->where('is_active', true)->contains('complete', false), 422, '아직 확인하지 않은 제품이 있어 마감할 수 없습니다.');
        ProductionDailyClosure::updateOrCreate(['store_id' => $data['store_id'], 'work_date' => $data['work_date']], ['status' => 'closed', 'daily_memo' => $data['daily_memo'] ?? null, 'closed_by' => $user->id, 'closed_at' => now(), 'updated_by' => $user->id]);
        StoreDailyWeather::firstOrCreate(['store_id' => $data['store_id'], 'work_date' => $data['work_date']], ['collection_status' => 'pending']);
        $openCorrection = ProductionCorrection::query()->where('store_id', $data['store_id'])->whereDate('work_date', $data['work_date'])->whereNull('after_data')->latest('id')->first();
        if ($openCorrection) {
            $openCorrection->update(['after_data' => $this->dailyService->build((int) $data['store_id'], $data['work_date'])]);
        }
        Session::forget($this->draftKey((int) $data['store_id'], $data['work_date']));
        $this->auditService->log($user, 'production', 'close', ProductionDailyClosure::class, (int) ProductionDailyClosure::query()->where('store_id', $data['store_id'])->whereDate('work_date', $data['work_date'])->value('id'), null, $data, '하루 업무 마감');
        return response()->json(['message' => '하루 업무를 마감했습니다.']);
    }

    /**
     * 월간 달력에 필요한 최소 데이터만 범위 조회로 조립합니다.
     *
     * 일일 상세 계산을 날짜 수만큼 반복하면 월 이동 때 수백 개 쿼리가 발생하므로,
     * 캘린더에서는 월간 합계와 마감·휴점·행사 상태만 한 번씩 모아 사용합니다.
     */
    public function calendar(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'month' => ['required', 'date_format:Y-m'],
        ]);
        $storeId = (int) $data['store_id'];
        $this->assertStoreReadable($user, $storeId);

        $start = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $production = $this->monthlyQuantities(ProductionBatch::query(), $storeId, 'work_date', $startDate, $endDate);
        $losses = $this->monthlyQuantities(ProductionLoss::query(), $storeId, 'work_date', $startDate, $endDate);
        $wastes = $this->monthlyQuantities(ProductionWaste::query(), $storeId, 'attribution_date', $startDate, $endDate);
        $carryIn = $this->monthlyMovementQuantities($storeId, $startDate, $endDate, 'carryover_in');

        $closures = ProductionDailyClosure::query()
            ->where('store_id', $storeId)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->get(['work_date', 'status'])
            ->keyBy(fn ($row) => Carbon::parse($row->work_date)->toDateString());
        $dayStatuses = StoreDailyStatus::query()
            ->where('store_id', $storeId)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->get(['work_date', 'status'])
            ->keyBy(fn ($row) => Carbon::parse($row->work_date)->toDateString());
        $events = StoreCalendarEvent::query()
            ->with('products:id,name')
            ->where('store_id', $storeId)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->orderBy('start_date')
            ->get();

        $days = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateKey = $date->toDateString();
            $produced = (int) ($production[$dateKey] ?? 0);
            $carriedIn = (int) ($carryIn[$dateKey] ?? 0);
            $loss = (int) ($losses[$dateKey] ?? 0);
            $waste = (int) ($wastes[$dateKey] ?? 0);
            $status = $dayStatuses->get($dateKey)?->status === 'closed'
                ? 'store_closed'
                : ($closures->get($dateKey)?->status ?? 'in_progress');
            $dayEvents = $events->filter(fn (StoreCalendarEvent $event) =>
                $event->start_date->toDateString() <= $dateKey
                && $event->end_date->toDateString() >= $dateKey
            )->map(fn (StoreCalendarEvent $event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'title' => $event->title,
                'products' => $event->products->map->only(['id', 'name'])->values(),
            ])->values();

            $days->push([
                'date' => $dateKey,
                'totals' => [
                    'production' => $produced,
                    'carryover' => $carriedIn,
                    'loss' => $loss,
                    'waste' => $waste,
                    'waste_rate' => $produced > 0 ? round($waste / $produced * 100, 1) : null,
                ],
                'status' => $status,
                'events' => $dayEvents,
            ]);
        }

        return response()->json(['days' => $days]);
    }

    /** 월간 수량 모델을 한 번 조회해 날짜별 합계로 묶습니다. */
    private function monthlyQuantities($query, int $storeId, string $dateColumn, string $startDate, string $endDate): array
    {
        return $query
            ->where('store_id', $storeId)
            ->whereBetween($dateColumn, [$startDate, $endDate])
            ->get([$dateColumn, 'quantity'])
            ->groupBy(fn ($row) => Carbon::parse($row->{$dateColumn})->toDateString())
            ->map(fn ($rows) => (int) $rows->sum('quantity'))
            ->all();
    }

    /** 이월 이동 기록을 월 단위로 조회해 날짜별 합계로 묶습니다. */
    private function monthlyMovementQuantities(int $storeId, string $startDate, string $endDate, string $type): array
    {
        return ProductStockMovement::query()
            ->where('store_id', $storeId)
            ->whereBetween('work_date', [$startDate, $endDate])
            ->where('movement_type', $type)
            ->get(['work_date', 'quantity'])
            ->groupBy(fn ($row) => Carbon::parse($row->work_date)->toDateString())
            ->map(fn ($rows) => (int) $rows->sum('quantity'))
            ->all();
    }

    /** 점포 행사·할인·단체주문 같은 분석 조건을 저장합니다. */
    public function storeEvent(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.update');
        $data = $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'event_type' => ['required', 'string', Rule::in(['holiday', 'department_event', 'nearby_event', 'promotion', 'group_order', 'hours_change', 'other'])], 'title' => ['required', 'string', 'max:120'], 'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'], 'discount_type' => ['nullable', 'string', 'max:40'], 'discount_value' => ['nullable', 'numeric', 'min:0'], 'order_quantity' => ['nullable', 'integer', 'min:0'], 'memo' => ['nullable', 'string', 'max:1000'], 'product_ids' => ['array'], 'product_ids.*' => ['integer', 'exists:products,id']]);
        $this->accessService->assertStoreDepartment($user, (int) $data['store_id']);
        $event = DB::transaction(function () use ($data, $user) {
            $event = StoreCalendarEvent::create([...$data, 'created_by' => $user->id]);
            if (!empty($data['product_ids'])) {
                $event->belongsToMany(Product::class, 'store_calendar_event_products', 'event_id', 'product_id')->sync($data['product_ids']);
            }
            return $event;
        });
        $this->auditService->log($user, 'production', 'create', StoreCalendarEvent::class, $event->id, null, $event->toArray(), '캘린더 행사 등록');
        return response()->json(['message' => '캘린더 일정을 저장했습니다.', 'event' => $event], 201);
    }

    /** 날짜를 휴점 또는 정상 영업일로 변경하며 기존 업무 기록이 있는 휴점 지정은 차단합니다. */
    public function setStoreDayStatus(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.update');
        $data = $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'work_date' => ['required', 'date_format:Y-m-d'], 'status' => ['required', Rule::in(['open', 'closed'])], 'reason' => ['nullable', 'string', 'max:1000']]);
        $this->accessService->assertStoreDepartment($user, (int) $data['store_id']);
        if ($data['status'] === 'closed') {
            $daily = $this->dailyService->build((int) $data['store_id'], $data['work_date']);
            $hasBusinessData = collect($daily['rows'])->contains(fn(array $row) => $row['production'] + $row['loss'] + $row['waste'] + $row['other_outflow'] + $row['carryover_out'] > 0);
            abort_if($hasBusinessData, 422, '이미 업무 기록이 있는 날짜는 바로 휴점 처리할 수 없습니다. 기록을 먼저 확인해주세요.');
        }
        StoreDailyStatus::updateOrCreate(['store_id' => $data['store_id'], 'work_date' => $data['work_date']], ['status' => $data['status'], 'reason' => $data['reason'] ?? null, 'created_by' => $user->id, 'updated_by' => $user->id]);
        $this->auditService->log($user, 'production', 'update', StoreDailyStatus::class, (int) StoreDailyStatus::query()->where('store_id', $data['store_id'])->whereDate('work_date', $data['work_date'])->value('id'), null, $data, '영업일 상태 변경');
        return response()->json(['message' => $data['status'] === 'closed' ? '휴점일로 설정했습니다.' : '휴점을 해제했습니다.']);
    }

    /** 기간별 생산·이월·로스·폐기 통계를 반환합니다. */
    public function statistics(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');
        $data = $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $this->assertStoreReadable($user, (int) $data['store_id']);
        $series = collect();
        for ($date = Carbon::parse($data['from']); $date->lte(Carbon::parse($data['to'])); $date->addDay()) {
            $daily = $this->dailyService->build((int) $data['store_id'], $date->toDateString());
            $series->push(['date' => $date->toDateString(), ...$daily['totals']]);
        }
        $productionTotal = (int) $series->sum('production');
        $wasteTotal = (int) $series->sum('waste');

        return response()->json([
            'series' => $series,
            'totals' => [
                'production' => $productionTotal,
                'carryover' => (int) $series->sum('carryover'),
                'loss' => (int) $series->sum('loss'),
                'waste' => $wasteTotal,
                'waste_rate' => $productionTotal > 0
                    ? round(($wasteTotal / $productionTotal) * 100, 1)
                    : null,
            ],
        ]);
    }

    /** 제품별 최근 흐름을 검사해 확인이 필요한 항목과 설명 가능한 추천을 반환합니다. */
    public function analysis(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        $storeId = (int) $data['store_id'];
        $this->assertStoreReadable($user, $storeId);

        $target = Carbon::parse($data['date']);
        $products = Product::query()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        // 같은 날짜의 일일 계산을 제품마다 반복하지 않고 28일을 한 번씩만 계산합니다.
        $historyByProduct = collect();
        for ($i = 28; $i >= 1; $i--) {
            $date = $target->copy()->subDays($i)->toDateString();
            $daily = $this->dailyService->build($storeId, $date);
            $specialDay = ! empty($this->eventsForDate($storeId, $date));

            foreach ($daily['rows'] as $row) {
                if (! $row['production_confirmed']) {
                    continue;
                }

                $historyByProduct->push([
                    'product_id' => $row['id'],
                    'date' => $date,
                    ...$row,
                    'special_day' => $specialDay,
                ]);
            }
        }
        $historyByProduct = $historyByProduct->groupBy('product_id');

        $targetEvents = collect($this->eventsForDate($storeId, $data['date']));
        $items = $products->map(function (Product $product) use ($historyByProduct, $targetEvents, $target) {
            $history = collect($historyByProduct->get($product->id, []))->values();
            $productEvents = $targetEvents
                ->filter(fn (array $event) => empty($event['products']) || collect($event['products'])->contains('id', $product->id))
                ->values()
                ->all();
            $recommendation = $this->recommendationService->recommend($history, $target, $productEvents);
            $recent = $history->take(-5);
            $flags = [];

            if ($recent->where('waste', '>', 0)->count() >= 3) {
                $flags[] = '최근 폐기가 반복되고 있습니다.';
            }
            if ($recent->where('carryover_out', '>', 0)->count() >= 3) {
                $flags[] = '최근 이월이 반복되고 있습니다.';
            }
            if ($recommendation['warning']) {
                $flags[] = $recommendation['warning'];
            }

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'recommendation' => $recommendation,
                'flags' => $flags,
            ];
        })->values();

        return response()->json(['items' => $items]);
    }

    /** 선택한 날짜의 생산 관련 변경 이력을 최신순으로 반환합니다. */
    public function history(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');
        $data = $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'work_date' => ['required', 'date_format:Y-m-d']]);
        $this->assertStoreReadable($user, (int) $data['store_id']);
        $logs = \App\Models\AuditLog::query()->with('user:id,name')->where('domain', 'production')->where(function ($query) use ($data) {
            $query->where('new_values->work_date', $data['work_date'])->orWhere('new_values->target_date', $data['work_date'])->orWhereDate('created_at', $data['work_date']);
        })->latest('id')->limit(100)->get(['id', 'user_id', 'action', 'target_type', 'target_id', 'description', 'old_values', 'new_values', 'created_at']);
        return response()->json(['logs' => $logs]);
    }

    /** 현재 사용자의 생산 다단계 입력 임시저장을 Laravel Session에서 조회합니다. */
    public function draft(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');
        $data = $request->validate(['store_id' => ['required', 'integer'], 'work_date' => ['required', 'date_format:Y-m-d']]);
        return response()->json(['draft' => Session::get($this->draftKey((int) $data['store_id'], $data['work_date']))]);
    }

    /** 생산 다단계 입력 내용을 현재 로그인 세션에 임시저장합니다. */
    public function saveDraft(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.create');
        $data = $request->validate(['store_id' => ['required', 'integer'], 'work_date' => ['required', 'date_format:Y-m-d'], 'draft' => ['required', 'array']]);
        Session::put($this->draftKey((int) $data['store_id'], $data['work_date']), $data['draft']);
        return response()->json(['message' => '작성 중인 내용을 임시저장했습니다.']);
    }

    /** 현재 날짜의 생산 임시저장만 삭제하고 실제 업무 기록은 건드리지 않습니다. */
    public function deleteDraft(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.create');
        $data = $request->validate(['store_id' => ['required', 'integer'], 'work_date' => ['required', 'date_format:Y-m-d']]);
        Session::forget($this->draftKey((int) $data['store_id'], $data['work_date']));
        return response()->json(['message' => '임시저장을 삭제했습니다.']);
    }

    /** 인증 사용자를 반환하고 비정상 요청은 인증 오류로 차단합니다. */
    private function user(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user, 401, '로그인이 필요합니다.');
        return $user;
    }

    /** 일반 직원은 소속 점포, 본사/최고 관리자는 요청 점포를 사용합니다. */
    private function resolveStoreId(User $user, ?int $requestedStoreId): int
    {
        if ($user->role?->code === 'super_admin' || $user->isHeadOffice()) {
            return $requestedStoreId ?? (int) Store::query()->where('name', '무역점')->value('id');
        }
        abort_unless($user->store_id, 403, '소속 점포가 없습니다.');
        return (int) $user->store_id;
    }

    /** 조회 가능한 점포인지 서버에서 다시 확인합니다. */
    private function assertStoreReadable(User $user, int $storeId): void
    {
        if ($user->role?->code === 'super_admin' || $user->isHeadOffice()) {
            return;
        }
        abort_unless((int) $user->store_id === $storeId, 403, '다른 점포의 데이터는 조회할 수 없습니다.');
    }

    /** 사용자가 선택할 수 있는 점포 목록을 권한 범위에 맞게 반환합니다. */
    private function accessibleStores(User $user)
    {
        if ($user->role?->code === 'super_admin' || $user->isHeadOffice()) {
            return Store::query()->where('status', 'active')->orderByRaw("CASE WHEN name = '무역점' THEN 0 ELSE 1 END")->orderBy('name')->get(['id', 'name']);
        }
        return Store::query()->whereKey($user->store_id)->get(['id', 'name']);
    }

    /** 생산 배치 요청을 검증합니다. */
    private function validateBatch(Request $request): array
    {
        return $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'product_id' => ['required', 'integer', 'exists:products,id'], 'work_date' => ['required', 'date_format:Y-m-d'], 'quantity' => ['required', 'integer', 'min:1', 'max:100000'], 'recipe_deviated' => ['boolean'], 'recipe_deviation_note' => ['nullable', 'required_if:recipe_deviated,true', 'string', 'max:1000'], 'note' => ['nullable', 'string', 'max:1000'], 'recommendation_referenced' => ['nullable', 'boolean'], 'recommendation_deviation_reason' => ['nullable', 'string', 'max:1000'], 'workers' => ['array'], 'workers.*.user_id' => ['required', 'integer', 'exists:users,id'], 'workers.*.process_type' => ['nullable', 'string', Rule::in(['all', 'mixing', 'shaping', 'proofing', 'oven', 'other'])]]);
    }

    /**
     * 해당 날짜의 근무자를 우선 반환하고, 근무표가 비어 있으면 점포 소속 재직자를 보완합니다.
     * 근무표 미등록 때문에 생산 입력 자체가 막히지 않도록 하되 다른 점포 직원은 포함하지 않습니다.
     */
    private function availableWorkers(int $storeId, string $date)
    {
        $scheduledIds = WorkSchedule::query()
            ->where('store_id', $storeId)
            ->whereDate('work_date', $date)
            ->pluck('user_id');

        $assignedIds = EmployeeAssignment::query()
            ->where('store_id', $storeId)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->pluck('user_id');

        $workerIds = $scheduledIds->merge($assignedIds)->unique();
        if ($workerIds->isEmpty()) {
            $workerIds = User::query()->where('store_id', $storeId)->pluck('id');
        }

        return User::query()
            ->whereIn('id', $workerIds)
            ->where('employment_status', 'active')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'department']);
    }

    /** 작업자로 선택된 직원이 해당 날짜에 실제 근무 예정인지 확인합니다. */
    private function assertWorkersScheduled(int $storeId, string $date, array $workers): void
    {
        if (empty($workers)) {
            return;
        }
        $ids = collect($workers)->pluck('user_id')->unique()->values();
        $allowed = $this->availableWorkers($storeId, $date)->pluck('id');
        abort_unless($ids->diff($allowed)->isEmpty(), 422, '선택한 작업자의 점포 소속 또는 재직 상태를 확인해주세요.');
    }

    /** 마감된 날짜의 일반 수정을 차단합니다. */
    private function assertDateUnlocked(int $storeId, string $date): void
    {
        $closed = ProductionDailyClosure::query()->where('store_id', $storeId)->whereDate('work_date', $date)->where('status', 'closed')->exists();
        abort_if($closed, 422, '이미 마감된 날짜입니다. 마감 후 수정 기능을 이용해주세요.');
    }

    /** 선택 날짜에 적용되는 행사 목록을 반환합니다. */
    private function eventsForDate(int $storeId, string $date): array
    {
        return StoreCalendarEvent::query()->with('products:id,name')->where('store_id', $storeId)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->orderBy('start_date')->get()->toArray();
    }

    /** 선택 날짜보다 이전에 남아 있는 가장 오래된 미마감 영업일을 찾습니다. */
    private function blockingPreviousDate(int $storeId, string $date): ?string
    {
        $start = Carbon::parse($date)->subDays(30);
        $target = Carbon::parse($date)->subDay();
        for ($cursor = $start; $cursor->lte($target); $cursor->addDay()) {
            $closedDay = StoreDailyStatus::query()->where('store_id', $storeId)->whereDate('work_date', $cursor)->where('status', 'closed')->exists();
            if ($closedDay) {
                continue;
            }
            $hasData = ProductionBatch::query()->where('store_id', $storeId)->whereDate('work_date', $cursor)->exists() || ProductionConfirmation::query()->where('store_id', $storeId)->whereDate('work_date', $cursor)->exists();
            if (!$hasData) {
                continue;
            }
            $closed = ProductionDailyClosure::query()->where('store_id', $storeId)->whereDate('work_date', $cursor)->where('status', 'closed')->exists();
            if (!$closed) {
                return $cursor->toDateString();
            }
        }
        return null;
    }

    /** 로스 기록과 사유 배분을 현재 입력값으로 교체합니다. */
    private function replaceLosses(int $storeId, int $productId, string $date, array $reasons, ?string $note, int $userId): void
    {
        $old = ProductionLoss::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('work_date', $date)->get();
        foreach ($old as $record) {
            ProductionLossReason::query()->where('production_loss_id', $record->id)->delete();
            $record->delete();
        }
        $total = collect($reasons)->sum('quantity');
        if ($total === 0) {
            return;
        }
        $loss = ProductionLoss::create(['store_id' => $storeId, 'product_id' => $productId, 'work_date' => $date, 'quantity' => $total, 'note' => $note, 'created_by' => $userId]);
        foreach ($reasons as $reason) {
            ProductionLossReason::create(['production_loss_id' => $loss->id, ...$reason]);
        }
    }

    /** 폐기 기록과 사유 배분을 현재 입력값으로 교체합니다. */
    private function replaceWastes(int $storeId, int $productId, string $date, array $reasons, ?string $note, int $userId): void
    {
        $old = ProductionWaste::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('work_date', $date)->get();
        foreach ($old as $record) {
            ProductionWasteReason::query()->where('production_waste_id', $record->id)->delete();
            $record->delete();
        }
        $total = collect($reasons)->sum('quantity');
        if ($total === 0) {
            return;
        }
        $hasCarryoverWaste = collect($reasons)->contains(fn(array $reason) => $reason['reason_code'] === 'carryover_waste');
        $lot = $hasCarryoverWaste ? $this->incomingLotForDate($storeId, $productId, $date) ?? $this->lotForProductDate($storeId, $productId, $date) : $this->lotForProductDate($storeId, $productId, $date);
        $waste = ProductionWaste::create(['store_id' => $storeId, 'product_id' => $productId, 'stock_lot_id' => $lot?->id, 'work_date' => $date, 'attribution_date' => $lot?->origin_production_date?->toDateString() ?? $date, 'quantity' => $total, 'note' => $note, 'created_by' => $userId]);
        foreach ($reasons as $reason) {
            ProductionWasteReason::create(['production_waste_id' => $waste->id, ...$reason]);
        }
    }

    /** 시식·서비스·직원사용 등 기타 출고를 현재 입력값으로 교체합니다. */
    private function replaceOutflows(int $storeId, int $productId, string $date, array $reasons, ?string $note, int $userId): void
    {
        ProductionOtherOutflow::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('work_date', $date)->delete();
        $lot = $this->lotForProductDate($storeId, $productId, $date);
        foreach ($reasons as $reason) {
            ProductionOtherOutflow::create(['store_id' => $storeId, 'product_id' => $productId, 'stock_lot_id' => $lot?->id, 'work_date' => $date, 'quantity' => $reason['quantity'], 'reason_code' => $reason['reason_code'], 'reason_text' => $reason['reason_text'] ?? null, 'note' => $note, 'created_by' => $userId]);
        }
    }

    /** 다음 날 이월을 동일 재고 lot의 이동으로 기록하여 수량이 새로 생기지 않게 합니다. */
    private function replaceCarryover(int $storeId, int $productId, string $date, int $quantity, string $source, int $userId): void
    {
        $existing = ProductStockMovement::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('work_date', $date)->where('movement_type', 'carryover_out')->get();
        foreach ($existing as $movement) {
            ProductStockMovement::query()->where('stock_lot_id', $movement->stock_lot_id)->whereDate('work_date', Carbon::parse($date)->addDay())->where('movement_type', 'carryover_in')->delete();
            $movement->delete();
        }
        if ($quantity === 0) {
            ProductStockLot::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('origin_production_date', $date)->update(['remaining_quantity' => 0, 'status' => 'exhausted']);
            return;
        }
        $lot = $source === 'incoming' ? $this->incomingLotForDate($storeId, $productId, $date) : $this->lotForProductDate($storeId, $productId, $date);
        abort_unless($lot, 422, '이월할 수 있는 생산 또는 이월 재고가 없습니다.');
        ProductStockMovement::create(['stock_lot_id' => $lot->id, 'store_id' => $storeId, 'product_id' => $productId, 'work_date' => $date, 'movement_type' => 'carryover_out', 'quantity' => $quantity, 'created_by' => $userId]);
        ProductStockMovement::create(['stock_lot_id' => $lot->id, 'store_id' => $storeId, 'product_id' => $productId, 'work_date' => Carbon::parse($date)->addDay()->toDateString(), 'movement_type' => 'carryover_in', 'quantity' => $quantity, 'created_by' => $userId]);
        $lot->update(['remaining_quantity' => $quantity, 'status' => 'active']);
    }

    /** 해당 날짜에 들어온 이월과 연결된 원산지 재고 lot를 찾습니다. */
    private function incomingLotForDate(int $storeId, int $productId, string $date): ?ProductStockLot
    {
        $movement = ProductStockMovement::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('work_date', $date)->where('movement_type', 'carryover_in')->latest('id')->first();
        return $movement ? ProductStockLot::query()->find($movement->stock_lot_id) : null;
    }

    /** 해당 날짜의 당일 생산 또는 들어온 이월과 연결된 재고 lot를 찾습니다. */
    private function lotForProductDate(int $storeId, int $productId, string $date): ?ProductStockLot
    {
        $batchLot = ProductStockLot::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('origin_production_date', $date)->latest('id')->first();
        if ($batchLot) {
            return $batchLot;
        }
        $movement = ProductStockMovement::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('work_date', $date)->where('movement_type', 'carryover_in')->latest('id')->first();
        return $movement ? ProductStockLot::query()->find($movement->stock_lot_id) : null;
    }

    /** 로그인 사용자·점포·날짜별로 격리된 생산 임시저장 Session 키를 만듭니다. */
    private function draftKey(int $storeId, string $date): string
    {
        return 'production.draft.' . auth()->id() . '.' . $storeId . '.' . $date;
    }
}
