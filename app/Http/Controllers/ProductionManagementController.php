<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\EmployeeAssignment;
use App\Models\Product;
use App\Models\ProductStockLot;
use App\Models\ProductStockMovement;
use App\Models\ProductionBatch;
use App\Models\ProductionRecommendationSnapshot;
use App\Models\ProductionConfirmation;
use App\Models\ProductionCorrection;
use App\Models\ProductionDailyClosure;
use App\Models\ProductionLoss;
use App\Models\ProductionLossReason;
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
                        ['loss', 'waste', 'carryover'],
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
     * 생산 기록을 생성합니다.
     *
     * 기존 생산·재고·작업자 저장 정책을 유지합니다.
     *
     * 추천 데이터가 없는 경우에도 생산 등록을 허용합니다.
     *
     * 과거 추천 검증은 신뢰도가 충분한 경우에만 실행하며,
     * 생산 입력 화면과 동일한 최종 추천량을 사용합니다.
     */
    public function storeBatch(Request $request): JsonResponse
    {
        $user = $this->user($request);

        /*
        * 기존 권한 및 입력값 검증
        */
        $this->accessService->requirePermission(
            $user,
            'production.create'
        );

        $data = $this->validateBatch($request);

        $this->accessService->assertStoreDepartment(
            $user,
            (int) $data['store_id']
        );

        /*
        * 기존 마감 상태 및 작업자 검증
        */
        $this->assertDateUnlocked(
            (int) $data['store_id'],
            $data['work_date']
        );

        $this->assertWorkersScheduled(
            (int) $data['store_id'],
            $data['work_date'],
            $data['workers'] ?? []
        );

        /*
        * 모든 생산·재고 변경을
        * 하나의 트랜잭션으로 처리합니다.
        */
        $batch = DB::transaction(function () use ($data, $user) {
            /*
            * 동일 제품의 동시 변경 방지
            */
            $product = Product::query()
                ->whereKey(
                    (int) $data['product_id']
                )
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $product->store_id ===
                    (int) $data['store_id'],
                422,
                '선택한 점포의 제품이 아닙니다.'
            );

            /*
            * 최신 레시피 조회
            */
            $recipe = $product->recipes()
                ->whereNull('deleted_at')
                ->with([
                    'ingredients',
                    'steps',
                ])
                ->latest('id')
                ->first();

            $recipeSnapshot = $recipe?->toArray();

            /*
            * 생산 기록 생성
            */
            $batch = ProductionBatch::create([
                'store_id' => $data['store_id'],

                'product_id' => $data['product_id'],

                'work_date' => $data['work_date'],

                'quantity' => $data['quantity'],

                'recipe_id' => $recipe?->id,

                'recipe_snapshot' =>
                    $recipeSnapshot,

                'recipe_deviated' => (bool) (
                    $data['recipe_deviated'] ?? false
                ),

                'recipe_deviation_note' =>
                    $data['recipe_deviation_note'] ?? null,

                'note' => $data['note'] ?? null,

                'created_by' => $user->id,
            ]);

            /*
            * 작업자 일괄 저장
            */
            $workers = [];

            $now = now();

            foreach ($data['workers'] ?? [] as $worker) {
                $workers[] = [
                    'production_batch_id' =>
                        $batch->id,

                    'user_id' =>
                        $worker['user_id'],

                    'process_type' =>
                        $worker['process_type'] ?? 'all',

                    'created_at' => $now,

                    'updated_at' => $now,
                ];
            }

            if (!empty($workers)) {
                DB::table('production_batch_workers')
                    ->insert($workers);
            }

            /*
            * 생산 재고 생성
            */
            ProductStockLot::create([
                'store_id' => $data['store_id'],

                'product_id' => $data['product_id'],

                'production_batch_id' => $batch->id,

                'origin_production_date' =>
                    $data['work_date'],

                'initial_quantity' =>
                    $data['quantity'],

                'remaining_quantity' =>
                    $data['quantity'],

                'status' => 'active',
            ]);

            /*
            * 생산 확인 상태 갱신
            */
            $this->updateConfirmationForDate(
                (int) $data['store_id'],
                (int) $data['product_id'],
                $data['work_date'],
                [
                    'production_confirmed' => true,

                    'zero_production_reason' => null,

                    'confirmed_by' => $user->id,
                ]
            );

            /*
            * 기존 추천 참고 여부
            */
            $recommendationReferenced = (bool) (
                $data['recommendation_referenced'] ?? false
            );

            /*
            * 추천량 차이 사유
            */
            $recommendationDeviationReason = trim(
                (string) (
                    $data['recommendation_deviation_reason'] ?? ''
                )
            );

            /*
            * 생산 입력 화면에 표시된 추천 결과
            */
            $displayedRecommendation =
                $data['displayed_recommendation'] ?? null;

            $hasDisplayedRecommendation =
                is_array($displayedRecommendation);

            /*
            * 추천량을 표시했거나
            * 추천 관련 입력이 있는 경우에만
            * 추천 기록을 계산합니다.
            */
            if (
                $hasDisplayedRecommendation
                || $recommendationReferenced
                || $recommendationDeviationReason !== ''
            ) {
                $target = Carbon::parse(
                    $data['work_date']
                );

                $storeId = (int) $data['store_id'];

                $productId = (int) $data['product_id'];

                /*
                * 기존 최근 12개월 기록 조회
                *
                * 생산·폐기·이월·로스 여러 건은
                * 기존 날짜별 합산 정책을 사용합니다.
                */
                $history =
                    $this->recommendationHistoryForProduct(
                        $storeId,
                        $productId,
                        $target
                    );

                /*
                * 추천 대상 날짜의 행사
                */
                $targetEvents = collect(
                    $this->eventsForDate(
                        $storeId,
                        $data['work_date']
                    )
                )
                    ->filter(
                        fn (array $event) =>
                            empty($event['products'])
                            || collect(
                                $event['products']
                            )->contains(
                                'id',
                                $productId
                            )
                    )
                    ->values()
                    ->all();

                /*
                * 추천 대상 날짜의 날씨
                */
                $targetWeatherCode =
                    StoreDailyWeather::query()
                        ->where('store_id', $storeId)
                        ->whereDate(
                            'work_date',
                            $data['work_date']
                        )
                        ->value('condition');

                if (!empty($targetWeatherCode)) {
                    $targetEvents[] = [
                        'weather_code' =>
                            (string) $targetWeatherCode,
                    ];
                }

                /*
                * 당일 이월 입고 수량
                *
                * 여러 건이 있어도 모두 합산합니다.
                */
                $targetCarryoverIn =
                    (int) ProductStockMovement::query()
                        ->where('store_id', $storeId)
                        ->where('product_id', $productId)
                        ->whereDate(
                            'work_date',
                            $data['work_date']
                        )
                        ->where(
                            'movement_type',
                            'carryover_in'
                        )
                        ->sum('quantity');

                $targetEvents[] = [
                    'condition_type' =>
                        'target_inventory',

                    'carryover_in' =>
                        $targetCarryoverIn,
                ];

                /*
                * 1. 기존 추천 생산량 계산
                *
                * 기존 12개월 분석 및 가중치 정책 유지
                */
                $recommendation =
                    $this->recommendationService->recommend(
                        $history,
                        $target,
                        $targetEvents
                    );

                /*
                * 2. 과거 추천 검증
                *
                * 기존 추천량이 존재하고
                * 신뢰도가 충분한 경우에만
                * 추가 분석을 실행합니다.
                *
                * 신뢰도가 낮거나 기록이 부족하면
                * 기존 추천량을 그대로 사용합니다.
                */
                if (
                    $recommendation['center'] !== null
                    && ($recommendation['confidence'] ?? null)
                        === 'normal'
                ) {
                    /*
                    * 전체 누적 추천 결과 조회
                    */
                    $recommendationOutcomes =
                        $this->recommendationOutcomesForProduct(
                            $storeId,
                            $productId,
                            $target,
                            $history
                        );

                    /*
                    * 검증된 추천량 조정 적용
                    *
                    * 조정 조건을 충족하지 않으면
                    * 기존 추천량이 유지됩니다.
                    */
                    $recommendation =
                        $this->applyHistoricalRecommendation(
                            $recommendation,
                            $recommendationOutcomes,
                            $target
                        );
                }

                /*
                * 3. 화면 추천량과 서버 추천량 비교
                *
                * 서버에서 계산한 최종 추천량과
                * 화면에 표시된 추천량을 비교합니다.
                */
                if ($hasDisplayedRecommendation) {
                    $displayedCenter = (int) (
                        $displayedRecommendation['center']
                    );

                    $displayedMin = (int) (
                        $displayedRecommendation['min']
                    );

                    $displayedMax = (int) (
                        $displayedRecommendation['max']
                    );

                    $displayedConfidence = (string) (
                        $displayedRecommendation['confidence']
                    );

                    /*
                    * 잘못된 추천 범위 차단
                    */
                    abort_if(
                        $displayedMin > $displayedCenter
                        || $displayedCenter > $displayedMax,
                        422,
                        '추천 생산량 범위가 올바르지 않습니다.'
                    );

                    /*
                    * 추천 계산에 사용된 기록이 변경되거나
                    * 화면과 서버 결과가 다르면 저장 차단
                    */
                    $recommendationMatches =
                        $recommendation['center'] !== null
                        && $displayedCenter ===
                            (int) $recommendation['center']
                        && $displayedMin ===
                            (int) $recommendation['min']
                        && $displayedMax ===
                            (int) $recommendation['max']
                        && $displayedConfidence ===
                            (string) $recommendation['confidence'];

                    abort_unless(
                        $recommendationMatches,
                        409,
                        '추천 생산량이 변경되었습니다. 최신 추천량을 확인한 후 다시 저장해주세요.'
                    );
                }

                /*
                * 4. 추천 결과 저장
                *
                * 기존 스냅샷 테이블을 사용합니다.
                *
                * 사용자가 추천량을 적용하지 않았더라도
                * 화면에 표시된 추천 결과를
                * 생산 기록과 연결합니다.
                */
                if ($recommendation['center'] !== null) {
                    $this->recommendationService->snapshot(
                        $storeId,
                        $productId,
                        $data['work_date'],
                        $recommendation,
                        $batch->id,
                        $recommendationDeviationReason !== ''
                            ? $recommendationDeviationReason
                            : null,
                        $recommendationReferenced
                    );
                }
            }

            return $batch;
        });

        /*
        * 기존 생산 등록 감사 로그
        */
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
                * 생산 수량 변경과 로스·폐기·이월 저장이
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
                * 생산 수량을 줄일 때 해당 생산 내역의 실제 사용 수량을 검사합니다.
                *
                * 원 생산일에 발생한 로스·폐기·이월 출고를 합산합니다.
                * 이후 날짜의 재이월·로스·폐기는 최초 이월 출고에 포함되므로
                * 다시 합산하지 않습니다.
                */
                $reducingQuantity = (int) $data['quantity']
                    < (int) $lockedBatch->quantity;

                if ($reducingQuantity) {
                    // 수정 대상 생산 기록과 연결된 재고 내역을 잠급니다.
                    $lots = ProductStockLot::query()
                        ->where('production_batch_id', $lockedBatch->id)
                        ->lockForUpdate()
                        ->get(['id']);

                    // 현재 시스템은 생산 기록 1건에 재고 내역 1건을 생성합니다.
                    abort_unless(
                        $lots->count() === 1,
                        422,
                        '생산 기록의 재고 연결을 확인할 수 없습니다.'
                    );

                    $lotId = (int) $lots->first()->id;
                    $originDate = $lockedBatch->work_date->toDateString();

                    // 해당 생산 내역에서 원 생산일에 발생한 로스를 계산합니다.
                    $usedLoss = (int) ProductionLoss::query()
                        ->where('stock_lot_id', $lotId)
                        ->whereDate('work_date', $originDate)
                        ->sum('quantity');

                    // 해당 생산 내역에서 원 생산일에 발생한 폐기를 계산합니다.
                    $usedWaste = (int) ProductionWaste::query()
                        ->where('stock_lot_id', $lotId)
                        ->whereDate('work_date', $originDate)
                        ->sum('quantity');

                    // 다음 날짜로 넘긴 수량을 계산합니다.
                    $carriedOut = (int) ProductStockMovement::query()
                        ->where('stock_lot_id', $lotId)
                        ->whereDate('work_date', $originDate)
                        ->where('movement_type', 'carryover_out')
                        ->sum('quantity');

                    // 기존 업무 기록을 유지하기 위해 필요한 최소 생산 수량입니다.
                    $minimumQuantity = $usedLoss + $usedWaste + $carriedOut;

                    // 수량이 부족하면 트랜잭션을 중단합니다.
                    abort_if(
                        (int) $data['quantity'] < $minimumQuantity,
                        422,
                        "이미 사용한 {$minimumQuantity}개보다 생산 수량을 적게 설정할 수 없습니다."
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

                /*
                * 생산 수량의 변경분을 계산합니다.
                * 수정 전 수량은 이미 보관한 $before에서 가져옵니다.
                * 기존 로스·폐기·이월 기록은 변경하지 않습니다.
                */
                $quantityDifference = (int) $data['quantity']
                    - (int) $before['quantity'];

                // 생산 수량, 메모, 수정자 및 잠금 버전을 갱신합니다.
                $lockedBatch->update([
                    'quantity' => $data['quantity'],
                    'note' => $data['note'] ?? null,
                    'updated_by' => $user->id,
                    'lock_version' => $lockedBatch->lock_version + 1,
                ]);

                /*
                * 변경한 생산 수량을 해당 생산 내역의 재고에 반영합니다.
                * 최초 생산 수량은 새 수량으로 변경합니다.
                * 남은 재고에는 생산 수량의 차이만 반영합니다.
                */
                ProductStockLot::query()
                    ->where(
                        'production_batch_id',
                        $lockedBatch->id
                    )
                    ->update([
                        'initial_quantity' => $data['quantity'],
                        'remaining_quantity' => DB::raw(
                            'remaining_quantity + (' . $quantityDifference . ')'
                        ),
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
     * 로스·폐기가 연결된 경우에도 재고 불일치를 방지하기 위해
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
            // 같은 제품의 생산 수정 및 재고 처리와 저장 순서를 맞춥니다.
            Product::query()
                ->whereKey((int) $batch->product_id)
                ->lockForUpdate()
                ->firstOrFail();

            // 생산 기록을 잠그고 최신 내용을 확인합니다.
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

            // 이 생산 기록에 연결된 이월 출고·입고를 모두 검사합니다.
            // 한쪽 기록만 남아 있는 경우에도 임의로 삭제하지 않습니다.
            $hasCarryoverMovement = ProductStockMovement::query()
                ->whereIn('stock_lot_id', $lotIds)
                ->whereIn('movement_type', [
                    'carryover_out',
                    'carryover_in',
                ])
                ->exists();

            abort_if(
                $hasCarryoverMovement,
                422,
                '이월 기록이 연결된 생산 내역은 삭제할 수 없습니다. 연결된 이월 기록을 먼저 확인해주세요.'
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

            // 연결된 재고 처리 기록이 있다면 삭제를 차단합니다.
            abort_if(
                $hasLoss || $hasWaste,
                422,
                '이미 로스·폐기 기록이 연결되어 있어 생산 기록을 삭제할 수 없습니다. 연결 기록을 먼저 확인해주세요.'
            );

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
            * 해당 날짜의 로스, 폐기,
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

                // 기존 차단 조건과 오류 메시지를 유지합니다.
                abort_if(
                    $hasMovement
                        || $hasWaste
                        || $hasLoss,
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
     * 화면에서 로스·폐기·이월을 각각 독립적으로 수정하므로
     * 선택하지 않은 업무 기록을 빈 배열로 덮어쓰지 않도록 서버에서도 분리 처리합니다.
     */
    public function saveFlow(Request $request): JsonResponse
    {
        // 로그인 사용자와 생산 관리 수정 권한을 확인합니다.
        $user = $this->user($request);
        $this->accessService->requirePermission($user, 'production.update');

        /**
         * 처리할 점포, 제품, 날짜 및 업무 유형을 검증합니다.
         * 로스·폐기는 사유와 수량을 사용하고,
         * 이월은 별도의 수량과 재고 출처를 사용합니다.
         */
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'type' => [
                'required',
                Rule::in(['loss', 'waste', 'carryover']),
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

            /*
             * 로스 변경 대상인 생산 기록 ID 목록입니다.
             * 비어 있는 배열도 허용해 로스 0개 확인을 유지합니다.
             */
            'affected_stock_lot_ids' => [
                'sometimes',
                'array',
            ],
            'affected_stock_lot_ids.*' => [
                'required',
                'integer',
                'distinct',
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
         * 로스·폐기는 사유가 'other'일 때
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

            /**
             * 이월은 전체 사용 가능 수량을 확인합니다.
             * 로스·폐기는 아래 생산 내역별 검사에서 확인합니다.
             */
            if ($data['type'] === 'carryover') {
                $availableForType = $this->availableQuantityForDisposition(
                    $row,
                    $data['type']
                );

                $requestedQuantity = (int) ($data['quantity'] ?? 0);

                abort_if(
                    $requestedQuantity > $availableForType,
                    422,
                    '입력한 수량이 현재 사용 가능한 수량보다 많습니다.'
                );
            }

            // 로스, 폐기의 재고 출처와 수량을 검증합니다.
            if (in_array($data['type'], ['loss', 'waste'], true)) {
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
                // 화면에서 명시한 생산 기록의 로스만 변경합니다.
                // 다른 생산 기록의 기존 로스는 그대로 보존합니다.
                abort_unless(
                    array_key_exists('affected_stock_lot_ids', $data),
                    422,
                    '변경할 생산 기록 목록이 누락되었습니다.'
                );

                $this->replaceLosses(
                    $storeId,
                    $productId,
                    $date,
                    $reasons,
                    $note,
                    $user->id,
                    $data['affected_stock_lot_ids']
                );

                $this->confirmFlowField(
                    $storeId,
                    $productId,
                    $date,
                    'loss_confirmed',
                    $user->id
                );
            } elseif ($type === 'waste') {
                // 폐기는 변경 대상 생산 내역만 교체합니다.
                // 요청에서 변경 대상을 누락하면 기존 폐기를 보호하기 위해 중단합니다.
                abort_unless(
                    array_key_exists('affected_stock_lot_ids', $data),
                    422,
                    '변경할 생산 기록 목록이 누락되었습니다.'
                );

                $this->replaceWastes(
                    $storeId,
                    $productId,
                    $date,
                    $reasons,
                    $note,
                    $user->id,
                    $data['affected_stock_lot_ids']
                );

                $this->confirmFlowField(
                    $storeId,
                    $productId,
                    $date,
                    'waste_confirmed',
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
            'carryover' => '이월',
        ];

        $label = $labels[$data['type']];

        // 저장한 업무 항목의 완료 상태만 확인합니다.
        // 로스·폐기·이월의 확인 상태를 각각 관리합니다.
        $confirmationField = [
            'loss' => 'loss_confirmed',
            'waste' => 'waste_confirmed',
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
            $hasBusinessData = collect($daily['rows'])->contains(fn(array $row) => $row['production'] + $row['loss'] + $row['waste'] + $row['carryover_out'] > 0);
            abort_if($hasBusinessData, 422, '이미 업무 기록이 있는 날짜는 바로 휴점 처리할 수 없습니다. 기록을 먼저 확인해주세요.');
        }
        StoreDailyStatus::updateOrCreate(['store_id' => $data['store_id'], 'work_date' => $data['work_date']], ['status' => $data['status'], 'reason' => $data['reason'] ?? null, 'created_by' => $user->id, 'updated_by' => $user->id]);
        $this->auditService->log($user, 'production', 'update', StoreDailyStatus::class, (int) StoreDailyStatus::query()->where('store_id', $data['store_id'])->whereDate('work_date', $data['work_date'])->value('id'), null, $data, '영업일 상태 변경');
        return response()->json(['message' => $data['status'] === 'closed' ? '휴점일로 설정했습니다.' : '휴점을 해제했습니다.']);
    }

    /**
     * 선택 제품의 최근/이전 7일 및 30일 폐기율을 조회합니다.
     * 폐기량은 실제 폐기일이 아닌 최초 생산일 기준으로 집계합니다.
     */
    public function productWasteStatistics(
        Request $request,
        int $productId
    ): JsonResponse {
        $user = $this->user($request);

        $this->accessService->requirePermission(
            $user,
            'production.view'
        );

        $data = $request->validate([
            'work_date' => ['required', 'date_format:Y-m-d'],
        ]);

        $product = Product::withTrashed()->findOrFail($productId);
        $storeId = (int) $product->store_id;

        $this->assertStoreReadable($user, $storeId);

        $end = Carbon::createFromFormat('!Y-m-d', $data['work_date']);
        $start = $end->copy()->subDays(59);

        $from = $start->toDateString();
        $to = $end->toDateString();

        // 최근 60일 생산량
        $production = ProductionBatch::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to)
            ->get(['work_date', 'quantity'])
            ->groupBy(
                fn ($row) => Carbon::parse($row->work_date)->toDateString()
            )
            ->map(fn ($rows) => (int) $rows->sum('quantity'));

        // 최초 생산일에 귀속되는 폐기량
        $attributedWaste = ProductionWaste::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('attribution_date', '>=', $from)
            ->whereDate('attribution_date', '<=', $to)
            ->get(['attribution_date', 'quantity'])
            ->groupBy(
                fn ($row) => Carbon::parse($row->attribution_date)->toDateString()
            )
            ->map(fn ($rows) => (int) $rows->sum('quantity'));

        // 생산량 0개 확인과 기록 미확인 구분
        $confirmedDates = ProductionConfirmation::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('production_confirmed', true)
            ->whereDate('work_date', '>=', $from)
            ->whereDate('work_date', '<=', $to)
            ->get(['work_date'])
            ->mapWithKeys(fn ($row) => [
                Carbon::parse($row->work_date)->toDateString() => true,
            ]);

        $days = collect();

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $date = $day->toDateString();

            $hasProduction = $production->has($date);
            $isConfirmed = $hasProduction || $confirmedDates->has($date);

            $quantity = $hasProduction
                ? (int) $production->get($date)
                : ($isConfirmed ? 0 : null);

            $wasteQuantity = (int) $attributedWaste->get($date, 0);

            $days->push([
                'date' => $date,
                'production' => $quantity,
                'attributed_waste' => $wasteQuantity,
                'waste_rate' => $quantity !== null
                    ? $this->wasteRate($wasteQuantity, $quantity)
                    : null,
                'confirmed' => $isConfirmed,
            ]);
        }

        // 기간별 총생산량과 총폐기량을 기준으로 폐기율 계산
        $buildPeriod = function ($items) {
            $productionTotal = (int) $items->sum('production');
            $wasteTotal = (int) $items->sum('attributed_waste');

            return [
                'production' => $productionTotal,
                'attributed_waste' => $wasteTotal,
                'waste_rate' => $this->wasteRate(
                    $wasteTotal,
                    $productionTotal
                ),
                'complete' => $items->every(
                    fn ($day) => $day['confirmed']
                ),
            ];
        };

        // 60일 데이터를 시간순으로 구분
        $previous30Days = $days->take(30);
        $recent30Days = $days->slice(30, 30)->values();

        $previous7Days = $days->slice(46, 7)->values();
        $recent7Days = $days->slice(53, 7)->values();

        // 기존 7일 응답 유지
        $previous = $buildPeriod($previous7Days);
        $recent = $buildPeriod($recent7Days);

        // 신규 30일 비교
        $previous30 = $buildPeriod($previous30Days);
        $recent30 = $buildPeriod($recent30Days);

        // 기존 7일 변화량
        $difference = (
            $recent['waste_rate'] !== null &&
            $previous['waste_rate'] !== null
        )
            ? round(
                $recent['waste_rate'] - $previous['waste_rate'],
                1
            )
            : null;

        // 신규 30일 변화량
        $difference30 = (
            $recent30['waste_rate'] !== null &&
            $previous30['waste_rate'] !== null
        )
            ? round(
                $recent30['waste_rate'] - $previous30['waste_rate'],
                1
            )
            : null;

        // 최근 30일에 귀속된 폐기 사유를 한 번에 조회
        $reasonRows = DB::table('production_waste_reasons')
            ->join(
                'production_wastes',
                'production_wastes.id',
                '=',
                'production_waste_reasons.production_waste_id'
            )
            ->where('production_wastes.store_id', $storeId)
            ->where('production_wastes.product_id', $productId)
            ->whereNull('production_wastes.deleted_at')
            ->whereDate(
                'production_wastes.attribution_date',
                '>=',
                $end->copy()->subDays(29)->toDateString()
            )
            ->whereDate(
                'production_wastes.attribution_date',
                '<=',
                $to
            )
            ->select(
                'production_wastes.attribution_date',
                'production_waste_reasons.reason_code',
                'production_waste_reasons.reason_text'
            )
            ->selectRaw(
                'SUM(production_waste_reasons.quantity) as quantity'
            )
            ->groupBy(
                'production_wastes.attribution_date',
                'production_waste_reasons.reason_code',
                'production_waste_reasons.reason_text'
            )
            ->get();

        $recent7Start = $end->copy()
            ->subDays(6)
            ->toDateString();

        // 동일 사유의 날짜별 수량을 합쳐 응답 생성
        $summarizeReasons = function ($rows) {
            return $rows
                ->groupBy(
                    fn ($row) => json_encode([
                        $row->reason_code,
                        $row->reason_text,
                    ])
                )
                ->map(function ($group) {
                    $first = $group->first();

                    return [
                        'reason_code' => $first->reason_code,
                        'reason_text' => $first->reason_text,
                        'quantity' => (int) $group->sum('quantity'),
                    ];
                })
                ->values();
        };

        // 기존 7일 사유 데이터 유지
        $reasons7 = $summarizeReasons(
            $reasonRows->filter(
                fn ($row) => Carbon::parse(
                    $row->attribution_date
                )->toDateString() >= $recent7Start
            )
        );

        // 신규 30일 사유 데이터
        $reasons30 = $summarizeReasons($reasonRows);

        return response()->json([
            'product_id' => $productId,
            'work_date' => $to,

            // 기존 응답
            'recent' => $recent,
            'previous' => $previous,
            'difference_percentage_points' => $difference,
            'daily' => $recent7Days,
            'reasons' => $reasons7,

            // 신규 30일 응답
            'recent_30' => $recent30,
            'previous_30' => $previous30,
            'difference_30_percentage_points' => $difference30,
            'daily_30' => $recent30Days,
            'reasons_30' => $reasons30,
        ]);
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

    /**
     * 추천 분석에 필요한 판매·날씨 자료를 조회합니다.
     *
     * 기존 판매 자료 조회 구조는 유지합니다.
     *
     * productId가 전달되면 해당 제품의 자료만 조회합니다.
     * 전달되지 않으면 기존처럼 점포 전체를 조회합니다.
     *
     * 생산·이월·로스·폐기 기반 판매량 추정 정책은
     * recommendationHistoryRow()에서 그대로 유지합니다.
     */
    private function recommendationData(
        int $storeId,
        string $from,
        string $to,
        ?int $productId = null
    ): array {
        /*
        * 기존 확정 판매 날짜 조회
        *
        * 점포별 확정 날짜 정보는 유지합니다.
        */
        $confirmedDates = DB::table('sales')
            ->where('store_id', $storeId)
            ->where('status', 'confirmed')
            ->whereBetween(
                'sales_date',
                [$from, $to]
            )
            ->pluck('sales_date')
            ->map(
                fn ($date) =>
                    Carbon::parse($date)->toDateString()
            )
            ->flip()
            ->all();

        /*
        * 기존 판매 자료 조회
        *
        * 제품 하나를 분석하는 경우
        * 해당 제품만 조회하도록 범위를 제한합니다.
        */
        $salesQuery = DB::table('sale_items as si')
            ->join(
                'sales as s',
                's.id',
                '=',
                'si.sale_id'
            )
            ->where('s.store_id', $storeId)
            ->where('s.status', 'confirmed')
            ->whereBetween(
                's.sales_date',
                [$from, $to]
            )
            ->whereIn(
                'si.sale_type',
                [
                    'regular',
                    'promotion',
                ]
            );

        /*
        * 제품별 추천에서는
        * 다른 제품의 판매 자료를 가져오지 않습니다.
        */
        if ($productId !== null) {
            $salesQuery->where(
                'si.product_id',
                $productId
            );
        }

        $sales = $salesQuery
            ->selectRaw(
                's.sales_date AS sales_day, ' .
                'si.product_id, ' .
                'SUM(si.quantity) AS sold_quantity'
            )
            ->groupBy(
                's.sales_date',
                'si.product_id'
            )
            ->get();

        /*
        * 날짜별 판매 자료 정리
        */
        $salesByDay = [];

        foreach ($sales as $sale) {
            $day = Carbon::parse(
                $sale->sales_day
            )->toDateString();

            $salesByDay[$day][
                (int) $sale->product_id
            ] = (int) $sale->sold_quantity;
        }

        /*
        * 기존 날짜별 날씨 조회
        *
        * 날짜 범위를 한 번에 조회합니다.
        */
        $weather = StoreDailyWeather::query()
            ->where('store_id', $storeId)
            ->whereBetween(
                'work_date',
                [$from, $to]
            )
            ->whereNotNull('condition')
            ->get([
                'work_date',
                'condition',
            ]);

        $weatherByDay = [];

        foreach ($weather as $record) {
            $day = Carbon::parse(
                $record->work_date
            )->toDateString();

            $weatherByDay[$day] =
                (string) $record->condition;
        }

        return [
            'confirmed_dates' =>
                $confirmedDates,

            'sales' =>
                $salesByDay,

            'weather' =>
                $weatherByDay,
        ];
    }

    /**
     * 기존 생산 기록에 판매량, 날씨, 행사 정보를 결합합니다.
     *
     * 기존 폐기율은 최초 생산일 귀속 정책을 유지합니다.
     *
     * 추천 분석에서는 당일 재고 흐름을 별도로 계산하고,
     * 정상 마감된 날짜에 한해 추정 판매량을 산출합니다.
     *
     * 판매량은 별도로 입력받지 않습니다.
     */
    private function recommendationHistoryRow(
        array $row,
        string $date,
        array $events,
        array $context
    ): array {
        $productId = (int) $row['id'];

        // 현재 제품에 적용되는 행사만 추출
        $productEvents = collect($events)
            ->filter(
                fn (array $event) =>
                    empty($event['products'])
                    || collect(
                        $event['products']
                    )->contains('id', $productId)
            )
            ->values();

        // 기존 추천 분석 데이터 유지
        $result = [
            ...$row,
            'product_id' => $productId,
            'date' => $date,
            'special_day' => $productEvents->isNotEmpty(),
            'weather_code' =>
                $context['weather'][$date] ?? null,
            'is_holiday' => $productEvents->contains(
                fn (array $event) =>
                    ($event['event_type'] ?? null)
                        === 'holiday'
            ),
            'discount_type' => $productEvents
                ->pluck('discount_type')
                ->filter()
                ->first(),
        ];

        /*
        * 기존 확정 판매 기록 연동은 유지합니다.
        *
        * 다만 이번 생산 추천은 별도 판매 입력에
        * 의존하지 않도록 재고 기반 추정값을 사용합니다.
        */
        if (
            isset($context['sales'][$date]) &&
            array_key_exists(
                $productId,
                $context['sales'][$date]
            )
        ) {
            $result['sales_quantity'] =
                (int) $context['sales'][$date][$productId];

            $result['sales_source'] =
                'confirmed';
        }

        /*
        * 추천 분석용 당일 재고 흐름
        *
        * 최초 생산일 귀속 폐기율은 변경하지 않습니다.
        */
        $production = max(
            0,
            (int) ($row['production'] ?? 0)
        );

        $carryoverIn = max(
            0,
            (int) ($row['carryover_in'] ?? 0)
        );

        $carryoverOut = max(
            0,
            (int) ($row['carryover_out'] ?? 0)
        );

        $operationalLoss = max(
            0,
            (int) ($row['operational_loss'] ?? 0)
        );

        $operationalWaste = max(
            0,
            (int) ($row['operational_waste'] ?? 0)
        );

        /*
        * 실제 해당 날짜의 사용 가능 수량
        */
        $availableQuantity =
            $production + $carryoverIn;

        /*
        * 당일 남은 수량
        *
        * 폐기와 이월은 과잉 생산 분석에서
        * 동등한 잔여 수량으로 취급합니다.
        */
        $remainingQuantity =
            $operationalWaste + $carryoverOut;

        /*
        * 로스·폐기·이월 합계가 사용 가능 수량을
        * 초과하면 정상적인 재고 흐름이 아닙니다.
        */
        $consumedQuantity =
            $operationalLoss + $remainingQuantity;

        $validQuantity =
            $consumedQuantity <= $availableQuantity;

        /*
        * 추천 전용 분석 지표
        */
        $result['daily_available_quantity'] =
            $availableQuantity;

        $result['daily_remaining_quantity'] =
            $remainingQuantity;

        $result['daily_quantity_valid'] =
            $validQuantity;

        /*
        * 수량이 0인 경우에는 비율을 계산하지 않습니다.
        * 정상적인 생산 0개 확인 기록은 유지합니다.
        */
        $canCalculateRate =
            $validQuantity &&
            $availableQuantity > 0;

        $result['daily_remaining_rate'] =
            $canCalculateRate
                ? round(
                    $remainingQuantity /
                    $availableQuantity * 100,
                    2
                )
                : null;

        $result['daily_waste_rate'] =
            $canCalculateRate
                ? round(
                    $operationalWaste /
                    $availableQuantity * 100,
                    2
                )
                : null;

        $result['daily_carryover_rate'] =
            $canCalculateRate
                ? round(
                    $carryoverOut /
                    $availableQuantity * 100,
                    2
                )
                : null;

        /*
        * 자동 추정 판매량
        *
        * 생산 + 이월 입고
        * - 로스 - 폐기 - 이월 출고
        *
        * 정상 마감되고 재고 흐름이 유효한 경우에만
        * 추정 판매량을 계산합니다.
        */
        $closureStatus =
            $row['closure_status'] ?? null;

        $canEstimateSales =
            $closureStatus === 'closed' &&
            $validQuantity;

        $result['estimated_sales_quantity'] =
            $canEstimateSales
                ? $availableQuantity -
                    $consumedQuantity
                : null;

        /*
        * 별도 판매량 입력 없이도 추천 엔진이
        * 자동 추정 판매량을 사용할 수 있도록 합니다.
        *
        * 기존 recommendationService의 sales_quantity
        * 입력 구조를 재사용합니다.
        */
        if ($canEstimateSales) {
            $result['sales_quantity'] =
                $result['estimated_sales_quantity'];

            $result['sales_source'] =
                'inventory_estimate';
        }

        return $result;
    }

    /**
     * 과거 추천량 조정 방법을 검증합니다.
     *
     * 과거 날짜를 오래된 순서대로 확인하며
     * 각 날짜보다 이전에 쌓인 기록만 사용합니다.
     *
     * 이미 자동 조정된 추천 결과는
     * 조정 전 최초 추천량으로 복원하여 비교합니다.
     *
     * 실제 생산량을 정답으로 삼지 않고,
     * 마감 기록으로 계산한 판매 추정량을 사용합니다.
     */
    private function recommendationAdjustmentBacktest(
        array $outcomes
    ): array {
        /*
        * 이미 일별로 합산된 과거 기록을 사용합니다.
        *
        * 생산·폐기·이월·로스가 하루에 여러 건이어도
        * 기존 일별 합산 수량을 그대로 사용합니다.
        */
        $records = $outcomes['records'] ?? [];

        /*
        * 오래된 날짜부터 처리합니다.
        *
        * 특정 날짜의 추천량을 계산할 때
        * 그날 이후의 결과가 사용되지 않도록 합니다.
        */
        usort(
            $records,
            fn (array $a, array $b) =>
                strcmp(
                    (string) ($a['date'] ?? ''),
                    (string) ($b['date'] ?? '')
                )
        );

        /*
        * 기존 반환 항목을 유지합니다.
        */
        $result = [
            'tested_days' => 0,

            'original_total_difference' => 0,
            'candidate_total_difference' => 0,

            'original_shortage_days' => 0,
            'candidate_shortage_days' => 0,

            'improvement_rate' => null,
            'result' => 'insufficient',

            'details' => [],

            'original_shortfall_total' => 0,
            'candidate_shortfall_total' => 0,

            'original_possible_excess_total' => 0,
            'candidate_possible_excess_total' => 0,

            'candidate_supported_days' => 0,

            'shortfall_reduction' => 0,
            'additional_possible_excess' => 0,

            'supported_rate' => null,
        ];

        /*
        * 요일별 과거 기록입니다.
        *
        * 기록을 삭제하지 않고 누적합니다.
        * 추천 계산 함수에서 최근 기록에
        * 더 높은 비중을 적용합니다.
        */
        $previousByWeekday = array_fill(
            0,
            7,
            []
        );

        foreach ($records as $record) {
            if (
                !is_array($record)
                || empty($record['date'])
            ) {
                continue;
            }

            /*
            * 같은 날짜에 서로 다른 추천량이 있으면
            * 어떤 수량을 기준으로 비교해야 할지
            * 불명확하므로 제외합니다.
            *
            * 생산 기록이 여러 건이라는 뜻은 아닙니다.
            */
            if (
                $record['has_multiple_recommendations']
                ?? false
            ) {
                continue;
            }

            $date = Carbon::parse(
                $record['date']
            )->startOfDay();

            $dateString = $date->toDateString();
            $weekday = $date->dayOfWeek;

            /*
            * 과거에 처음 계산했던 추천량입니다.
            *
            * 이후 자동으로 변경된 추천량이 있더라도
            * 처음 추천했던 수량을 기준으로 판단합니다.
            */
            $originalQuantity = max(
                0,
                (int) (
                    $record['original_recommended_quantity']
                    ?? $record['recommended_quantity']
                    ?? 0
                )
            );

            /*
            * 해당 날짜의 실제 마감 기록입니다.
            */
            $actualProduction = max(
                0,
                (int) ($record['actual_production'] ?? 0)
            );

            $carryoverIn = max(
                0,
                (int) ($record['carryover_in'] ?? 0)
            );

            $estimatedSales = max(
                0,
                (int) ($record['estimated_sales'] ?? 0)
            );

            $waste = max(
                0,
                (int) ($record['waste'] ?? 0)
            );

            $carryoverOut = max(
                0,
                (int) ($record['carryover_out'] ?? 0)
            );

            $loss = max(
                0,
                (int) ($record['loss'] ?? 0)
            );

            /*
            * 실제 기록된 폐기량과 이월예정입니다.
            *
            * 로스는 폐기량에 중복해서 포함하지 않습니다.
            */
            $actualRemaining =
                $waste + $carryoverOut;

            /*
            * 실제 생산량과 이월재고에서
            * 로스를 제외한 판매 가능 수량입니다.
            */
            $actualSellableAvailable = max(
                0,
                $actualProduction + $carryoverIn - $loss
            );

            /*
            * 폐기와 이월예정이 모두 없었던 날입니다.
            *
            * 더 판매할 기회가 있었을 가능성은 있지만
            * 추가 판매량을 확정할 수는 없습니다.
            */
            $possibleSellout =
                $actualSellableAvailable > 0
                && $estimatedSales > 0
                && $waste === 0
                && $carryoverOut === 0;

            /*
            * 처음 추천했던 수량과 이월재고의 합계입니다.
            */
            $originalAvailable =
                $originalQuantity + $carryoverIn;

            $originalDifference =
                $originalAvailable - $estimatedSales;

            $tolerance = max(
                2,
                (int) round($estimatedSales * 0.10)
            );

            /*
            * 기존 추천 결과 분류를 유지합니다.
            */
            $baselineStatus = 'uncertain';

            if ($originalDifference < 0) {
                $baselineStatus =
                    'below_observed_sales';
            } elseif (
                $originalDifference > $tolerance
                && $actualRemaining > 0
            ) {
                $baselineStatus =
                    'possible_excess';
            } elseif (
                abs($originalDifference) <= $tolerance
                && $actualRemaining > 0
            ) {
                $baselineStatus =
                    $originalDifference === 0
                        ? 'exact'
                        : 'near';
            }

            /*
            * 추천 결과를 수량으로 비교할 수 있는지
            * 확인합니다.
            */
            $comparable = in_array(
                $baselineStatus,
                [
                    'exact',
                    'near',
                    'below_observed_sales',
                    'possible_excess',
                ],
                true
            );

            /*
            * 비교 가능한 기록도 아니고
            * 판매 기회가 있었을 가능성도 없다면
            * 이후 추천 계산에 사용하지 않습니다.
            */
            if (!$comparable && !$possibleSellout) {
                continue;
            }

            /*
            * 과거 추천량 계산에 사용할 기록입니다.
            *
            * 처음 추천했던 수량을 사용해
            * 이전에 변경했던 수량이 중복 반영되지
            * 않도록 합니다.
            */
            $baselineRecord = $record;

            $baselineRecord['date'] = $dateString;

            $baselineRecord['recommended_quantity'] =
                $originalQuantity;

            $baselineRecord['status'] =
                $baselineStatus;

            /*
            * 폐기·이월예정이 없었던 날짜를
            * 별도로 표시합니다.
            *
            * 추천 계산 함수가 이 값을 참고합니다.
            */
            $baselineRecord['possible_sellout'] =
                $possibleSellout;

            $baselineRecord['actual_sellable_available'] =
                $actualSellableAvailable;

            /*
            * 현재 날짜보다 이전의
            * 같은 요일 기록만 가져옵니다.
            */
            $previousRecords =
                $previousByWeekday[$weekday];

            /*
            * 과거 기록을 바탕으로
            * 해당 날짜에 추천할 수 있었던
            * 생산량을 계산합니다.
            */
            $adjustment = $originalQuantity > 0
                ? $this->recommendationService
                    ->calculateAdaptiveAdjustment(
                        $previousRecords,
                        $originalQuantity,
                        $date
                    )
                : [
                    'suggested_quantity' =>
                        $originalQuantity,

                    'record_count' => 0,
                ];

            $candidateQuantity = max(
                0,
                (int) (
                    $adjustment['suggested_quantity']
                    ?? $originalQuantity
                )
            );

            /*
            * 현재 날짜의 결과는
            * 추천량 계산이 끝난 다음 추가합니다.
            *
            * 따라서 미래의 판매 결과를
            * 과거 추천 계산에 사용하지 않습니다.
            */
            $previousByWeekday[$weekday][] =
                $baselineRecord;

            /*
            * 폐기·이월예정이 없었던 날짜는
            * 이후 날짜의 추천 계산에 참고합니다.
            *
            * 다만 비교할 수 없는 날짜라면
            * 추천 정확도 계산에는 포함하지 않습니다.
            */
            if (!$comparable) {
                continue;
            }

            /*
            * 처음 추천했던 생산량이 0이거나
            * 추천량 변경이 없다면
            * 두 수량을 비교할 필요가 없습니다.
            */
            if (
                $originalQuantity <= 0
                || $candidateQuantity === $originalQuantity
            ) {
                continue;
            }

            /*
            * 같은 날짜의 추정 판매량을 기준으로
            * 두 추천 생산량을 비교합니다.
            *
            * 실제로 다른 수량을 생산했을 때의
            * 결과를 확정하는 계산은 아닙니다.
            */
            $originalEvaluation =
                $this->recommendationService
                    ->evaluateProductionCandidate(
                        $record,
                        $originalQuantity
                    );

            $candidateEvaluation =
                $this->recommendationService
                    ->evaluateProductionCandidate(
                        $record,
                        $candidateQuantity
                    );

            $originalAvailable = max(
                0,
                (int) (
                    $originalEvaluation['candidate_available']
                    ?? 0
                )
            );

            $candidateAvailable = max(
                0,
                (int) (
                    $candidateEvaluation['candidate_available']
                    ?? 0
                )
            );

            /*
            * 각각의 추천량과 추정 판매량 사이의
            * 절대적인 수량 차이입니다.
            */
            $originalDifference = abs(
                $originalAvailable - $estimatedSales
            );

            $candidateDifference = abs(
                $candidateAvailable - $estimatedSales
            );

            /*
            * 과거 추정 판매량보다
            * 부족했던 수량을 확인합니다.
            */
            $originalShortfall = max(
                0,
                (int) (
                    $originalEvaluation['confirmed_shortfall']
                    ?? 0
                )
            );

            $candidateShortfall = max(
                0,
                (int) (
                    $candidateEvaluation['confirmed_shortfall']
                    ?? 0
                )
            );

            /*
            * 과거 추정 판매량보다
            * 많을 가능성이 있는 수량입니다.
            *
            * 실제 폐기량으로 단정하지 않습니다.
            */
            $originalPossibleExcess = max(
                0,
                (int) (
                    $originalEvaluation['possible_excess']
                    ?? 0
                )
            );

            $candidatePossibleExcess = max(
                0,
                (int) (
                    $candidateEvaluation['possible_excess']
                    ?? 0
                )
            );

            $originalShortage =
                $originalShortfall > 0;

            $candidateShortage =
                $candidateShortfall > 0;

            /*
            * 다음 조건을 함께 만족하는 경우입니다.
            *
            * 1. 추천량을 늘리면 부족 수량이 감소함
            * 2. 남을 가능성이 있는 수량이
            *    실제 폐기·이월예정 합계를 넘지 않음
            *
            * 기존의 보수적인 판단 기준을 유지합니다.
            */
            $candidateSupported =
                $candidateShortfall < $originalShortfall
                && $candidatePossibleExcess <= $actualRemaining;

            /*
            * 전체 비교 결과를 누적합니다.
            */
            $result['tested_days']++;

            $result['original_total_difference'] +=
                $originalDifference;

            $result['candidate_total_difference'] +=
                $candidateDifference;

            $result['original_shortfall_total'] +=
                $originalShortfall;

            $result['candidate_shortfall_total'] +=
                $candidateShortfall;

            $result['original_possible_excess_total'] +=
                $originalPossibleExcess;

            $result['candidate_possible_excess_total'] +=
                $candidatePossibleExcess;

            if ($originalShortage) {
                $result['original_shortage_days']++;
            }

            if ($candidateShortage) {
                $result['candidate_shortage_days']++;
            }

            if ($candidateSupported) {
                $result['candidate_supported_days']++;
            }

            /*
            * 기존 날짜별 반환 항목을 유지합니다.
            */
            $result['details'][] = [
                'date' => $dateString,
                'weekday' => $weekday,

                'original_quantity' =>
                    $originalQuantity,

                'candidate_quantity' =>
                    $candidateQuantity,

                'adjustment_quantity' =>
                    $candidateQuantity - $originalQuantity,

                'estimated_sales' =>
                    $estimatedSales,

                'carryover_in' =>
                    $carryoverIn,

                'original_difference' =>
                    $originalDifference,

                'candidate_difference' =>
                    $candidateDifference,

                'original_shortage' =>
                    $originalShortage,

                'candidate_shortage' =>
                    $candidateShortage,

                'previous_record_count' =>
                    (int) (
                        $adjustment['record_count'] ?? 0
                    ),

                'original_shortfall' =>
                    $originalShortfall,

                'candidate_shortfall' =>
                    $candidateShortfall,

                'original_possible_excess' =>
                    $originalPossibleExcess,

                'candidate_possible_excess' =>
                    $candidatePossibleExcess,

                'actual_remaining' =>
                    $actualRemaining,

                'candidate_supported' =>
                    $candidateSupported,
            ];
        }

        /*
        * 비교 가능한 날짜가 부족하면
        * 추천량 변경을 보류합니다.
        *
        * 기존 최소 기록 기준을 유지합니다.
        */
        if ($result['tested_days'] < 5) {
            return $result;
        }

        $originalTotal =
            $result['original_total_difference'];

        $candidateTotal =
            $result['candidate_total_difference'];

        $originalShortfall =
            $result['original_shortfall_total'];

        $candidateShortfall =
            $result['candidate_shortfall_total'];

        $originalExcess =
            $result['original_possible_excess_total'];

        $candidateExcess =
            $result['candidate_possible_excess_total'];

        $supportedDays =
            $result['candidate_supported_days'];

        $testedDays =
            $result['tested_days'];

        /*
        * 추천량을 변경했을 때
        * 추정 판매량과의 수량 차이가
        * 얼마나 줄었는지 계산합니다.
        */
        if ($originalTotal > 0) {
            $result['improvement_rate'] = round(
                ($originalTotal - $candidateTotal)
                    / $originalTotal,
                4
            );
        }

        /*
        * 추천량 변경으로 줄어들 가능성이 있는
        * 부족 수량입니다.
        */
        $shortfallReduction = max(
            0,
            $originalShortfall - $candidateShortfall
        );

        /*
        * 추천량 변경으로 추가로 남을 가능성이 있는
        * 수량입니다.
        */
        $additionalExcess = max(
            0,
            $candidateExcess - $originalExcess
        );

        $result['shortfall_reduction'] =
            $shortfallReduction;

        $result['additional_possible_excess'] =
            $additionalExcess;

        $result['supported_rate'] =
            $testedDays > 0
                ? round(
                    $supportedDays / $testedDays,
                    4
                )
                : null;

        /*
        * 부족 수량이 줄지 않았다면
        * 추천량 변경을 적용하지 않습니다.
        */
        if ($shortfallReduction <= 0) {
            $result['result'] = 'not_better';

            return $result;
        }

        /*
        * 부족한 날짜가 증가한다면
        * 추천량 변경을 적용하지 않습니다.
        */
        if (
            $result['candidate_shortage_days']
            > $result['original_shortage_days']
        ) {
            $result['result'] = 'shortage_risk';

            return $result;
        }

        /*
        * 부족 수량이 줄어드는 효과보다
        * 추가로 남을 가능성이 있는 수량이
        * 많다면 변경을 보류합니다.
        */
        if ($additionalExcess > $shortfallReduction) {
            $result['result'] = 'excess_risk';

            return $result;
        }

        /*
        * 비교한 날짜 중 절반 이상에서
        * 추천량 증가를 뒷받침하는지 확인합니다.
        */
        $hasEnoughSupportedDays =
            $testedDays > 0
            && $supportedDays / $testedDays >= 0.50;

        if (!$hasEnoughSupportedDays) {
            $result['result'] = 'needs_review';

            return $result;
        }

        /*
        * 과거 기록에서 생산량 부족 감소 가능성이
        * 확인된 경우입니다.
        *
        * 실제 추천량 적용 여부는
        * applyHistoricalValidation()에서 판단합니다.
        */
        $result['result'] = 'promising';

        return $result;
    }

    /**
     * 추천량 조정에 필요한 과거 추천 결과를 조회합니다.
     *
     * 기존 추천량은 최근 12개월 기록으로 계산하고,
     * 과거 추천 검증은 전체 누적 기록을 사용합니다.
     *
     * 기존 날짜별 생산·폐기·이월·로스
     * 집계 함수를 그대로 활용합니다.
     *
     * 생산 입력 화면과 저장 과정에서
     * 동일하게 사용하는 공통 함수입니다.
     */
    private function recommendationOutcomesForProduct(
        int $storeId,
        int $productId,
        Carbon $targetDate,
        Collection $recentHistory
    ): array {
        /*
        * 해당 날짜 이전의 가장 오래된 추천 기록을 확인합니다.
        */
        $firstSnapshotDate =
            ProductionRecommendationSnapshot::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereNotNull('production_batch_id')
                ->whereNotNull('center_quantity')
                ->where(
                    'target_date',
                    '<',
                    $targetDate->toDateString()
                )
                ->min('target_date');

        /*
        * 과거 추천 기록이 없다면
        * 분석 결과를 비워서 반환합니다.
        */
        if (!$firstSnapshotDate) {
            return [
                'records' => [],
                'summary' => [],
                'adjustment_evidence' => [],
                'adjustment_backtest' => [],
                'adjustment_comparison' => [
                    'evaluated_days' => 0,
                    'adjusted_days' => 0,
                    'original_shortfall_total' => 0,
                    'adjusted_shortfall_total' => 0,
                    'original_possible_excess_total' => 0,
                    'adjusted_possible_excess_total' => 0,
                    'improved_days' => 0,
                    'worsened_days' => 0,
                    'unchanged_days' => 0,
                    'records' => [],
                ],
            ];
        }

        /*
        * 기본 추천 계산 기간은 최근 12개월입니다.
        */
        $recommendationFrom = $targetDate->copy()
            ->subYear()
            ->startOfDay();

        $outcomeHistory = $recentHistory;

        $outcomeStartDate = $recommendationFrom;

        $firstDate = Carbon::parse(
            $firstSnapshotDate
        )->startOfDay();

        /*
        * 12개월 이전에도 추천 기록이 있다면
        * 과거 검증에 필요한 기록을 추가 조회합니다.
        */
        if ($firstDate->lt($recommendationFrom)) {
            $outcomeStartDate = $firstDate;

            $outcomeHistory =
                $this->recommendationHistoryForProduct(
                    $storeId,
                    $productId,
                    $targetDate,
                    $outcomeStartDate
                );
        }

        /*
        * 날짜별 추천량과 실제 마감 결과를 분석합니다.
        *
        * 생산·폐기·이월·로스가 여러 건인 경우에도
        * 기존 날짜별 합산 결과를 사용합니다.
        */
        $outcomes =
            $this->recommendationOutcomeAnalysis(
                $storeId,
                $productId,
                $outcomeHistory,
                $targetDate,
                $outcomeStartDate
            );

        /*
        * 기존 반복 부족 분석을 유지합니다.
        */
        $outcomes['adjustment_evidence'] =
            $this->recommendationAdjustmentEvidence(
                $outcomes,
                $targetDate
            );

        /*
        * 기존 과거 추천 검증을 유지합니다.
        */
        $outcomes['adjustment_backtest'] =
            $this->recommendationAdjustmentBacktest(
                $outcomes
            );

        /*
        * 새로 추가한 추천량 조정 비교입니다.
        *
        * 조정 전 추천량과 최종 추천량을
        * 실제 마감 결과를 기준으로 비교합니다.
        */
        $outcomes['adjustment_comparison'] =
            $this->recommendationAdjustmentComparison(
                $storeId,
                $productId,
                $outcomes['records'] ?? []
            );

        return $outcomes;
    }
    
    /**
     * 과거 추천 결과를 검증하여 현재 추천량을 조정합니다.
     *
     * 기존 추천 계산 결과는 유지합니다.
     * 과거에 이미 조정된 추천량은 최초 추천량으로
     * 되돌려 분석한 뒤 새로운 조정 후보를 계산합니다.
     *
     * 추천 조건이 충족되지 않으면
     * 기존 추천량을 그대로 반환합니다.
     */
    private function applyHistoricalRecommendation(
        array $recommendation,
        array $outcomes,
        Carbon $targetDate
    ): array {
        /*
        * 현재 계산된 추천 생산량입니다.
        */
        $center = $recommendation['center'] ?? null;

        $recommendation['metrics'] ??= [];

        $recommendation['metrics']['historical_validation'] ??= [];

        $recommendation['metrics']
            ['historical_validation']
            ['applied'] = false;

        /*
        * 추천량이 없거나 0개 이하이면
        * 추가 증감 계산을 진행하지 않습니다.
        */
        if (
            $center === null
            || !is_numeric($center)
            || (int) $center <= 0
        ) {
            return $recommendation;
        }

        $center = (int) $center;

        /*
        * 신뢰도가 충분하지 않으면
        * 추천 생산량을 자동 변경하지 않습니다.
        */
        if (
            ($recommendation['confidence'] ?? null)
            !== 'normal'
        ) {
            return $recommendation;
        }

        /*
        * 먼저 폐기·이월예정 기록을 확인합니다.
        *
        * 기존 추천 서비스에서 이미 계산한 결과를
        * 재사용하므로 추가 데이터베이스 조회는 없습니다.
        */
        $metrics = $recommendation['metrics'];

        $surplusRate = $metrics['surplus_rate'] ?? null;

        $surplusHistoryCount = max(
            0,
            (int) (
                $metrics['surplus_history_count'] ?? 0
            )
        );

        $surplusAssessment = (string) (
            $metrics['surplus_adjustment_assessment']
            ?? 'insufficient'
        );

        /*
        * 먼저 같은 요일의 과거 생산량 부족 경향을 확인합니다.
        *
        * 최근 폐기가 많았더라도 같은 요일에
        * 추천량 부족이 반복됐다면 감량을 보류합니다.
        *
        * 이미 계산한 기록을 사용하므로
        * 추가 데이터베이스 조회는 발생하지 않습니다.
        */
        $evidence =
            $outcomes['adjustment_evidence']
            ?? $this->recommendationAdjustmentEvidence(
                $outcomes,
                $targetDate
            );

        /*
        * 실제 판매 부족을 줄일 필요가 있다는
        * 과거 기록이 충분한지 확인합니다.
        */
        $hasShortageEvidence =
            ($evidence['eligible'] ?? false) === true
            && ($evidence['direction'] ?? null) === 'increase';

        /*
        * 감량은 다음 조건을 모두 충족할 때만 검토합니다.
        *
        * 1. 과거 감량 결과가 긍정적임
        * 2. 최근 폐기·이월예정 비율이 높음
        * 3. 참고할 기록이 충분함
        * 4. 같은 요일에 반복적인 생산량 부족이 확인되지 않음
        *
        * 판매 기회가 줄어들 위험을 우선 고려합니다.
        */
        $canReviewDecrease =
            !$hasShortageEvidence
            && $surplusAssessment === 'promising'
            && $surplusRate !== null
            && is_numeric($surplusRate)
            && (float) $surplusRate >= 0.20
            && $surplusHistoryCount >= 3;

        if ($canReviewDecrease) {
            /*
            * 남은 수량 비율이 목표보다 높은 정도를
            * 계산합니다.
            *
            * 목표 잔여율 10%는 초기 참고 기준입니다.
            */
            $targetRemainingRate = 0.10;

            $excessAboveTarget = max(
                0.0,
                (float) $surplusRate - $targetRemainingRate
            );

            /*
            * 기록이 적을수록 감량 폭을 낮춥니다.
            *
            * 기록 수가 많아질수록
            * 실제 남은 수량 비율을 더 많이 반영합니다.
            */
            $evidenceStrength =
                $surplusHistoryCount
                / ($surplusHistoryCount + 4);

            /*
            * 한 번에 지나치게 감량하지 않도록
            * 최대 30%까지만 적용합니다.
            */
            $decreaseRate = min(
                0.30,
                $excessAboveTarget * $evidenceStrength
            );

            $decreaseQuantity = max(
                0,
                (int) round(
                    $center * $decreaseRate
                )
            );

            /*
            * 실제 감량할 수량이 있을 때만
            * 추천 생산량을 변경합니다.
            */
            if ($decreaseQuantity > 0) {
                $adjustedCenter = max(
                    0,
                    $center - $decreaseQuantity
                );

                /*
                * 최종 추천량과 추천 범위를
                * 동일하게 변경합니다.
                */
                $originalMin =
                    $recommendation['min'] ?? null;

                $originalMax =
                    $recommendation['max'] ?? null;

                $recommendation['center'] =
                    $adjustedCenter;

                if (
                    $originalMin !== null
                    && is_numeric($originalMin)
                ) {
                    $recommendation['min'] = max(
                        0,
                        (int) $originalMin - $decreaseQuantity
                    );
                }

                if (
                    $originalMax !== null
                    && is_numeric($originalMax)
                ) {
                    $recommendation['max'] = max(
                        0,
                        (int) $originalMax - $decreaseQuantity
                    );
                }

                /*
                * 추천 이유 화면에 표시되는 수량도
                * 최종 추천량과 일치시킵니다.
                */
                if (
                    isset(
                        $recommendation['explanation']
                            ['calculation']
                    )
                ) {
                    $calculation = &$recommendation[
                        'explanation'
                    ]['calculation'];

                    $calculation['recommended_production'] =
                        $adjustedCenter;

                    $calculation['minimum_recommendation'] =
                        $recommendation['min'] ?? null;

                    $calculation['maximum_recommendation'] =
                        $recommendation['max'] ?? null;

                    unset($calculation);
                }

                /*
                * 사용자에게는 수량과 이유만 설명합니다.
                *
                * 내부 계산 방식이나 비교 과정은
                * 안내 문장에 포함하지 않습니다.
                */
                $reason =
                    "최근 폐기량과 이월예정을 고려해 "
                    . "추천 생산량을 {$decreaseQuantity}개 줄였습니다.";

                /*
                * 실제 적용한 변경 내용을 기록합니다.
                *
                * 기존 반환 구조와 호환되도록
                * 수량과 적용 여부를 유지합니다.
                */
                $recommendation['metrics']
                    ['historical_adjustment'] = [
                        'applied' => true,

                        'direction' => 'decrease',

                        'original_quantity' => $center,

                        'adjusted_quantity' =>
                            $adjustedCenter,

                        'adjustment_quantity' =>
                            -$decreaseQuantity,

                        'adjustment_rate' =>
                            round(-$decreaseRate, 4),

                        'reason' => $reason,
                    ];

                $recommendation['metrics']
                    ['historical_validation']
                    ['applied'] = true;

                $recommendation['metrics']
                    ['historical_validation']
                    ['final_quantity'] =
                        $adjustedCenter;

                /*
                * 기존 추천 이유는 유지하되
                * 같은 문장은 중복 추가하지 않습니다.
                */
                $recommendation['reasons'] ??= [];

                if (
                    !in_array(
                        $reason,
                        $recommendation['reasons'],
                        true
                    )
                ) {
                    $recommendation['reasons'][] = $reason;
                }

                /*
                * 상세 안내에도 동일한 이유를 반영합니다.
                */
                $recommendation['explanation']
                    ['notices']['reasons'] ??= [];

                if (
                    !in_array(
                        $reason,
                        $recommendation['explanation']
                            ['notices']['reasons'],
                        true
                    )
                ) {
                    $recommendation['explanation']
                        ['notices']['reasons'][] = $reason;
                }

                /*
                * 감량을 적용했다면
                * 같은 추천량에 증량을 다시 적용하지 않습니다.
                */
                return $recommendation;
            }
        }

        /*
        * 감량을 적용하지 않았다면
        * 기존의 과거 부족 기록을 이용한
        * 증량 판단을 진행합니다.
        */
        $records = $outcomes['records'] ?? [];

        if (empty($records)) {
            return $recommendation;
        }

        $evidence =
            $outcomes['adjustment_evidence']
            ?? $this->recommendationAdjustmentEvidence(
                $outcomes,
                $targetDate
            );

        $backtest =
            $outcomes['adjustment_backtest']
            ?? $this->recommendationAdjustmentBacktest(
                $outcomes
            );

        $baselineRecords = [];

        foreach ($records as $record) {
            if (
                !is_array($record)
                || empty($record['date'])
            ) {
                continue;
            }

            /*
            * 같은 날짜에 서로 다른 추천량이 있으면
            * 대표 추천량이 불명확하므로 제외합니다.
            */
            if (
                $record['has_multiple_recommendations']
                ?? false
            ) {
                continue;
            }

            $originalQuantity = max(
                0,
                (int) (
                    $record['original_recommended_quantity']
                    ?? $record['recommended_quantity']
                    ?? 0
                )
            );

            $actualProduction = max(
                0,
                (int) ($record['actual_production'] ?? 0)
            );

            $carryoverIn = max(
                0,
                (int) ($record['carryover_in'] ?? 0)
            );

            $estimatedSales = max(
                0,
                (int) ($record['estimated_sales'] ?? 0)
            );

            $waste = max(
                0,
                (int) ($record['waste'] ?? 0)
            );

            $carryoverOut = max(
                0,
                (int) ($record['carryover_out'] ?? 0)
            );

            $loss = max(
                0,
                (int) ($record['loss'] ?? 0)
            );

            $originalAvailable =
                $originalQuantity + $carryoverIn;

            $actualSellableAvailable = max(
                0,
                $actualProduction + $carryoverIn - $loss
            );

            /*
            * 폐기·이월예정이 모두 없었던 날입니다.
            *
            * 더 판매할 기회가 있었을 가능성으로만
            * 참고합니다.
            */
            $possibleSellout =
                $actualSellableAvailable > 0
                && $estimatedSales > 0
                && $waste === 0
                && $carryoverOut === 0;

            $difference =
                $originalAvailable - $estimatedSales;

            $tolerance = max(
                2,
                (int) round($estimatedSales * 0.10)
            );

            $status = 'uncertain';

            if ($difference < 0) {
                $status = 'below_observed_sales';
            } elseif (
                $difference > $tolerance
                && ($waste + $carryoverOut) > 0
            ) {
                $status = 'possible_excess';
            } elseif (
                abs($difference) <= $tolerance
                && ($waste + $carryoverOut) > 0
            ) {
                $status = $difference === 0
                    ? 'exact'
                    : 'near';
            }

            /*
            * 기존 비교 가능한 기록과
            * 판매 기회 가능성이 있는 기록을 사용합니다.
            */
            $comparable = in_array(
                $status,
                [
                    'exact',
                    'near',
                    'below_observed_sales',
                    'possible_excess',
                ],
                true
            );

            if (!$comparable && !$possibleSellout) {
                continue;
            }

            $baselineRecord = $record;

            $baselineRecord['recommended_quantity'] =
                $originalQuantity;

            $baselineRecord['status'] =
                $status;

            $baselineRecord['possible_sellout'] =
                $possibleSellout;

            $baselineRecord['actual_sellable_available'] =
                $actualSellableAvailable;

            $baselineRecords[] = $baselineRecord;
        }

        if (empty($baselineRecords)) {
            return $recommendation;
        }

        /*
        * 과거 기록으로 증량 후보를 계산합니다.
        */
        $candidate =
            $this->recommendationService
                ->calculateAdaptiveAdjustment(
                    $baselineRecords,
                    $center,
                    $targetDate
                );

        /*
        * 부족 수량 감소와 남을 수량 증가를
        * 함께 고려해 최종 적용 여부를 판단합니다.
        */
        $recommendation =
            $this->recommendationService
                ->applyHistoricalValidation(
                    $recommendation,
                    $evidence,
                    $backtest,
                    $candidate
                );

        $adjustmentApplied = (bool) (
            $recommendation['metrics']
                ['historical_adjustment']
                ['applied']
            ?? false
        );

        $recommendation['metrics']
            ['historical_validation']
            ['applied'] = $adjustmentApplied;

        $recommendation['metrics']
            ['historical_validation']
            ['final_quantity'] =
                $recommendation['center'];

        return $recommendation;
    }

    /**
     * 과거 추천 결과를 바탕으로
     * 추천 생산량 조정이 필요한지 검토합니다.
     *
     * 이미 자동 조정된 추천량이 아니라
     * 최초 추천량을 기준으로 부족 여부를 판단합니다.
     *
     * 기존 추천량 자체는 변경하지 않습니다.
     */
    private function recommendationAdjustmentEvidence(
        array $outcomes,
        Carbon $targetDate
    ): array {
        $records = $outcomes['records'] ?? [];

        $targetDateString = $targetDate->toDateString();
        $targetWeekday = $targetDate->dayOfWeek;

        $sameWeekday = [];

        foreach ($records as $record) {
            if (
                !is_array($record)
                || empty($record['date'])
            ) {
                continue;
            }

            $dateString = substr(
                (string) $record['date'],
                0,
                10
            );

            if ($dateString >= $targetDateString) {
                continue;
            }

            if (
                (int) ($record['weekday'] ?? -1)
                !== $targetWeekday
            ) {
                continue;
            }

            if (
                $record['has_multiple_recommendations']
                ?? false
            ) {
                continue;
            }

            $originalQuantity = max(
                0,
                (int) (
                    $record['original_recommended_quantity']
                    ?? $record['recommended_quantity']
                    ?? 0
                )
            );

            $carryoverIn = max(
                0,
                (int) ($record['carryover_in'] ?? 0)
            );

            $estimatedSales = max(
                0,
                (int) ($record['estimated_sales'] ?? 0)
            );

            $waste = max(
                0,
                (int) ($record['waste'] ?? 0)
            );

            $carryoverOut = max(
                0,
                (int) ($record['carryover_out'] ?? 0)
            );

            $available =
                $originalQuantity + $carryoverIn;

            $difference =
                $available - $estimatedSales;

            $tolerance = max(
                2,
                (int) round($estimatedSales * 0.10)
            );

            $status = 'uncertain';

            if ($difference < 0) {
                $status = 'below_observed_sales';
            } elseif (
                $difference > $tolerance
                && ($waste + $carryoverOut) > 0
            ) {
                $status = 'possible_excess';
            } elseif (
                abs($difference) <= $tolerance
                && ($waste + $carryoverOut) > 0
            ) {
                $status = $difference === 0
                    ? 'exact'
                    : 'near';
            }

            if (
                !in_array(
                    $status,
                    [
                        'exact',
                        'near',
                        'below_observed_sales',
                        'possible_excess',
                    ],
                    true
                )
            ) {
                continue;
            }

            $record['date'] = $dateString;

            $record['baseline_status'] = $status;

            $record['baseline_recommendation'] =
                $originalQuantity;

            $record['baseline_difference'] =
                $difference;

            $sameWeekday[] = $record;
        }

        usort(
            $sameWeekday,
            fn (array $a, array $b) =>
                strcmp($a['date'], $b['date'])
        );

        $count = count($sameWeekday);

        $result = [
            'eligible' => false,
            'direction' => 'none',
            'reason' =>
                '비교할 수 있는 같은 요일의 생산 기록이 부족합니다.',
            'same_weekday_count' => $count,
            'shortage_days' => 0,
            'possible_excess_days' => 0,
            'near_days' => 0,
            'suggested_adjustment_rate' => 0,
            'evidence_dates' => [],
        ];

        if ($count === 0) {
            return $result;
        }

        $shortageRecords = [];
        $excessRecords = [];

        $nearCount = 0;

        $totalWeight = 0.0;
        $shortageWeight = 0.0;
        $excessWeight = 0.0;

        $weightedShortageRate = 0.0;
        $weightedExcessRate = 0.0;

        /*
        * 오래된 기록도 사용하되,
        * 최근 기록에 더 높은 비중을 둡니다.
        *
        * 약 90일 전 기록의 비중은
        * 최근 기록의 절반 수준입니다.
        */
        $halfLifeDays = 90.0;

        foreach ($sameWeekday as $record) {
            $status = $record['baseline_status'];

            $recordDate = Carbon::parse(
                $record['date']
            )->startOfDay();

            $daysAgo = max(
                0,
                $recordDate->diffInDays(
                    $targetDate->copy()->startOfDay()
                )
            );

            $weight = pow(
                0.5,
                $daysAgo / $halfLifeDays
            );

            $available = max(
                0,
                (int) $record['baseline_recommendation']
                + (int) ($record['carryover_in'] ?? 0)
            );

            $estimatedSales = max(
                0,
                (int) ($record['estimated_sales'] ?? 0)
            );

            $difference = (int) (
                $record['baseline_difference'] ?? 0
            );

            $quantityBase = max(
                1,
                $available,
                $estimatedSales
            );

            $differenceRate =
                abs($difference) / $quantityBase;

            $totalWeight += $weight;

            if ($status === 'below_observed_sales') {
                $shortageRecords[] = $record;

                $shortageWeight += $weight;

                $weightedShortageRate +=
                    $differenceRate * $weight;
            } elseif ($status === 'possible_excess') {
                $excessRecords[] = $record;

                $excessWeight += $weight;

                $weightedExcessRate +=
                    $differenceRate * $weight;
            } elseif (
                $status === 'exact'
                || $status === 'near'
            ) {
                $nearCount++;
            }
        }

        $shortageCount = count($shortageRecords);
        $excessCount = count($excessRecords);

        $result['shortage_days'] = $shortageCount;
        $result['possible_excess_days'] = $excessCount;
        $result['near_days'] = $nearCount;

        $shortageShare = $totalWeight > 0
            ? $shortageWeight / $totalWeight
            : 0.0;

        $excessShare = $totalWeight > 0
            ? $excessWeight / $totalWeight
            : 0.0;

        $averageShortageRate = $shortageWeight > 0
            ? $weightedShortageRate / $shortageWeight
            : 0.0;

        $averageExcessRate = $excessWeight > 0
            ? $weightedExcessRate / $excessWeight
            : 0.0;

        /*
        * 부족한 기록이 반복되면서
        * 수량 차이도 큰 경우를 구분합니다.
        *
        * 실제 증량 수량은 기존 추천 서비스가
        * 별도로 계산하고 검증합니다.
        */
        $repeatedShortage =
            $shortageCount >= 2
            && $shortageShare >= 0.60
            && $shortageShare > $excessShare;

        $largeShortage =
            $shortageCount >= 2
            && $shortageShare >= 0.45
            && $averageShortageRate >= 0.30
            && $shortageShare > $excessShare;

        if ($repeatedShortage || $largeShortage) {
            $result['eligible'] = true;

            $result['direction'] = 'increase';

            $result['suggested_adjustment_rate'] = 0;

            $result['evidence_dates'] = array_values(
                array_map(
                    fn (array $record) => $record['date'],
                    $shortageRecords
                )
            );

            $result['reason'] =
                "같은 요일의 과거 {$count}일치 기록 중 "
                . "{$shortageCount}일은 추천 생산량과 이월재고의 합계보다 "
                . "추정 판매량이 많았습니다.";

            return $result;
        }

        /*
        * 생산량이 많았던 경향은 기록하되
        * 이 함수에서 즉시 감량하지 않습니다.
        */
        $repeatedExcess =
            $excessCount >= 2
            && $excessShare >= 0.60
            && $excessShare > $shortageShare;

        $largeExcess =
            $excessCount >= 2
            && $excessShare >= 0.45
            && $averageExcessRate >= 0.30
            && $excessShare > $shortageShare;

        if ($repeatedExcess || $largeExcess) {
            $result['direction'] = 'review_decrease';

            $result['evidence_dates'] = array_values(
                array_map(
                    fn (array $record) => $record['date'],
                    $excessRecords
                )
            );

            $result['reason'] =
                "같은 요일의 과거 {$count}일치 기록 중 "
                . "{$excessCount}일은 추천 생산량이 추정 판매량보다 많았고, "
                . "폐기 또는 이월예정도 발생했습니다.";

            return $result;
        }

        $result['reason'] =
            '같은 요일의 과거 기록에서 추천 생산량이 반복적으로 부족하거나 많았던 경향은 확인되지 않았습니다.';

        return $result;
    }

    /**
     * 과거 추천 생산량과 실제 생산 결과를 설명합니다.
     *
     * 이미 계산된 날짜별 결과만 사용합니다.
     * 추가 데이터베이스 조회는 하지 않습니다.
     */
    private function recommendationOutcomeMessages(
        array $outcomes
    ): array {
        /*
        * 이미 계산된 날짜별 추천 결과를 사용합니다.
        *
        * 데이터베이스를 추가 조회하지 않습니다.
        * 생산·이월·로스·폐기 기록도 기존 일별 합산값을 사용합니다.
        */
        $records = $outcomes['records'] ?? [];
        $summary = $outcomes['summary'] ?? [];

        $messages = [];

        $evaluatedDays = (int) (
            $summary['evaluated_days'] ?? 0
        );

        $multipleDays = (int) (
            $summary['multiple_recommendation_days'] ?? 0
        );

        /*
        * 비교 가능한 추천 기록이 없는 경우입니다.
        */
        if ($evaluatedDays === 0) {
            $messages[] =
                '아직 추천 결과를 비교할 기록이 없습니다.';

            if ($multipleDays > 0) {
                $messages[] =
                    "추천 생산량이 여러 번 기록된 {$multipleDays}일치는 "
                    . '비교에서 제외했습니다.';
            }

            return $messages;
        }

        /*
        * 기존 추천 결과의 날짜별 집계입니다.
        *
        * 이전부터 사용하던 정확도 집계 기준을 유지합니다.
        */
        $nearDays =
            (int) ($summary['exact_days'] ?? 0)
            + (int) ($summary['near_days'] ?? 0);

        $belowDays = (int) (
            $summary['below_observed_sales_days'] ?? 0
        );

        $possibleExcessDays = (int) (
            $summary['possible_excess_days'] ?? 0
        );

        $uncertainDays = (int) (
            $summary['uncertain_days'] ?? 0
        );

        /*
        * 폐기와 이월예정이 모두 없었던 날짜를 집계합니다.
        *
        * 실제 생산량과 이월재고에서 로스를 제외한
        * 판매 가능 수량이 있는 정상 기록만 참고합니다.
        *
        * 이런 날은 추가 판매 기회가 있었을 수 있지만
        * 실제 품절이나 추가 수요를 확정하지는 않습니다.
        */
        $possibleSelloutDays = 0;

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            /*
            * 같은 날짜에 서로 다른 추천량이 있으면
            * 대표 추천량을 확정하기 어려워 제외합니다.
            */
            if (
                $record['has_multiple_recommendations']
                ?? false
            ) {
                continue;
            }

            $production = max(
                0,
                (int) ($record['actual_production'] ?? 0)
            );

            $carryoverIn = max(
                0,
                (int) ($record['carryover_in'] ?? 0)
            );

            $loss = max(
                0,
                (int) ($record['loss'] ?? 0)
            );

            $waste = max(
                0,
                (int) ($record['waste'] ?? 0)
            );

            $carryoverOut = max(
                0,
                (int) ($record['carryover_out'] ?? 0)
            );

            $estimatedSales = max(
                0,
                (int) ($record['estimated_sales'] ?? 0)
            );

            /*
            * 실제 생산량과 이월재고에서
            * 로스를 제외한 수량입니다.
            */
            $sellableAvailable = max(
                0,
                $production + $carryoverIn - $loss
            );

            if (
                $sellableAvailable > 0
                && $estimatedSales > 0
                && $waste === 0
                && $carryoverOut === 0
            ) {
                $possibleSelloutDays++;
            }
        }

        /*
        * 추천 결과의 전체 요약입니다.
        */
        $messages[] =
            "과거 {$evaluatedDays}일치 기록 중 "
            . "{$nearDays}일치는 추천 생산량과 추정 판매량이 비슷했습니다.";

        if ($belowDays > 0) {
            $messages[] =
                "{$belowDays}일치는 추천 생산량과 이월재고의 합계보다 "
                . '추정 판매량이 많았습니다.';
        }

        if ($possibleExcessDays > 0) {
            $messages[] =
                "{$possibleExcessDays}일치는 추천 생산량이 추정 판매량보다 많았고, "
                . '폐기 또는 이월예정도 발생했습니다.';
        }

        /*
        * 추가 판매 기회가 있었을 가능성을 안내합니다.
        *
        * 판매량을 임의로 늘려 계산하지 않습니다.
        * 실제 품절이 발생했다고 단정하지도 않습니다.
        */
        if ($possibleSelloutDays > 0) {
            $messages[] =
                "과거 {$evaluatedDays}일치 기록 중 "
                . "{$possibleSelloutDays}일은 폐기와 이월예정이 없었습니다. "
                . '더 판매할 기회가 있었을 수 있습니다.';
        }

        /*
        * 다른 이유로 결과를 판단하기 어려웠던 날짜도
        * 기존 집계 결과에 따라 안내합니다.
        */
        if ($uncertainDays > 0) {
            $messages[] =
                "{$uncertainDays}일치는 추천 생산량이 적절했는지 "
                . '판단하기 어렵습니다.';
        }

        /*
        * 날짜별 실제 생산 결과를 표시합니다.
        *
        * 가장 최근 기록부터 최대 3일치만 안내합니다.
        * 기존 API 호출 횟수는 증가하지 않습니다.
        */
        $recentRecords = array_reverse($records);

        $exampleCount = 0;

        foreach ($recentRecords as $record) {
            if ($exampleCount >= 3) {
                break;
            }

            if (
                !is_array($record)
                || empty($record['date'])
            ) {
                continue;
            }

            /*
            * 서로 다른 추천량이 저장된 날짜는
            * 날짜별 비교 안내에서 제외합니다.
            */
            if (
                $record['has_multiple_recommendations']
                ?? false
            ) {
                continue;
            }

            $date = Carbon::parse(
                $record['date']
            )->format('n월 j일');

            $recommended = max(
                0,
                (int) ($record['recommended_quantity'] ?? 0)
            );

            $production = max(
                0,
                (int) ($record['actual_production'] ?? 0)
            );

            $carryoverIn = max(
                0,
                (int) ($record['carryover_in'] ?? 0)
            );

            $estimatedSales = max(
                0,
                (int) ($record['estimated_sales'] ?? 0)
            );

            $loss = max(
                0,
                (int) ($record['loss'] ?? 0)
            );

            $waste = max(
                0,
                (int) ($record['waste'] ?? 0)
            );

            $carryoverOut = max(
                0,
                (int) ($record['carryover_out'] ?? 0)
            );

            $status = (string) (
                $record['status'] ?? 'uncertain'
            );

            /*
            * 날짜별 핵심 수량입니다.
            */
            $message =
                "{$date}: 추천 {$recommended}개, "
                . "실제 생산 {$production}개, "
                . "추정 판매 {$estimatedSales}개였습니다.";

            /*
            * 필요한 수량만 추가로 표시합니다.
            * 0개인 항목은 반복해서 나열하지 않습니다.
            */
            $details = [];

            if ($carryoverIn > 0) {
                $details[] = "이월재고 {$carryoverIn}개";
            }

            if ($loss > 0) {
                $details[] = "로스 {$loss}개";
            }

            if ($waste > 0) {
                $details[] = "폐기 {$waste}개";
            }

            if ($carryoverOut > 0) {
                $details[] =
                    "이월예정 {$carryoverOut}개";
            }

            if ($details !== []) {
                $message .=
                    ' ' . implode(', ', $details) . '였습니다.';
            }

            /*
            * 실제 확인할 수 있는 결과만 설명합니다.
            */
            if ($status === 'below_observed_sales') {
                $message .=
                    ' 추천 생산량과 이월재고의 합계보다 '
                    . '추정 판매량이 많았습니다.';
            } elseif ($status === 'possible_excess') {
                $message .=
                    ' 추천 생산량이 추정 판매량보다 많았고, '
                    . '폐기 또는 이월예정도 발생했습니다.';
            } elseif ($status === 'exact') {
                $message .=
                    ' 추천 생산량과 이월재고의 합계가 '
                    . '추정 판매량과 같았습니다.';
            } elseif ($status === 'near') {
                $message .=
                    ' 추천 생산량과 추정 판매량이 비슷했습니다.';
            } elseif (
                $waste === 0
                && $carryoverOut === 0
                && $estimatedSales > 0
            ) {
                $message .=
                    ' 폐기와 이월예정이 없어 '
                    . '더 판매할 기회가 있었을 수 있습니다.';
            } else {
                $message .=
                    ' 추천 생산량이 적절했는지 '
                    . '판단하기 어렵습니다.';
            }

            $messages[] = $message;

            $exampleCount++;
        }

        /*
        * 서로 다른 추천량이 여러 번 저장된 날짜는
        * 비교 대상에서 제외했음을 안내합니다.
        */
        if ($multipleDays > 0) {
            $messages[] =
                "추천 생산량이 여러 번 기록된 {$multipleDays}일치는 "
                . '비교에서 제외했습니다.';
        }

        /*
        * 분석 결과의 한계를 간단하게 안내합니다.
        *
        * 실제로 다른 수량을 생산했을 때의
        * 판매량이나 폐기량은 확인할 수 없습니다.
        */
        $messages[] =
            '추정 판매량은 생산·이월재고·로스·폐기·이월예정 기록으로 계산했습니다. '
            . '추천량대로 생산했을 때 실제로 얼마나 판매됐을지는 알 수 없습니다.';

        return $messages;
    }

    /**
     * 과거 추천 검증 결과를 월별·요일별로 집계합니다.
     *
     * 기존 날짜별 분석 결과를 재사용하므로
     * 데이터베이스를 추가 조회하지 않습니다.
     *
     * 동일 날짜에 추천이 여러 번 생성된 경우
     * 정확도 비교에서 제외하고 별도로 표시합니다.
     */
    private function summarizeRecommendationOutcomes(
        array $outcomes
    ): array {
        $weekdayNames = [
            '일요일',
            '월요일',
            '화요일',
            '수요일',
            '목요일',
            '금요일',
            '토요일',
        ];

        $monthly = [];
        $weekdays = [];

        /*
        * 기존 날짜별 결과를 집계할 기본 구조입니다.
        */
        $createGroup = static function (
            string $label
        ): array {
            return [
                'label' => $label,

                'record_days' => 0,

                'evaluated_days' => 0,

                'multiple_recommendation_days' => 0,

                'exact_days' => 0,

                'near_days' => 0,

                'below_observed_sales_days' => 0,

                'possible_excess_days' => 0,

                'uncertain_days' => 0,

                'recommended_total' => 0,

                'estimated_sales_total' => 0,

                'absolute_difference_total' => 0,

                'average_absolute_difference' => null,
            ];
        };

        /*
        * 요일은 기록이 없어도 7개를 반환합니다.
        */
        foreach ($weekdayNames as $index => $name) {
            $weekdays[$index] = $createGroup($name);

            $weekdays[$index]['weekday'] = $index;
        }

        $multipleRecommendationDays = 0;

        foreach ($outcomes['records'] ?? [] as $record) {
            if (!is_array($record)) {
                continue;
            }

            $date = $record['date'] ?? null;

            if (!$date) {
                continue;
            }

            $month = Carbon::parse($date)->format('Y-m');

            $weekday = Carbon::parse($date)->dayOfWeek;

            if (!isset($monthly[$month])) {
                $monthly[$month] = $createGroup($month);

                $monthly[$month]['month'] = $month;
            }

            $multiple = (bool) (
                $record['has_multiple_recommendations'] ?? false
            );

            $status = (string) (
                $record['status'] ?? 'uncertain'
            );

            /*
            * 중복 추천 날짜도 기록 수에는 포함하지만
            * 정확도 계산에는 넣지 않습니다.
            */
            foreach ([
                &$monthly[$month],
                &$weekdays[$weekday],
            ] as &$group) {
                $group['record_days']++;

                if ($multiple) {
                    $group['multiple_recommendation_days']++;

                    continue;
                }

                $group['evaluated_days']++;

                $group['recommended_total'] += (int) (
                    $record['recommended_available'] ?? 0
                );

                $group['estimated_sales_total'] += (int) (
                    $record['estimated_sales'] ?? 0
                );

                $group['absolute_difference_total'] += (int) (
                    $record['absolute_difference'] ?? 0
                );

                if ($status === 'exact') {
                    $group['exact_days']++;
                } elseif ($status === 'near') {
                    $group['near_days']++;
                } elseif ($status === 'below_observed_sales') {
                    $group['below_observed_sales_days']++;
                } elseif ($status === 'possible_excess') {
                    $group['possible_excess_days']++;
                } else {
                    $group['uncertain_days']++;
                }
            }

            unset($group);

            if ($multiple) {
                $multipleRecommendationDays++;
            }
        }

        /*
        * 평균 차이는 기록이 있는 날짜만 사용합니다.
        * 기록이 없으면 0개가 아닌 null을 반환합니다.
        */
        $finalizeGroup = static function (
            array $group
        ): array {
            $count = $group['evaluated_days'];

            $group['average_absolute_difference'] =
                $count > 0
                    ? round(
                        $group['absolute_difference_total'] / $count,
                        2
                    )
                    : null;

            return $group;
        };

        ksort($monthly);
        ksort($weekdays);

        $monthlyResults = [];

        foreach ($monthly as $group) {
            $monthlyResults[] = $finalizeGroup($group);
        }

        $weekdayResults = [];

        foreach ($weekdays as $group) {
            $weekdayResults[] = $finalizeGroup($group);
        }

        return [
            'monthly' => $monthlyResults,

            'weekdays' => $weekdayResults,

            'multiple_recommendation_days' =>
                $multipleRecommendationDays,

            'notice' =>
                '월별·요일별 비교는 마감 기록과 추천 기록이 확인되는 날짜를 기준으로 계산했습니다. 하루에 추천이 여러 번 생성된 날짜는 별도로 구분하며, 평균 차이는 실제 판매량이 아닌 추정 판매량을 기준으로 합니다.',
        ];
    }

    /**
     * 기존 추천량과 개선된 추천량을 비교합니다.
     *
     * 실제 판매량은 별도로 입력받지 않습니다.
     * 기존 생산·이월·로스·폐기 기록으로 계산한
     * 판매 추정량을 활용합니다.
     *
     * 동일 날짜에 생산이 여러 건이어도
     * 이미 합산된 날짜별 기록을 사용합니다.
     */
    private function recommendationAdjustmentComparison(
        int $storeId,
        int $productId,
        array $records
    ): array {
        $result = [
            'evaluated_days' => 0,
            'adjusted_days' => 0,

            'original_shortfall_total' => 0,
            'adjusted_shortfall_total' => 0,

            'original_possible_excess_total' => 0,
            'adjusted_possible_excess_total' => 0,

            'improved_days' => 0,
            'worsened_days' => 0,
            'unchanged_days' => 0,

            'records' => [],
        ];

        if (empty($records)) {
            return $result;
        }

        /*
        * 분석 대상 날짜만 수집합니다.
        */
        $dates = collect($records)
            ->pluck('date')
            ->filter()
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return $result;
        }

        /*
        * 추천 기록을 한 번에 조회합니다.
        * 날짜마다 별도 조회하지 않습니다.
        */
        $snapshots = ProductionRecommendationSnapshot::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereIn('target_date', $dates->all())
            ->whereNotNull('production_batch_id')
            ->orderBy('id')
            ->get([
                'id',
                'target_date',
                'center_quantity',
                'adjustment_meta',
            ])
            ->groupBy(
                fn ($snapshot) =>
                    $snapshot->target_date->toDateString()
            );

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $date = $record['date'] ?? null;

            if (!$date || !isset($snapshots[$date])) {
                continue;
            }

            $dailySnapshots = $snapshots[$date];

            /*
            * 같은 날 여러 생산 건이 있더라도
            * 추천량이 동일하면 한 번만 평가합니다.
            *
            * 서로 다른 추천량이 저장되어 있다면
            * 어느 추천을 기준으로 생산했는지
            * 불명확하므로 제외합니다.
            */
            $recommendationValues = $dailySnapshots
                ->map(function ($snapshot) {
                    $meta = $snapshot->adjustment_meta;

                    if (!is_array($meta)) {
                        return null;
                    }

                    return json_encode([
                        'original' =>
                            $meta['original_quantity'] ?? null,

                        'adjusted' =>
                            $meta['adjusted_quantity'] ?? null,

                        'final' =>
                            $snapshot->center_quantity,
                    ]);
                })
                ->unique()
                ->values();

            if ($recommendationValues->count() !== 1) {
                continue;
            }

            $snapshot = $dailySnapshots->first();

            $meta = $snapshot->adjustment_meta;

            /*
            * 개선 전후 기록이 없는 과거 데이터는
            * 비교 대상에서 제외합니다.
            */
            if (
                !is_array($meta)
                || !isset(
                    $meta['original_quantity'],
                    $meta['adjusted_quantity']
                )
            ) {
                continue;
            }

            $original = $meta['original_quantity'];
            $adjusted = $meta['adjusted_quantity'];

            if (
                !is_numeric($original)
                || !is_numeric($adjusted)
                || (int) $original < 0
                || (int) $adjusted < 0
            ) {
                continue;
            }

            $original = (int) $original;
            $adjusted = (int) $adjusted;

            /*
            * 저장된 최종 추천량과 일치하는지 확인합니다.
            */
            if (
                $adjusted !==
                (int) $snapshot->center_quantity
            ) {
                continue;
            }

            /*
            * 실제 마감 기록으로 계산된
            * 판매 추정량을 사용합니다.
            */
            $sales = $record['estimated_sales'] ?? null;

            if (
                !is_numeric($sales)
                || (int) $sales < 0
            ) {
                continue;
            }

            $sales = (int) $sales;

            $carryoverIn = max(
                0,
                (int) ($record['carryover_in'] ?? 0)
            );

            /*
            * 조정 전후 추천량을 사용했을 때
            * 확보할 수 있었던 수량입니다.
            */
            $originalAvailable =
                $original + $carryoverIn;

            $adjustedAvailable =
                $adjusted + $carryoverIn;

            /*
            * 실제 판매 추정량보다 부족한 수량입니다.
            *
            * 추가로 판매할 수 있었던 수량 전체를
            * 확정하는 값은 아닙니다.
            */
            $originalShortfall = max(
                0,
                $sales - $originalAvailable
            );

            $adjustedShortfall = max(
                0,
                $sales - $adjustedAvailable
            );

            /*
            * 실제 판매 추정량을 초과하는 수량입니다.
            *
            * 폐기 확정량이 아닌
            * 남을 가능성이 있는 수량입니다.
            */
            $originalExcess = max(
                0,
                $originalAvailable - $sales
            );

            $adjustedExcess = max(
                0,
                $adjustedAvailable - $sales
            );

            /*
            * 부족량과 남을 가능성이 있는 수량을
            * 함께 비교합니다.
            *
            * 임시 비교 지표이며
            * 최종 추천 방식의 우열을 확정하지 않습니다.
            */
            $originalDifference =
                $originalShortfall + $originalExcess;

            $adjustedDifference =
                $adjustedShortfall + $adjustedExcess;

            if ($adjustedDifference < $originalDifference) {
                $comparison = 'improved';
                $result['improved_days']++;
            } elseif (
                $adjustedDifference > $originalDifference
            ) {
                $comparison = 'worsened';
                $result['worsened_days']++;
            } else {
                $comparison = 'unchanged';
                $result['unchanged_days']++;
            }

            $result['evaluated_days']++;

            if ($original !== $adjusted) {
                $result['adjusted_days']++;
            }

            $result['original_shortfall_total'] +=
                $originalShortfall;

            $result['adjusted_shortfall_total'] +=
                $adjustedShortfall;

            $result['original_possible_excess_total'] +=
                $originalExcess;

            $result['adjusted_possible_excess_total'] +=
                $adjustedExcess;

            $result['records'][] = [
                'date' => $date,

                'original_quantity' => $original,

                'adjusted_quantity' => $adjusted,

                /*
                * 해당 날짜의 이월재고
                *
                * 추천 생산량과 추정 판매량을 비교할 때
                * 이미 확보된 이월재고도 함께 고려합니다.
                *
                * 해당 날짜의 여러 기록은 기존 일별 집계값을 사용합니다.
                */
                'carryover_in' => $carryoverIn,

                /*
                * 실제 생산·폐기·이월예정 수량
                *
                * 기록이 없으면 null을 전달합니다.
                * 실제 0개로 기록된 경우에는 0을 전달합니다.
                *
                * 생산·폐기·이월이 여러 건인 경우에도
                * 기존에 합산된 날짜별 수량을 그대로 사용합니다.
                */
                'actual_production' =>
                    isset($record['actual_production'])
                    && is_numeric($record['actual_production'])
                        ? (int) $record['actual_production']
                        : null,
                'estimated_sales' => $sales,
                'actual_waste' =>
                    isset($record['waste'])
                    && is_numeric($record['waste'])
                        ? (int) $record['waste']
                        : null,
                'actual_carryover' =>
                    isset($record['carryover_out'])
                    && is_numeric($record['carryover_out'])
                        ? (int) $record['carryover_out']
                        : null,

                'original_shortfall' =>
                    $originalShortfall,

                'adjusted_shortfall' =>
                    $adjustedShortfall,

                'original_possible_excess' =>
                    $originalExcess,

                'adjusted_possible_excess' =>
                    $adjustedExcess,

                'comparison' => $comparison,
            ];
        }

        usort(
            $result['records'],
            fn (array $a, array $b) =>
                strcmp($a['date'], $b['date'])
        );

        return $result;
    }

    /**
     * 과거 추천 생산량과 실제 마감 결과를 비교합니다.
     *
     * 기존 추천 분석 기능을 유지하면서
     * 자동 조정 전 추천량과 최종 추천량을 구분합니다.
     *
     * 같은 날짜에 생산 기록이 여러 건 있어도
     * 날짜별 합산 수량을 사용합니다.
     *
     * 같은 날짜에 서로 다른 추천 결과가 존재하면
     * 해당 날짜의 정확도 판단을 보류합니다.
     */
    private function recommendationOutcomeAnalysis(
        int $storeId,
        int $productId,
        Collection $history,
        Carbon $targetDate,
        ?Carbon $startDate = null
    ): array {
        $from = $startDate !== null
            ? $startDate->copy()->toDateString()
            : $targetDate->copy()
                ->subYear()
                ->toDateString();

        $to = $targetDate->copy()
            ->subDay()
            ->toDateString();

        /*
        * 추천 기록을 기간 전체에 대해 한 번 조회합니다.
        */
        $snapshots = ProductionRecommendationSnapshot::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereBetween(
                'target_date',
                [$from, $to]
            )
            ->whereNotNull('production_batch_id')
            ->whereNotNull('center_quantity')
            ->orderBy('target_date')
            ->orderBy('id')
            ->get([
                'id',
                'production_batch_id',
                'target_date',
                'center_quantity',
                'range_min',
                'range_max',
                'confidence',
                'referenced',
                'adjustment_meta',
            ])
            ->groupBy(
                fn ($item) =>
                    $item->target_date->toDateString()
            );

        $records = [];

        $summary = [
            'evaluated_days' => 0,

            'exact_days' => 0,

            'near_days' => 0,

            'below_observed_sales_days' => 0,

            'possible_excess_days' => 0,

            'uncertain_days' => 0,

            'multiple_recommendation_days' => 0,

            'excluded_days' => 0,

            'average_absolute_difference' => null,
        ];

        $totalAbsoluteDifference = 0;

        foreach ($history as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date = $row['date'] ?? null;

            if (
                !$date
                || !isset($snapshots[$date])
            ) {
                continue;
            }

            /*
            * 정상 마감된 날짜만 분석합니다.
            *
            * 수량이 잘못된 날짜도 제외합니다.
            */
            if (
                ($row['closure_status'] ?? null) !== 'closed'
                || ($row['daily_quantity_valid'] ?? false) !== true
            ) {
                $summary['excluded_days']++;
                continue;
            }

            $salesValue =
                $row['estimated_sales_quantity'] ?? null;

            if (
                $salesValue === null
                || !is_numeric($salesValue)
                || (float) $salesValue < 0
            ) {
                $summary['excluded_days']++;
                continue;
            }

            /*
            * 해당 날짜의 추천 기록입니다.
            *
            * 생산을 여러 번 등록했다면
            * 추천 기록도 여러 건일 수 있습니다.
            */
            $dailySnapshots = $snapshots[$date];

            $recommendationCount =
                $dailySnapshots->count();

            /*
            * 추천량, 추천 범위, 신뢰도가
            * 모두 동일한지 확인합니다.
            *
            * 동일한 결과가 여러 번 저장된 것은
            * 하나의 추천으로 취급합니다.
            */
            $distinctRecommendations = $dailySnapshots
                ->map(
                    fn ($item) => json_encode([
                        'center' =>
                            (int) $item->center_quantity,

                        'min' =>
                            $item->range_min !== null
                                ? (int) $item->range_min
                                : null,

                        'max' =>
                            $item->range_max !== null
                                ? (int) $item->range_max
                                : null,

                        'confidence' =>
                            (string) $item->confidence,
                    ])
                )
                ->unique()
                ->values();

            $hasMultipleRecommendations =
                $distinctRecommendations->count() > 1;

            /*
            * 동일한 추천 기록이라면
            * 첫 번째 기록을 대표값으로 사용합니다.
            */
            $snapshot = $dailySnapshots->first();

            /*
            * 사용자에게 실제 표시했던 최종 추천량
            */
            $recommended =
                (int) $snapshot->center_quantity;

            /*
            * 조정 전 추천량을 확인합니다.
            *
            * 과거 데이터에는 조정 기록이
            * 존재하지 않을 수 있습니다.
            */
            $originalRecommended = $recommended;

            $recommendationAdjusted = false;

            $adjustmentMeta =
                $snapshot->adjustment_meta;

            if (
                is_array($adjustmentMeta)
                && isset(
                    $adjustmentMeta['original_quantity'],
                    $adjustmentMeta['adjusted_quantity']
                )
                && is_numeric(
                    $adjustmentMeta['original_quantity']
                )
                && is_numeric(
                    $adjustmentMeta['adjusted_quantity']
                )
            ) {
                $storedOriginal = (int) (
                    $adjustmentMeta['original_quantity']
                );

                $storedAdjusted = (int) (
                    $adjustmentMeta['adjusted_quantity']
                );

                /*
                * 최종 추천량과 저장된 조정 결과가
                * 일치하는 경우에만 사용합니다.
                */
                if (
                    $storedOriginal >= 0
                    && $storedAdjusted === $recommended
                ) {
                    $originalRecommended =
                        $storedOriginal;

                    $recommendationAdjusted =
                        $originalRecommended !== $recommended;
                }
            }

            /*
            * 최종 추천은 같더라도
            * 조정 전 추천량이 서로 다를 수 있습니다.
            *
            * 이 경우 하나의 대표 추천을
            * 확정할 수 없으므로 검증에서 제외합니다.
            */
            $distinctOriginals = $dailySnapshots
                ->map(function ($item) {
                    $final = (int) $item->center_quantity;

                    $meta = $item->adjustment_meta;

                    if (
                        !is_array($meta)
                        || !isset(
                            $meta['original_quantity'],
                            $meta['adjusted_quantity']
                        )
                        || !is_numeric(
                            $meta['original_quantity']
                        )
                        || !is_numeric(
                            $meta['adjusted_quantity']
                        )
                    ) {
                        return $final;
                    }

                    $original =
                        (int) $meta['original_quantity'];

                    $adjusted =
                        (int) $meta['adjusted_quantity'];

                    if (
                        $original < 0
                        || $adjusted !== $final
                    ) {
                        return $final;
                    }

                    return $original;
                })
                ->unique()
                ->values();

            if ($distinctOriginals->count() > 1) {
                $hasMultipleRecommendations = true;
            }

            /*
            * 기존 날짜별 합산 결과를 사용합니다.
            */
            $production = max(
                0,
                (int) ($row['production'] ?? 0)
            );

            $carryoverIn = max(
                0,
                (int) ($row['carryover_in'] ?? 0)
            );

            $carryoverOut = max(
                0,
                (int) ($row['carryover_out'] ?? 0)
            );

            $loss = max(
                0,
                (int) ($row['operational_loss'] ?? 0)
            );

            $waste = max(
                0,
                (int) ($row['operational_waste'] ?? 0)
            );

            /*
            * 판매량은 별도로 입력받지 않습니다.
            *
            * 생산 + 이월 입고
            * - 로스 - 폐기 - 이월 출고
            *
            * 기존 함수에서 계산된 값을 사용합니다.
            */
            $estimatedSales = (int) $salesValue;

            /*
            * 최종 추천량으로 확보 가능한 수량
            */
            $recommendedAvailable =
                $recommended + $carryoverIn;

            /*
            * 최초 추천량으로 확보 가능한 수량
            */
            $originalRecommendedAvailable =
                $originalRecommended + $carryoverIn;

            /*
            * 실제 확보했던 수량
            */
            $actualAvailable =
                $production + $carryoverIn;

            /*
            * 기존 추천 검증은
            * 사용자에게 표시된 최종 추천량을
            * 기준으로 유지합니다.
            */
            $difference =
                $recommendedAvailable - $estimatedSales;

            $absoluteDifference = abs(
                $difference
            );

            /*
            * 최초 추천량과 판매 추정량의 차이
            *
            * 기존 검증 결과를 변경하지 않고
            * 별도 비교 자료로 보관합니다.
            */
            $originalDifference =
                $originalRecommendedAvailable
                    - $estimatedSales;

            /*
            * 기존 근접 판단 기준 유지
            */
            $tolerance = max(
                2,
                (int) round($estimatedSales * 0.10)
            );

            $status = 'uncertain';

            /*
            * 서로 다른 추천이 존재하는 날짜는
            * 정확도 판단을 보류합니다.
            */
            if ($hasMultipleRecommendations) {
                $status = 'multiple_recommendations';

                $summary['multiple_recommendation_days']++;
            } elseif ($difference < 0) {
                $status = 'below_observed_sales';

                $summary['below_observed_sales_days']++;
            } elseif (
                $difference > $tolerance
                && ($waste + $carryoverOut) > 0
            ) {
                $status = 'possible_excess';

                $summary['possible_excess_days']++;
            } elseif (
                $absoluteDifference <= $tolerance
                && ($waste + $carryoverOut) > 0
            ) {
                if ($difference === 0) {
                    $status = 'exact';

                    $summary['exact_days']++;
                } else {
                    $status = 'near';

                    $summary['near_days']++;
                }
            } else {
                $status = 'uncertain';

                $summary['uncertain_days']++;
            }

            /*
            * 날짜별 상세 분석 기록
            */
            $records[] = [
                'date' => $date,

                'weekday' =>
                    Carbon::parse($date)->dayOfWeek,

                'month' =>
                    Carbon::parse($date)->format('Y-m'),

                /*
                * 사용자에게 표시된 최종 추천량
                */
                'recommended_quantity' =>
                    $recommended,

                /*
                * 자동 조정 전 최초 추천량
                */
                'original_recommended_quantity' =>
                    $originalRecommended,

                /*
                * 추천량 조정 여부
                */
                'recommendation_adjusted' =>
                    $recommendationAdjusted,

                /*
                * 조정된 수량
                */
                'recommendation_adjustment_quantity' =>
                    $recommended - $originalRecommended,

                'recommended_available' =>
                    $recommendedAvailable,

                'original_recommended_available' =>
                    $originalRecommendedAvailable,

                'actual_production' =>
                    $production,

                'actual_available' =>
                    $actualAvailable,

                'carryover_in' =>
                    $carryoverIn,

                'loss' => $loss,

                'waste' => $waste,

                'carryover_out' =>
                    $carryoverOut,

                'estimated_sales' =>
                    $estimatedSales,

                'difference_from_observed_sales' =>
                    $difference,

                'original_difference_from_observed_sales' =>
                    $originalDifference,

                'absolute_difference' =>
                    $absoluteDifference,

                'original_absolute_difference' =>
                    abs($originalDifference),

                'status' => $status,

                'recommendation_referenced' =>
                    (bool) $snapshot->referenced,

                'recommendation_count' =>
                    $recommendationCount,

                'has_multiple_recommendations' =>
                    $hasMultipleRecommendations,

                'recommendation_selection' =>
                    $recommendationCount > 1
                        ? (
                            $hasMultipleRecommendations
                                ? 'conflicting'
                                : 'identical'
                        )
                        : 'single',

                'confidence' =>
                    $snapshot->confidence,
            ];

            /*
            * 서로 다른 추천이 존재하는 날짜는
            * 기존 정확도 집계에서 제외합니다.
            */
            if (!$hasMultipleRecommendations) {
                $summary['evaluated_days']++;

                $totalAbsoluteDifference +=
                    $absoluteDifference;
            }
        }

        /*
        * 분석 날짜를 오래된 순서로 정렬합니다.
        */
        usort(
            $records,
            fn (array $a, array $b) =>
                strcmp(
                    $a['date'],
                    $b['date']
                )
        );

        /*
        * 기존 평균 차이 계산 유지
        */
        if ($summary['evaluated_days'] > 0) {
            $summary['average_absolute_difference'] =
                round(
                    $totalAbsoluteDifference
                        / $summary['evaluated_days'],
                    2
                );
        }

        return [
            'period' => [
                'from' => $from,
                'to' => $to,
            ],

            'summary' => $summary,

            'records' => $records,

            'notice' =>
                '추천량과 실제 마감 결과를 비교한 자료입니다. 실제로 추천량대로 생산했을 때의 판매량이나 폐기량을 확정하는 결과는 아닙니다.',
        ];
    }

    /**
     * 제품별 추천 상세 분석 화면에 필요한 자료 구성
     *
     * 기존에 조회한 최대 12개월 생산 기록을 재사용합니다.
     * 여기서는 추가 DB 조회를 하지 않습니다.
     *
     * 정상 마감되고 수량이 유효한 기록만 계산합니다.
     * 기록이 없는 날짜를 판매량 0개로 만들지 않습니다.
     *
     * 요일별 평균은 일반 영업일 기록만 사용합니다.
     */
    private function recommendationAnalysisDetails(
        Collection $history,
        Carbon $targetDate
    ): array {
        $monthly = [];

        $weekdayNames = [
            '일요일',
            '월요일',
            '화요일',
            '수요일',
            '목요일',
            '금요일',
            '토요일',
        ];

        $weekdays = [];

        foreach ($weekdayNames as $index => $name) {
            $weekdays[$index] = [
                'weekday' => $index,
                'name' => $name,
                'record_count' => 0,
                'sales_total' => 0,
            ];
        }

        $daily = [];

        $totals = [
            'valid_days' => 0,
            'production' => 0,
            'carryover_in' => 0,
            'loss' => 0,
            'waste' => 0,
            'carryover_out' => 0,
            'estimated_sales' => 0,
        ];

        foreach ($history as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (
                ($row['closure_status'] ?? null)
                    !== 'closed'
            ) {
                continue;
            }

            if (
                ($row['daily_quantity_valid'] ?? false)
                    !== true
            ) {
                continue;
            }

            $sales = $row['estimated_sales_quantity']
                ?? null;

            if (
                $sales === null
                || !is_numeric($sales)
                || (float) $sales < 0
            ) {
                continue;
            }

            $dateValue = $row['date'] ?? null;

            if (!$dateValue) {
                continue;
            }

            $date = Carbon::parse($dateValue);
            $dateString = $date->toDateString();
            $monthKey = $date->format('Y-m');
            $weekday = $date->dayOfWeek;

            $production = max(
                0,
                (int) ($row['production'] ?? 0)
            );

            $carryoverIn = max(
                0,
                (int) ($row['carryover_in'] ?? 0)
            );

            $loss = max(
                0,
                (int) ($row['operational_loss'] ?? 0)
            );

            $waste = max(
                0,
                (int) ($row['operational_waste'] ?? 0)
            );

            $carryoverOut = max(
                0,
                (int) ($row['carryover_out'] ?? 0)
            );

            $estimatedSales = (int) $sales;

            $available = $production + $carryoverIn;

            $remaining = $waste + $carryoverOut;

            $specialDay = (bool) (
                $row['special_day'] ?? false
            );

            /*
            * 날짜별 그래프 자료
            */
            $daily[] = [
                'date' => $dateString,
                'weekday' => $weekday,
                'production' => $production,
                'carryover_in' => $carryoverIn,
                'loss' => $loss,
                'waste' => $waste,
                'carryover_out' => $carryoverOut,
                'available' => $available,
                'remaining' => $remaining,
                'estimated_sales' => $estimatedSales,
                'special_day' => $specialDay,
            ];

            /*
            * 월별 그래프 자료
            */
            if (!isset($monthly[$monthKey])) {
                $monthly[$monthKey] = [
                    'month' => $monthKey,
                    'record_count' => 0,
                    'production' => 0,
                    'carryover_in' => 0,
                    'loss' => 0,
                    'waste' => 0,
                    'carryover_out' => 0,
                    'estimated_sales' => 0,
                ];
            }

            $monthly[$monthKey]['record_count']++;
            $monthly[$monthKey]['production'] +=
                $production;
            $monthly[$monthKey]['carryover_in'] +=
                $carryoverIn;
            $monthly[$monthKey]['loss'] += $loss;
            $monthly[$monthKey]['waste'] += $waste;
            $monthly[$monthKey]['carryover_out'] +=
                $carryoverOut;
            $monthly[$monthKey]['estimated_sales'] +=
                $estimatedSales;

            /*
            * 요일별 비교는 특별한 행사일을 제외합니다.
            */
            if (!$specialDay) {
                $weekdays[$weekday]['record_count']++;
                $weekdays[$weekday]['sales_total'] +=
                    $estimatedSales;
            }

            /*
            * 전체 집계
            */
            $totals['valid_days']++;
            $totals['production'] += $production;
            $totals['carryover_in'] += $carryoverIn;
            $totals['loss'] += $loss;
            $totals['waste'] += $waste;
            $totals['carryover_out'] += $carryoverOut;
            $totals['estimated_sales'] +=
                $estimatedSales;
        }

        /*
        * 날짜순 정렬
        */
        usort(
            $daily,
            fn (array $a, array $b) =>
                strcmp($a['date'], $b['date'])
        );

        ksort($monthly);

        /*
        * 월별 판매 추정 평균
        *
        * 해당 월의 실제 유효 기록일 수로 나눕니다.
        * 달력상 전체 날짜 수로 나누지 않습니다.
        */
        $monthlyResults = [];

        foreach ($monthly as $item) {
            $count = $item['record_count'];

            $item['average_sales'] = $count > 0
                ? round(
                    $item['estimated_sales'] / $count,
                    2
                )
                : null;

            $monthlyResults[] = $item;
        }

        /*
        * 요일별 평균
        */
        $weekdayResults = [];

        foreach ($weekdays as $item) {
            $count = $item['record_count'];

            $item['average_sales'] = $count > 0
                ? round(
                    $item['sales_total'] / $count,
                    2
                )
                : null;

            $weekdayResults[] = $item;
        }

        /*
        * 전체 잔여 재고율
        *
        * 폐기와 이월을 동등한 잔여 수량으로 취급합니다.
        */
        $availableTotal =
            $totals['production']
            + $totals['carryover_in'];

        $remainingTotal =
            $totals['waste']
            + $totals['carryover_out'];

        $totals['remaining_rate'] =
            $availableTotal > 0
                ? round(
                    $remainingTotal / $availableTotal,
                    4
                )
                : null;

        return [
            'period' => [
                'from' => $targetDate->copy()
                    ->subYear()
                    ->toDateString(),
                'to' => $targetDate->copy()
                    ->subDay()
                    ->toDateString(),
            ],

            'monthly' => $monthlyResults,
            'weekdays' => $weekdayResults,

            // 최근 최대 28개 기록의 상세 흐름
            'recent_daily' => array_slice(
                $daily,
                -28
            ),

            'totals' => $totals,
        ];
    }

    /**
     * 생산 분석 데이터를 조회합니다.
     *
     * product_id가 있으면 해당 제품의
     * 최근 12개월 추천 데이터를 분석합니다.
     *
     * 과거 추천 검증은 전체 누적 기록을 사용합니다.
     *
     * product_id가 없으면 기존 전체 분석 방식을 유지합니다.
     */
    public function analysis(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $this->accessService->requirePermission(
            $user,
            'production.view'
        );

        $data = $request->validate([
            'store_id' => [
                'required',
                'integer',
                'exists:stores,id',
            ],
            'date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'days' => [
                'nullable',
                'integer',
                Rule::in([7, 30, 90, 365]),
            ],
            'product_id' => [
                'nullable',
                'integer',
                'exists:products,id',
            ],
        ]);

        $storeId = (int) $data['store_id'];

        $this->assertStoreReadable(
            $user,
            $storeId
        );

        $target = Carbon::parse($data['date']);

        $days = (int) ($data['days'] ?? 30);

        $from = $target->copy()
            ->subDays($days - 1)
            ->toDateString();

        $to = $target->toDateString();

        /*
        * 제품별 추천 분석
        *
        * 생산 입력창과 추천 이유 분석 다이얼로그에서
        * 사용하는 기존 조회 방식입니다.
        */
        if (isset($data['product_id'])) {
            $productId = (int) $data['product_id'];

            $product = Product::query()
                ->where('store_id', $storeId)
                ->where('is_active', true)
                ->whereKey($productId)
                ->firstOrFail([
                    'id',
                    'name',
                ]);

            /*
            * 기존 추천 생산량 계산용 기록
            *
            * 최근 12개월 정책을 유지합니다.
            */
            $history =
                $this->recommendationHistoryForProduct(
                    $storeId,
                    $productId,
                    $target
                );

            /*
            * 추천 대상 날짜의 행사
            */
            $targetEvents = collect(
                $this->eventsForDate(
                    $storeId,
                    $data['date']
                )
            );

            $productEvents = $targetEvents
                ->filter(
                    fn (array $event) =>
                        empty($event['products'])
                        || collect(
                            $event['products']
                        )->contains(
                            'id',
                            $productId
                        )
                )
                ->values()
                ->all();

            /*
            * 추천 대상 날짜의 날씨
            *
            * 기존 날씨 보정 정책을 유지합니다.
            */
            $targetWeatherCode =
                StoreDailyWeather::query()
                    ->where('store_id', $storeId)
                    ->whereDate(
                        'work_date',
                        $data['date']
                    )
                    ->value('condition');

            if (!empty($targetWeatherCode)) {
                $productEvents[] = [
                    'weather_code' =>
                        (string) $targetWeatherCode,
                ];
            }

            /*
            * 추천 대상 날짜의 이월 입고
            *
            * 기존 기록을 날짜별 합산하며
            * 추천량 계산 시 한 번만 차감합니다.
            */
            $targetCarryoverIn =
                (int) ProductStockMovement::query()
                    ->where('store_id', $storeId)
                    ->where('product_id', $productId)
                    ->whereDate(
                        'work_date',
                        $data['date']
                    )
                    ->where(
                        'movement_type',
                        'carryover_in'
                    )
                    ->sum('quantity');

            $productEvents[] = [
                'condition_type' => 'target_inventory',
                'carryover_in' => $targetCarryoverIn,
            ];

            /*
            * 1. 기존 추천 생산량 계산
            *
            * 동일 요일 분석, 최근 3개월 70%,
            * 이전 9개월 30%, 폐기·이월 분석 등
            * 기존 계산 정책을 그대로 사용합니다.
            */
            $recommendation =
                $this->recommendationService->recommend(
                    $history,
                    $target,
                    $productEvents
                );

            /*
            * 2. 기존 상세 분석 자료
            *
            * 기존 12개월 기록을 재사용하므로
            * 추가 데이터베이스 조회가 발생하지 않습니다.
            */
            $analysisDetails =
                $this->recommendationAnalysisDetails(
                    $history,
                    $target
                );

            /*
            * 3. 과거 추천 검증 자료
            *
            * 전체 누적 추천 기록을 분석합니다.
            *
            * 기존 12개월 기록을 우선 재사용하고,
            * 필요한 경우에만 이전 기록을 조회합니다.
            *
            * 생산 저장 과정에서도 동일한
            * 공통 조회 함수를 사용합니다.
            */
            $recommendationOutcomes =
                $this->recommendationOutcomesForProduct(
                    $storeId,
                    $productId,
                    $target,
                    $history
                );

            /*
            * 4. 월별·요일별 추천 결과 분석
            *
            * 이미 조회한 과거 추천 결과를 사용합니다.
            */
            $recommendationOutcomes['breakdowns'] =
                $this->summarizeRecommendationOutcomes(
                    $recommendationOutcomes
                );

            /*
            * 5. 실제 날짜와 수량을 활용한 분석 문장
            *
            * 기존 추천 이유는 유지합니다.
            */
            $recommendationOutcomes['messages'] =
                $this->recommendationOutcomeMessages(
                    $recommendationOutcomes
                );

            /*
            * 6. 과거 추천 검증 결과
            *
            * 기존 분석 결과에 검증 지표를 추가합니다.
            */
            $actualBacktest =
                $recommendationOutcomes['adjustment_backtest']
                    ?? [];

            $adjustmentEvidence =
                $recommendationOutcomes['adjustment_evidence']
                    ?? [];

            $testedDays = (int) (
                $actualBacktest['tested_days'] ?? 0
            );

            $backtestResult = (string) (
                $actualBacktest['result'] ?? 'insufficient'
            );

            $evidenceEligible = (bool) (
                $adjustmentEvidence['eligible'] ?? false
            );

            /*
            * 기존 추천 지표는 보존하면서
            * 새로운 검증 결과만 추가합니다.
            */
            $recommendation['metrics'] ??= [];

            $recommendation['metrics']['historical_validation'] = [
                'tested_days' => $testedDays,

                'result' => $backtestResult,

                'evidence_eligible' => $evidenceEligible,

                'original_total_difference' =>
                    $actualBacktest['original_total_difference']
                        ?? 0,

                'candidate_total_difference' =>
                    $actualBacktest['candidate_total_difference']
                        ?? 0,

                'improvement_rate' =>
                    $actualBacktest['improvement_rate']
                        ?? null,

                'original_shortage_days' =>
                    $actualBacktest['original_shortage_days']
                        ?? 0,

                'candidate_shortage_days' =>
                    $actualBacktest['candidate_shortage_days']
                        ?? 0,

                'original_shortfall_total' =>
                    $actualBacktest['original_shortfall_total']
                        ?? 0,

                'candidate_shortfall_total' =>
                    $actualBacktest['candidate_shortfall_total']
                        ?? 0,

                'candidate_supported_days' =>
                    $actualBacktest['candidate_supported_days']
                        ?? 0,

                'applied' => false,
            ];

            /*
            * 7. 최종 추천량 계산
            *
            * 기존 추천량에 과거 검증 결과를 반영합니다.
            *
            * 검증 조건을 충족하지 않으면
            * 기존 추천량을 그대로 유지합니다.
            *
            * 생산 저장 시에도 동일한 공통 함수를 사용합니다.
            */
            $recommendation =
                $this->applyHistoricalRecommendation(
                    $recommendation,
                    $recommendationOutcomes,
                    $target
                );

            /*
            * 8. 기존 주의사항 및 경고
            *
            * 기존 동작을 유지합니다.
            */
            $recent = $history->take(-5);

            $recentCount = $recent->count();

            $flags = [];

            if (
                $recent->where('waste', '>', 0)->count() >= 3
            ) {
                $flags[] =
                    "최근 생산 기록 {$recentCount}건 중 3건 이상에서 폐기가 발생했습니다. 생산량을 확인해 주세요.";
            }

            if (
                $recent->where(
                    'carryover_out',
                    '>',
                    0
                )->count() >= 3
            ) {
                $flags[] =
                    "최근 생산 기록 {$recentCount}건 중 3건 이상에서 재고를 다음 날로 이월했습니다.";
            }

            if (!empty($recommendation['warning'])) {
                $flags[] = $recommendation['warning'];
            }

            /*
            * 9. 기존 API 응답 구조 유지
            *
            * 새로운 추천 검증 결과는
            * recommendation_outcomes로 전달합니다.
            */
            return response()->json([
                'items' => [
                    [
                        'product_id' => $product->id,

                        'product_name' => $product->name,

                        'recommendation' => $recommendation,

                        'analysis_details' => $analysisDetails,

                        'recommendation_outcomes' =>
                            $recommendationOutcomes,

                        'flags' => $flags,
                    ],
                ],

                'daily_flow' => [],

                'product_totals' => [],

                'range' => [
                    'from' => $from,
                    'to' => $to,
                    'days' => $days,
                ],
            ]);
        }

        /*
        * 기존 전체 분석 요청
        *
        * 개발 검토 중인 분석 탭의
        * 기존 동작을 그대로 유지합니다.
        */
        $products = Product::query()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        $dateMetrics = [
            'production' => $this->sumByDate(
                ProductionBatch::query(),
                $storeId,
                'work_date',
                $from,
                $to
            ),

            'carryover' => $this->sumByDate(
                ProductStockMovement::query()
                    ->where(
                        'movement_type',
                        'carryover_in'
                    ),
                $storeId,
                'work_date',
                $from,
                $to
            ),

            'loss' => $this->sumByDate(
                ProductionLoss::query(),
                $storeId,
                'work_date',
                $from,
                $to
            ),

            'waste' => $this->sumByDate(
                ProductionWaste::query(),
                $storeId,
                'work_date',
                $from,
                $to
            ),

            'attributed_waste' => $this->sumByDate(
                ProductionWaste::query(),
                $storeId,
                'attribution_date',
                $from,
                $to
            ),
        ];

        $dailyFlow = collect();

        for (
            $cursor = Carbon::parse($from);
            $cursor->lte($target);
            $cursor->addDay()
        ) {
            $key = $cursor->toDateString();

            $production = (int) (
                $dateMetrics['production'][$key] ?? 0
            );

            $waste = (int) (
                $dateMetrics['waste'][$key] ?? 0
            );

            $attributedWaste = (int) (
                $dateMetrics['attributed_waste'][$key] ?? 0
            );

            $dailyFlow->push([
                'date' => $key,

                'production' => $production,

                'carryover' => (int) (
                    $dateMetrics['carryover'][$key] ?? 0
                ),

                'loss' => (int) (
                    $dateMetrics['loss'][$key] ?? 0
                ),

                'waste' => $waste,

                'waste_rate' => $this->wasteRate(
                    $attributedWaste,
                    $production
                ),
            ]);
        }

        $productMetrics = [
            'production' => $this->sumByProduct(
                ProductionBatch::query(),
                $storeId,
                'work_date',
                $from,
                $to
            ),

            'carryover' => $this->sumByProduct(
                ProductStockMovement::query()
                    ->where(
                        'movement_type',
                        'carryover_in'
                    ),
                $storeId,
                'work_date',
                $from,
                $to
            ),

            'loss' => $this->sumByProduct(
                ProductionLoss::query(),
                $storeId,
                'work_date',
                $from,
                $to
            ),

            'waste' => $this->sumByProduct(
                ProductionWaste::query(),
                $storeId,
                'work_date',
                $from,
                $to
            ),
        ];

        $productTotals = $products
            ->map(
                fn (Product $product) => [
                    'product_id' => $product->id,

                    'product_name' => $product->name,

                    'production' => (int) (
                        $productMetrics['production'][$product->id] ?? 0
                    ),

                    'carryover' => (int) (
                        $productMetrics['carryover'][$product->id] ?? 0
                    ),

                    'loss' => (int) (
                        $productMetrics['loss'][$product->id] ?? 0
                    ),

                    'waste' => (int) (
                        $productMetrics['waste'][$product->id] ?? 0
                    ),
                ]
            )
            ->values();

        /*
        * 기존 전체 분석은 최근 28일 기록을 사용합니다.
        */
        $historyByProduct = collect();

        $recommendationDays = 28;

        $historyFrom = $target->copy()
            ->subDays($recommendationDays)
            ->toDateString();

        $historyTo = $target->copy()
            ->subDay()
            ->toDateString();

        $recommendationContext =
            $this->recommendationData(
                $storeId,
                $historyFrom,
                $historyTo
            );

        for (
            $i = $recommendationDays;
            $i >= 1;
            $i--
        ) {
            $date = $target->copy()
                ->subDays($i)
                ->toDateString();

            $daily = $this->dailyService->build(
                $storeId,
                $date
            );

            $events = $this->eventsForDate(
                $storeId,
                $date
            );

            foreach ($daily['rows'] as $row) {
                if (
                    !($row['production_confirmed'] ?? false)
                ) {
                    continue;
                }

                $historyByProduct->push(
                    $this->recommendationHistoryRow(
                        $row,
                        $date,
                        $events,
                        $recommendationContext
                    )
                );
            }
        }

        $historyByProduct =
            $historyByProduct->groupBy('product_id');

        $targetEvents = collect(
            $this->eventsForDate(
                $storeId,
                $data['date']
            )
        );

        $targetWeatherCode =
            StoreDailyWeather::query()
                ->where('store_id', $storeId)
                ->whereDate(
                    'work_date',
                    $data['date']
                )
                ->value('condition');

        if (!empty($targetWeatherCode)) {
            $targetEvents->push([
                'weather_code' =>
                    (string) $targetWeatherCode,
            ]);
        }

        $items = $products->map(
            function (Product $product) use (
                $historyByProduct,
                $targetEvents,
                $target
            ) {
                $history = collect(
                    $historyByProduct->get(
                        $product->id,
                        []
                    )
                )->values();

                $productEvents = $targetEvents
                    ->filter(
                        fn (array $event) =>
                            empty($event['products'])
                            || collect(
                                $event['products']
                            )->contains(
                                'id',
                                $product->id
                            )
                    )
                    ->values()
                    ->all();

                /*
                * 기존 전체 분석 추천 계산 유지
                */
                $recommendation =
                    $this->recommendationService->recommend(
                        $history,
                        $target,
                        $productEvents
                    );

                $recent = $history->take(-5);

                $recentCount = $recent->count();

                $flags = [];

                if (
                    $recent->where(
                        'waste',
                        '>',
                        0
                    )->count() >= 3
                ) {
                    $flags[] =
                        "최근 생산 기록 {$recentCount}건 중 3건 이상에서 폐기가 발생했습니다. 생산량을 확인해 주세요.";
                }

                if (
                    $recent->where(
                        'carryover_out',
                        '>',
                        0
                    )->count() >= 3
                ) {
                    $flags[] =
                        "최근 생산 기록 {$recentCount}건 중 3건 이상에서 재고를 다음 날로 이월했습니다.";
                }

                if (!empty($recommendation['warning'])) {
                    $flags[] = $recommendation['warning'];
                }

                return [
                    'product_id' => $product->id,

                    'product_name' => $product->name,

                    'recommendation' => $recommendation,

                    'flags' => $flags,
                ];
            }
        )->values();

        return response()->json([
            'items' => $items,

            'daily_flow' => $dailyFlow,

            'product_totals' => $productTotals,

            'range' => [
                'from' => $from,
                'to' => $to,
                'days' => $days,
            ],
        ]);
    }

    /**
     * 제품별 생산·이월·로스·폐기 기록 조회
     *
     * 기본 조회 기간은 기존과 동일한 최근 12개월입니다.
     *
     * 시작 날짜를 별도로 전달하면
     * 해당 날짜부터 전체 누적 기록을 조회할 수 있습니다.
     *
     * 기존 호출부와 계산 정책을 유지합니다.
     */
    private function recommendationHistoryForProduct(
        int $storeId,
        int $productId,
        Carbon $targetDate,
        ?Carbon $startDate = null
    ): Collection {
        $target = $targetDate->copy()->startOfDay();

        /*
        * 기본값: 최근 12개월
        *
        * 기존 추천 생산량 계산은
        * 이전과 동일하게 동작합니다.
        */
        $from = $startDate !== null
            ? $startDate->copy()->toDateString()
            : $target->copy()
                ->subYear()
                ->toDateString();

        $to = $target->copy()
            ->subDay()
            ->toDateString();

        /*
        * 날짜별 수량 집계
        *
        * 원본 기록 전체를 가져오지 않고
        * DB에서 날짜별 합계를 계산합니다.
        */
        $sumByDate = static function (
            $query,
            string $dateColumn
        ) use (
            $storeId,
            $productId,
            $from,
            $to
        ): array {
            return $query
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereBetween(
                    $dateColumn,
                    [$from, $to]
                )
                ->selectRaw(
                    "{$dateColumn} AS history_date, " .
                    "SUM(quantity) AS total_quantity"
                )
                ->groupBy($dateColumn)
                ->pluck(
                    'total_quantity',
                    'history_date'
                )
                ->map(
                    fn ($quantity) => (int) $quantity
                )
                ->all();
        };

        // 신규 생산량
        $production = $sumByDate(
            ProductionBatch::query(),
            'work_date'
        );

        // 최초 생산일 귀속 로스
        $attributedLoss = $sumByDate(
            ProductionLoss::query(),
            'attribution_date'
        );

        // 실제 업무일 로스
        $operationalLoss = $sumByDate(
            ProductionLoss::query(),
            'work_date'
        );

        // 최초 생산일 귀속 폐기
        $attributedWaste = $sumByDate(
            ProductionWaste::query(),
            'attribution_date'
        );

        // 실제 업무일 폐기
        $operationalWaste = $sumByDate(
            ProductionWaste::query(),
            'work_date'
        );

        // 이월 입고
        $carryoverIn = $sumByDate(
            ProductStockMovement::query()
                ->where(
                    'movement_type',
                    'carryover_in'
                ),
            'work_date'
        );

        // 이월 출고
        $carryoverOut = $sumByDate(
            ProductStockMovement::query()
                ->where(
                    'movement_type',
                    'carryover_out'
                ),
            'work_date'
        );

        /*
        * 생산 확인 기록
        *
        * 신규 생산 0개 확인도 유효한 기록입니다.
        */
        $confirmations = ProductionConfirmation::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereBetween(
                'work_date',
                [$from, $to]
            )
            ->where(
                'production_confirmed',
                true
            )
            ->pluck(
                'production_confirmed',
                'work_date'
            )
            ->all();

        /*
        * 날짜별 생산관리 마감 상태
        */
        $closures = ProductionDailyClosure::query()
            ->where('store_id', $storeId)
            ->whereBetween(
                'work_date',
                [$from, $to]
            )
            ->pluck(
                'status',
                'work_date'
            )
            ->all();

        /*
        * 제품별 추천에 필요한 자료만 조회합니다.
        *
        * 기존 판매량 자동 추정 계산은 유지합니다.
        */
        $context = $this->recommendationData(
            $storeId,
            $from,
            $to,
            $productId
        );

        /*
        * 행사 정보는 기간별 1회 조회합니다.
        */
        $events = StoreCalendarEvent::query()
            ->with('products:id,name')
            ->where('store_id', $storeId)
            ->whereDate(
                'start_date',
                '<=',
                $to
            )
            ->whereDate(
                'end_date',
                '>=',
                $from
            )
            ->orderBy('start_date')
            ->get()
            ->toArray();

        /*
        * 유효한 데이터가 존재하는 날짜 수집
        *
        * 생산 기록뿐 아니라 이월 재고를 실제로
        * 처리한 날짜도 후보에 포함합니다.
        *
        * 단순 날짜 누락을 생산 0개로 만들지 않습니다.
        */
        $dates = collect([
            ...array_keys($production),
            ...array_keys($confirmations),
            ...array_keys($carryoverIn),
            ...array_keys($carryoverOut),
            ...array_keys($operationalLoss),
            ...array_keys($operationalWaste),
        ])
            ->unique()
            ->sort()
            ->values();

        $history = collect();

        foreach ($dates as $date) {
            $date = (string) $date;

            $productionQuantity =
                (int) ($production[$date] ?? 0);

            $hasProductionRecord =
                array_key_exists(
                    $date,
                    $production
                );

            $productionConfirmed =
                (bool) ($confirmations[$date] ?? false);

            $closureStatus =
                $closures[$date] ?? 'in_progress';

            /*
            * 해당 날짜에 실제 이월 재고 처리가 있었는지 확인합니다.
            */
            $hasInventoryActivity =
                array_key_exists($date, $carryoverIn) ||
                array_key_exists($date, $carryoverOut) ||
                array_key_exists($date, $operationalLoss) ||
                array_key_exists($date, $operationalWaste);

            /*
            * 기존 생산 기록 및 생산 0개 확인 정책 유지
            */
            $hasProductionHistory =
                $hasProductionRecord ||
                $productionConfirmed;

            /*
            * 신규 생산 기록이 없는 날짜라도
            * 이월 재고 처리 기록이 있고 마감됐다면
            * 유효한 분석 대상으로 포함합니다.
            */
            $hasClosedInventoryHistory =
                !$hasProductionHistory &&
                $hasInventoryActivity &&
                $closureStatus === 'closed';

            if (
                !$hasProductionHistory &&
                !$hasClosedInventoryHistory
            ) {
                continue;
            }

            /*
            * 해당 날짜의 행사만 메모리에서 선택합니다.
            * 날짜별 추가 DB 조회는 발생하지 않습니다.
            */
            $dayEvents = array_values(
                array_filter(
                    $events,
                    static function (
                        array $event
                    ) use ($date): bool {
                        $start = substr(
                            (string) (
                                $event['start_date'] ?? ''
                            ),
                            0,
                            10
                        );

                        $end = substr(
                            (string) (
                                $event['end_date'] ?? ''
                            ),
                            0,
                            10
                        );

                        return
                            $start <= $date &&
                            $end >= $date;
                    }
                )
            );

            /*
            * 기존 추천 엔진과 호환되는 데이터 구조
            *
            * 원 생산일 귀속 로스·폐기와
            * 실제 처리일 로스·폐기는 구분합니다.
            */
            $row = [
                'id' => $productId,

                'production' =>
                    $productionQuantity,

                'carryover_in' =>
                    (int) ($carryoverIn[$date] ?? 0),

                'carryover_out' =>
                    (int) ($carryoverOut[$date] ?? 0),

                'loss' =>
                    (int) ($operationalLoss[$date] ?? 0),

                'operational_loss' =>
                    (int) ($operationalLoss[$date] ?? 0),

                'attributed_loss' =>
                    (int) ($attributedLoss[$date] ?? 0),

                'waste' =>
                    (int) ($operationalWaste[$date] ?? 0),

                'operational_waste' =>
                    (int) ($operationalWaste[$date] ?? 0),

                'attributed_waste' =>
                    (int) ($attributedWaste[$date] ?? 0),

                'production_confirmed' =>
                    $hasProductionHistory,

                'closure_status' =>
                    $closureStatus,
            ];

            /*
            * 기존 추천 데이터 결합 함수를 재사용합니다.
            *
            * 자동 추정 판매량:
            *
            * 생산 + 이월 입고
            * - 로스 - 폐기 - 이월 출고
            */
            $history->push(
                $this->recommendationHistoryRow(
                    $row,
                    $date,
                    $dayEvents,
                    $context
                )
            );
        }

        return $history->values();
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
        return $request->validate([
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

            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:100000',
            ],

            'recipe_deviated' => [
                'boolean',
            ],

            'recipe_deviation_note' => [
                'nullable',
                'required_if:recipe_deviated,true',
                'string',
                'max:1000',
            ],

            'note' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'recommendation_referenced' => [
                'nullable',
                'boolean',
            ],

            'recommendation_deviation_reason' => [
                'nullable',
                'string',
                'max:1000',
            ],

            /*
            * 추천 데이터가 없는 경우에도
            * 기존 생산 기록 등록은 정상적으로 허용합니다.
            *
            * 추천 데이터가 존재하는 경우에만
            * 배열 형식과 내부 수량을 검증합니다.
            */
            'displayed_recommendation' => [
                'nullable',
                'array',
            ],

            'displayed_recommendation.center' => [
                'required_with:displayed_recommendation',
                'integer',
                'min:0',
                'max:100000',
            ],

            'displayed_recommendation.min' => [
                'required_with:displayed_recommendation',
                'integer',
                'min:0',
                'max:100000',
            ],

            'displayed_recommendation.max' => [
                'required_with:displayed_recommendation',
                'integer',
                'min:0',
                'max:100000',
            ],

            'displayed_recommendation.confidence' => [
                'required_with:displayed_recommendation',
                'string',
                Rule::in([
                    'insufficient',
                    'low',
                    'normal',
                ]),
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

            'workers.*.process_type' => [
                'nullable',
                'string',
                Rule::in([
                    'all',
                    'mixing',
                    'shaping',
                    'proofing',
                    'oven',
                    'other',
                ]),
            ],
        ]);
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
     * 지정한 생산 기록의 로스만 교체합니다.
     *
     * 같은 날짜와 제품이라도 변경 대상으로 전달되지 않은
     * 생산 기록의 로스는 삭제하거나 수정하지 않습니다.
     */
    private function replaceLosses(
        int $storeId,
        int $productId,
        string $date,
        array $reasons,
        ?string $note,
        int $userId,
        array $affectedLotIds
    ): void {
        $affectedLotIds = collect($affectedLotIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        // 실제 해당 점포·제품의 생산 기록인지 확인합니다.
        if (!empty($affectedLotIds)) {
            $validCount = ProductStockLot::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereIn('id', $affectedLotIds)
                ->count();

            abort_unless(
                $validCount === count($affectedLotIds),
                422,
                '변경할 생산 기록이 현재 제품과 일치하지 않습니다.'
            );
        }

        // 전달받은 사유가 모두 변경 대상에 속하는지 검사합니다.
        foreach ($reasons as $reason) {
            $lotId = (int) ($reason['stock_lot_id'] ?? 0);

            abort_unless(
                in_array($lotId, $affectedLotIds, true),
                422,
                '로스 사유의 생산 기록이 변경 대상과 일치하지 않습니다.'
            );
        }

        // 실제 변경 대상의 기존 로스만 삭제합니다.
        $old = empty($affectedLotIds)
            ? collect()
            : ProductionLoss::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereDate('work_date', $date)
                ->whereIn('stock_lot_id', $affectedLotIds)
                ->get();

        foreach ($old as $record) {
            ProductionLossReason::query()
                ->where('production_loss_id', $record->id)
                ->delete();

            $record->delete();
        }

        // 선택한 생산 기록의 새 로스 수량과 사유를 저장합니다.
        foreach ($reasons as $reason) {
            $quantity = (int) ($reason['quantity'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $lot = $this->resolveReasonLot(
                $storeId,
                $productId,
                $date,
                $reason
            );

            abort_unless(
                $lot,
                422,
                '처리할 생산 기록을 확인할 수 없습니다.'
            );

            $attributionDate = $lot->origin_production_date
                ->toDateString();

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

            ProductionLossReason::create([
                'production_loss_id' => $loss->id,
                'reason_code' => $reason['reason_code'],
                'reason_text' => $reason['reason_text'] ?? null,
                'quantity' => $quantity,
            ]);
        }
    }
    
    /**
     * 변경 대상으로 지정한 생산 내역의 폐기만 교체합니다.
     *
     * 같은 제품·날짜에 여러 생산 내역이 있더라도
     * 변경하지 않은 생산 내역의 폐기 기록은 유지합니다.
     *
     * 실제 폐기일과 최초 생산일은 기존과 같이 구분합니다.
     */
    private function replaceWastes(
        int $storeId,
        int $productId,
        string $date,
        array $reasons,
        ?string $note,
        int $userId,
        array $affectedLotIds
    ): void {
        // 전달된 생산 내역 ID를 정수 목록으로 정리합니다.
        $affectedLotIds = collect($affectedLotIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        // 변경 대상이 실제 해당 점포와 제품의 재고인지 확인합니다.
        if (!empty($affectedLotIds)) {
            $validCount = ProductStockLot::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereIn('id', $affectedLotIds)
                ->count();

            abort_unless(
                $validCount === count($affectedLotIds),
                422,
                '변경할 생산 기록이 현재 제품과 일치하지 않습니다.'
            );
        }

        // 새 폐기 사유는 반드시 변경 대상 생산 내역에 속해야 합니다.
        foreach ($reasons as $reason) {
            $lotId = (int) ($reason['stock_lot_id'] ?? 0);

            abort_unless(
                in_array($lotId, $affectedLotIds, true),
                422,
                '폐기 사유의 생산 기록이 변경 대상과 일치하지 않습니다.'
            );
        }

        // 변경 대상에 해당하는 기존 폐기 기록만 조회합니다.
        $old = empty($affectedLotIds)
            ? collect()
            : ProductionWaste::query()
                ->where('store_id', $storeId)
                ->where('product_id', $productId)
                ->whereDate('work_date', $date)
                ->whereIn('stock_lot_id', $affectedLotIds)
                ->get();

        // 선택한 생산 내역의 기존 폐기 기록과 사유만 제거합니다.
        foreach ($old as $record) {
            ProductionWasteReason::query()
                ->where('production_waste_id', $record->id)
                ->delete();

            $record->delete();
        }

        // 변경 대상 생산 내역에 새 폐기 수량과 사유를 저장합니다.
        foreach ($reasons as $reason) {
            $quantity = (int) ($reason['quantity'] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            // 생산 내역 ID 및 재고 구분을 실제 재고와 대조합니다.
            $lot = $this->resolveReasonLot(
                $storeId,
                $productId,
                $date,
                $reason
            );

            abort_unless(
                $lot,
                422,
                '폐기할 재고의 생산 기록을 확인할 수 없습니다.'
            );

            // 폐기율 계산에 사용할 최초 생산일을 유지합니다.
            $attributionDate = $lot->origin_production_date->toDateString();

            // 실제 처리일과 최초 생산일을 구분해 폐기 기록을 생성합니다.
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

            // 선택한 생산 내역에 연결된 폐기 사유를 기록합니다.
            ProductionWasteReason::create([
                'production_waste_id' => $waste->id,
                'reason_code' => $reason['reason_code'],
                'reason_text' => $reason['reason_text'] ?? null,
                'quantity' => $quantity,
            ]);
        }
    }

    /**
     * 선택한 업무에서 사용할 수 있는 수량을 계산합니다.
     *
     * 생산·이월 재고에서 다른 업무에 사용한 수량을 제외합니다.
     */
    private function availableQuantityForDisposition(array $row, string $type): int
    {
        $total =
            (int) $row['production']
            + (int) $row['carryover_in'];

        $allocatedByOtherTypes = match ($type) {
            'carryover' =>
                (int) $row['operational_loss']
                + (int) $row['operational_waste'],

            'loss' =>
                (int) $row['operational_waste']
                + (int) $row['carryover_out'],

            'waste' =>
                (int) $row['operational_loss']
                + (int) $row['carryover_out'],

            default => throw new \InvalidArgumentException(
                '지원하지 않는 재고 처리 유형입니다.'
            ),
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

        // 선택한 생산 기록에 연결된 기존 이월만 조회합니다.
        // 같은 제품의 다른 생산 기록은 변경 대상에서 제외합니다.
        $existing = ProductStockMovement::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->whereDate('work_date', $date)
            ->where('movement_type', 'carryover_out')
            ->when($stockLotId !== null, fn ($query) =>
                $query->where('stock_lot_id', $stockLotId)
            )
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

        // 생산 기록을 선택하지 않은 상태에서
        // 기존 이월 기록을 실수로 초기화하지 못하도록 보호합니다.
        if ($stockLotId === null) {
            abort_if(
                ProductStockMovement::query()
                    ->where('store_id', $storeId)
                    ->where('product_id', $productId)
                    ->whereDate('work_date', $date)
                    ->where('movement_type', 'carryover_out')
                    ->exists(),
                422,
                '기존 이월 기록이 있습니다. 수정할 생산 기록을 선택해 주세요.'
            );
        }

        // 기존 이월 기록이 없고 0개를 확인하는 경우입니다.
        // 변경할 생산 내역이 없으므로 재고 기록은 수정하지 않습니다.
        if ($quantity === 0 && $stockLotId === null) {
            return;
        }

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

        /*
         * 선택한 생산 기록의 이월만 교체합니다.
         *
         * 동일 제품을 여러 번 생산한 경우에도
         * 다른 생산 기록에 연결된 이월은 유지합니다.
         */
        abort_if(
            $stockLotId === null,
            422,
            '이월할 생산 기록을 선택해 주세요.'
        );

        // 선택한 생산 기록의 현재 날짜 이월만 제거합니다.
        ProductStockMovement::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('stock_lot_id', $stockLotId)
            ->whereDate('work_date', $date)
            ->where('movement_type', 'carryover_out')
            ->delete();

        // 선택한 생산 기록의 다음 날짜 연결만 제거합니다.
        ProductStockMovement::query()
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('stock_lot_id', $stockLotId)
            ->whereDate('work_date', $nextDate)
            ->where('movement_type', 'carryover_in')
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

            // 후속 날짜의 로스·폐기를 합산합니다.
            $usedByDate = [];

            foreach ([
                ProductionLoss::class,
                ProductionWaste::class,
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
     * @param int $used 다음 날 로스·폐기·재이월에 사용한 합계
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