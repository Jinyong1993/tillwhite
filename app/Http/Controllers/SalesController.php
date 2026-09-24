<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Services\AccessService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    /**
     * 권한 검사와 감사 로그 서비스를 주입받습니다.
     */
    public function __construct(
        private AccessService $access,
        private AuditService $audit
    ) {
    }

    /**
     * 권한 범위 내 매출 조회
     *
     * sales.view 권한이 필요하며,
     * 현재 사용자가 조회할 수 있는 점포의 매출만 반환합니다.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // 매출 조회 권한 확인
        $this->access->requirePermission($user, 'sales.view');

        // 현재 사용자가 접근할 수 있는 점포의 매출 조회
        $query = $this->access
            ->scopeStore(Sale::query(), $user)
            ->with([
                'store:id,name',
                'items.product:id,name',
                'refunds',
            ])
            ->orderByDesc('sales_date')
            ->orderByDesc('id');

        // 최대 최근 100건 반환
        return response()->json([
            'sales' => $query->limit(100)->get(),
        ]);
    }

    /**
     * 일 매출 등록
     *
     * sales.manage 권한이 필요합니다.
     * 하나의 매출에 여러 제품을 함께 등록할 수 있습니다.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // 매출 관리 권한 확인
        $this->access->requirePermission($user, 'sales.manage');

        // 매출 입력값 검증
        $validated = $request->validate([
            'store_id' => [
                'required',
                'exists:stores,id',
            ],
            'sales_date' => [
                'required',
                'date',
            ],
            'note' => [
                'nullable',
                'string',
            ],
            'items' => [
                'required',
                'array',
                'min:1',
            ],
            'items.*.product_id' => [
                'required',
                'exists:products,id',
            ],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
            'items.*.actual_unit_price' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);

        // 현재 사용자가 해당 점포의 매출을 관리할 수 있는지 확인
        $this->access->assertStoreDepartment(
            $user,
            (int) $validated['store_id']
        );

        /**
         * 매출과 매출 상세 항목을 하나의 트랜잭션으로 저장합니다.
         *
         * 처리 도중 오류가 발생하면
         * 매출 및 상세 항목 저장을 모두 취소합니다.
         */
        $sale = DB::transaction(function () use ($validated, $user) {
            // 매출 기본 정보 생성
            $sale = Sale::create([
                'store_id' => $validated['store_id'],
                'sales_date' => $validated['sales_date'],
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_by' => $user->id,
                'note' => $validated['note'] ?? null,
                'created_by' => $user->id,
            ]);

            // 매출에 포함된 제품별 상세 항목 생성
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                // 다른 점포의 제품은 현재 매출에 등록할 수 없음
                abort_unless(
                    $product->store_id === (int) $validated['store_id'],
                    422,
                    '다른 점포 제품은 등록할 수 없습니다.'
                );

                // 판매 단가와 수량
                $unitPrice = (int) $item['actual_unit_price'];
                $quantity = (int) $item['quantity'];

                // 할인 적용 전 현재 기본 매출 금액
                $amount = $unitPrice * $quantity;

                // 매출 상세 항목 생성
                $sale->items()->create([
                    'product_id' => $product->id,
                    'sale_type' => 'regular',
                    'promotion_id' => null,
                    'quantity' => $quantity,
                    'regular_unit_price' => $unitPrice,
                    'actual_unit_price' => $unitPrice,
                    'gross_amount' => $amount,
                    'discount_amount' => 0,
                    'net_amount' => $amount,
                ]);
            }

            return $sale;
        });

        // 매출 등록 감사 로그 기록
        $this->audit->log(
            $user,
            'sales',
            'create',
            Sale::class,
            $sale->id,
            null,
            $sale->load('items')->toArray(),
            '매출 등록'
        );

        return response()->json([
            'message' => '매출이 등록되었습니다.',
        ], 201);
    }
}