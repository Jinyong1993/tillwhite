<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductionRecord;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AccessService;
use App\Services\AuditService;
use App\Services\Scopes\ProductionRecordScope;
use Illuminate\Http\Request;

class ProductionRecordController extends Controller
{
    /**
     * 실제 근무로 인정하는 근무 코드
     *
     * A, B, C는 실제 출근하는 근무 코드입니다.
     *
     * D/O 등 그 외 근무 코드는
     * 생산·폐기·로스 입력 대상 근무자로 인정하지 않습니다.
     */
    private const WORKING_CODES = [
        'A',
        'B',
        'C',
    ];

    /**
     * 생산 기록 범위, 권한 검사, 감사 로그 서비스를 주입받습니다.
     *
     * ProductionRecordScope:
     * - 현재 사용자가 조회할 수 있는 생산 기록 범위를 결정합니다.
     *
     * AccessService:
     * - Permission 및 점포 접근 권한을 검사합니다.
     *
     * AuditService:
     * - 생산 기록의 등록, 수정, 삭제 이력을 기록합니다.
     */
    public function __construct(
        private ProductionRecordScope $scope,
        private AccessService $access,
        private AuditService $audit
    ) {
    }

    /**
     * 생산·폐기·로스 기록 목록 조회
     *
     * production.view 권한이 필요합니다.
     *
     * 조회 범위:
     * - staff: 자신의 점포 전체
     * - kitchen_head: 자신의 점포 전체
     * - hall_manager: 자신의 점포 전체
     * - head_office_staff: 전체 점포
     * - head_office_manager: 전체 점포
     * - super_admin: 전체 점포
     *
     * 조회 자체는 해당 날짜의 근무 여부와 관계없습니다.
     *
     * 따라서 점포 직원이 D/O인 날에도
     * 자신의 점포 생산·폐기·로스 기록은 조회할 수 있습니다.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // 생산 기록 조회 권한 확인
        $this->access->requirePermission(
            $user,
            'production.view'
        );

        /**
         * 현재 사용자가 접근할 수 있는 생산 기록 조회
         *
         * 점포, 제품, 작업자 정보도 함께 조회합니다.
         */
        $query = $this->scope
            ->apply($user)
            ->with([
                'store:id,name',
                'product:id,name',
                'worker:id,name',
            ])
            ->orderByDesc('work_date')
            ->orderByDesc('id');

        // 날짜가 전달된 경우 해당 날짜의 기록만 조회
        if ($request->filled('date')) {
            $query->whereDate(
                'work_date',
                $request->date
            );
        }

        // 화면 성능을 고려하여 최대 최근 200건 반환
        return response()->json([
            'records' => $query
                ->limit(200)
                ->get(),
        ]);
    }

    /**
     * 오늘 생산·폐기·로스 현황 조회
     *
     * 메인 화면의 '오늘 현황'에서 사용하는 API입니다.
     *
     * ProductionRecordScope를 그대로 적용하므로
     * 점포 직원은 자신의 점포 현황만 조회합니다.
     *
     * 본사 직원 및 관리자는 전체 점포 현황을 조회합니다.
     *
     * D/O 여부는 조회 범위에 영향을 주지 않습니다.
     */
    public function summary(Request $request)
    {
        $user = $request->user();

        // 생산 기록 조회 권한 확인
        $this->access->requirePermission(
            $user,
            'production.view'
        );

        /**
         * 현재 사용자가 조회할 수 있는 범위에서
         * 오늘 생산·폐기·로스 수량을 합산합니다.
         */
        $summary = $this->scope
            ->apply($user)
            ->whereDate(
                'work_date',
                today()
            )
            ->selectRaw('
                COALESCE(SUM(production_quantity), 0) as production_quantity,
                COALESCE(SUM(waste_quantity), 0) as waste_quantity,
                COALESCE(SUM(loss_quantity), 0) as loss_quantity
            ')
            ->first();

        return response()->json([
            'summary' => [
                'production_quantity' => (int) $summary->production_quantity,
                'waste_quantity' => (int) $summary->waste_quantity,
                'loss_quantity' => (int) $summary->loss_quantity,
            ],
        ]);
    }

    /**
     * 생산 입력 화면에 필요한 선택 항목 조회
     *
     * production.view 권한이 필요합니다.
     *
     * 제품:
     * - 점포 직원은 자신의 점포 제품만 조회
     * - 본사 및 super_admin은 접근 가능한 전체 제품 조회
     *
     * 작업자:
     * - 선택한 날짜에 A/B/C 근무가 존재하는 직원만 조회
     * - 점포 직원은 자신의 점포 근무자만 조회
     * - 부서는 구분하지 않음
     *
     * 예:
     * 무역점 주방 직원이 생산 입력 화면을 열어도
     * 해당 날짜에 근무하는 무역점 홀 직원도
     * 작업자로 선택할 수 있습니다.
     *
     * D/O 직원은 작업자 목록에 표시하지 않습니다.
     *
     * 본사 직원 및 본사 관리자는 조회 전용이므로
     * 작업자 목록을 반환하지 않습니다.
     */
    public function options(Request $request)
    {
        $user = $request->user();

        // 생산 기록 조회 권한 확인
        $this->access->requirePermission(
            $user,
            'production.view'
        );

        /**
         * 작업자 목록을 조회할 날짜
         *
         * 날짜가 전달되지 않은 경우
         * 오늘 날짜를 사용합니다.
         */
        $validated = $request->validate([
            'date' => [
                'nullable',
                'date',
            ],
        ]);

        $workDate = $validated['date']
            ?? today()->toDateString();

        /**
         * 현재 사용자가 접근할 수 있는 활성 제품 조회
         *
         * AccessService::scopeStore()를 사용하므로
         * 일반 점포 직원은 자신의 점포 제품만 조회합니다.
         */
        $products = $this->access
            ->scopeStore(
                Product::query(),
                $user
            )
            ->where(
                'is_active',
                true
            )
            ->orderBy('sort_order')
            ->get([
                'id',
                'store_id',
                'name',
                'production_department',
            ]);

        /**
         * 본사 직원 및 본사 관리자는
         * 생산 데이터를 조회만 할 수 있습니다.
         *
         * 따라서 생산 입력에 사용하는
         * 작업자 목록은 반환하지 않습니다.
         */
        if ($this->isHeadOfficeUser($user)) {
            return response()->json([
                'products' => $products,
                'workers' => [],
            ]);
        }

        /**
         * 해당 날짜에 실제 근무하는 직원 조회
         *
         * 조건:
         * - 활성 계정
         * - 재직 상태
         * - 해당 날짜 WorkSchedule 존재
         * - WorkCode가 A/B/C
         */
        $workers = User::query()
            ->where(
                'is_active',
                true
            )
            ->where(
                'employment_status',
                'active'
            )
            ->whereHas(
                'workSchedules',
                function ($scheduleQuery) use (
                    $workDate,
                    $user
                ) {
                    $scheduleQuery
                        ->whereDate(
                            'work_date',
                            $workDate
                        )
                        ->whereHas(
                            'workCode',
                            function ($workCodeQuery) {
                                $workCodeQuery->whereIn(
                                    'code',
                                    self::WORKING_CODES
                                );
                            }
                        );

                    /**
                     * 일반 점포 계정은
                     * 자신의 점포에서 실제 근무하는 직원만 조회합니다.
                     *
                     * kitchen / hall 부서는 구분하지 않습니다.
                     */
                    if (
                        $user->role?->code
                        !== 'super_admin'
                    ) {
                        $scheduleQuery->where(
                            'store_id',
                            $user->store_id
                        );
                    }
                }
            );

        /**
         * 일반 점포 계정은
         * 현재 소속도 자신의 점포인 직원만 조회합니다.
         *
         * 다른 점포 직원이 작업자 목록에
         * 섞이는 것을 방지합니다.
         */
        if (
            $user->role?->code
            !== 'super_admin'
        ) {
            $workers->where(
                'store_id',
                $user->store_id
            );
        }

        return response()->json([
            'products' => $products,

            'workers' => $workers
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'store_id',
                    'department',
                ]),
        ]);
    }

    /**
     * 생산·폐기·로스 기록 등록
     *
     * staff:
     * - 자신의 점포만 등록 가능
     * - 해당 날짜에 본인이 A/B/C 근무여야 함
     *
     * kitchen_head:
     * - 자신의 점포 전체 등록 가능
     * - 본인이 해당 날짜에 D/O여도 가능
     *
     * hall_manager:
     * - 자신의 점포 전체 등록 가능
     * - 본인이 해당 날짜에 D/O여도 가능
     *
     * head_office_staff:
     * - 조회만 가능
     *
     * head_office_manager:
     * - 조회만 가능
     *
     * super_admin:
     * - 전체 점포 등록 가능
     *
     * 중요:
     * 실제 기록에 지정되는 작업자는
     * 반드시 해당 날짜와 해당 점포에서
     * A/B/C 근무 중인 직원이어야 합니다.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // 생산 기록 등록 Permission 확인
        $this->access->requirePermission(
            $user,
            'production.create'
        );

        // 공통 입력값 검증
        $validated = $this->validated($request);

        // 선택된 작업자 조회
        $worker = User::findOrFail(
            $validated['worker_id']
        );

        // 선택된 제품 조회
        $product = Product::findOrFail(
            $validated['product_id']
        );

        /**
         * 현재 로그인 사용자가
         * 해당 점포의 생산 기록을 관리할 수 있는지 확인합니다.
         *
         * 일반 직원이면 본인의 해당 날짜
         * A/B/C 근무 여부까지 확인합니다.
         */
        $this->assertCanManageProduction(
            $user,
            $worker->store_id,
            $validated['work_date']
        );

        /**
         * 선택된 작업자의 현재 상태 확인
         *
         * 퇴사자, 휴직자, 비활성 계정을
         * 생산 작업자로 등록할 수 없습니다.
         */
        abort_unless(
            $worker->is_active
            && $worker->employment_status === 'active',
            422,
            '현재 근무 중인 직원만 작업자로 선택할 수 있습니다.'
        );

        /**
         * 선택된 작업자가 해당 날짜와 점포에서
         * 실제 A/B/C 근무자인지 확인합니다.
         *
         * 프론트 화면에서 작업자를 숨기는 것만으로는
         * 보안 검증이 충분하지 않기 때문에
         * 서버에서도 다시 검사합니다.
         */
        abort_unless(
            $this->isWorkingEmployee(
                $worker,
                $worker->store_id,
                $validated['work_date']
            ),
            422,
            '선택한 직원은 해당 날짜의 근무자가 아닙니다.'
        );

        /**
         * 제품과 작업자의 점포가 같은지 확인합니다.
         *
         * 예:
         * 무역점 작업자에게
         * 더현대서울점 제품을 등록할 수 없습니다.
         */
        abort_unless(
            $product->store_id
            === $worker->store_id,
            422,
            '제품과 작업자의 점포가 일치하지 않습니다.'
        );

        /**
         * 비활성 제품에는
         * 새로운 생산 기록을 등록할 수 없습니다.
         */
        abort_unless(
            $product->is_active,
            422,
            '현재 사용하지 않는 제품입니다.'
        );

        /**
         * 하나의 제품에 대한 생산 기록 생성
         *
         * 다른 제품의 기존 생산 기록은
         * 수정하거나 삭제하지 않습니다.
         */
        $record = ProductionRecord::create([
            ...$validated,
            'store_id' => $worker->store_id,
            'department' => $worker->department,
            'created_by' => $user->id,
        ]);

        // 생산 기록 등록 감사 로그 기록
        $this->audit->log(
            $user,
            'production',
            'create',
            ProductionRecord::class,
            $record->id,
            null,
            $record->toArray(),
            '생산·폐기·로스 기록 등록'
        );

        return response()->json([
            'message' => '기록이 등록되었습니다.',
            'record' => $record,
        ], 201);
    }

    /**
     * 생산·폐기·로스 기록 수정
     *
     * staff:
     * - 자신의 점포 기록만 수정 가능
     * - 해당 기록의 작업일에 본인이 A/B/C 근무여야 함
     *
     * kitchen_head / hall_manager:
     * - 자신의 점포 기록 수정 가능
     * - 본인이 해당 날짜에 D/O여도 가능
     *
     * 본사 직원 및 관리자:
     * - 수정 불가
     *
     * super_admin:
     * - 전체 수정 가능
     *
     * 수정 대상 ProductionRecord 하나만 변경하므로
     * 다른 제품의 기록에는 영향을 주지 않습니다.
     */
    public function update(
        Request $request,
        ProductionRecord $productionRecord
    ) {
        $user = $request->user();

        // 생산 기록 수정 Permission 확인
        $this->access->requirePermission(
            $user,
            'production.update'
        );

        /**
         * 공통 입력값 검증
         *
         * 현재 화면 구조상 수정 요청에도
         * product_id와 worker_id가 전달됩니다.
         *
         * 실제 수정 시에는 아래에서 제거하여
         * 기존 제품과 작업자를 유지합니다.
         */
        $validated = $this->validated($request);

        /**
         * 생산 기록의 작업일 자체는 수정 가능하므로
         * 수정 요청으로 전달된 work_date를 기준으로
         * 관리 가능 여부를 검사합니다.
         */
        $this->assertCanManageProduction(
            $user,
            $productionRecord->store_id,
            $validated['work_date']
        );

        /**
         * 기존 생산 기록에 저장된 작업자 조회
         */
        $worker = User::findOrFail(
            $productionRecord->worker_id
        );

        /**
         * 작업일을 변경할 수도 있으므로
         * 기존 작업자가 변경하려는 날짜에도
         * 해당 점포에서 A/B/C 근무인지 확인합니다.
         */
        abort_unless(
            $this->isWorkingEmployee(
                $worker,
                $productionRecord->store_id,
                $validated['work_date']
            ),
            422,
            '기록된 직원은 해당 날짜의 근무자가 아닙니다.'
        );

        // 변경 전 데이터를 감사 로그용으로 저장
        $oldData = $productionRecord->toArray();

        /**
         * 수정 시 제품과 작업자는 기존 값을 유지합니다.
         *
         * 따라서 바질식빵 기록을 수정하더라도
         * 블루베리식빵 등 다른 제품 기록에는
         * 아무런 영향을 주지 않습니다.
         */
        unset(
            $validated['worker_id'],
            $validated['product_id']
        );

        // 선택된 생산 기록 하나만 수정
        $productionRecord->update([
            ...$validated,
            'updated_by' => $user->id,
        ]);

        // 생산 기록 수정 감사 로그 기록
        $this->audit->log(
            $user,
            'production',
            'update',
            ProductionRecord::class,
            $productionRecord->id,
            $oldData,
            $productionRecord
                ->fresh()
                ->toArray(),
            '생산·폐기·로스 기록 수정'
        );

        return response()->json([
            'message' => '기록이 수정되었습니다.',
        ]);
    }

    /**
     * 생산·폐기·로스 기록 삭제
     *
     * staff:
     * - 자신의 점포 기록만 삭제 가능
     * - 해당 기록의 작업일에 본인이 A/B/C 근무여야 함
     *
     * kitchen_head / hall_manager:
     * - 자신의 점포 기록 삭제 가능
     * - 본인이 해당 날짜에 D/O여도 가능
     *
     * 본사 직원 및 관리자:
     * - 삭제 불가
     *
     * super_admin:
     * - 전체 삭제 가능
     *
     * 삭제 대상 ProductionRecord 하나만
     * Soft Delete 처리합니다.
     */
    public function destroy(
        Request $request,
        ProductionRecord $productionRecord
    ) {
        $user = $request->user();

        // 생산 기록 삭제 Permission 확인
        $this->access->requirePermission(
            $user,
            'production.delete'
        );

        /**
         * 현재 로그인 사용자가
         * 해당 기록을 삭제할 수 있는지 확인합니다.
         *
         * 일반 직원은 기록의 work_date에
         * 실제 A/B/C 근무자였어야 합니다.
         */
        $this->assertCanManageProduction(
            $user,
            $productionRecord->store_id,
            $productionRecord->work_date
        );

        // 삭제 전 데이터를 감사 로그용으로 저장
        $oldData = $productionRecord->toArray();

        /**
         * 현재 선택된 생산 기록 하나만 삭제합니다.
         *
         * ProductionRecord의 Soft Delete를 사용하므로
         * 실제 데이터는 즉시 물리 삭제하지 않습니다.
         */
        $productionRecord->delete();

        // 생산 기록 삭제 감사 로그 기록
        $this->audit->log(
            $user,
            'production',
            'delete',
            ProductionRecord::class,
            $productionRecord->id,
            $oldData,
            null,
            '생산·폐기·로스 기록 삭제'
        );

        return response()->json([
            'message' => '기록이 삭제되었습니다.',
        ]);
    }

    /**
     * 생산·폐기·로스 공통 입력값 검증
     *
     * 등록과 수정에서 공통으로 사용합니다.
     *
     * 수량 규칙:
     * - 생산량은 0 이상의 정수
     * - 폐기량은 0 이상의 정수
     * - 로스량은 0 이상의 정수
     * - 세 수량이 모두 0이어도 허용
     *
     * 사유 규칙:
     * - 폐기량이 1 이상이면 폐기 사유 필수
     * - 로스량이 1 이상이면 로스 사유 필수
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'work_date' => [
                'required',
                'date',
            ],
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],
            'worker_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'production_quantity' => [
                'required',
                'integer',
                'min:0',
            ],
            'waste_quantity' => [
                'required',
                'integer',
                'min:0',
            ],
            'loss_quantity' => [
                'required',
                'integer',
                'min:0',
            ],
            'waste_reason' => [
                'nullable',
                'string',
                'max:255',
            ],
            'waste_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'loss_reason' => [
                'nullable',
                'string',
                'max:255',
            ],
            'loss_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        /**
         * 폐기량이 1 이상이면
         * 폐기 사유를 반드시 입력합니다.
         */
        if (
            $validated['waste_quantity'] > 0
            && empty($validated['waste_reason'])
        ) {
            abort(
                422,
                '폐기 사유를 입력해주세요.'
            );
        }

        /**
         * 로스량이 1 이상이면
         * 로스 사유를 반드시 입력합니다.
         */
        if (
            $validated['loss_quantity'] > 0
            && empty($validated['loss_reason'])
        ) {
            abort(
                422,
                '로스 사유를 입력해주세요.'
            );
        }

        return $validated;
    }

    /**
     * 사용자가 해당 점포의 생산 기록을
     * 등록·수정·삭제할 수 있는지 확인합니다.
     *
     * super_admin:
     * - 모든 점포 관리 가능
     *
     * kitchen_head:
     * - 자신의 점포 전체 관리 가능
     * - 본인이 D/O여도 가능
     *
     * hall_manager:
     * - 자신의 점포 전체 관리 가능
     * - 본인이 D/O여도 가능
     *
     * staff:
     * - 자신의 점포만 관리 가능
     * - 해당 날짜에 본인이 A/B/C 근무여야 함
     *
     * head_office_staff:
     * - 조회만 가능
     *
     * head_office_manager:
     * - 조회만 가능
     *
     * 그 외 Role:
     * - 관리 불가
     */
    private function assertCanManageProduction(
        User $user,
        ?int $storeId,
        mixed $workDate
    ): void {
        /**
         * super_admin은
         * 모든 점포의 생산 기록을 관리할 수 있습니다.
         */
        if (
            $user->role?->code
            === 'super_admin'
        ) {
            return;
        }

        /**
         * 본사 직원 및 본사 관리자는
         * 생산 데이터를 조회만 할 수 있습니다.
         */
        if ($this->isHeadOfficeUser($user)) {
            abort(
                403,
                '본사 계정은 생산 기록을 조회만 할 수 있습니다.'
            );
        }

        /**
         * 점포 계정은 반드시 소속 점포가 존재해야 하며,
         * 자신의 점포만 관리할 수 있습니다.
         */
        abort_unless(
            $user->store_id !== null
            && $user->store_id === $storeId,
            403,
            '다른 점포의 생산 기록을 관리할 수 없습니다.'
        );

        /**
         * 헤드 셰프와 홀 매니저는
         * 본인의 해당 날짜 근무 여부와 관계없이
         * 자신의 점포 생산 기록을 관리할 수 있습니다.
         */
        if (
            in_array(
                $user->role?->code,
                [
                    'kitchen_head',
                    'hall_manager',
                ],
                true
            )
        ) {
            return;
        }

        /**
         * 일반 직원은
         * 해당 날짜에 자신의 점포에서
         * 실제 A/B/C 근무가 배정되어 있어야 합니다.
         *
         * D/O인 경우에는
         * 조회는 가능하지만 등록·수정·삭제는 불가능합니다.
         */
        if (
            $user->role?->code
            === 'staff'
        ) {
            abort_unless(
                $this->isWorkingEmployee(
                    $user,
                    $user->store_id,
                    $workDate
                ),
                403,
                '해당 날짜에 근무하는 직원만 생산 기록을 관리할 수 있습니다.'
            );

            return;
        }

        /**
         * 명시적으로 허용하지 않은 Role은
         * 생산 기록을 관리할 수 없습니다.
         */
        abort(
            403,
            '생산 기록을 관리할 권한이 없습니다.'
        );
    }

    /**
     * 직원이 특정 날짜와 점포에서
     * 실제 근무하는 직원인지 확인합니다.
     *
     * 다음 조건을 모두 만족해야 합니다.
     *
     * 1. 해당 직원의 WorkSchedule이 존재
     * 2. WorkSchedule의 날짜가 대상 날짜와 일치
     * 3. WorkSchedule의 점포가 대상 점포와 일치
     * 4. WorkCode가 A, B, C 중 하나
     *
     * D/O 등 다른 근무 코드는
     * 실제 근무로 인정하지 않습니다.
     */
    private function isWorkingEmployee(
        User $user,
        ?int $storeId,
        mixed $workDate
    ): bool {
        if ($storeId === null) {
            return false;
        }

        return WorkSchedule::query()
            ->where(
                'user_id',
                $user->id
            )
            ->where(
                'store_id',
                $storeId
            )
            ->whereDate(
                'work_date',
                $workDate
            )
            ->whereHas(
                'workCode',
                function ($workCodeQuery) {
                    $workCodeQuery->whereIn(
                        'code',
                        self::WORKING_CODES
                    );
                }
            )
            ->exists();
    }

    /**
     * 본사 직원 또는 본사 관리자인지 확인합니다.
     *
     * store_id가 NULL이라는 사실만으로
     * 본사 계정으로 판단하지 않습니다.
     *
     * department가 head_office이면서
     * 실제 본사 Role인 경우에만 true를 반환합니다.
     */
    private function isHeadOfficeUser(
        User $user
    ): bool {
        return $user->isHeadOffice()
            && in_array(
                $user->role?->code,
                [
                    'head_office_staff',
                    'head_office_manager',
                ],
                true
            );
    }
}