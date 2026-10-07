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
    // 생산 업무의 권한, 일일 계산, 감사, 추천 서비스를 주입합니다.
    public function __construct(private readonly AccessService $accessService, private readonly AuditService $auditService, private readonly ProductionDailyService $dailyService, private readonly ProductionRecommendationService $recommendationService)
    {
    }

    // 선택한 날짜의 생산·폐기 업무 화면 전체 데이터를 반환합니다.
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

    // 생산 화면에서 필요한 점포, 제품, 근무자와 사유 선택 목록을 반환합니다.
    public function options(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
        ]);
        $storeId = $this->resolveStoreId($user, $validated['store_id'] ?? null);
        $this->assertStoreReadable($user, $storeId);

        $stores = $this->accessibleStores($user);
        $products = Product::query()
            ->with('category:id,name')
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->orderBy('product_category_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'product_category_id', 'production_department']);
        $workers = $this->availableWorkers($storeId, $validated['date']);

        return response()->json([
            'stores' => $stores,
            'products' => $products,
            'workers' => $workers,
            'store_read_only' => $user->isHeadOffice() && $user->role?->code !== 'super_admin',
            'loss_reasons' => [
                ['value' => 'production_error', 'title' => '생산 실수'],
                ['value' => 'shape_failure', 'title' => '모양 불량'],
                ['value' => 'baking_failure', 'title' => '굽기 불량'],
                ['value' => 'dough_issue', 'title' => '재료·반죽 문제'],
                ['value' => 'damage', 'title' => '파손'],
                ['value' => 'other', 'title' => '직접입력'],
            ],
            'waste_reasons' => [
                ['value' => 'unsold', 'title' => '당일 잔여'],
                ['value' => 'quality', 'title' => '품질 저하'],
                ['value' => 'storage', 'title' => '보관 문제'],
                ['value' => 'damage', 'title' => '파손'],
                ['value' => 'carryover_waste', 'title' => '이월 후 폐기'],
                ['value' => 'other', 'title' => '직접입력'],
            ],
            'zero_reasons' => [
                ['value' => 'no_plan', 'title' => '생산계획 없음'],
                ['value' => 'material_shortage', 'title' => '재료 부족'],
                ['value' => 'equipment_issue', 'title' => '설비 문제'],
                ['value' => 'staffing_issue', 'title' => '인력 문제'],
                ['value' => 'no_demand', 'title' => '주문·수요 없음'],
                ['value' => 'other', 'title' => '직접입력'],
            ],
        ]);
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

    // 한 번의 생산 배치를 저장하고 생산 당시 레시피와 작업자를 함께 고정합니다.
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
            $this->updateConfirmationForDate(
                (int) $data['store_id'],
                (int) $data['product_id'],
                $data['work_date'],
                [
                    'production_confirmed' => true,
                    'zero_production_reason' => null,
                    'confirmed_by' => $user->id,
                ],
            );
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

    // 마감 전 생산 배치의 수량·메모를 수정하고 동시 수정 충돌을 검사합니다.
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

    // 마감 전 생산 배치를 Soft Delete하고 연결 재고가 이미 이월되었다면 삭제를 차단합니다.
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

    // 생산하지 않은 제품을 0개로 명시 확인하고 사유를 저장합니다.
    public function confirmZeroProduction(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.create');
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', Rule::in(['no_plan', 'material_shortage', 'equipment_issue', 'staffing_issue', 'no_demand', 'other'])],
            'reason_text' => ['nullable', 'required_if:reason,other', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->accessService->assertStoreDepartment($user, (int) $data['store_id']);
        $this->assertDateUnlocked((int) $data['store_id'], $data['work_date']);

        $storeId = (int) $data['store_id'];
        $productId = (int) $data['product_id'];
        $workDate = $data['work_date'];

        // 기존 생산량을 0개로 바꿀 때 이미 사용된 당일 생산 재고가 생기지 않는지 먼저 확인합니다.
        $carryIn = (int) ProductStockMovement::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('work_date', $workDate)
            ->where('movement_type', 'carryover_in')
            ->sum('quantity');
        $allocated = (int) ProductionLoss::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('work_date', $workDate)->sum('quantity')
            + (int) ProductionWaste::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('work_date', $workDate)->sum('quantity')
            + (int) ProductionOtherOutflow::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('work_date', $workDate)->sum('quantity')
            + (int) ProductStockMovement::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('work_date', $workDate)->where('movement_type', 'carryover_out')->sum('quantity');

        abort_if($allocated > $carryIn, 422, '생산량을 0개로 바꾸면 이미 입력한 로스·폐기·이월 수량이 사용 가능 수량을 초과합니다. 연결된 기록을 먼저 확인해주세요.');

        DB::transaction(function () use ($storeId, $productId, $workDate, $data, $user) {
            $batches = ProductionBatch::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereDate('work_date', $workDate)
                ->get();

            foreach ($batches as $batch) {
                $lotIds = ProductStockLot::query()->where('production_batch_id', $batch->id)->pluck('id');
                $hasMovement = ProductStockMovement::query()->whereIn('stock_lot_id', $lotIds)->exists();
                $hasWaste = ProductionWaste::query()->whereIn('stock_lot_id', $lotIds)->exists();
                $hasLoss = ProductionLoss::query()->where('production_batch_id', $batch->id)->exists();
                abort_if(
                    $hasMovement || $hasWaste || $hasLoss,
                    422,
                    '이미 이월·로스·폐기와 연결된 생산 기록이 있어 바로 0개로 변경할 수 없습니다. 연결된 기록을 먼저 확인해주세요.',
                );

                ProductStockLot::query()->whereIn('id', $lotIds)->update([
                    'remaining_quantity' => 0,
                    'status' => 'deleted',
                ]);
                $batch->delete();
            }

            $this->updateConfirmationForDate(
            (int) $data['store_id'],
            (int) $data['product_id'],
            $data['work_date'],
            [
                'production_confirmed' => true,
                'zero_production_reason' => $data['reason'],
                'zero_production_reason_text' => $data['reason_text'] ?? null,
                'zero_production_note' => $data['note'] ?? null,
                'confirmed_by' => $user->id,
            ],
            );
        });
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

        $reasons = collect($data['reasons'] ?? []);
        $missingDirectReason = $reasons->contains(fn (array $reason) => (
            $reason['reason_code'] === 'other'
            && trim((string) ($reason['reason_text'] ?? '')) === ''
        ));
        abort_if($missingDirectReason, 422, '직접입력 사유를 입력해주세요.');

        // 화면 검증을 우회한 요청도 당일 생산 + 들어온 이월 범위를 넘지 못하게 서버에서 다시 확인합니다.
        $daily = $this->dailyService->build((int) $data['store_id'], $data['work_date']);
        $row = collect($daily['rows'])->firstWhere('id', (int) $data['product_id']);
        abort_unless($row, 422, '선택한 제품의 생산 현황을 확인할 수 없습니다.');

        $available = (int) $row['production'] + (int) $row['carryover_in'];
        $otherAllocated = match ($data['type']) {
            'carryover' => (int) $row['loss'] + (int) $row['operational_waste'] + (int) $row['other_outflow'],
            'loss' => (int) $row['operational_waste'] + (int) $row['other_outflow'] + (int) $row['carryover_out'],
            'waste' => (int) $row['loss'] + (int) $row['other_outflow'] + (int) $row['carryover_out'],
            default => (int) $row['loss'] + (int) $row['operational_waste'] + (int) $row['carryover_out'],
        };
        $requestedQuantity = $data['type'] === 'carryover'
            ? (int) ($data['quantity'] ?? 0)
            : (int) $reasons->sum('quantity');
        abort_if($requestedQuantity > max(0, $available - $otherAllocated), 422, '입력한 수량이 현재 사용 가능한 수량보다 많습니다.');

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
        $confirmationField = [
            'loss' => 'loss_confirmed',
            'waste' => 'waste_confirmed',
            'other_outflow' => 'disposition_confirmed',
            'carryover' => 'disposition_confirmed',
        ][$data['type']];

        /**
         * 0개 확인은 별도 수량 행이 생기지 않으므로 확인 플래그가 실제 DB에 저장됐는지
         * 서버에서 다시 검증한 뒤에만 성공 응답을 보내 화면과 실제 상태가 어긋나지 않게 합니다.
         */
        $confirmed = ProductionConfirmation::query()
            ->where('store_id', $data['store_id'])
            ->where('product_id', $data['product_id'])
            ->whereDate('work_date', $data['work_date'])
            ->where($confirmationField, true)
            ->exists();
        abort_unless($confirmed, 500, "{$label} 확인 상태를 저장하지 못했습니다. 다시 시도해주세요.");

        $freshDaily = $this->dailyService->build((int) $data['store_id'], $data['work_date']);
        $freshRow = collect($freshDaily['rows'])->firstWhere('id', (int) $data['product_id']);

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

        return response()->json([
            'message' => "{$label} 처리를 저장했습니다.",
            'row' => $freshRow,
            'daily' => $freshDaily,
        ]);
    }

    // 선택한 수량 처리 항목의 확인 상태만 갱신합니다.
    private function confirmFlowField(
        int $storeId,
        int $productId,
        string $date,
        string $field,
        int $userId,
    ): void {
        /**
         * 과거 저장 과정에서 같은 업무 날짜가 `Y-m-d`와 `Y-m-d 00:00:00` 두 형식으로
         * 남을 수 있으므로 문자열 일치가 아닌 날짜 기준으로 기존 확인 행을 모두 찾습니다.
         */
        $confirmationQuery = DB::table('production_confirmations')
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('work_date', $date);

        /**
         * 같은 날짜의 확인 행이 여러 개 남아 있어도 모두 같은 확인 상태로 맞춥니다.
         * 그래야 일일 현황이 어느 행을 읽더라도 0개 확인이 다시 미확인으로 돌아가지 않습니다.
         */
        if ($confirmationQuery->exists()) {
            $confirmationQuery->update([
                $field => true,
                'confirmed_by' => $userId,
                'updated_at' => now(),
            ]);

            return;
        }

        // 기존 확인 행이 없는 제품만 새 확인 행을 생성합니다.
        DB::table('production_confirmations')->insert([
            'store_id' => $storeId,
            'product_id' => $productId,
            'work_date' => $date,
            $field => true,
            'confirmed_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // 미확인 생산·이월·로스·폐기를 0개 상태로 확인하며 기존 실제 기록은 건드리지 않습니다.
    public function bulkConfirmZero(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.update');
        $data = $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'work_date' => ['required', 'date_format:Y-m-d'], 'type' => ['required', Rule::in(['production', 'carryover', 'loss', 'waste'])]]);
        $this->accessService->assertStoreDepartment($user, (int) $data['store_id']);
        $this->assertDateUnlocked((int) $data['store_id'], $data['work_date']);

        $field = [
            'production' => 'production_confirmed',
            'carryover' => 'disposition_confirmed',
            'loss' => 'loss_confirmed',
            'waste' => 'waste_confirmed',
        ][$data['type']];
        $label = [
            'production' => '생산',
            'carryover' => '이월',
            'loss' => '로스',
            'waste' => '폐기',
        ][$data['type']];

        /**
         * 화면의 완료 판정과 동일한 일일 현황에서 실제 미확인 제품만 골라 처리합니다.
         * 수량이 이미 0인지가 아니라 확인 플래그가 false인지가 기준입니다.
         */
        $dailyBefore = $this->dailyService->build((int) $data['store_id'], $data['work_date']);
        $productIds = collect($dailyBefore['rows'])
            ->filter(fn (array $row) => $row['is_active'] && ! $row[$field])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        DB::transaction(function () use ($productIds, $data, $user, $field) {
            foreach ($productIds as $productId) {
                $this->confirmFlowField(
                    (int) $data['store_id'],
                    $productId,
                    $data['work_date'],
                    $field,
                    $user->id,
                );
            }
        });

        /**
         * 저장 직후 동일한 일일 계산을 다시 실행합니다. 대상이 하나라도 미확인으로
         * 남으면 성공 응답을 보내지 않아 화면과 실제 저장 상태가 어긋나지 않게 합니다.
         */
        $daily = $this->dailyService->build((int) $data['store_id'], $data['work_date']);
        $remaining = collect($daily['rows'])
            ->whereIn('id', $productIds)
            ->filter(fn (array $row) => ! $row[$field]);
        abort_if($remaining->isNotEmpty(), 500, "{$label} 확인 상태를 저장하지 못했습니다. 다시 시도해주세요.");

        $this->auditService->log($user, 'production', 'confirm', ProductionConfirmation::class, null, null, $data, "미입력 {$label} 전체 0 확인");

        return response()->json([
            'message' => "미입력 {$label}를 전체 0개로 확인했습니다.",
            'daily' => $daily,
            'confirmed_count' => $productIds->count(),
        ]);
    }

    // 관리자 권한으로 마감된 날짜를 수정 상태로 열고 필수 수정 사유를 기록합니다.
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

    // 마감 전 전체 수량과 미완료 제품을 검증하여 최종 확인 화면 데이터를 반환합니다.
    public function closePreview(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');
        $data = $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'work_date' => ['required', 'date_format:Y-m-d']]);
        $this->assertStoreReadable($user, (int) $data['store_id']);
        $daily = $this->dailyService->build((int) $data['store_id'], $data['work_date']);
        $incomplete = collect($daily['rows'])->where('is_active', true)->where('complete', false)->values();
        return response()->json([
            'daily' => $daily,
            'can_close' => $incomplete->isEmpty(),
            'incomplete' => $incomplete,
        ]);
    }

    // 사용자의 최종 확인 뒤 하루 업무를 마감합니다. 미완료나 수량 불일치가 있으면 서버에서 차단합니다.
    public function closeDay(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.update');
        $data = $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'work_date' => ['required', 'date_format:Y-m-d'], 'daily_memo' => ['nullable', 'string', 'max:2000']]);
        $this->accessService->assertStoreDepartment($user, (int) $data['store_id']);
        $daily = $this->dailyService->build((int) $data['store_id'], $data['work_date']);
        abort_if(collect($daily['rows'])->where('is_active', true)->contains('complete', false), 422, '아직 확인하지 않은 제품이 있어 마감할 수 없습니다.');
        $closure = $this->updateDailyClosureForDate(
            (int) $data['store_id'],
            $data['work_date'],
            [
                'status' => 'closed',
                'daily_memo' => $data['daily_memo'] ?? null,
                'closed_by' => $user->id,
                'closed_at' => now(),
                'updated_by' => $user->id,
            ],
        );
        $closure->refresh();
        abort_unless($closure->status === 'closed', 500, '마감 상태를 저장하지 못했습니다. 다시 시도해주세요.');

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
        $wastes = $this->monthlyQuantities(ProductionWaste::query(), $storeId, 'work_date', $startDate, $endDate);
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
                    'waste_rate' => $this->wasteRate($waste, $produced, $carriedIn),
                ],
                'status' => $status,
                'events' => $dayEvents,
            ]);
        }

        return response()->json(['days' => $days]);
    }

    // 월간 수량 모델을 한 번 조회해 날짜별 합계로 묶습니다.
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

    // 이월 이동 기록을 월 단위로 조회해 날짜별 합계로 묶습니다.
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

    // 점포 행사·할인·단체주문 같은 분석 조건을 저장합니다.
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

    // 날짜를 휴점 또는 정상 영업일로 변경하며 기존 업무 기록이 있는 휴점 지정은 차단합니다.
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

    // 기간별 생산·이월·로스·폐기 통계를 날짜별 집계 쿼리로 빠르게 반환합니다.
    public function statistics(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $storeId = (int) $data['store_id'];
        $this->assertStoreReadable($user, $storeId);

        // 일별 화면 전체를 날짜마다 다시 조립하지 않고 통계에 필요한 수량만 DB에서 한 번씩 집계합니다.
        $production = $this->sumByDate(ProductionBatch::query(), $storeId, 'work_date', $data['from'], $data['to']);
        $carryover = $this->sumByDate(
            ProductStockMovement::query()->where('movement_type', 'carryover_in'),
            $storeId,
            'work_date',
            $data['from'],
            $data['to'],
        );
        $loss = $this->sumByDate(ProductionLoss::query(), $storeId, 'work_date', $data['from'], $data['to']);
        $waste = $this->sumByDate(ProductionWaste::query(), $storeId, 'work_date', $data['from'], $data['to']);

        $series = collect();
        for ($date = Carbon::parse($data['from']); $date->lte(Carbon::parse($data['to'])); $date->addDay()) {
            $key = $date->toDateString();
            $productionValue = (int) ($production[$key] ?? 0);
            $wasteValue = (int) ($waste[$key] ?? 0);
            $series->push([
                'date' => $key,
                'production' => $productionValue,
                'carryover' => (int) ($carryover[$key] ?? 0),
                'loss' => (int) ($loss[$key] ?? 0),
                'waste' => $wasteValue,
                'waste_rate' => $this->wasteRate($wasteValue, $productionValue, (int) ($carryover[$key] ?? 0)),
            ]);
        }

        $productionTotal = (int) $series->sum('production');
        $wasteTotal = (int) $series->sum('waste');

        // 같은 길이의 직전 기간을 함께 계산해 현재 기간의 증감을 의미 있게 비교합니다.
        $periodDays = Carbon::parse($data['from'])->diffInDays(Carbon::parse($data['to'])) + 1;
        $previousTo = Carbon::parse($data['from'])->subDay()->toDateString();
        $previousFrom = Carbon::parse($previousTo)->subDays($periodDays - 1)->toDateString();
        $previousProduction = array_sum($this->sumByDate(ProductionBatch::query(), $storeId, 'work_date', $previousFrom, $previousTo));
        $previousCarryover = array_sum($this->sumByDate(ProductStockMovement::query()->where('movement_type', 'carryover_in'), $storeId, 'work_date', $previousFrom, $previousTo));
        $previousLoss = array_sum($this->sumByDate(ProductionLoss::query(), $storeId, 'work_date', $previousFrom, $previousTo));
        $previousWaste = array_sum($this->sumByDate(ProductionWaste::query(), $storeId, 'work_date', $previousFrom, $previousTo));

        // 도넛과 제품 순위는 일별 화면을 반복 조립하지 않고 제품별 합계 쿼리로 한 번씩 계산합니다.
        $productNames = Product::withTrashed()
            ->where('store_id', $storeId)
            ->pluck('name', 'id');
        $productMetrics = [
            'production' => $this->sumByProduct(ProductionBatch::query(), $storeId, 'work_date', $data['from'], $data['to']),
            'carryover' => $this->sumByProduct(
                ProductStockMovement::query()->where('movement_type', 'carryover_in'),
                $storeId,
                'work_date',
                $data['from'],
                $data['to'],
            ),
            'loss' => $this->sumByProduct(ProductionLoss::query(), $storeId, 'work_date', $data['from'], $data['to']),
            'waste' => $this->sumByProduct(ProductionWaste::query(), $storeId, 'work_date', $data['from'], $data['to']),
        ];
        $productIds = collect($productMetrics)->flatMap(fn (array $values) => array_keys($values))->unique()->values();
        $productTotals = $productIds->map(fn ($productId) => [
            'product_id' => (int) $productId,
            'product_name' => $productNames[$productId] ?? '-',
            'production' => (int) ($productMetrics['production'][$productId] ?? 0),
            'carryover' => (int) ($productMetrics['carryover'][$productId] ?? 0),
            'loss' => (int) ($productMetrics['loss'][$productId] ?? 0),
            'waste' => (int) ($productMetrics['waste'][$productId] ?? 0),
        ])->values();

        return response()->json([
            'series' => $series,
            'product_totals' => $productTotals,
            'totals' => [
                'production' => $productionTotal,
                'carryover' => (int) $series->sum('carryover'),
                'loss' => (int) $series->sum('loss'),
                'waste' => $wasteTotal,
                'waste_rate' => $this->wasteRate($wasteTotal, $productionTotal, (int) $series->sum('carryover')),
            ],
            'previous_totals' => [
                'production' => (int) $previousProduction,
                'carryover' => (int) $previousCarryover,
                'loss' => (int) $previousLoss,
                'waste' => (int) $previousWaste,
                'waste_rate' => $this->wasteRate((int) $previousWaste, (int) $previousProduction, (int) $previousCarryover),
            ],
        ]);
    }

    // 날짜 컬럼과 quantity를 기간별로 합산해 통계용 날짜=>수량 맵을 만듭니다.
    private function sumByDate($query, int $storeId, string $dateColumn, string $from, string $to): array
    {
        return $query
            ->where('store_id', $storeId)
            ->whereDate($dateColumn, '>=', $from)
            ->whereDate($dateColumn, '<=', $to)
            ->selectRaw("DATE({$dateColumn}) as aggregate_date, SUM(quantity) as aggregate_quantity")
            ->groupByRaw("DATE({$dateColumn})")
            ->pluck('aggregate_quantity', 'aggregate_date')
            ->map(fn ($quantity) => (int) $quantity)
            ->all();
    }

    // 날짜 범위의 quantity를 제품별로 합산해 그래프와 순위에서 재사용합니다.
    private function sumByProduct($query, int $storeId, string $dateColumn, string $from, string $to): array
    {
        return $query
            ->where('store_id', $storeId)
            ->whereDate($dateColumn, '>=', $from)
            ->whereDate($dateColumn, '<=', $to)
            ->selectRaw('product_id, SUM(quantity) as aggregate_quantity')
            ->groupBy('product_id')
            ->pluck('aggregate_quantity', 'product_id')
            ->map(fn ($quantity) => (int) $quantity)
            ->all();
    }

    // 선택 기간의 흐름과 제품별 현황을 반환하며 추천 계산은 최근 28일 이내 기록만 사용합니다.
    public function analysis(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', Rule::in([7, 30, 90, 365])],
        ]);
        $storeId = (int) $data['store_id'];
        $this->assertStoreReadable($user, $storeId);

        $target = Carbon::parse($data['date']);
        $days = (int) ($data['days'] ?? 30);
        $from = $target->copy()->subDays($days - 1)->toDateString();
        $to = $target->toDateString();
        $products = Product::query()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        // 기간 흐름과 제품 합계는 집계 쿼리로 계산합니다. 1년 조회에서도 일일 화면을 365번
        // 조립하지 않아 분석 탭의 응답 지연과 불필요한 DB 조회를 크게 줄입니다.
        $dateMetrics = [
            'production' => $this->sumByDate(ProductionBatch::query(), $storeId, 'work_date', $from, $to),
            'carryover' => $this->sumByDate(ProductStockMovement::query()->where('movement_type', 'carryover_in'), $storeId, 'work_date', $from, $to),
            'loss' => $this->sumByDate(ProductionLoss::query(), $storeId, 'work_date', $from, $to),
            'waste' => $this->sumByDate(ProductionWaste::query(), $storeId, 'work_date', $from, $to),
        ];
        $dailyFlow = collect();
        for ($cursor = Carbon::parse($from); $cursor->lte($target); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $production = (int) ($dateMetrics['production'][$key] ?? 0);
            $waste = (int) ($dateMetrics['waste'][$key] ?? 0);
            $dailyFlow->push([
                'date' => $key,
                'production' => $production,
                'carryover' => (int) ($dateMetrics['carryover'][$key] ?? 0),
                'loss' => (int) ($dateMetrics['loss'][$key] ?? 0),
                'waste' => $waste,
                'waste_rate' => $this->wasteRate($waste, $production, (int) ($dateMetrics['carryover'][$key] ?? 0)),
            ]);
        }

        $productMetrics = [
            'production' => $this->sumByProduct(ProductionBatch::query(), $storeId, 'work_date', $from, $to),
            'carryover' => $this->sumByProduct(ProductStockMovement::query()->where('movement_type', 'carryover_in'), $storeId, 'work_date', $from, $to),
            'loss' => $this->sumByProduct(ProductionLoss::query(), $storeId, 'work_date', $from, $to),
            'waste' => $this->sumByProduct(ProductionWaste::query(), $storeId, 'work_date', $from, $to),
        ];
        $productTotals = $products->map(fn (Product $product) => [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'production' => (int) ($productMetrics['production'][$product->id] ?? 0),
            'carryover' => (int) ($productMetrics['carryover'][$product->id] ?? 0),
            'loss' => (int) ($productMetrics['loss'][$product->id] ?? 0),
            'waste' => (int) ($productMetrics['waste'][$product->id] ?? 0),
        ])->values();

        // 추천은 기존 의미를 보존하기 위해 최대 최근 28일의 확인된 생산 기록만 사용합니다.
        $historyByProduct = collect();
        $recommendationDays = min($days, 28);
        for ($i = $recommendationDays - 1; $i >= 0; $i--) {
            $date = $target->copy()->subDays($i)->toDateString();
            $daily = $this->dailyService->build($storeId, $date);
            $specialDay = ! empty($this->eventsForDate($storeId, $date));
            foreach ($daily['rows'] as $row) {
                if (! $row['production_confirmed']) {
                    continue;
                }
                $historyByProduct->push(['product_id' => $row['id'], 'date' => $date, ...$row, 'special_day' => $specialDay]);
            }
        }
        $historyByProduct = $historyByProduct->groupBy('product_id');
        $targetEvents = collect($this->eventsForDate($storeId, $data['date']));
        $items = $products->map(function (Product $product) use ($historyByProduct, $targetEvents, $target) {
            $history = collect($historyByProduct->get($product->id, []))->values();
            $productEvents = $targetEvents
                ->filter(fn (array $event) => empty($event['products']) || collect($event['products'])->contains('id', $product->id))
                ->values()->all();
            $recommendation = $this->recommendationService->recommend($history, $target, $productEvents);
            $recent = $history->take(-5);
            $flags = [];
            if ($recent->where('waste', '>', 0)->count() >= 3) $flags[] = '최근 폐기가 반복되고 있습니다.';
            if ($recent->where('carryover_out', '>', 0)->count() >= 3) $flags[] = '최근 이월이 반복되고 있습니다.';
            if ($recommendation['warning']) $flags[] = $recommendation['warning'];
            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'recommendation' => $recommendation,
                'flags' => $flags,
            ];
        })->values();

        return response()->json([
            'items' => $items,
            'daily_flow' => $dailyFlow,
            'product_totals' => $productTotals,
            'range' => ['from' => $from, 'to' => $to, 'days' => $days],
        ]);
    }

    // 선택한 날짜의 생산 관련 변경 이력을 최신순으로 반환합니다.
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

    // 현재 사용자의 생산 다단계 입력 임시저장을 Laravel Session에서 조회합니다.
    public function draft(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');
        $data = $request->validate(['store_id' => ['required', 'integer'], 'work_date' => ['required', 'date_format:Y-m-d']]);
        return response()->json(['draft' => Session::get($this->draftKey((int) $data['store_id'], $data['work_date']))]);
    }

    // 생산 다단계 입력 내용을 현재 로그인 세션에 임시저장합니다.
    public function saveDraft(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.create');
        $data = $request->validate(['store_id' => ['required', 'integer'], 'work_date' => ['required', 'date_format:Y-m-d'], 'draft' => ['required', 'array']]);
        Session::put($this->draftKey((int) $data['store_id'], $data['work_date']), $data['draft']);
        return response()->json(['message' => '작성 중인 내용을 임시저장했습니다.']);
    }

    // 현재 날짜의 생산 임시저장만 삭제하고 실제 업무 기록은 건드리지 않습니다.
    public function deleteDraft(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.create');
        $data = $request->validate(['store_id' => ['required', 'integer'], 'work_date' => ['required', 'date_format:Y-m-d']]);
        Session::forget($this->draftKey((int) $data['store_id'], $data['work_date']));
        return response()->json(['message' => '임시저장을 삭제했습니다.']);
    }

    // 인증 사용자를 반환하고 비정상 요청은 인증 오류로 차단합니다.
    private function user(Request $request): User
    {
        /**
         * @var User|null $user
         */
        $user = $request->user();
        abort_unless($user, 401, '로그인이 필요합니다.');
        return $user;
    }

    // 일반 직원은 소속 점포, 본사/최고 관리자는 요청 점포를 사용합니다.
    private function resolveStoreId(User $user, ?int $requestedStoreId): int
    {
        if ($user->role?->code === 'super_admin' || $user->isHeadOffice()) {
            return $requestedStoreId ?? (int) Store::query()->where('name', '무역점')->value('id');
        }
        abort_unless($user->store_id, 403, '소속 점포가 없습니다.');
        return (int) $user->store_id;
    }

    // 조회 가능한 점포인지 서버에서 다시 확인합니다.
    private function assertStoreReadable(User $user, int $storeId): void
    {
        if ($user->role?->code === 'super_admin' || $user->isHeadOffice()) {
            return;
        }
        abort_unless((int) $user->store_id === $storeId, 403, '다른 점포의 데이터는 조회할 수 없습니다.');
    }

    // 사용자가 선택할 수 있는 점포 목록을 권한 범위에 맞게 반환합니다.
    private function accessibleStores(User $user)
    {
        if ($user->role?->code === 'super_admin' || $user->isHeadOffice()) {
            return Store::query()->where('status', 'active')->orderByRaw("CASE WHEN name = '무역점' THEN 0 ELSE 1 END")->orderBy('name')->get(['id', 'name']);
        }
        return Store::query()->whereKey($user->store_id)->get(['id', 'name']);
    }

    // 생산 배치 요청을 검증합니다.
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

    /**
     * 같은 점포·제품·업무일의 확인 행을 날짜 기준으로 찾아 갱신합니다.
     * 과거 날짜 저장 형식이 달라도 새 중복 행을 만들지 않도록 기존 행을 우선 사용합니다.
     */
    private function updateConfirmationForDate(int $storeId, int $productId, string $date, array $values): ProductionConfirmation
    {
        $confirmation = ProductionConfirmation::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('work_date', $date)
            ->first();

        if (! $confirmation) {
            $confirmation = new ProductionConfirmation([
                'store_id' => $storeId,
                'product_id' => $productId,
                'work_date' => $date,
            ]);
        }

        $confirmation->fill($values);
        $confirmation->save();

        return $confirmation;
    }

    /**
     * 같은 점포·업무일의 마감 행을 날짜 기준으로 찾아 갱신합니다.
     * 기존 날짜 형식 차이 때문에 마감 행이 중복 생성되는 문제를 방지합니다.
     */
    private function updateDailyClosureForDate(int $storeId, string $date, array $values): ProductionDailyClosure
    {
        $closure = ProductionDailyClosure::query()
            ->where('store_id', $storeId)
            ->whereDate('work_date', $date)
            ->first();

        if (! $closure) {
            $closure = new ProductionDailyClosure([
                'store_id' => $storeId,
                'work_date' => $date,
            ]);
        }

        $closure->fill($values);
        $closure->save();

        return $closure;
    }

    // 작업자로 선택된 직원이 해당 날짜에 실제 근무 예정인지 확인합니다.
    private function assertWorkersScheduled(int $storeId, string $date, array $workers): void
    {
        if (empty($workers)) {
            return;
        }
        $ids = collect($workers)->pluck('user_id')->unique()->values();
        $allowed = $this->availableWorkers($storeId, $date)->pluck('id');
        abort_unless($ids->diff($allowed)->isEmpty(), 422, '선택한 작업자의 점포 소속 또는 재직 상태를 확인해주세요.');
    }

    // 마감된 날짜의 일반 수정을 차단합니다.
    private function assertDateUnlocked(int $storeId, string $date): void
    {
        $closed = ProductionDailyClosure::query()->where('store_id', $storeId)->whereDate('work_date', $date)->where('status', 'closed')->exists();
        abort_if($closed, 422, '이미 마감된 날짜입니다. 마감 후 수정 기능을 이용해주세요.');
    }

    // 선택 날짜에 적용되는 행사 목록을 반환합니다.
    private function eventsForDate(int $storeId, string $date): array
    {
        return StoreCalendarEvent::query()->with('products:id,name')->where('store_id', $storeId)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->orderBy('start_date')->get()->toArray();
    }

    // 선택 날짜보다 이전에 남아 있는 가장 오래된 미마감 영업일을 찾습니다.
    private function blockingPreviousDate(int $storeId, string $date): ?string
    {
        $start = Carbon::parse($date)->subDays(30);
        $target = Carbon::parse($date)->subDay();
        for ($cursor = $start; $cursor->lte($target); $cursor->addDay()) {
            // whereDate 비교값은 DB와 동일한 Y-m-d 문자열로 고정해 DB 엔진별 날짜 바인딩 차이를 없앱니다.
            $cursorDate = $cursor->toDateString();

            $closedDay = StoreDailyStatus::query()->where('store_id', $storeId)->whereDate('work_date', $cursorDate)->where('status', 'closed')->exists();
            if ($closedDay) {
                continue;
            }
            $hasData = ProductionBatch::query()->where('store_id', $storeId)->whereDate('work_date', $cursorDate)->exists() || ProductionConfirmation::query()->where('store_id', $storeId)->whereDate('work_date', $cursorDate)->exists();
            if (!$hasData) {
                continue;
            }
            $closed = ProductionDailyClosure::query()->where('store_id', $storeId)->whereDate('work_date', $cursorDate)->where('status', 'closed')->exists();
            if (!$closed) {
                return $cursor->toDateString();
            }
        }
        return null;
    }

    // 로스 기록과 사유 배분을 현재 입력값으로 교체합니다.
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

    // 폐기 기록과 사유 배분을 현재 입력값으로 교체합니다.
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
        $waste = ProductionWaste::create(['store_id' => $storeId, 'product_id' => $productId, 'stock_lot_id' => $lot?->id, 'work_date' => $date, 'attribution_date' => $date, 'quantity' => $total, 'note' => $note, 'created_by' => $userId]);
        foreach ($reasons as $reason) {
            ProductionWasteReason::create(['production_waste_id' => $waste->id, ...$reason]);
        }
    }

    // 시식·서비스·직원사용 등 기타 출고를 현재 입력값으로 교체합니다.
    private function replaceOutflows(int $storeId, int $productId, string $date, array $reasons, ?string $note, int $userId): void
    {
        ProductionOtherOutflow::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('work_date', $date)->delete();
        $lot = $this->lotForProductDate($storeId, $productId, $date);
        foreach ($reasons as $reason) {
            ProductionOtherOutflow::create(['store_id' => $storeId, 'product_id' => $productId, 'stock_lot_id' => $lot?->id, 'work_date' => $date, 'quantity' => $reason['quantity'], 'reason_code' => $reason['reason_code'], 'reason_text' => $reason['reason_text'] ?? null, 'note' => $note, 'created_by' => $userId]);
        }
    }

    // 다음 날 이월을 동일 재고 lot의 이동으로 기록하여 수량이 새로 생기지 않게 합니다.
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

    // 해당 날짜에 들어온 이월과 연결된 원산지 재고 lot를 찾습니다.
    private function incomingLotForDate(int $storeId, int $productId, string $date): ?ProductStockLot
    {
        $movement = ProductStockMovement::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('work_date', $date)->where('movement_type', 'carryover_in')->latest('id')->first();
        return $movement ? ProductStockLot::query()->find($movement->stock_lot_id) : null;
    }

    // 해당 날짜의 당일 생산 또는 들어온 이월과 연결된 재고 lot를 찾습니다.
    private function lotForProductDate(int $storeId, int $productId, string $date): ?ProductStockLot
    {
        $batchLot = ProductStockLot::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('origin_production_date', $date)->latest('id')->first();
        if ($batchLot) {
            return $batchLot;
        }
        $movement = ProductStockMovement::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('work_date', $date)->where('movement_type', 'carryover_in')->latest('id')->first();
        return $movement ? ProductStockLot::query()->find($movement->stock_lot_id) : null;
    }

    // 폐기율은 당일 생산과 들어온 이월을 합친 실제 사용 가능 수량을 기준으로 계산합니다.
    private function wasteRate(int $waste, int $production, int $carryover): ?float
    {
        $available = $production + $carryover;
        return $available > 0 ? round($waste / $available * 100, 1) : null;
    }

    // 로그인 사용자·점포·날짜별로 격리된 생산 임시저장 Session 키를 만듭니다.
    private function draftKey(int $storeId, string $date): string
    {
        return 'production.draft.' . auth()->id() . '.' . $storeId . '.' . $date;
    }
}
