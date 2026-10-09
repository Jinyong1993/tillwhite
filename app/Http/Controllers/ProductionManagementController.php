<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class ProductionManagementController extends Controller
{
    /**
     * 생산 관리에 필요한 서비스들을 생성자를 통해 주입합니다.
     * 각 서비스는 권한 확인, 일일 현황 계산, 변경 이력 기록, 생산량 추천을 담당합니다.
     */
    public function __construct(
        private readonly AccessService $accessService,
        private readonly AuditService $auditService,
        private readonly ProductionDailyService $dailyService,
        private readonly ProductionRecommendationService $recommendationService
    ) {
    }

    /**
     * 선택한 날짜의 생산 관리 화면에 필요한 전체 데이터를 반환합니다.
     * 생산 현황, 점포 정보, 행사, 날씨, 이전 날짜의 마감 상태를 함께 제공합니다.
     */
    public function daily(Request $request): JsonResponse
    {
        // 로그인 사용자를 확인하고 생산 관리 조회 권한을 검사합니다.
        $user = $this->user($request);

        $this->accessService->requirePermission(
            $user,
            'production.view'
        );

        /**
         * 조회할 업무 날짜와 점포 ID를 검증합니다.
         * 날짜는 필수이며 점포 ID는 사용자의 접근 권한에 따라 결정될 수 있습니다.
         */
        $validated = $request->validate([
            'date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'store_id' => [
                'nullable',
                'integer',
                'exists:stores,id',
            ],
        ]);

        // 조회할 점포를 결정하고 해당 점포의 조회 권한을 확인합니다.
        $storeId = $this->resolveStoreId(
            $user,
            $validated['store_id'] ?? null
        );

        $this->assertStoreReadable($user, $storeId);

        // 검증된 업무 날짜를 가져옵니다.
        $date = $validated['date'];

        /**
         * 선택한 점포와 날짜의 생산 현황을 계산합니다.
         * 기존 build() 호출의 세 번째 인자(true)를 유지합니다.
         */
        $daily = $this->dailyService->build(
            $storeId,
            $date,
            true
        );

        // 화면에 표시할 업무 날짜와 점포 기본 정보를 설정합니다.
        $daily['date'] = $date;
        $daily['store'] = Store::query()
            ->findOrFail($storeId, ['id', 'name']);

        // 선택한 날짜에 등록된 행사 및 일정 정보를 조회합니다.
        $daily['events'] = $this->eventsForDate(
            $storeId,
            $date
        );

        /**
         * 해당 점포와 날짜에 저장된 날씨 기록을 조회합니다.
         * 저장된 날짜에 시각이 포함되어 있어도 날짜 기준으로 비교합니다.
         */
        $daily['weather'] = StoreDailyWeather::query()
            ->where('store_id', $storeId)
            ->whereDate('work_date', $date)
            ->first();

        // 이전 날짜의 미마감 상태 등 현재 날짜의 작업을 제한하는 날짜를 확인합니다.
        $daily['blocking_previous_date'] = $this->blockingPreviousDate(
            $storeId,
            $date
        );

        /**
         * 일일 현황의 제품 목록은 Collection 또는 배열로 반환될 수 있습니다.
         *
         * 최근 입력 제품 조회 함수는 배열을 받도록 정의되어 있으므로
         * 기존 제품 목록의 순서와 데이터를 유지하면서 배열로 변환합니다.
         */
        $daily['recent_product_ids'] = $this->recentProductIds(
            $storeId,
            $date,
            collect($daily['rows'] ?? [])->all()
        );

        // 생산 관리 화면에 필요한 전체 데이터를 JSON 형식으로 반환합니다.
        return response()->json($daily);
    }

    /**
     * 선택한 점포와 날짜에서 최근 입력된 제품 ID를 반환합니다.
     *
     * 생산 기록과 개별 재고 처리 이력만 대상으로 하며,
     * 마감처럼 특정 제품을 식별할 수 없는 이력은 제외합니다.
     *
     * 현재 화면에 표시할 수 있는 활성 제품만 포함하고
     * 같은 제품의 반복 입력은 최신 기록 하나로 취급합니다.
     */
    private function recentProductIds(
        int $storeId,
        string $date,
        array $rows
    ): array {
        // 현재 화면에 표시 가능한 활성 제품 ID를 준비합니다.
        $activeProductIds = collect($rows)
            ->filter(fn ($row) => !empty($row['is_active']))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($activeProductIds)) {
            return [];
        }

        // 한국 업무 날짜를 생산 기록의 UTC 직렬화 형식으로 변환합니다.
        $productionDate = Carbon::createFromFormat(
            '!Y-m-d',
            $date,
            'Asia/Seoul'
        )->utc()->format('Y-m-d\TH:i:s.u\Z');

        /**
         * 기존 변경 이력의 날짜 조회 기준을 사용합니다.
         * 점포와 날짜가 일치하는 기록만 최신순으로 조회합니다.
         */
        $logs = AuditLog::query()
            ->where('domain', 'production')
            ->where(function ($query) use ($date, $productionDate) {
                $query->where('new_values->work_date', $date)
                    ->orWhere('new_values->target_date', $date)
                    ->orWhere('old_values->work_date', $date)
                    ->orWhereJsonContains(
                        'new_values->attribution_dates',
                        $date
                    )
                    ->orWhere(function ($batchQuery) use ($productionDate) {
                        $batchQuery
                            ->where('target_type', ProductionBatch::class)
                            ->where(function ($dateQuery) use ($productionDate) {
                                $dateQuery
                                    ->where('new_values->work_date', $productionDate)
                                    ->orWhere('old_values->work_date', $productionDate);
                            });
                    });
            })
            ->where(function ($query) use ($storeId) {
                $query->where('new_values->store_id', $storeId)
                    ->orWhere('old_values->store_id', $storeId)
                    ->orWhere(function ($nested) {
                        $nested->whereNull('new_values->store_id')
                            ->whereNull('old_values->store_id');
                    });
            })
            ->latest('id')
            ->limit(100)
            ->get([
                'action',
                'target_type',
                'target_id',
                'description',
                'old_values',
                'new_values',
            ]);

        // 현재 일일 현황의 생산 기록 ID와 제품 ID를 연결합니다.
        $batchProductIds = [];

        foreach ($rows as $row) {
            foreach ($row['batches'] ?? [] as $batch) {
                $batchProductIds[(int) $batch['id']] = (int) $row['id'];
            }
        }

        $recentIds = [];

        foreach ($logs as $log) {
            $newValues = $log->new_values ?? [];
            $oldValues = $log->old_values ?? [];
            $productId = null;

            if (
                $log->target_type === ProductionBatch::class
                && in_array($log->action, ['create', 'update', 'delete'], true)
            ) {
                $productId = $newValues['product_id']
                    ?? $oldValues['product_id']
                    ?? $batchProductIds[(int) $log->target_id]
                    ?? null;
            } elseif ($log->target_type === Product::class) {
                $isFlowRecord = $log->action === 'update'
                    && in_array(
                        $newValues['type'] ?? null,
                        ['loss', 'waste', 'carryover', 'other_outflow'],
                        true
                    );

                $isZeroProduction = $log->action === 'confirm'
                    && ($log->description ?? '') === '생산 0개 확인';

                if ($isFlowRecord || $isZeroProduction) {
                    $productId = $newValues['product_id']
                        ?? $oldValues['product_id']
                        ?? $log->target_id;
                }
            }

            if ($productId === null) {
                continue;
            }

            $productId = (int) $productId;

            if (
                !in_array($productId, $activeProductIds, true)
                || in_array($productId, $recentIds, true)
            ) {
                continue;
            }

            $recentIds[] = $productId;

            if (count($recentIds) === 5) {
                break;
            }
        }

        return $recentIds;
    }

    /**
     * 생산 화면에서 사용하는 선택 목록을 반환합니다.
     * 접근 가능한 점포, 활성 제품, 근무자 및 각종 사유 목록을 제공합니다.
     */
    public function options(Request $request): JsonResponse
    {
        // 로그인 사용자를 확인하고 생산 관리 조회 권한을 검사합니다.
        $user = $this->user($request);

        $this->accessService->requirePermission(
            $user,
            'production.view'
        );

        // 조회 날짜와 선택한 점포 ID를 검증합니다.
        $validated = $request->validate([
            'date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'store_id' => [
                'nullable',
                'integer',
                'exists:stores,id',
            ],
        ]);

        // 조회할 점포를 결정하고 해당 점포의 조회 권한을 확인합니다.
        $storeId = $this->resolveStoreId(
            $user,
            $validated['store_id'] ?? null
        );

        $this->assertStoreReadable($user, $storeId);

        // 사용자가 조회할 수 있는 점포 목록을 가져옵니다.
        $stores = $this->accessibleStores($user);

        /**
         * 선택한 점포의 활성 제품을 조회합니다.
         * 카테고리, 표시 순서, 제품명 순으로 정렬합니다.
         */
        $products = Product::query()
            ->with('category:id,name')
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->orderBy('product_category_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'product_category_id',
                'production_department',
            ]);

        // 선택한 날짜에 생산 업무를 담당할 수 있는 근무자를 조회합니다.
        $workers = $this->availableWorkers(
            $storeId,
            $validated['date']
        );

        /**
         * 생산 화면에서 필요한 선택 목록을 반환합니다.
         * 본사 직원의 읽기 전용 여부와 사유 코드도 함께 전달합니다.
         */
        return response()->json([
            'stores' => $stores,
            'products' => $products,
            'workers' => $workers,

            'store_read_only' => $user->isHeadOffice()
                && $user->role?->code !== 'super_admin',

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
     * 생산 화면에서 제품의 기본 정보와 레시피를 읽기 전용으로 반환합니다.
     * 제품 관리 권한과 별개로 생산 조회 권한 범위 안에서만 조회할 수 있습니다.
     */
    public function productDetail(
        Request $request,
        int $productId
    ): JsonResponse {
        // 로그인 사용자를 확인하고 생산 관리 조회 권한을 검사합니다.
        $user = $this->user($request);

        $this->accessService->requirePermission(
            $user,
            'production.view'
        );

        // 삭제된 제품을 포함하여 요청한 제품을 조회합니다.
        $product = Product::withTrashed()
            ->findOrFail($productId);

        // 해당 제품이 속한 점포의 조회 권한을 확인합니다.
        $this->assertStoreReadable(
            $user,
            (int) $product->store_id
        );

        /**
         * 제품에 연결된 점포, 카테고리, 가격 및 레시피 정보를 조회합니다.
         * 가격은 적용 시작일과 ID를 기준으로 최신순 정렬합니다.
         */
        $product->load([
            'store:id,name',
            'category:id,store_id,name',
            'prices' => fn ($query) => $query
                ->orderByDesc('effective_from')
                ->orderByDesc('id'),
            'recipes.ingredients',
            'recipes.steps',
        ]);

        /**
         * 선택 날짜가 전달된 경우에만 최근 7일 생산 기록을 조회합니다.
         *
         * 생산 기록이 없는 날짜는 확인 상태를 추가로 검사합니다.
         * - 미확인: null
         * - 생산 없음(0개) 확인: 0
         * - 생산 기록 존재: 해당 날짜의 생산 수량 합계
         *
         * 기존 제품 정보와 레시피 응답은 유지합니다.
         */
        $history = null;

        $validated = $request->validate([
            'work_date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
            ],
        ]);

        if (isset($validated['work_date'])) {
            // 선택 날짜를 마지막 날로 하는 7일 구간입니다.
            $end = Carbon::createFromFormat(
                '!Y-m-d',
                $validated['work_date']
            );

            $start = $end->copy()->subDays(6);
            $storeId = (int) $product->store_id;

            // 해당 점포·제품의 날짜별 생산 수량을 합산합니다.
            $quantities = ProductionBatch::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereDate('work_date', '>=', $start->toDateString())
                ->whereDate('work_date', '<=', $end->toDateString())
                ->selectRaw(
                    'DATE(work_date) as production_date, SUM(quantity) as total_quantity'
                )
                ->groupByRaw('DATE(work_date)')
                ->get()
                ->mapWithKeys(
                    fn ($row) => [
                        $row->production_date => (int) $row->total_quantity,
                    ]
                );

            // 생산량이 0개여도 명시적으로 확인된 날짜를 구합니다.
            $confirmedDates = ProductionConfirmation::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->where('production_confirmed', true)
                ->whereDate('work_date', '>=', $start->toDateString())
                ->whereDate('work_date', '<=', $end->toDateString())
                ->get(['work_date'])
                ->mapWithKeys(
                    fn ($confirmation) => [
                        Carbon::parse($confirmation->work_date)->toDateString() => true,
                    ]
                );

            // 누락 날짜까지 포함하여 7일을 순서대로 구성합니다.
            $history = [];

            for (
                $day = $start->copy();
                $day->lte($end);
                $day->addDay()
            ) {
                $date = $day->toDateString();

                $history[] = [
                    'date' => $date,
                    'quantity' => $quantities->has($date)
                        ? $quantities->get($date)
                        : ($confirmedDates->has($date) ? 0 : null),
                ];
            }
        }

        // 기존 제품 상세 응답을 유지하면서 비교 데이터만 추가합니다.
        return response()->json([
            'product' => $product,
            'production_history' => $history,
        ]);
    }

    /**
     * 한 번의 생산 기록과 작업자, 재고 및 추천 참고 이력을 저장합니다.
     *
     * 최신 레시피만 조회하고, 작업자는 일괄 저장하여
     * 불필요한 DB 접근을 줄입니다.
     */
    public function storeBatch(Request $request): JsonResponse
    {
        // 사용자 권한과 입력값을 검증합니다.
        $user = $this->user($request);

        $this->accessService->requirePermission(
            $user,
            'production.create'
        );

        $data = $this->validateBatch($request);

        $this->accessService->assertStoreDepartment(
            $user,
            (int) $data['store_id']
        );

        $this->assertDateUnlocked(
            (int) $data['store_id'],
            $data['work_date']
        );

        $this->assertWorkersScheduled(
            (int) $data['store_id'],
            $data['work_date'],
            $data['workers'] ?? []
        );

        /**
         * 생산 기록과 연결 데이터를 하나의 트랜잭션으로 저장합니다.
         * 저장 중 오류가 발생하면 변경 사항을 롤백합니다.
         */
        $batch = DB::transaction(function () use ($data, $user) {
            /**
             * 제품의 기본 정보만 조회합니다.
             *
             * 기존에는 모든 레시피와 재료, 제조 단계를 먼저 조회한 뒤
             * 최신 레시피를 다시 조회했습니다.
             */
            $product = Product::query()
                ->findOrFail($data['product_id']);

            abort_unless(
                $product->store_id === (int) $data['store_id'],
                422,
                '선택한 점포의 제품이 아닙니다.'
            );

            /**
             * 삭제되지 않은 최신 레시피 한 개만 조회합니다.
             * 생산 당시 스냅샷에 필요한 재료와 제조 단계도 함께 가져옵니다.
             */
            $recipe = $product->recipes()
                ->whereNull('deleted_at')
                ->with(['ingredients', 'steps'])
                ->latest('id')
                ->first();

            // 조회한 레시피를 그대로 사용하여 생산 당시 내용을 보존합니다.
            $recipeSnapshot = $recipe?->toArray();

            // 생산 기록을 생성합니다.
            $batch = ProductionBatch::create([
                'store_id' => $data['store_id'],
                'product_id' => $data['product_id'],
                'work_date' => $data['work_date'],
                'quantity' => $data['quantity'],
                'recipe_id' => $recipe?->id,
                'recipe_snapshot' => $recipeSnapshot,
                'recipe_deviated' => (bool) (
                    $data['recipe_deviated'] ?? false
                ),
                'recipe_deviation_note' =>
                    $data['recipe_deviation_note'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $user->id,
            ]);

            /**
             * 작업자 정보를 한 번의 INSERT로 저장합니다.
             * 기존 담당 공정과 기본값을 유지합니다.
             */
            $workers = [];
            $now = now();

            foreach ($data['workers'] ?? [] as $worker) {
                $workers[] = [
                    'production_batch_id' => $batch->id,
                    'user_id' => $worker['user_id'],
                    'process_type' => $worker['process_type'] ?? 'all',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($workers)) {
                DB::table('production_batch_workers')
                    ->insert($workers);
            }

            // 생산량에 해당하는 최초 재고 기록을 생성합니다.
            ProductStockLot::create([
                'store_id' => $data['store_id'],
                'product_id' => $data['product_id'],
                'production_batch_id' => $batch->id,
                'origin_production_date' => $data['work_date'],
                'initial_quantity' => $data['quantity'],
                'remaining_quantity' => $data['quantity'],
                'status' => 'active',
            ]);

            // 생산 확인 상태와 확인자를 갱신합니다.
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

            /**
             * 추천을 참고한 경우에만 이전 28일의 기록을 조회합니다.
             * 추천 대상, 조회 순서 및 행사 적용 조건은 유지합니다.
             */
            if ((bool) ($data['recommendation_referenced'] ?? false)) {
                $history = collect();
                $target = Carbon::parse($data['work_date']);

                for ($i = 28; $i >= 1; $i--) {
                    $historyDate = $target->copy()
                        ->subDays($i)
                        ->toDateString();

                    $daily = $this->dailyService->build(
                        (int) $data['store_id'],
                        $historyDate
                    );

                    $row = collect($daily['rows'])
                        ->firstWhere(
                            'id',
                            (int) $data['product_id']
                        );

                    if ($row && $row['production_confirmed']) {
                        $history->push([
                            'date' => $historyDate,
                            ...$row,
                            'special_day' => !empty(
                                $this->eventsForDate(
                                    (int) $data['store_id'],
                                    $historyDate
                                )
                            ),
                        ]);
                    }
                }

                // 대상 날짜의 행사 중 현재 제품에 적용되는 행사를 선택합니다.
                $targetEvents = collect(
                    $this->eventsForDate(
                        (int) $data['store_id'],
                        $data['work_date']
                    )
                )
                    ->filter(
                        fn (array $event) =>
                            empty($event['products'])
                            || collect($event['products'])->contains(
                                'id',
                                (int) $data['product_id']
                            )
                    )
                    ->values()
                    ->all();

                // 기존 추천 계산과 추천 결과 저장을 유지합니다.
                $recommendation = $this->recommendationService->recommend(
                    $history,
                    $target,
                    $targetEvents
                );

                $this->recommendationService->snapshot(
                    (int) $data['store_id'],
                    (int) $data['product_id'],
                    $data['work_date'],
                    $recommendation,
                    $batch->id,
                    $data['recommendation_deviation_reason'] ?? null
                );
            }

            return $batch;
        });

        // 생산 기록 등록 이력을 기존 방식으로 저장합니다.
        $this->auditService->log(
            $user,
            'production',
            'create',
            ProductionBatch::class,
            $batch->id,
            null,
            $batch->toArray(),
            '생산 기록 등록'
        );

        return response()->json([
            'message' => '생산 기록을 저장했습니다.',
            'batch' => $batch,
        ], 201);
    }

    /**
     * 마감 전 생산 기록의 수량, 메모 및 작업자를 수정합니다.
     *
     * 동일 제품의 재고 처리와 생산 수량 변경이 충돌하지 않도록
     * 제품 행을 먼저 잠그고, 생산 기록의 최신 버전을 확인합니다.
     *
     * 기존 작업자 저장 방식, 생산 수량 변경 조건,
     * 재고 최초 수량 갱신 및 감사 로그 동작은 유지합니다.
     */
    public function updateBatch(
        Request $request,
        ProductionBatch $batch
    ): JsonResponse {
        // 로그인 사용자와 생산 관리 수정 권한을 확인합니다.
        $user = $this->user($request);

        $this->accessService->requirePermission(
            $user,
            'production.update'
        );

        // 기존 입력값 검증 규칙을 유지합니다.
        $data = $request->validate([
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:100000',
            ],
            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'lock_version' => [
                'required',
                'integer',
                'min:1',
            ],
            'workers' => [
                'required',
                'array',
                'min:1',
            ],
            'workers.*.user_id' => [
                'required',
                'integer',
                'distinct',
                'exists:users,id',
            ],
        ]);

        $storeId = (int) $batch->store_id;
        $workDate = $batch->work_date->toDateString();

        // 기존 점포 권한 및 근무자 검사를 유지합니다.
        $this->accessService->assertStoreDepartment(
            $user,
            $storeId
        );

        $this->assertDateUnlocked(
            $storeId,
            $workDate
        );

        $this->assertWorkersScheduled(
            $storeId,
            $workDate,
            $data['workers']
        );

        /*
        * 변경 전후 기록은 트랜잭션 내부에서 확보합니다.
        * 잠금 이전의 오래된 모델 데이터를 감사 로그에
        * 사용하는 문제를 방지하기 위한 처리입니다.
        */
        [$before, $after] = DB::transaction(
            function () use ($batch, $data, $user) {

                /*
                * saveFlow()와 동일한 제품 행을 먼저 잠급니다.
                *
                * 생산 수량 변경과 로스·폐기·기타 출고·이월 저장이
                * 동일 제품의 재고를 동시에 처리하지 않도록 합니다.
                */
                Product::query()
                    ->whereKey((int) $batch->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                * 생산 기록을 잠그고 최신 데이터를 다시 조회합니다.
                *
                * 다른 사용자의 수정이 먼저 완료되었다면
                * 잠금이 해제된 이후 변경된 버전을 읽게 됩니다.
                */
                $lockedBatch = ProductionBatch::query()
                    ->whereKey($batch->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // 저장 직전의 마감 상태를 다시 확인합니다.
                $this->assertDateUnlocked(
                    (int) $lockedBatch->store_id,
                    $lockedBatch->work_date->toDateString()
                );

                // 최신 생산 기록의 버전으로 동시 수정 충돌을 검사합니다.
                abort_unless(
                    (int) $lockedBatch->lock_version
                        === (int) $data['lock_version'],
                    409,
                    '다른 사용자가 먼저 이 생산 기록을 변경했습니다. 최신 내용을 다시 확인해주세요.'
                );

                /*
                * 기존 수량보다 감소하는 경우에만
                * 연결된 재고 처리 기록을 검사합니다.
                */
                $reducingQuantity = (int) $data['quantity']
                    < (int) $lockedBatch->quantity;

                if ($reducingQuantity) {
                    $lotIds = ProductStockLot::query()
                        ->where(
                            'production_batch_id',
                            $lockedBatch->id
                        )
                        ->pluck('id');

                    // 기존 재고 이동 검사입니다.
                    $hasMovement = ProductStockMovement::query()
                        ->whereIn('stock_lot_id', $lotIds)
                        ->exists();

                    // 기존 로스 검사입니다.
                    $hasLoss = ProductionLoss::query()
                        ->whereIn('stock_lot_id', $lotIds)
                        ->exists();

                    // 기존 폐기 검사입니다.
                    $hasWaste = ProductionWaste::query()
                        ->whereIn('stock_lot_id', $lotIds)
                        ->exists();

                    // 기존 기타 출고 검사입니다.
                    $hasOtherOutflow = ProductionOtherOutflow::query()
                        ->whereIn('stock_lot_id', $lotIds)
                        ->exists();

                    abort_if(
                        $hasMovement
                            || $hasLoss
                            || $hasWaste
                            || $hasOtherOutflow,
                        422,
                        '이미 이월·로스·폐기 등 연결 기록이 있어 생산 수량을 줄일 수 없습니다. 연결 기록을 먼저 확인해주세요.'
                    );
                }

                // 잠금 이후 최신 수정 전 데이터를 확보합니다.
                $before = $lockedBatch->toArray();

                // 기존 작업자 연결 기록을 삭제합니다.
                DB::table('production_batch_workers')
                    ->where(
                        'production_batch_id',
                        $lockedBatch->id
                    )
                    ->delete();

                /*
                * 기존 방식대로 작업자 목록을 일괄 등록합니다.
                * 담당 공정의 기본값 'all'도 변경하지 않습니다.
                */
                $now = now();
                $workers = [];

                foreach ($data['workers'] as $worker) {
                    $workers[] = [
                        'production_batch_id' => $lockedBatch->id,
                        'user_id' => $worker['user_id'],
                        'process_type' => 'all',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('production_batch_workers')
                    ->insert($workers);

                // 생산 수량, 메모, 수정자 및 잠금 버전을 갱신합니다.
                $lockedBatch->update([
                    'quantity' => $data['quantity'],
                    'note' => $data['note'] ?? null,
                    'updated_by' => $user->id,
                    'lock_version' => $lockedBatch->lock_version + 1,
                ]);

                /*
                * 기존 동작대로 최초 생산 수량만 변경합니다.
                * remaining_quantity는 수정하지 않습니다.
                */
                ProductStockLot::query()
                    ->where(
                        'production_batch_id',
                        $lockedBatch->id
                    )
                    ->update([
                        'initial_quantity' => $data['quantity'],
                    ]);

                // 실제 저장된 최신 생산 기록을 확보합니다.
                $after = $lockedBatch->fresh()->toArray();

                return [$before, $after];
            }
        );

        // 기존 감사 로그 형식과 기록 내용을 유지합니다.
        $this->auditService->log(
            $user,
            'production',
            'update',
            ProductionBatch::class,
            $batch->id,
            $before,
            $after,
            '생산 기록 수정'
        );

        // 기존 성공 응답을 유지합니다.
        return response()->json([
            'message' => '생산 기록을 수정했습니다.',
        ]);
    }

    /**
     * 마감 전 생산 기록을 삭제합니다.
     *
     * 기존 동작대로 다음 날짜의 이월 입고가 연결된 경우 삭제를 차단합니다.
     * 로스·폐기·기타 출고가 연결된 경우에도 재고 불일치를 방지하기 위해
     * 생산 기록 삭제를 허용하지 않습니다.
     *
     * 삭제 가능한 경우 기존 재고 이동 기록을 정리하고,
     * 연결 재고를 삭제 상태로 변경한 뒤 생산 기록을 Soft Delete합니다.
     */
    public function deleteBatch(
        Request $request,
        ProductionBatch $batch
    ): JsonResponse {
        // 로그인 사용자와 생산 기록 삭제 권한을 확인합니다.
        $user = $this->user($request);

        $this->accessService->requirePermission(
            $user,
            'production.delete'
        );

        // 기존 삭제 메모 검증을 유지합니다.
        $data = $request->validate([
            'memo' => ['nullable', 'string', 'max:1000'],
        ]);

        // 해당 점포에 대한 권한과 업무 날짜의 수정 가능 여부를 확인합니다.
        $this->accessService->assertStoreDepartment(
            $user,
            (int) $batch->store_id
        );

        $this->assertDateUnlocked(
            (int) $batch->store_id,
            $batch->work_date->toDateString()
        );

        // 감사 로그에 사용할 삭제 전 정보를 보관합니다.
        $before = $batch->toArray();

        DB::transaction(function () use ($batch) {
            // 생산 기록을 잠가 동시 수정에 의한 충돌을 방지합니다.
            $lockedBatch = ProductionBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 삭제 직전에 마감 상태를 다시 확인합니다.
            $this->assertDateUnlocked(
                (int) $lockedBatch->store_id,
                $lockedBatch->work_date->toDateString()
            );

            // 생산 기록에 연결된 재고 ID를 조회합니다.
            $lotIds = ProductStockLot::query()
                ->where('production_batch_id', $lockedBatch->id)
                ->pluck('id');

            // 기존 동작: 다음 날짜의 이월 입고가 있으면 삭제를 차단합니다.
            $hasCarryoverIn = ProductStockMovement::query()
                ->whereIn('stock_lot_id', $lotIds)
                ->where('movement_type', 'carryover_in')
                ->exists();

            abort_if(
                $hasCarryoverIn,
                422,
                '이미 다음 날짜 이월과 연결된 생산 기록입니다. 연결 기록 수정 기능을 이용해주세요.'
            );

            // 로스 기록이 연결되어 있는지 확인합니다.
            $hasLoss = ProductionLoss::query()
                ->where(function ($query) use ($lotIds, $lockedBatch) {
                    $query->whereIn('stock_lot_id', $lotIds)
                        ->orWhere(
                            'production_batch_id',
                            $lockedBatch->id
                        );
                })
                ->exists();

            // 폐기 기록이 연결되어 있는지 확인합니다.
            $hasWaste = ProductionWaste::query()
                ->whereIn('stock_lot_id', $lotIds)
                ->exists();

            // 기타 출고 기록이 연결되어 있는지 확인합니다.
            $hasOtherOutflow = ProductionOtherOutflow::query()
                ->whereIn('stock_lot_id', $lotIds)
                ->exists();

            // 연결된 재고 처리 기록이 있다면 삭제를 차단합니다.
            abort_if(
                $hasLoss || $hasWaste || $hasOtherOutflow,
                422,
                '이미 로스·폐기·기타 출고 기록이 연결되어 있어 생산 기록을 삭제할 수 없습니다. 연결 기록을 먼저 확인해주세요.'
            );

            // 기존 동작을 유지하여 삭제 가능한 재고 이동 기록을 정리합니다.
            ProductStockMovement::query()
                ->whereIn('stock_lot_id', $lotIds)
                ->delete();

            // 연결 재고의 잔여 수량을 0으로 설정하고 삭제 상태로 변경합니다.
            ProductStockLot::query()
                ->whereIn('id', $lotIds)
                ->update([
                    'remaining_quantity' => 0,
                    'status' => 'deleted',
                ]);

            // 생산 기록을 기존 방식대로 Soft Delete합니다.
            $lockedBatch->delete();
        });

        // 기존 감사 로그를 유지합니다.
        $this->auditService->log(
            $user,
            'production',
            'delete',
            ProductionBatch::class,
            $batch->id,
            $before,
            ['memo' => $data['memo'] ?? null],
            '생산 기록 삭제'
        );

        // 기존 성공 응답을 유지합니다.
        return response()->json([
            'message' => '생산 기록을 삭제했습니다.',
        ]);
    }

    
    /**
     * 생산하지 않은 제품을 0개로 명시 확인하고 사유를 저장합니다.
     *
     * 동일 제품의 생산 수량 변경 및 재고 처리와 충돌하지 않도록
     * 제품 행을 먼저 잠근 뒤 생산 기록과 연결된 재고를 검증합니다.
     *
     * 기존 생산 기록 삭제 조건, 0개 확인 사유,
     * 감사 로그 및 응답 동작은 유지합니다.
     */
    public function confirmZeroProduction(Request $request): JsonResponse
    {
        // 로그인 사용자와 생산 등록 권한을 확인합니다.
        $user = $this->user($request);

        $this->accessService->requirePermission(
            $user,
            'production.create'
        );

        // 기존 입력값 검증 규칙을 유지합니다.
        $data = $request->validate([
            'store_id' => [
                'required',
                'integer',
                'exists:stores,id',
            ],
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
            'work_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'reason' => [
                'required',
                'string',
                Rule::in([
                    'no_plan',
                    'material_shortage',
                    'equipment_issue',
                    'staffing_issue',
                    'no_demand',
                    'other',
                ]),
            ],
            'reason_text' => [
                'nullable',
                'required_if:reason,other',
                'string',
                'max:120',
            ],
            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        // 기존 점포 권한 및 마감 상태 검사를 유지합니다.
        $this->accessService->assertStoreDepartment(
            $user,
            (int) $data['store_id']
        );

        $this->assertDateUnlocked(
            (int) $data['store_id'],
            $data['work_date']
        );

        $storeId = (int) $data['store_id'];
        $productId = (int) $data['product_id'];
        $workDate = $data['work_date'];

        /*
        * 생산 기록 검사, 연결 재고 검사, 생산 기록 삭제,
        * 생산 0개 확인 상태 저장을 하나의 트랜잭션으로 처리합니다.
        */
        DB::transaction(function () use (
            $storeId,
            $productId,
            $workDate,
            $data,
            $user
        ) {
            /*
            * saveFlow() 및 수정된 updateBatch()와 동일하게
            * 제품 행을 먼저 잠급니다.
            *
            * 같은 제품의 재고 처리와 생산 0개 확인이
            * 동시에 진행되지 않도록 하기 위한 공통 잠금입니다.
            */
            Product::query()
                ->whereKey($productId)
                ->lockForUpdate()
                ->firstOrFail();

            // 제품 잠금 이후 마감 상태를 다시 확인합니다.
            $this->assertDateUnlocked(
                $storeId,
                $workDate
            );

            // 기존 생산 기록을 잠그고 최신 데이터를 조회합니다.
            $batches = ProductionBatch::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereDate('work_date', $workDate)
                ->lockForUpdate()
                ->get();

            // 기존 이월 입고 수량 계산을 유지합니다.
            $carryIn = (int) ProductStockMovement::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereDate('work_date', $workDate)
                ->where('movement_type', 'carryover_in')
                ->sum('quantity');

            /*
            * 해당 날짜의 로스, 폐기, 기타 출고,
            * 이월 출고 수량을 기존 방식대로 합산합니다.
            */
            $allocated = (int) ProductionLoss::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereDate('work_date', $workDate)
                ->sum('quantity');

            $allocated += (int) ProductionWaste::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereDate('work_date', $workDate)
                ->sum('quantity');

            $allocated += (int) ProductionOtherOutflow::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereDate('work_date', $workDate)
                ->sum('quantity');

            $allocated += (int) ProductStockMovement::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereDate('work_date', $workDate)
                ->where('movement_type', 'carryover_out')
                ->sum('quantity');

            /*
            * 생산 수량을 0개로 변경했을 때
            * 이월 입고 수량만으로 기존 처리 수량을
            * 충당할 수 있는지 검사합니다.
            */
            abort_if(
                $allocated > $carryIn,
                422,
                '생산량을 0개로 바꾸면 이미 입력한 로스·폐기·이월 수량이 사용 가능 수량을 초과합니다. 연결된 기록을 먼저 확인해주세요.'
            );

            /*
            * 기존 생산 기록별 연결 재고를 확인합니다.
            * 연결 기록이 존재하면 생산 기록 삭제를 차단합니다.
            */
            foreach ($batches as $batch) {
                $lotIds = ProductStockLot::query()
                    ->where('production_batch_id', $batch->id)
                    ->pluck('id');

                // 기존 재고 이동 연결 검사입니다.
                $hasMovement = ProductStockMovement::query()
                    ->whereIn('stock_lot_id', $lotIds)
                    ->exists();

                // 기존 폐기 연결 검사입니다.
                $hasWaste = ProductionWaste::query()
                    ->whereIn('stock_lot_id', $lotIds)
                    ->exists();

                // 기존 생산 기록 기준 로스 연결 검사입니다.
                $hasLoss = ProductionLoss::query()
                    ->where('production_batch_id', $batch->id)
                    ->exists();

                // 기존에 추가한 기타 출고 연결 검사를 유지합니다.
                $hasOtherOutflow = ProductionOtherOutflow::query()
                    ->whereIn('stock_lot_id', $lotIds)
                    ->exists();

                // 기존 차단 조건과 오류 메시지를 유지합니다.
                abort_if(
                    $hasMovement
                        || $hasWaste
                        || $hasLoss
                        || $hasOtherOutflow,
                    422,
                    '이미 이월·로스·폐기와 연결된 생산 기록이 있어 바로 0개로 변경할 수 없습니다. 연결된 기록을 먼저 확인해주세요.'
                );

                // 연결 재고의 남은 수량을 0으로 변경하고 삭제 처리합니다.
                ProductStockLot::query()
                    ->whereIn('id', $lotIds)
                    ->update([
                        'remaining_quantity' => 0,
                        'status' => 'deleted',
                    ]);

                // 기존 방식대로 생산 기록을 Soft Delete합니다.
                $batch->delete();
            }

            /*
            * 기존 생산 0개 확인 상태와 사유를 저장합니다.
            * 사유 코드, 직접입력 내용, 메모 및 확인자는 변경하지 않습니다.
            */
            $this->updateConfirmationForDate(
                $storeId,
                $productId,
                $workDate,
                [
                    'production_confirmed' => true,
                    'zero_production_reason' => $data['reason'],
                    'zero_production_reason_text' =>
                        $data['reason_text'] ?? null,
                    'zero_production_note' =>
                        $data['note'] ?? null,
                    'confirmed_by' => $user->id,
                ]
            );
        });

        // 기존 감사 로그 기록을 유지합니다.
        $this->auditService->log(
            $user,
            'production',
            'confirm',
            Product::class,
            $productId,
            null,
            $data,
            '생산 0개 확인'
        );

        // 기존 성공 메시지를 유지합니다.
        return response()->json([
            'message' => '생산 0개를 확인했습니다.',
        ]);
    }

    /**
     * 제품의 한 가지 수량 처리 업무만 저장합니다.
     *
     * 화면에서 로스·폐기·기타 출고·이월을 각각 독립적으로 수정하므로
     * 선택하지 않은 업무 기록을 빈 배열로 덮어쓰지 않도록 서버에서도 분리 처리합니다.
     */
    public function saveFlow(Request $request): JsonResponse
    {
        // 로그인 사용자와 생산 관리 수정 권한을 확인합니다.
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.update');

        /**
         * 처리할 점포, 제품, 날짜 및 업무 유형을 검증합니다.
         * 로스·폐기·기타 출고는 사유와 수량을 사용하고,
         * 이월은 별도의 수량과 재고 출처를 사용합니다.
         */
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'type' => [
                'required',
                Rule::in(['loss', 'waste', 'other_outflow', 'carryover']),
            ],
            'reasons' => ['array'],
            'reasons.*.reason_code' => [
                'required_unless:type,carryover',
                'string',
                'max:60',
            ],
            'reasons.*.reason_text' => [
                'nullable',
                'string',
                'max:120',
            ],
            'reasons.*.quantity' => [
                'required_unless:type,carryover',
                'integer',
                'min:1',
            ],
            'reasons.*.stock_source' => [
                'nullable',
                Rule::in(['today', 'carryover']),
            ],
            'reasons.*.stock_lot_id' => [
                'nullable',
                'integer',
                'exists:product_stock_lots,id',
            ],
            'quantity' => [
                'required_if:type,carryover',
                'nullable',
                'integer',
                'min:0',
            ],
            /**
             * 이월 수량이 1개 이상이면 재고 출처를 반드시 선택해야 합니다.
             * 이월 수량이 0개인 경우에는 출처 없이도 저장할 수 있습니다.
             */
            'carryover_source' => [
                Rule::requiredIf(
                    fn () => $request->input('type') === 'carryover'
                        && (int) $request->input('quantity', 0) > 0
                ),
                'nullable',
                Rule::in(['today', 'incoming']),
            ],
            'stock_lot_id' => [
                Rule::requiredIf(
                    fn () => $request->input('type') === 'carryover'
                        && (int) $request->input('quantity', 0) > 0
                ),
                'nullable',
                'integer',
                'exists:product_stock_lots,id',
            ],
            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        // 선택한 점포의 업무 권한과 날짜의 수정 가능 여부를 확인합니다.
        $this->accessService->assertStoreDepartment(
            $user,
            (int) $data['store_id']
        );

        $this->assertDateUnlocked(
            (int) $data['store_id'],
            $data['work_date']
        );

        // 입력된 사유 목록을 컬렉션으로 변환합니다.
        $reasons = collect($data['reasons'] ?? []);

        /**
         * 이월은 사유 목록을 사용하지 않으므로 직접입력 검증에서 제외합니다.
         * 로스·폐기·기타 출고는 사유가 'other'일 때
         * 직접입력 내용이 비어 있는지 확인합니다.
         */
        $missingDirectReason = $data['type'] !== 'carryover'
            && $reasons->contains(
                fn (array $reason) => (
                    ($reason['reason_code'] ?? null) === 'other'
                    && trim((string) ($reason['reason_text'] ?? '')) === ''
                )
            );

        // 직접입력 사유가 비어 있으면 저장을 차단합니다.
        abort_if(
            $missingDirectReason,
            422,
            '직접입력 사유를 입력해주세요.'
        );

        /**
         * 같은 제품의 재고 변경을 트랜잭션으로 처리합니다.
         * 폐기 업무에서는 저장 전후 기록을 함께 확보합니다.
         */
        $wasteAuditRecords = DB::transaction(function () use ($data, $user, $reasons) {
            /**
             * 동일 제품의 재고 변경을 직렬화하여 동시 요청이
             * 같은 잔여 수량을 중복 사용하지 못하도록 합니다.
             */
            Product::query()
                ->whereKey((int) $data['product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // 저장 직전에 마감 상태를 다시 확인합니다.
            $this->assertDateUnlocked((int) $data['store_id'], $data['work_date']);

            /**
             * 잠금 이후 최신 생산 현황을 조회하고
             * 요청 수량이 사용 가능한 수량을 초과하지 않는지 확인합니다.
             */
            $daily = $this->dailyService->build(
                (int) $data['store_id'],
                $data['work_date']
            );

            $row = collect($daily['rows'])
                ->firstWhere('id', (int) $data['product_id']);

            abort_unless(
                $row,
                422,
                '선택한 제품의 생산 현황을 확인할 수 없습니다.'
            );

            $availableForType = $this->availableQuantityForDisposition(
                $row,
                $data['type']
            );

            $requestedQuantity = $data['type'] === 'carryover'
                ? (int) ($data['quantity'] ?? 0)
                : (int) $reasons->sum('quantity');

            abort_if(
                $requestedQuantity > $availableForType,
                422,
                '입력한 수량이 현재 사용 가능한 수량보다 많습니다.'
            );

            // 로스, 폐기, 기타 출고의 재고 출처와 수량을 검증합니다.
            if (in_array($data['type'], ['loss', 'waste', 'other_outflow'], true)) {
                $this->validateReasonStockSources(
                    (int) $data['store_id'],
                    (int) $data['product_id'],
                    $data['work_date'],
                    $data['type'],
                    $reasons,
                    $row['stock_sources'] ?? [],
                );
            }

            // 저장에 필요한 점포, 제품, 날짜 및 업무 유형을 준비합니다.
            $storeId = (int) $data['store_id'];
            $productId = (int) $data['product_id'];
            $date = $data['work_date'];
            $type = $data['type'];
            $reasons = $data['reasons'] ?? [];
            $note = $data['note'] ?? null;

            /**
             * 폐기 기록을 교체하기 전에 기존 기록을 확보합니다.
             * 실제 폐기일과 최초 생산일을 구분하여 보관하고
             * 이후 변경 전후 이력을 기록하는 데 사용합니다.
             */
            $previousWasteRecords = [];

            if ($type === 'waste') {
                $previousWasteRecords = ProductionWaste::query()
                    ->where('store_id', $storeId)
                    ->where('product_id', $productId)
                    ->whereDate('work_date', $date)
                    ->get([
                        'id',
                        'stock_lot_id',
                        'work_date',
                        'attribution_date',
                        'quantity',
                        'note',
                    ])
                    ->toArray();
            }

            /**
             * 요청한 업무 유형의 기록만 교체하고
             * 해당 업무의 확인 상태를 저장합니다.
             */
            if ($type === 'loss') {
                $this->replaceLosses(
                    $storeId,
                    $productId,
                    $date,
                    $reasons,
                    $note,
                    $user->id
                );

                $this->confirmFlowField(
                    $storeId,
                    $productId,
                    $date,
                    'loss_confirmed',
                    $user->id
                );
            } elseif ($type === 'waste') {
                $this->replaceWastes(
                    $storeId,
                    $productId,
                    $date,
                    $reasons,
                    $note,
                    $user->id
                );

                $this->confirmFlowField(
                    $storeId,
                    $productId,
                    $date,
                    'waste_confirmed',
                    $user->id
                );
            } elseif ($type === 'other_outflow') {
                $this->replaceOutflows(
                    $storeId,
                    $productId,
                    $date,
                    $reasons,
                    $note,
                    $user->id
                );

                $this->confirmFlowField(
                    $storeId,
                    $productId,
                    $date,
                    'disposition_confirmed',
                    $user->id
                );
            } else {
                $this->replaceCarryover(
                    $storeId,
                    $productId,
                    $date,
                    (int) ($data['quantity'] ?? 0),
                    $data['carryover_source'] ?? 'today',
                    $user->id,
                    isset($data['stock_lot_id'])
                        ? (int) $data['stock_lot_id']
                        : null
                );

                $this->confirmFlowField(
                    $storeId,
                    $productId,
                    $date,
                    'disposition_confirmed',
                    $user->id
                );
            }

            // 폐기가 아닌 업무는 기존 저장 동작을 유지합니다.
            if ($type !== 'waste') {
                return null;
            }

            /**
             * 폐기 저장 후 실제 DB에 기록된 내용을 조회합니다.
             * 변경 전후 기록을 함께 반환하여 이력에서
             * 실제 폐기일과 최초 생산일을 비교할 수 있도록 합니다.
             */
            $currentWasteRecords = ProductionWaste::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereDate('work_date', $date)
                ->get([
                    'id',
                    'stock_lot_id',
                    'work_date',
                    'attribution_date',
                    'quantity',
                    'note',
                ])
                ->toArray();

            return [
                'before' => $previousWasteRecords,
                'after' => $currentWasteRecords,
            ];
        });

        // 업무 유형별 화면 표시 명칭을 설정합니다.
        $labels = [
            'loss' => '로스',
            'waste' => '폐기',
            'other_outflow' => '기타 출고',
            'carryover' => '이월',
        ];

        $label = $labels[$data['type']];

        // 업무 유형에 해당하는 확인 상태 필드를 결정합니다.
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

        abort_unless(
            $confirmed,
            500,
            "{$label} 확인 상태를 저장하지 못했습니다. 다시 시도해주세요."
        );

        // 저장 후 최신 일일 현황과 해당 제품 정보를 조회합니다.
        $freshDaily = $this->dailyService->build(
            (int) $data['store_id'],
            $data['work_date']
        );

        $freshRow = collect($freshDaily['rows'])
            ->firstWhere('id', (int) $data['product_id']);

        /**
         * 변경 이력에 표시할 설명을 생성합니다.
         * 이월 재고를 처리한 경우 최초 생산일을 설명에 포함합니다.
         */
        $auditDescription = $this->flowAuditDescription(
            $label,
            $data['work_date'],
            $data['reasons'] ?? [],
        );

        /**
         * 폐기 업무에서는 변경 전후의 실제 폐기 기록을 감사 로그에 포함합니다.
         * 이월 재고의 최초 생산일도 함께 저장하여
         * 실제 폐기일과 최초 생산일 양쪽에서 이력을 조회할 수 있도록 준비합니다.
         */
        $auditOldValues = null;
        $auditNewValues = $data;

        if ($data['type'] === 'waste' && $wasteAuditRecords !== null) {
            $previousWasteRecords = $wasteAuditRecords['before'];
            $currentWasteRecords = $wasteAuditRecords['after'];

            /**
             * 변경 전후 기록에 포함된 최초 생산일을 모두 수집합니다.
             * 수정 또는 삭제로 폐기 수량이 0개가 되더라도
             * 기존 최초 생산일에서 변경 이력을 찾을 수 있도록 합니다.
             */
            $attributionDates = collect($previousWasteRecords)
                ->concat($currentWasteRecords)
                ->pluck('attribution_date')
                ->filter()
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->unique()
                ->values()
                ->all();

            $auditOldValues = [
                'work_date' => $data['work_date'],
                'waste_records' => $previousWasteRecords,
            ];

            $auditNewValues = array_merge($data, [
                'waste_records' => $currentWasteRecords,
                'attribution_dates' => $attributionDates,
            ]);
        }

        // 변경 전후 데이터를 기존 감사 로그 서비스로 저장합니다.
        $this->auditService->log(
            $user,
            'production',
            'update',
            Product::class,
            (int) $data['product_id'],
            $auditOldValues,
            $auditNewValues,
            $auditDescription,
        );

        // 저장 완료 메시지와 최신 생산 현황을 반환합니다.
        return response()->json([
            'message' => "{$label} 처리를 저장했습니다.",
            'row' => $freshRow,
            'daily' => $freshDaily,
        ]);
    }

    /**
     * 변경 이력에서 이월 재고의 원 생산일을 바로 알아볼 수 있도록 설명을 구성합니다.
     * 여러 생산일을 한 번에 처리한 경우 중복 날짜는 제거해 짧게 표시합니다.
     */
    private function flowAuditDescription(string $label, string $workDate, array $reasons): string
    {
        // 입력된 사유에서 중복되지 않은 재고 기록 ID를 수집합니다.
        $lotIds = collect($reasons)
            ->pluck('stock_lot_id')
            ->filter()
            ->unique()
            ->values();

        // 재고 기록이 없다면 기존 기본 설명을 반환합니다.
        if ($lotIds->isEmpty()) {
            return "{$label} 처리 저장";
        }

        /**
         * 재고 기록을 한 번에 조회하고 ID별 최초 생산일을 연결합니다.
         * 입력된 재고 기록의 순서를 유지하면서
         * 불필요한 개별 DB 조회를 방지합니다.
         */
        $originDatesByLot = ProductStockLot::query()
            ->whereIn('id', $lotIds)
            ->get(['id', 'origin_production_date'])
            ->mapWithKeys(
                fn (ProductStockLot $lot) => [
                    $lot->id => $lot->origin_production_date?->toDateString(),
                ]
            );

        // 실제 작업일과 다른 최초 생산일만 추출하고 중복 날짜를 제거합니다.
        $originDates = $lotIds
            ->map(fn ($lotId) => $originDatesByLot->get($lotId))
            ->filter()
            ->reject(fn ($originDate) => $originDate === $workDate)
            ->unique()
            ->map(fn ($originDate) => Carbon::parse($originDate)->format('m/d'))
            ->values();

        // 이월 재고가 없다면 기존 기본 설명을 반환합니다.
        if ($originDates->isEmpty()) {
            return "{$label} 처리 저장";
        }

        // 최초 생산일을 포함한 기존 형식의 변경 이력 설명을 반환합니다.
        return "{$label} 처리 저장 · {$originDates->join(' · ')} 생산 이월 재고";
    }

    
    /**
     * 선택한 수량 처리 항목의 확인 상태만 갱신합니다.
     *
     * 기존 확인 기록이 있으면 모두 갱신하고,
     * 기록이 없을 때만 새 확인 행을 생성합니다.
     *
     * 불필요한 존재 여부 조회를 제거하여 DB 접근을 줄입니다.
     */
    private function confirmFlowField(
        int $storeId,
        int $productId,
        string $date,
        string $field,
        int $userId,
    ): void {
        // 확인 상태를 갱신할 기준 시각을 한 번만 생성합니다.
        $now = now();

        /**
         * 과거 날짜 저장 형식의 차이를 고려하여 날짜 기준으로 조회합니다.
         *
         * 동일한 점포, 제품, 날짜에 확인 기록이 여러 개 존재하면
         * 기존 동작대로 해당 기록을 모두 갱신합니다.
         */
        $updatedCount = DB::table('production_confirmations')
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('work_date', $date)
            ->update([
                $field => true,
                'confirmed_by' => $userId,
                'updated_at' => $now,
            ]);

        // 기존 확인 기록을 갱신했다면 새 행을 생성하지 않습니다.
        if ($updatedCount > 0) {
            return;
        }

        /**
         * 기존 확인 기록이 없는 경우에만 새 행을 생성합니다.
         *
         * 기존 확인 필드와 생성자, 생성 시각을 그대로 유지합니다.
         */
        DB::table('production_confirmations')->insert([
            'store_id' => $storeId,
            'product_id' => $productId,
            'work_date' => $date,
            $field => true,
            'confirmed_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 미확인 항목을 없음(0)으로 확인합니다.
     * type=all은 생산·이월·로스·폐기의 미확인 플래그만 한 번에 확인하며 실제 수량 기록은 변경하지 않습니다.
     */
    public function bulkConfirmZero(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.update');
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', Rule::in(['production', 'carryover', 'loss', 'waste', 'all'])],
        ]);
        $storeId = (int) $data['store_id'];
        $this->accessService->assertStoreDepartment($user, $storeId);
        $this->assertDateUnlocked($storeId, $data['work_date']);

        $fieldMap = [
            'production' => 'production_confirmed',
            'carryover' => 'disposition_confirmed',
            'loss' => 'loss_confirmed',
            'waste' => 'waste_confirmed',
        ];
        $labelMap = [
            'production' => '생산',
            'carryover' => '이월',
            'loss' => '로스',
            'waste' => '폐기',
        ];
        $types = $data['type'] === 'all' ? array_keys($fieldMap) : [$data['type']];
        $dailyBefore = $this->dailyService->build($storeId, $data['work_date']);
        $confirmedCount = 0;

        DB::transaction(function () use ($types, $fieldMap, $dailyBefore, $storeId, $data, $user, &$confirmedCount) {
            foreach ($types as $type) {
                $field = $fieldMap[$type];
                $productIds = collect($dailyBefore['rows'])
                    ->filter(fn (array $row) => $row['is_active'] && ! $row[$field])
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id);

                foreach ($productIds as $productId) {
                    $this->confirmFlowField($storeId, $productId, $data['work_date'], $field, $user->id);
                    $confirmedCount++;
                }
            }
        });

        $daily = $this->dailyService->build($storeId, $data['work_date']);
        $remaining = collect($daily['rows'])->filter(function (array $row) use ($types, $fieldMap) {
            if (! $row['is_active']) {
                return false;
            }

            return collect($types)->contains(fn (string $type) => ! $row[$fieldMap[$type]]);
        });
        abort_if($remaining->isNotEmpty(), 500, '일괄 확인 상태를 저장하지 못했습니다. 다시 시도해주세요.');

        $label = $data['type'] === 'all' ? '전체 미확인 항목' : $labelMap[$data['type']];
        $this->auditService->log(
            $user,
            'production',
            'confirm',
            ProductionConfirmation::class,
            null,
            null,
            $data,
            "{$label} 0 확인",
        );

        return response()->json([
            'message' => "{$label}을 없음(0)으로 확인했습니다.",
            'daily' => $daily,
            'confirmed_count' => $confirmedCount,
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
        $stockIssues = $this->dailyStockIssues($daily);

        return response()->json([
            'daily' => $daily,
            'can_close' => $incomplete->isEmpty() && $stockIssues->isEmpty(),
            'incomplete' => $incomplete,
            'stock_issues' => $stockIssues,
        ]);
    }

    // 사용자의 최종 확인 뒤 하루 업무를 마감합니다. 미완료나 수량 불일치가 있으면 서버에서 차단합니다.
    public function closeDay(Request $request): JsonResponse
    {
        // 현재 사용자와 생산 관리 수정 권한을 확인합니다.
        $user = $this->user($request);

        $this->accessService->requirePermission(
            $user,
            'production.update'
        );

        /**
         * 마감에 필요한 점포, 업무 날짜, 메모를 검증합니다.
         * 점포와 날짜는 필수이며 마감 메모는 선택 사항입니다.
         */
        $data = $request->validate([
            'store_id' => [
                'required',
                'integer',
                'exists:stores,id',
            ],
            'work_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'daily_memo' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        // 사용자가 해당 점포의 생산 업무를 처리할 권한이 있는지 확인합니다.
        $this->accessService->assertStoreDepartment(
            $user,
            (int) $data['store_id']
        );

        /**
         * 마감 대상 날짜의 최신 생산 현황을 조회합니다.
         * 생산, 이월, 로스, 폐기 등의 확인 상태와 수량을 검증하는 데 사용합니다.
         */
        $daily = $this->dailyService->build(
            (int) $data['store_id'],
            $data['work_date']
        );

        // 활성 제품 중 아직 확인이 완료되지 않은 제품이 있는지 확인합니다.
        $hasIncompleteProducts = collect($daily['rows'])
            ->where('is_active', true)
            ->contains('complete', false);

        // 미확인 제품이 남아 있으면 마감을 차단합니다.
        abort_if(
            $hasIncompleteProducts,
            422,
            '아직 확인하지 않은 제품이 있어 마감할 수 없습니다.'
        );

        /**
         * 일일 생산 현황에서 재고 수량의 불일치 여부를 확인합니다.
         * 문제가 발견되면 첫 번째 오류 메시지를 반환하고 마감을 차단합니다.
         */
        $stockIssues = $this->dailyStockIssues($daily);

        abort_if(
            $stockIssues->isNotEmpty(),
            422,
            $stockIssues->first()
        );

        /**
         * 모든 마감 조건을 통과하면 해당 날짜를 마감 완료 상태로 저장합니다.
         * 마감 메모, 처리자, 처리 시각 및 최종 수정자를 함께 기록합니다.
         */
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

        /**
         * 저장된 마감 기록을 DB에서 다시 조회합니다.
         * 실제 마감 상태가 완료로 반영되지 않았다면 오류를 반환합니다.
         */
        $closure->refresh();

        abort_unless(
            $closure->status === 'closed',
            500,
            '마감 상태를 저장하지 못했습니다. 다시 시도해주세요.'
        );

        /** 
         * 해당 점포와 날짜의 날씨 기록이 이미 존재하는지 확인합니다.
         * work_date에 시각이 포함되어 있어도 날짜를 기준으로 조회합니다.
        */ 
        $existingWeather = StoreDailyWeather::query()
            ->where('store_id', $data['store_id'])
            ->whereDate('work_date', $data['work_date'])
            ->first();

        // 기존 기록이 없을 때만 새로운 날씨 기록을 생성합니다.
        if ($existingWeather === null) {
            StoreDailyWeather::create([
                'store_id' => $data['store_id'],
                'work_date' => $data['work_date'],
                'collection_status' => 'pending',
            ]);
        }

        /**
         * 마감 후 수정이 진행 중인 기록을 조회합니다.
         * 같은 점포와 날짜에서 수정 후 데이터가 저장되지 않은 최신 기록만 가져옵니다.
         */
        $openCorrection = ProductionCorrection::query()
            ->where('store_id', $data['store_id'])
            ->whereDate('work_date', $data['work_date'])
            ->whereNull('after_data')
            ->latest('id')
            ->first();

        // 마감 후 수정 기록이 존재하면 현재 일일 현황을 수정 완료 데이터로 저장합니다.
        if ($openCorrection) {
            $openCorrection->update([
                'after_data' => $this->dailyService->build(
                    (int) $data['store_id'],
                    $data['work_date']
                ),
            ]);
        }

        // 마감 완료 후 해당 점포와 날짜의 임시 저장 데이터를 삭제합니다.
        Session::forget(
            $this->draftKey(
                (int) $data['store_id'],
                $data['work_date']
            )
        );

        /**
         * 마감 작업의 변경 이력을 기록합니다.
         * 날짜를 기준으로 마감 기록 ID를 조회해 감사 로그에 연결합니다.
         */
        $closureId = ProductionDailyClosure::query()
            ->where('store_id', $data['store_id'])
            ->whereDate('work_date', $data['work_date'])
            ->value('id');

        $this->auditService->log(
            $user,
            'production',
            'close',
            ProductionDailyClosure::class,
            (int) $closureId,
            null,
            $data,
            '하루 업무 마감'
        );

        // 마감 완료 메시지와 최신 일일 현황을 반환합니다.
        return response()->json([
            'message' => '하루 업무를 마감했습니다.',
            'daily' => $this->dailyService->build(
                (int) $data['store_id'],
                $data['work_date']
            ),
        ]);
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
        $attributedWastes = $this->monthlyQuantities(ProductionWaste::query(), $storeId, 'attribution_date', $startDate, $endDate);
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
            $attributedWaste = (int) ($attributedWastes[$dateKey] ?? 0);
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
                    'waste_rate' => $this->wasteRate($attributedWaste, $produced),
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
        $attributedWaste = $this->sumByDate(ProductionWaste::query(), $storeId, 'attribution_date', $data['from'], $data['to']);

        $series = collect();
        for ($date = Carbon::parse($data['from']); $date->lte(Carbon::parse($data['to'])); $date->addDay()) {
            $key = $date->toDateString();
            $productionValue = (int) ($production[$key] ?? 0);
            $wasteValue = (int) ($waste[$key] ?? 0);
            $attributedWasteValue = (int) ($attributedWaste[$key] ?? 0);
            $series->push([
                'date' => $key,
                'production' => $productionValue,
                'carryover' => (int) ($carryover[$key] ?? 0),
                'loss' => (int) ($loss[$key] ?? 0),
                'waste' => $wasteValue,
                'attributed_waste' => $attributedWasteValue,
                'waste_rate' => $this->wasteRate($attributedWasteValue, $productionValue),
            ]);
        }

        $productionTotal = (int) $series->sum('production');
        $wasteTotal = (int) $series->sum('waste');
        $attributedWasteTotal = (int) $series->sum('attributed_waste');

        // 같은 길이의 직전 기간을 함께 계산해 현재 기간의 증감을 의미 있게 비교합니다.
        $periodDays = Carbon::parse($data['from'])->diffInDays(Carbon::parse($data['to'])) + 1;
        $previousTo = Carbon::parse($data['from'])->subDay()->toDateString();
        $previousFrom = Carbon::parse($previousTo)->subDays($periodDays - 1)->toDateString();
        $previousProduction = array_sum($this->sumByDate(ProductionBatch::query(), $storeId, 'work_date', $previousFrom, $previousTo));
        $previousCarryover = array_sum($this->sumByDate(ProductStockMovement::query()->where('movement_type', 'carryover_in'), $storeId, 'work_date', $previousFrom, $previousTo));
        $previousLoss = array_sum($this->sumByDate(ProductionLoss::query(), $storeId, 'work_date', $previousFrom, $previousTo));
        $previousWaste = array_sum($this->sumByDate(ProductionWaste::query(), $storeId, 'work_date', $previousFrom, $previousTo));
        $previousAttributedWaste = array_sum($this->sumByDate(ProductionWaste::query(), $storeId, 'attribution_date', $previousFrom, $previousTo));

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
            'attributed_waste' => $this->sumByProduct(ProductionWaste::query(), $storeId, 'attribution_date', $data['from'], $data['to']),
        ];
        $productIds = collect($productMetrics)->flatMap(fn (array $values) => array_keys($values))->unique()->values();
        $productTotals = $productIds->map(fn ($productId) => [
            'product_id' => (int) $productId,
            'product_name' => $productNames[$productId] ?? '-',
            'production' => (int) ($productMetrics['production'][$productId] ?? 0),
            'carryover' => (int) ($productMetrics['carryover'][$productId] ?? 0),
            'loss' => (int) ($productMetrics['loss'][$productId] ?? 0),
            'waste' => (int) ($productMetrics['waste'][$productId] ?? 0),
            'attributed_waste' => (int) ($productMetrics['attributed_waste'][$productId] ?? 0),
        ])->values();

        return response()->json([
            'series' => $series,
            'product_totals' => $productTotals,
            'totals' => [
                'production' => $productionTotal,
                'carryover' => (int) $series->sum('carryover'),
                'loss' => (int) $series->sum('loss'),
                'waste' => $wasteTotal,
                'waste_rate' => $this->wasteRate($attributedWasteTotal, $productionTotal),
            ],
            'previous_totals' => [
                'production' => (int) $previousProduction,
                'carryover' => (int) $previousCarryover,
                'loss' => (int) $previousLoss,
                'waste' => (int) $previousWaste,
                'waste_rate' => $this->wasteRate((int) $previousAttributedWaste, (int) $previousProduction),
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
            'attributed_waste' => $this->sumByDate(ProductionWaste::query(), $storeId, 'attribution_date', $from, $to),
        ];
        $dailyFlow = collect();
        for ($cursor = Carbon::parse($from); $cursor->lte($target); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $production = (int) ($dateMetrics['production'][$key] ?? 0);
            $waste = (int) ($dateMetrics['waste'][$key] ?? 0);
            $attributedWaste = (int) ($dateMetrics['attributed_waste'][$key] ?? 0);
            $dailyFlow->push([
                'date' => $key,
                'production' => $production,
                'carryover' => (int) ($dateMetrics['carryover'][$key] ?? 0),
                'loss' => (int) ($dateMetrics['loss'][$key] ?? 0),
                'waste' => $waste,
                'waste_rate' => $this->wasteRate($attributedWaste, $production),
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

    /**
     * 선택한 날짜의 생산 관련 변경 이력을 최신순으로 반환합니다.
     *
     * 일반 변경 이력은 기존 작업일 기준으로 조회하고,
     * 이월 재고 폐기는 실제 폐기일과 최초 생산일 모두에서
     * 동일한 변경 이력을 확인할 수 있도록 합니다.
     *
     * 변경 이력에 제품명을 함께 표시할 수 있도록
     * 관련 제품을 한 번에 조회하여 응답에 추가합니다.
     */
    public function history(Request $request): JsonResponse
    {
        // 로그인 사용자와 생산 관리 조회 권한을 확인합니다.
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.view');

        // 조회할 점포와 날짜를 검증합니다.
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'work_date' => ['required', 'date_format:Y-m-d'],
        ]);

        // 선택한 점포의 생산 정보를 조회할 수 있는지 확인합니다.
        $this->assertStoreReadable($user, (int) $data['store_id']);

        /**
         * 생산 기록의 날짜는 모델 직렬화 과정에서 UTC로 저장됩니다.
         * 조회할 한국 날짜의 자정을 UTC로 변환하여 정확히 비교합니다.
         */
        $productionDate = Carbon::createFromFormat(
            '!Y-m-d',
            $data['work_date'],
            'Asia/Seoul'
        )->utc()->format('Y-m-d\TH:i:s.u\Z');

        /**
         * 기존 작업일 및 대상 날짜에 해당하는 변경 이력을 조회합니다.
         * 폐기 기록에 최초 생산일이 포함된 경우에는
         * 해당 생산일에서도 동일한 변경 이력을 조회합니다.
         */
        $logs = AuditLog::query()
            ->with('user:id,name')
            ->where('domain', 'production')
            ->where(function ($query) use ($data, $productionDate) {
                /*
                * 기존 생산·이월·로스·폐기 및 0개 확인 이력의
                * 작업일 조회 조건을 유지합니다.
                */
                $query->where('new_values->work_date', $data['work_date'])
                    ->orWhere('new_values->target_date', $data['work_date'])
                    ->orWhere('old_values->work_date', $data['work_date'])
                    ->orWhereJsonContains(
                        'new_values->attribution_dates',
                        $data['work_date']
                );

                /*
                * 생산 등록·수정·삭제 기록은 ProductionBatch 모델을
                * 대상으로 저장되므로 UTC 날짜 형식도 조회합니다.
                *
                * 등록은 new_values, 삭제는 old_values,
                * 수정은 변경 전후 양쪽 날짜를 확인합니다.
                */
                $query->orWhere(function ($batchQuery) use ($productionDate) {
                    $batchQuery
                        ->where('target_type', ProductionBatch::class)
                        ->where(function ($dateQuery) use ($productionDate) {
                            $dateQuery
                                ->where('new_values->work_date', $productionDate)
                                ->orWhere('old_values->work_date', $productionDate);
                    });
                });
            })
            ->where(function ($query) use ($data) {
                $query->where('new_values->store_id', (int) $data['store_id'])
                    ->orWhere('old_values->store_id', (int) $data['store_id'])
                    ->orWhere(function ($nested) {
                        $nested->whereNull('new_values->store_id')
                            ->whereNull('old_values->store_id');
                    });
            })
            ->latest('id')
            ->limit(100)
            ->get([
                'id',
                'user_id',
                'action',
                'target_type',
                'target_id',
                'description',
                'old_values',
                'new_values',
                'created_at',
            ]);

        /**
         * 변경 이력에서 사용된 제품 ID를 수집합니다.
         *
         * 생산 기록은 ProductionBatch에 제품 ID가 저장되고,
         * 로스·폐기·이월 기록은 Product에 연결됩니다.
         *
         * 제품별로 DB를 반복 조회하지 않도록
         * 중복된 제품 ID는 제거합니다.
         */
        $productIds = $logs
            ->map(function ($log) {
                $newValues = $log->new_values ?? [];
                $oldValues = $log->old_values ?? [];

                if ($log->target_type === ProductionBatch::class) {
                    return $newValues['product_id']
                        ?? $oldValues['product_id']
                        ?? null;
                }

                if ($log->target_type === Product::class) {
                    return $newValues['product_id']
                        ?? $oldValues['product_id']
                        ?? $log->target_id;
                }

                return null;
            })
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->unique()
            ->values();

        /**
         * 변경 이력에 필요한 제품명을 한 번에 조회합니다.
         *
         * 삭제된 제품의 과거 변경 이력도 확인할 수 있도록
         * Soft Delete된 제품까지 조회 대상에 포함합니다.
         *
         * 현재 조회 중인 점포의 제품으로 범위를 제한합니다.
         */
        $productNames = Product::withTrashed()
            ->where('store_id', (int) $data['store_id'])
            ->whereIn('id', $productIds)
            ->pluck('name', 'id');

        /**
         * 각 변경 이력에 제품명을 추가합니다.
         *
         * 기존 action, description, old_values, new_values 등은
         * 변경하지 않고 화면 표시용 product_name만 추가합니다.
         *
         * 마감처럼 특정 제품과 연결되지 않은 이력에는
         * product_name을 null로 설정합니다.
         */
        $logs->each(function ($log) use ($productNames) {
            $newValues = $log->new_values ?? [];
            $oldValues = $log->old_values ?? [];

            $productId = null;

            if ($log->target_type === ProductionBatch::class) {
                $productId = $newValues['product_id']
                    ?? $oldValues['product_id']
                    ?? null;
            } elseif ($log->target_type === Product::class) {
                $productId = $newValues['product_id']
                    ?? $oldValues['product_id']
                    ?? $log->target_id;
            }

            $log->setAttribute(
                'product_name',
                $productId !== null
                    ? $productNames->get((int) $productId)
                    : null
            );

            /*
            * 원본 감사 로그 설명은 보존합니다.
            * 화면에서 동작 칩을 별도로 표시하므로
            * 기본 작업 설명에 중복된 동작 문구를 붙이지 않습니다.
            */
            $displayDescription = $log->description;

            if ($log->target_type === ProductionBatch::class) {
                $displayDescription = '생산 기록';
            } elseif ($log->target_type === Product::class) {
                $type = $newValues['type'] ?? $oldValues['type'] ?? null;

                $labels = [
                    'loss' => '로스',
                    'waste' => '폐기',
                    'carryover' => '이월',
                    'other_outflow' => '기타 출고',
                ];

                if (isset($labels[$type])) {
                    $displayDescription = $labels[$type] . ' 처리';
                } elseif (
                    $log->action === 'confirm'
                    && $log->description === '생산 0개 확인'
                ) {
                    $displayDescription = '생산 0개';
                }
            }

            $log->setAttribute('display_description', $displayDescription);
        });

        // 기존 응답 구조를 유지하면서 제품명을 함께 반환합니다.
        return response()->json([
            'logs' => $logs,
        ]);
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
        return $request->validate(['store_id' => ['required', 'integer', 'exists:stores,id'], 'product_id' => ['required', 'integer', 'exists:products,id'], 'work_date' => ['required', 'date_format:Y-m-d'], 'quantity' => ['required', 'integer', 'min:1', 'max:100000'], 'recipe_deviated' => ['boolean'], 'recipe_deviation_note' => ['nullable', 'required_if:recipe_deviated,true', 'string', 'max:1000'], 'note' => ['nullable', 'string', 'max:1000'], 'recommendation_referenced' => ['nullable', 'boolean'], 'recommendation_deviation_reason' => ['nullable', 'string', 'max:1000'], 'workers' => ['required', 'array', 'min:1'], 'workers.*.user_id' => ['required', 'integer', 'exists:users,id'], 'workers.*.process_type' => ['nullable', 'string', Rule::in(['all', 'mixing', 'shaping', 'proofing', 'oven', 'other'])]]);
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

    /**
     * 마감 상태와 수정 권한을 확인합니다.
     * 마감 후 수정 중인 날짜는 관리자 수정 권한이 필요합니다.
     */
    private function assertDateUnlocked(int $storeId, string $date): void
    {
        // 해당 날짜의 마감 상태를 조회합니다.
        $closure = ProductionDailyClosure::query()
            ->where('store_id', $storeId)
            ->whereDate('work_date', $date)
            ->first();

        // 마감 완료 상태에서는 일반 수정을 차단합니다.
        abort_if(
            $closure?->status === 'closed',
            422,
            '이미 마감된 날짜입니다. 마감 후 수정 기능을 이용해주세요.'
        );

        // 마감 후 수정 상태에서는 별도 관리자 권한을 검사합니다.
        if ($closure?->status === 'correction_open') {
            $user = $this->user(request());

            $this->accessService->requirePermission(
                $user,
                'production.correct'
            );
        }
    }

    // 선택 날짜에 적용되는 행사 목록을 반환합니다.
    private function eventsForDate(int $storeId, string $date): array
    {
        return StoreCalendarEvent::query()->with('products:id,name')->where('store_id', $storeId)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->orderBy('start_date')->get()->toArray();
    }

    /**
     * 선택 날짜보다 이전에 남아 있는 가장 오래된 미마감 영업일을 찾습니다.
     *
     * 기존 30일 검사 범위와 날짜별 판단 순서를 유지하면서
     * 반복적인 DB 조회를 날짜 범위별 일괄 조회로 변경합니다.
     */
    private function blockingPreviousDate(int $storeId, string $date): ?string
    {
        // 기존과 동일하게 선택 날짜의 30일 전부터 전날까지 확인합니다.
        $start = Carbon::parse($date)->subDays(30);
        $target = Carbon::parse($date)->subDay();

        $startDate = $start->toDateString();
        $targetDate = $target->toDateString();

        /**
         * 점포의 영업일 마감 기록을 한 번에 가져옵니다.
         * 기존과 동일하게 closed 상태인 날짜만 제외합니다.
         */
        $closedStatusDates = StoreDailyStatus::query()
            ->where('store_id', $storeId)
            ->whereDate('work_date', '>=', $startDate)
            ->whereDate('work_date', '<=', $targetDate)
            ->where('status', 'closed')
            ->pluck('work_date')
            ->map(fn ($value) => Carbon::parse($value)->toDateString())
            ->flip();

        // 실제 생산 기록이 있는 날짜를 수집합니다.
        $batchDates = ProductionBatch::query()
            ->where('store_id', $storeId)
            ->whereDate('work_date', '>=', $startDate)
            ->whereDate('work_date', '<=', $targetDate)
            ->pluck('work_date')
            ->map(fn ($value) => Carbon::parse($value)->toDateString())
            ->flip();

        // 생산 확인 기록이 있는 날짜를 수집합니다.
        $confirmationDates = ProductionConfirmation::query()
            ->where('store_id', $storeId)
            ->whereDate('work_date', '>=', $startDate)
            ->whereDate('work_date', '<=', $targetDate)
            ->pluck('work_date')
            ->map(fn ($value) => Carbon::parse($value)->toDateString())
            ->flip();

        // 생산 관리 마감이 완료된 날짜를 수집합니다.
        $closedProductionDates = ProductionDailyClosure::query()
            ->where('store_id', $storeId)
            ->whereDate('work_date', '>=', $startDate)
            ->whereDate('work_date', '<=', $targetDate)
            ->where('status', 'closed')
            ->pluck('work_date')
            ->map(fn ($value) => Carbon::parse($value)->toDateString())
            ->flip();

        /**
         * 기존과 동일하게 오래된 날짜부터 검사합니다.
         *
         * 1. 영업일 마감이면 제외
         * 2. 생산·확인 기록이 모두 없으면 제외
         * 3. 생산 관리 마감이 없으면 해당 날짜 반환
         */
        for ($cursor = $start->copy(); $cursor->lte($target); $cursor->addDay()) {
            $cursorDate = $cursor->toDateString();

            if ($closedStatusDates->has($cursorDate)) {
                continue;
            }

            $hasData = $batchDates->has($cursorDate)
                || $confirmationDates->has($cursorDate);

            if (!$hasData) {
                continue;
            }

            if (!$closedProductionDates->has($cursorDate)) {
                return $cursorDate;
            }
        }

        return null;
    }

    /**
     * 로스 기록을 재고 출처별로 저장합니다.
     *
     * 실제 처리일은 work_date에 기록하고,
     * 생산 실적 귀속일은 해당 재고의 최초 생산일을 사용합니다.
     *
     * 재고 출처를 확인할 수 없는 경우에는
     * 잘못된 생산일로 로스가 집계되지 않도록 저장을 중단합니다.
     */
    private function replaceLosses(
        int $storeId,
        int $productId,
        string $date,
        array $reasons,
        ?string $note,
        int $userId
    ): void {
        // 선택한 날짜의 기존 로스 기록을 조회합니다.
        $old = ProductionLoss::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('work_date', $date)
            ->get();

        // 기존 로스 기록과 연결된 사유를 삭제합니다.
        foreach ($old as $record) {
            ProductionLossReason::query()
                ->where('production_loss_id', $record->id)
                ->delete();

            $record->delete();
        }

        // 입력된 로스 사유를 재고 출처별로 저장합니다.
        foreach ($reasons as $reason) {
            $quantity = (int) ($reason['quantity'] ?? 0);

            // 수량이 없는 사유는 기존과 동일하게 건너뜁니다.
            if ($quantity <= 0) {
                continue;
            }

            // 선택한 재고가 실제 어느 생산 기록에 속하는지 확인합니다.
            $lot = $this->resolveReasonLot(
                $storeId,
                $productId,
                $date,
                $reason
            );

            // 재고 출처가 없으면 잘못된 로스 기록을 저장하지 않습니다.
            abort_unless(
                $lot,
                422,
                '처리할 재고의 생산일을 다시 확인해주세요.'
            );

            // 실제 처리일과 별개로 최초 생산일을 귀속일로 사용합니다.
            $attributionDate = $lot->origin_production_date->toDateString();

            // 로스 기록을 원 생산 재고에 연결하여 저장합니다.
            $loss = ProductionLoss::create([
                'store_id' => $storeId,
                'product_id' => $productId,
                'production_batch_id' => $lot->production_batch_id,
                'stock_lot_id' => $lot->id,
                'work_date' => $date,
                'attribution_date' => $attributionDate,
                'quantity' => $quantity,
                'note' => $note,
                'created_by' => $userId,
            ]);

            // 로스 사유와 해당 수량을 기존 구조대로 저장합니다.
            ProductionLossReason::create([
                'production_loss_id' => $loss->id,
                'reason_code' => $reason['reason_code'],
                'reason_text' => $reason['reason_text'] ?? null,
                'quantity' => $quantity,
            ]);
        }
    }
    
    /**
     * 폐기 기록을 재고 출처별로 저장합니다.
     *
     * 폐기 수량은 실제 처리한 날짜에 기록하지만,
     * 폐기율 계산에 사용하는 귀속 날짜는 해당 빵의 최초 생산일로 저장합니다.
     *
     * 재고 출처를 확인할 수 없는 경우에는 잘못된 생산일로
     * 폐기량이 집계되지 않도록 저장을 중단합니다.
     */
    private function replaceWastes(
        int $storeId,
        int $productId,
        string $date,
        array $reasons,
        ?string $note,
        int $userId
    ): void {
        // 선택한 업무일의 기존 폐기 기록을 조회합니다.
        $old = ProductionWaste::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('work_date', $date)
            ->get();

        /**
         * 기존 폐기 기록과 사유를 제거합니다.
         * 이 함수는 기존과 동일하게 현재 입력값으로 교체 저장합니다.
         */
        foreach ($old as $record) {
            ProductionWasteReason::query()
                ->where('production_waste_id', $record->id)
                ->delete();

            $record->delete();
        }

        // 입력된 폐기 사유를 재고 출처별로 저장합니다.
        foreach ($reasons as $reason) {
            $quantity = (int) ($reason['quantity'] ?? 0);

            // 폐기 수량이 없는 사유는 기존과 동일하게 저장하지 않습니다.
            if ($quantity <= 0) {
                continue;
            }

            /**
             * 선택한 재고 출처를 실제 생산 재고와 연결합니다.
             *
             * 당일 생산 재고와 이월 재고를 구분하며,
             * 이월 재고는 최초 생산일 정보를 유지합니다.
             */
            $lot = $this->resolveReasonLot(
                $storeId,
                $productId,
                $date,
                $reason
            );

            /**
             * 재고 출처를 찾지 못했다면 폐기 당일을
             * 최초 생산일로 임의 지정하지 않습니다.
             */
            abort_unless(
                $lot,
                422,
                '폐기할 재고의 생산일을 확인할 수 없습니다. 재고 출처를 다시 선택해주세요.'
            );

            // 폐기율 계산에 사용할 최초 생산일을 확정합니다.
            $attributionDate = $lot->origin_production_date->toDateString();

            /**
             * 폐기 기록을 저장합니다.
             *
             * work_date: 실제 폐기한 날짜
             * attribution_date: 해당 빵을 최초 생산한 날짜
             */
            $waste = ProductionWaste::create([
                'store_id' => $storeId,
                'product_id' => $productId,
                'stock_lot_id' => $lot->id,
                'work_date' => $date,
                'attribution_date' => $attributionDate,
                'quantity' => $quantity,
                'note' => $note,
                'created_by' => $userId,
            ]);

            // 기존 폐기 사유와 수량을 그대로 기록합니다.
            ProductionWasteReason::create([
                'production_waste_id' => $waste->id,
                'reason_code' => $reason['reason_code'],
                'reason_text' => $reason['reason_text'] ?? null,
                'quantity' => $quantity,
            ]);
        }
    }

    /**
     * 업무일의 전체 재고에서 다른 처리 유형에 이미 배정된 수량을 제외합니다.
     *
     * 같은 유형의 기존 입력은 수정 요청으로 대체되므로 다시 차감하지 않습니다.
     * 이월 예정은 사용자가 직접 수정해야 하며, 로스·폐기 가능량에서 제외합니다.
     * 생산일별 실제 잔여량은 validateReasonStockSources()에서 별도로 검사합니다.
     *
     * @param array<string, mixed> $row 일별 현황 서비스에서 계산한 제품별 합계
     * @param string $type 처리 유형(carryover, loss, waste, other_outflow)
     */
    private function availableQuantityForDisposition(array $row, string $type): int
    {
        $total = (int) $row['production'] + (int) $row['carryover_in'];
        $allocatedByOtherTypes = match ($type) {
            'carryover' => (int) $row['operational_loss'] + (int) $row['operational_waste'] + (int) $row['other_outflow'],
            'loss' => (int) $row['operational_waste'] + (int) $row['other_outflow'] + (int) $row['carryover_out'],
            'waste' => (int) $row['operational_loss'] + (int) $row['other_outflow'] + (int) $row['carryover_out'],
            'other_outflow' => (int) $row['operational_loss'] + (int) $row['operational_waste'] + (int) $row['carryover_out'],
            default => throw new \InvalidArgumentException('지원하지 않는 재고 처리 유형입니다.'),
        };

        return max(0, $total - $allocatedByOtherTypes);
    }

    /**
     * 선택한 생산 기록별로 사용 가능한 수량을 검사합니다.
     * 기존 기록 수정 시 해당 업무의 저장 수량은 다시 사용할 수 있습니다.
     */
    private function validateReasonStockSources(
        int $storeId,
        int $productId,
        string $date,
        string $type,
        Collection $reasons,
        array $stockSources
    ): void {
        // 현재 날짜에 실제 사용할 수 있는 생산 기록입니다.
        $sourceMap = collect($stockSources)->keyBy('stock_lot_id');
        $requestedByLot = [];

        foreach ($reasons as $reason) {
            $lotId = $reason['stock_lot_id'] ?? null;
            $sourceType = $reason['stock_source'] ?? null;
            $quantity = $reason['quantity'] ?? null;

            // 생산 기록과 재고 구분을 반드시 선택해야 합니다.
            abort_if(
                $lotId === null || $sourceType === null,
                422,
                '사용할 생산 기록을 선택해 주세요.'
            );

            // 요청 수량이 올바른지 확인합니다.
            abort_unless(
                filter_var($quantity, FILTER_VALIDATE_INT) !== false
                    && (int) $quantity > 0,
                422,
                '처리 수량을 확인해 주세요.'
            );

            $source = $sourceMap->get((int) $lotId);

            // 현재 날짜에 사용할 수 없는 재고는 제외합니다.
            abort_unless(
                $source,
                422,
                '선택한 생산 기록을 사용할 수 없습니다.'
            );

            // 선택한 재고 구분과 실제 생산 기록이 일치해야 합니다.
            abort_unless(
                $source['source'] === $sourceType,
                422,
                '선택한 재고 구분과 생산 기록이 일치하지 않습니다.'
            );

            // 같은 생산 기록에 입력한 모든 사유를 합산합니다.
            $requestedByLot[$lotId] =
                ($requestedByLot[$lotId] ?? 0) + (int) $quantity;
        }

        if (empty($requestedByLot)) {
            return;
        }

        // 현재 수정 중인 업무의 기존 기록을 조회합니다.
        $modelClass = match ($type) {
            'loss' => ProductionLoss::class,
            'waste' => ProductionWaste::class,
            'other_outflow' => ProductionOtherOutflow::class,
            default => throw new \InvalidArgumentException(
                '지원하지 않는 재고 처리 유형입니다.'
            ),
        };

        $existingByLot = $modelClass::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('work_date', $date)
            ->whereIn('stock_lot_id', array_keys($requestedByLot))
            ->select('stock_lot_id')
            ->selectRaw('SUM(quantity) AS total_quantity')
            ->groupBy('stock_lot_id')
            ->pluck('total_quantity', 'stock_lot_id');

        foreach ($requestedByLot as $lotId => $requested) {
            $source = $sourceMap->get((int) $lotId);

            // 음수 재고도 숨기지 않고 실제 계산값을 사용합니다.
            $rawAvailable = array_key_exists(
                'unallocated_quantity',
                $source
            )
                ? $source['unallocated_quantity']
                : ($source['remaining_quantity'] ?? null);

            abort_if(
                $rawAvailable === null || !is_numeric($rawAvailable),
                422,
                '선택한 재고의 남은 수량을 확인할 수 없습니다.'
            );

            // 기존에 저장한 동일 업무 수량만 복원합니다.
            $existingQuantity = (int) (
                $existingByLot->get($lotId) ?? 0
            );

            $available = (int) $rawAvailable + $existingQuantity;

            // 다른 생산 기록의 재고는 합산하지 않습니다.
            $originDate = Carbon::parse(
                $source['origin_production_date']
            )->format('m/d');

            abort_if(
                $requested > $available,
                422,
                "{$originDate} 생산 재고는 "
                    . max(0, $available)
                    . "개까지 입력할 수 있습니다."
            );
        }
    }

    // 사유 행의 재고 출처를 실제 stock lot로 변환합니다. 이전 화면 요청은 당일 재고를 기본값으로 유지합니다.
    private function resolveReasonLot(int $storeId, int $productId, string $date, array $reason): ?ProductStockLot
    {
        if (! empty($reason['stock_lot_id'])) {
            return ProductStockLot::query()
                ->whereKey((int) $reason['stock_lot_id'])
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->first();
        }

        if (($reason['stock_source'] ?? 'today') === 'carryover') {
            return $this->incomingLotForDate($storeId, $productId, $date);
        }

        // 당일 생산 재고를 요청했다면 이전 날짜의 이월 lot로 대체하지 않습니다.
        // 당일 생산 lot가 없을 때는 검증 단계에서 정확한 출처 선택을 요구합니다.
        $todayLots = ProductStockLot::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('origin_production_date', $date)
            ->orderBy('id')
            ->limit(2)
            ->get();

        // 동일 날짜에 생산 기록이 여러 개면 최신 기록을 임의로 선택하지 않습니다.
        // 재고 출처를 지정한 요청은 위의 stock_lot_id 분기에서 그대로 처리합니다.
        abort_if($todayLots->count() > 1, 422, '같은 날짜의 생산 재고가 여러 건입니다. 처리할 생산 기록을 선택해주세요.');

        return $todayLots->first();
    }

    // 시식·서비스·직원사용 등 기타 출고를 현재 입력값으로 교체합니다.
    private function replaceOutflows(int $storeId, int $productId, string $date, array $reasons, ?string $note, int $userId): void
    {
        ProductionOtherOutflow::query()->where('store_id', $storeId)->where('product_id', $productId)->whereDate('work_date', $date)->delete();
        foreach ($reasons as $reason) {
            // 기타 출고도 사유별로 선택한 생산 기록에서 차감합니다.
            $lot = $this->resolveReasonLot($storeId, $productId, $date, $reason);
            // 검증 이후에도 출처를 찾지 못했다면 출처 없는 출고 기록을 남기지 않습니다.
            // 각 사유의 생산 lot 연결을 보존해야 원 생산일별 잔여량을 정확히 계산할 수 있습니다.
            abort_unless($lot, 422, '처리할 재고의 생산일을 다시 확인해주세요.');

            ProductionOtherOutflow::create([
                'store_id' => $storeId,
                'product_id' => $productId,
                'stock_lot_id' => $lot->id,
                'work_date' => $date,
                'quantity' => $reason['quantity'],
                'reason_code' => $reason['reason_code'],
                'reason_text' => $reason['reason_text'] ?? null,
                'note' => $note,
                'created_by' => $userId,
            ]);
        }
    }

    /**
     * 선택한 생산 기록의 재고만 다음 날로 이월합니다.
     * 후속 날짜의 로스·폐기·재이월 기록은 유지합니다.
     */
    private function replaceCarryover(
        int $storeId,
        int $productId,
        string $date,
        int $quantity,
        string $source,
        int $userId,
        ?int $stockLotId = null
    ): void {
        abort_if(
            $quantity < 0,
            422,
            '이월 수량을 확인해 주세요.'
        );

        $nextDate = Carbon::parse($date)
            ->addDay()
            ->toDateString();

        // 기존 이월 출고를 확인합니다.
        $existing = ProductStockMovement::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('work_date', $date)
            ->where('movement_type', 'carryover_out')
            ->get();

        // 생산 기록 ID가 없는 과거 이월 내역은 자동 삭제하지 않습니다.
        abort_if(
            $existing->contains(
                fn ($movement) => $movement->stock_lot_id === null
            ),
            422,
            '생산 기록이 연결되지 않은 기존 이월 내역입니다. 관리자 확인이 필요합니다.'
        );

        $existingLotIds = $existing
            ->pluck('stock_lot_id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        // 과거에 여러 생산 기록으로 나눠 이월한 내역을 보호합니다.
        abort_if(
            $existingLotIds->count() > 1,
            422,
            '여러 생산 기록에 나누어 저장된 이월 내역입니다. 기존 기록을 확인해 주세요.'
        );

        if ($quantity > 0) {
            abort_if(
                $stockLotId === null,
                422,
                '이월할 생산 기록을 선택해 주세요.'
            );

            abort_unless(
                in_array($source, ['today', 'incoming'], true),
                422,
                '이월할 재고 구분을 확인해 주세요.'
            );
        }

        // 변경 대상인 기존 기록과 새 생산 기록을 모두 검사합니다.
        $affectedLotIds = $existingLotIds->all();

        if ($quantity > 0) {
            $affectedLotIds[] = $stockLotId;
        }

        $affectedLotIds = array_values(array_unique(
            $affectedLotIds
        ));

        // 이전 출고와 연결된 다음 날 입고 수량이 일치하는지 확인합니다.
        foreach ($existingLotIds as $lotId) {
            $previousOut = (int) $existing
                ->where('stock_lot_id', $lotId)
                ->sum('quantity');

            $nextIn = (int) ProductStockMovement::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->where('stock_lot_id', $lotId)
                ->whereDate('work_date', $nextDate)
                ->where('movement_type', 'carryover_in')
                ->sum('quantity');

            abort_if(
                $previousOut !== $nextIn,
                422,
                '기존 이월 출고와 다음 날 입고 수량이 일치하지 않습니다.'
            );
        }

        // 기존 이월 출고와 다음 날 입고만 교체합니다.
        foreach ($existingLotIds as $lotId) {
            ProductStockMovement::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->where('stock_lot_id', $lotId)
                ->whereDate('work_date', $nextDate)
                ->where('movement_type', 'carryover_in')
                ->delete();
        }

        ProductStockMovement::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('work_date', $date)
            ->where('movement_type', 'carryover_out')
            ->delete();

        // 0개 확인도 후속 날짜의 재고를 검사합니다.
        if ($quantity === 0) {
            $this->assertNextDayCarryoverIntegrity(
                $storeId,
                $productId,
                $date,
                $affectedLotIds
            );

            return;
        }

        // 오늘 생산과 기존 이월 재고를 구분합니다.
        $selectedSource = $source === 'today'
            ? 'today'
            : 'carryover';

        // 기존 출고를 제거한 상태의 최신 재고를 조회합니다.
        $daily = $this->dailyService->build(
            $storeId,
            $date
        );

        $row = collect($daily['rows'])
            ->firstWhere('id', $productId);

        abort_unless(
            $row,
            422,
            '선택한 제품의 재고를 확인할 수 없습니다.'
        );

        // 선택한 생산 기록만 찾습니다.
        $stockSource = collect($row['stock_sources'] ?? [])
            ->first(fn (array $item) => (
                (int) ($item['stock_lot_id'] ?? 0) === $stockLotId
                && ($item['source'] ?? null) === $selectedSource
            ));

        abort_unless(
            $stockSource,
            422,
            '선택한 생산 기록을 이월할 수 없습니다.'
        );

        // 실제 계산된 잔량을 검사합니다.
        $rawAvailable = array_key_exists(
            'unallocated_quantity',
            $stockSource
        )
            ? $stockSource['unallocated_quantity']
            : ($stockSource['remaining_quantity'] ?? null);

        abort_if(
            $rawAvailable === null ||
            !is_numeric($rawAvailable),
            422,
            '선택한 재고의 남은 수량을 확인할 수 없습니다.'
        );

        $available = (int) $rawAvailable;

        abort_if(
            $quantity > $available,
            422,
            '선택한 생산 기록의 이월 가능 수량이 부족합니다.'
        );
        
        // 다음 날 같은 생산 기록의 입고가 중복되지 않도록 검사합니다.
        $existingNextIncoming = ProductStockMovement::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('stock_lot_id', $stockLotId)
            ->whereDate('work_date', $nextDate)
            ->where('movement_type', 'carryover_in')
            ->exists();

        abort_if(
            $existingNextIncoming,
            422,
            '다음 날짜에 동일한 생산 기록의 이월 입고가 이미 존재합니다. 기존 내역을 확인해 주세요.'
        );

        // 동일 생산 기록을 유지한 채 출고와 입고를 만듭니다.
        $movementData = [
            'store_id' => $storeId,
            'product_id' => $productId,
            'stock_lot_id' => $stockLotId,
            'quantity' => $quantity,
            'created_by' => $userId,
        ];

        ProductStockMovement::create([
            ...$movementData,
            'work_date' => $date,
            'movement_type' => 'carryover_out',
        ]);

        ProductStockMovement::create([
            ...$movementData,
            'work_date' => $nextDate,
            'movement_type' => 'carryover_in',
        ]);

        // 기존 폐기·로스 및 모든 후속 재이월을 보호합니다.
        $this->assertNextDayCarryoverIntegrity(
            $storeId,
            $productId,
            $date,
            $affectedLotIds
        );
    }

    /**
     * 이월 수정 후 모든 후속 날짜의 재고를 검증합니다.
     * 재이월 횟수에 관계없이 최초 생산 기록을 유지합니다.
     */
    private function assertNextDayCarryoverIntegrity(
        int $storeId,
        int $productId,
        string $date,
        array $affectedLotIds
    ): void {
        $lotIds = collect($affectedLotIds)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        foreach ($lotIds as $lotId) {
            // 수정 날짜 이후의 모든 이월 이동을 조회합니다.
            $movements = ProductStockMovement::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->where('stock_lot_id', $lotId)
                ->whereDate('work_date', '>=', $date)
                ->whereIn('movement_type', [
                    'carryover_in',
                    'carryover_out',
                ])
                ->get([
                    'work_date',
                    'movement_type',
                    'quantity',
                ]);

            $incoming = [];
            $outgoing = [];
            $dates = [];

            foreach ($movements as $movement) {
                $movementDate = Carbon::parse(
                    $movement->work_date
                )->toDateString();

                $quantity = (int) $movement->quantity;

                if ($movement->movement_type === 'carryover_in') {
                    $incoming[$movementDate] =
                        ($incoming[$movementDate] ?? 0) + $quantity;
                } else {
                    $outgoing[$movementDate] =
                        ($outgoing[$movementDate] ?? 0) + $quantity;

                    // 이월 출고 다음 날에는 입고가 있어야 합니다.
                    $nextDate = Carbon::parse($movementDate)
                        ->addDay()
                        ->toDateString();

                    if ($nextDate > $date) {
                        $dates[$nextDate] = true;
                    }
                }

                if ($movementDate > $date) {
                    $dates[$movementDate] = true;
                }
            }

            // 후속 날짜의 로스·폐기·기타 출고를 합산합니다.
            $usedByDate = [];

            foreach ([
                ProductionLoss::class,
                ProductionWaste::class,
                ProductionOtherOutflow::class,
            ] as $model) {
                $records = $model::query()
                    ->where('store_id', $storeId)
                    ->where('product_id', $productId)
                    ->where('stock_lot_id', $lotId)
                    ->whereDate('work_date', '>', $date)
                    ->selectRaw(
                        'DATE(work_date) AS usage_date, SUM(quantity) AS total'
                    )
                    ->groupByRaw('DATE(work_date)')
                    ->get();

                foreach ($records as $record) {
                    $usageDate = $record->usage_date;

                    $usedByDate[$usageDate] =
                        ($usedByDate[$usageDate] ?? 0)
                        + (int) $record->total;

                    $dates[$usageDate] = true;
                }
            }

            // 기록이 존재하는 모든 후속 날짜를 순서대로 확인합니다.
            $checkDates = array_keys($dates);
            sort($checkDates);

            foreach ($checkDates as $checkDate) {
                $previousDate = Carbon::parse($checkDate)
                    ->subDay()
                    ->toDateString();

                $previousOut = $outgoing[$previousDate] ?? 0;
                $currentIn = $incoming[$checkDate] ?? 0;

                // 전날 이월 출고와 오늘 입고는 일치해야 합니다.
                abort_if(
                    $currentIn !== $previousOut,
                    422,
                    "{$checkDate} 이월 입고 기록이 이전 날짜의 이월 출고와 일치하지 않습니다."
                );

                $currentUsed =
                    ($usedByDate[$checkDate] ?? 0)
                    + ($outgoing[$checkDate] ?? 0);

                // 해당 날짜에 들어온 재고보다 많이 사용할 수 없습니다.
                abort_if(
                    $currentUsed > $currentIn,
                    422,
                    "{$checkDate}에 이미 사용한 재고가 있습니다. 해당 날짜의 기록을 먼저 확인해 주세요."
                );
            }
        }
    }

    /**
     * 다음 날 실제 입고량과 이미 처리한 수량의 정합성을 검사합니다.
     *
     * 두 값은 같은 점포·제품·원 생산 재고·업무일에서 합산한 수량입니다.
     * 이월을 줄이거나 출처를 바꿀 때 이미 사용한 수량을 보존할 수 없다면
     * 상위 DB 트랜잭션을 취소하여 다음 날의 기록이 고아 데이터가 되지 않게 합니다.
     *
     * @param int $incoming 다음 날 해당 원 생산 재고로 들어온 이월 수량
     * @param int $used 다음 날 로스·폐기·기타 출고·재이월에 사용한 합계
     */
    private function assertNextDayCarryoverQuantity(int $incoming, int $used): void
    {
        abort_if(
            $used > $incoming,
            422,
            '다음 날 이미 처리한 재고보다 이월 수량을 적게 설정할 수 없습니다. 다음 날 기록을 먼저 수정해주세요.',
        );
    }

    /**
     * 해당 업무일에 입고된 이월 재고의 원 생산 기록을 조회합니다.
     *
     * 생산일이 서로 다른 재고가 함께 입고됐다면 특정 기록을 임의로 선택하지 않습니다.
     * 호출자는 사용자에게 재고 출처를 다시 선택하도록 안내해야 합니다.
     */
    private function incomingLotForDate(int $storeId, int $productId, string $date): ?ProductStockLot
    {
        $lotIds = ProductStockMovement::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('work_date', $date)
            ->where('movement_type', 'carryover_in')
            ->distinct()
            ->pluck('stock_lot_id')
            ->filter()
            ->unique();

        abort_if($lotIds->count() > 1, 422, '여러 생산일의 이월 재고가 있습니다. 처리할 생산일을 선택해주세요.');

        return $lotIds->isEmpty() ? null : ProductStockLot::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->find($lotIds->first());
    }

    /**
     * 업무일의 재고 출처를 찾되, 여러 생산 기록이 존재하면 임의로 귀속시키지 않습니다.
     *
     * 출처를 명확히 지정하지 않은 기존 요청의 호환성을 위한 조회 함수입니다.
     * 수량 계산과 출처 검증은 저장 트랜잭션의 별도 검증에서 수행합니다.
     */
    private function lotForProductDate(int $storeId, int $productId, string $date): ?ProductStockLot
    {
        // 기타 출고도 임의의 최신 lot에 귀속시키지 않습니다. 출처가 여러 건이면
        // 기존 요청 형식으로는 어느 생산 기록을 차감할지 판단할 수 없습니다.
        $todayLots = ProductStockLot::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('origin_production_date', $date)
            ->orderBy('id')
            ->limit(2)
            ->get();
        abort_if($todayLots->count() > 1, 422, '같은 날짜의 생산 재고가 여러 건입니다. 재고 출처를 확인해주세요.');
        if ($todayLots->isNotEmpty()) {
            return $todayLots->first();
        }

        return $this->incomingLotForDate($storeId, $productId, $date);
    }

    // 폐기율은 원 생산일에 귀속된 폐기와 해당 생산일의 생산 수량만으로 계산합니다.
    private function wasteRate(int $waste, int $production): ?float
    {
        return $production > 0 ? round($waste / $production * 100, 1) : null;
    }

    /**
     * 마감 직전 제품별 재고 흐름을 검증합니다.
     *
     * 기존 제품 전체 수량과 귀속 폐기 수량 검증을 유지하면서,
     * 원 생산 재고별 초과 처리 여부도 확인합니다.
     *
     * 화면 검증을 우회했거나 과거 데이터가 어긋난 경우
     * 해당 제품과 생산일을 안내하고 마감을 차단합니다.
     */
    private function dailyStockIssues(array $daily): Collection
    {
        return collect($daily['rows'])
            ->filter(fn (array $row) => $row['is_active'])
            ->flatMap(function (array $row) {
                // 당일 생산량과 이월 입고량을 합산합니다.
                $available = (int) $row['production']
                    + (int) $row['carryover_in'];

                // 실제 업무일에 처리한 수량을 합산합니다.
                $processed = (int) $row['operational_loss']
                    + (int) $row['operational_waste']
                    + (int) $row['other_outflow']
                    + (int) $row['carryover_out'];

                $issues = collect();

                // 기존 제품 전체 수량 검증을 유지합니다.
                if ($processed > $available) {
                    $issues->push(
                        "{$row['name']}: 사용 가능한 재고 {$available}개보다 처리 수량 {$processed}개가 많습니다."
                    );
                }

                // 기존 생산일 귀속 폐기 수량 검증을 유지합니다.
                if ((int) $row['attributed_waste'] > (int) $row['production']) {
                    $issues->push(
                        "{$row['name']}: 생산 수량보다 귀속 폐기 수량이 많습니다. 폐기 재고 출처를 확인해주세요."
                    );
                }

                /**
                 * 동일 제품에 여러 생산일의 재고가 존재할 수 있으므로
                 * 각 원 생산 재고의 초과 처리 여부를 개별적으로 검사합니다.
                 *
                 * remaining_quantity는 음수를 0으로 제한한 화면용 값이므로,
                 * 실제 계산 결과인 unallocated_quantity를 사용합니다.
                 */
                foreach ($row['stock_sources'] ?? [] as $stockSource) {
                    $unallocated = (int) $stockSource['unallocated_quantity'];

                    if ($unallocated >= 0) {
                        continue;
                    }

                    // 해당 원 생산 재고에서 초과 처리된 수량입니다.
                    $exceeded = abs($unallocated);

                    // 문제가 발생한 재고의 최초 생산일을 표시합니다.
                    $originDate = Carbon::parse(
                        $stockSource['origin_production_date']
                    )->format('m/d');

                    $issues->push(
                        "{$row['name']}: {$originDate} 생산 재고가 {$exceeded}개 초과 처리되었습니다. 로스·폐기·이월 수량을 확인해주세요."
                    );
                }

                return $issues;
            })
            ->values();
    }

    // 로그인 사용자·점포·날짜별로 격리된 생산 임시저장 Session 키를 만듭니다.
    private function draftKey(int $storeId, string $date): string
    {
        return 'production.draft.' . auth()->id() . '.' . $storeId . '.' . $date;
    }
}
