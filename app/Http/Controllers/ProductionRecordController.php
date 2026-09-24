<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductionRecord;
use App\Models\User;
use App\Services\AccessService;
use App\Services\AuditService;
use App\Services\Scopes\ProductionRecordScope;
use Illuminate\Http\Request;

class ProductionRecordController extends Controller
{
    /**
     * 생산 기록 범위, 권한 검사, 감사 로그 서비스를 주입받습니다.
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
     * production.view 권한이 필요하며,
     * ProductionRecordScope를 통해 현재 사용자가
     * 조회할 수 있는 범위의 기록만 반환합니다.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // 생산 기록 조회 권한 확인
        $this->access->requirePermission($user, 'production.view');

        // 현재 사용자의 접근 범위에 해당하는 생산 기록 조회
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
            $query->whereDate('work_date', $request->date);
        }

        // 최대 최근 200건 반환
        return response()->json([
            'records' => $query->limit(200)->get(),
        ]);
    }

    /**
     * 생산 입력 화면에 필요한 선택 항목 조회
     *
     * 제품 목록과 작업자 목록을 반환합니다.
     */
    public function options(Request $request)
    {
        $user = $request->user();

        // 생산 기록 조회 권한 확인
        $this->access->requirePermission($user, 'production.view');

        // 현재 사용자가 접근할 수 있는 활성 제품 조회
        $products = $this->access
            ->scopeStore(Product::query(), $user)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get([
                'id',
                'store_id',
                'name',
                'production_department',
            ]);

        // 현재 근무 중이며 활성화된 작업자 조회
        $workers = User::query()
            ->where('is_active', true)
            ->where('employment_status', 'active');

        /**
         * 점포 직원은 자신의 점포 및 부서 작업자만 조회합니다.
         *
         * 본사 직원과 super_admin은 이 제한을 적용하지 않습니다.
         */
        if (! $user->isHeadOffice() && $user->role?->code !== 'super_admin') {
            $workers
                ->where('store_id', $user->store_id)
                ->where('department', $user->department);
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
     * production.create 권한이 필요합니다.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // 생산 기록 등록 권한 확인
        $this->access->requirePermission($user, 'production.create');

        // 공통 입력값 검증
        $validated = $this->validated($request);

        // 선택된 작업자 조회
        $worker = User::findOrFail($validated['worker_id']);

        // 선택된 제품 조회
        $product = Product::findOrFail($validated['product_id']);

        // 현재 사용자가 해당 작업자의 점포 및 부서에 접근 가능한지 확인
        $this->access->assertStoreDepartment(
            $user,
            $worker->store_id,
            $worker->department
        );

        // 제품과 작업자의 소속 점포가 동일한지 확인
        abort_unless(
            $product->store_id === $worker->store_id,
            422,
            '제품과 작업자의 점포가 일치하지 않습니다.'
        );

        // 생산·폐기·로스 기록 생성
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
     * production.update 권한이 필요합니다.
     */
    public function update(
        Request $request,
        ProductionRecord $productionRecord
    ) {
        $user = $request->user();

        // 생산 기록 수정 권한 확인
        $this->access->requirePermission($user, 'production.update');

        // 해당 기록의 점포 및 부서에 대한 접근 권한 확인
        $this->access->assertStoreDepartment(
            $user,
            $productionRecord->store_id,
            $productionRecord->department
        );

        // 변경 전 데이터를 감사 로그용으로 저장
        $oldData = $productionRecord->toArray();

        // 공통 입력값 검증
        $validated = $this->validated($request);

        /**
         * 수정 시에는 기존 기록의 작업자와 제품을 유지합니다.
         *
         * 공통 검증 메서드에서는 worker_id와 product_id를 검증하지만,
         * 실제 update에는 포함하지 않습니다.
         */
        unset(
            $validated['worker_id'],
            $validated['product_id']
        );

        // 생산·폐기·로스 기록 수정
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
            $productionRecord->fresh()->toArray(),
            '생산·폐기·로스 기록 수정'
        );

        return response()->json([
            'message' => '기록이 수정되었습니다.',
        ]);
    }

    /**
     * 생산·폐기·로스 기록 삭제
     *
     * production.delete 권한이 필요합니다.
     * ProductionRecord 모델의 Soft Delete를 사용합니다.
     */
    public function destroy(
        Request $request,
        ProductionRecord $productionRecord
    ) {
        $user = $request->user();

        // 생산 기록 삭제 권한 확인
        $this->access->requirePermission($user, 'production.delete');

        // 해당 기록의 점포 및 부서에 대한 접근 권한 확인
        $this->access->assertStoreDepartment(
            $user,
            $productionRecord->store_id,
            $productionRecord->department
        );

        // 삭제 전 데이터를 감사 로그용으로 저장
        $oldData = $productionRecord->toArray();

        // Soft Delete 처리
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
     * 등록과 수정에서 동일하게 사용하는 검증 규칙입니다.
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

        // 생산량, 폐기량, 로스량이 모두 0인 기록은 허용하지 않음
        if (
            (
                $validated['production_quantity']
                + $validated['waste_quantity']
                + $validated['loss_quantity']
            ) === 0
        ) {
            abort(
                422,
                '생산량, 폐기량, 로스량 중 하나 이상 입력해주세요.'
            );
        }

        // 폐기량이 존재하면 폐기 사유 필수
        if (
            $validated['waste_quantity'] > 0
            && empty($validated['waste_reason'])
        ) {
            abort(
                422,
                '폐기량이 있으면 폐기 사유를 입력해주세요.'
            );
        }

        // 로스량이 존재하면 로스 사유 필수
        if (
            $validated['loss_quantity'] > 0
            && empty($validated['loss_reason'])
        ) {
            abort(
                422,
                '로스량이 있으면 로스 사유를 입력해주세요.'
            );
        }

        return $validated;
    }
}