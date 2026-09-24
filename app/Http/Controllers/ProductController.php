<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\Recipe;
use App\Services\AccessService;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ProductController extends Controller
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
     * 제품 목록 조회
     *
     * 제품 관리 화면에서 사용할 제품, 카테고리,
     * 가격, 레시피 정보를 함께 반환합니다.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // 제품 조회 권한 확인
        $this->access->requirePermission($user, 'product.view');

        /**
         * 현재 사용자가 조회할 수 있는 점포 범위의 제품만 조회합니다.
         *
         * 제품과 함께 다음 정보를 불러옵니다.
         * - 점포
         * - 카테고리
         * - 가격
         * - 레시피 재료
         * - 레시피 작업 순서
         */
        $query = $this->access
            ->scopeStore(Product::query(), $user)
            ->with([
                'store:id,name',
                'category:id,name',

                // 적용일이 최근인 가격부터 조회
                'prices' => fn ($query) => $query->orderByDesc('effective_from'),

                'recipes.ingredients',
                'recipes.steps',
            ])
            ->orderBy('sort_order');

        // 현재 사용자가 조회할 수 있는 카테고리 조회
        $categories = $this->access
            ->scopeStore(ProductCategory::query(), $user)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'products' => $query->get(),
            'categories' => $categories,
        ]);
    }

    /**
     * 제품 등록
     *
     * product.manage 권한이 필요합니다.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // 제품 관리 권한 확인
        $this->access->requirePermission($user, 'product.manage');

        // 제품 입력값 검증
        $validated = $request->validate([
            'store_id' => [
                'required',
                'integer',
                'exists:stores,id',
            ],
            'product_category_id' => [
                'required',
                'integer',
                'exists:product_categories,id',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'production_department' => [
                'required',
                'in:kitchen,hall',
            ],
            'management_department' => [
                'required',
                'in:kitchen,hall',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'price' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        // 현재 사용자가 해당 점포 및 관리 부서의 제품을 관리할 수 있는지 확인
        $this->access->assertStoreDepartment(
            $user,
            (int) $validated['store_id'],
            $validated['management_department']
        );

        /**
         * 가격은 products 테이블이 아니라
         * product_prices 테이블에 별도로 저장하므로 분리합니다.
         */
        $price = $validated['price'] ?? null;
        unset($validated['price']);

        // 제품 생성
        $product = Product::create([
            ...$validated,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        // 가격이 입력된 경우 최초 제품 가격 등록
        if ($price !== null) {
            ProductPrice::create([
                'product_id' => $product->id,
                'price' => $price,
                'effective_from' => now()->toDateString(),
                'created_by' => $user->id,
            ]);
        }

        // 제품 등록 감사 로그 기록
        $this->audit->log(
            $user,
            'product',
            'create',
            Product::class,
            $product->id,
            null,
            $product->toArray(),
            '제품 등록'
        );

        return response()->json([
            'message' => '제품이 등록되었습니다.',
        ], 201);
    }

    /**
     * 제품 사용 상태 변경
     *
     * 활성화된 제품은 비활성화하고,
     * 비활성화된 제품은 다시 활성화합니다.
     */
    public function toggle(Request $request, Product $product)
    {
        $user = $request->user();

        // 제품 관리 권한 확인
        $this->access->requirePermission($user, 'product.manage');

        // 해당 제품의 점포 및 관리 부서에 대한 접근 권한 확인
        $this->access->assertStoreDepartment(
            $user,
            $product->store_id,
            $product->management_department
        );

        // 변경 전 데이터를 감사 로그용으로 저장
        $oldData = $product->toArray();

        // 현재 활성화 상태를 반대로 변경
        $product->update([
            'is_active' => ! $product->is_active,
        ]);

        // 제품 상태 변경 감사 로그 기록
        $this->audit->log(
            $user,
            'product',
            'update',
            Product::class,
            $product->id,
            $oldData,
            $product->toArray(),
            '제품 사용 상태 변경'
        );

        return response()->json([
            'message' => '제품 상태가 변경되었습니다.',
        ]);
    }

    /**
     * 레시피 등록
     *
     * recipe.manage 권한이 필요합니다.
     * 레시피 기본 정보와 재료, 작업 순서를 함께 등록합니다.
     */
    public function recipe(Request $request, Product $product)
    {
        $user = $request->user();

        // 레시피 관리 권한 확인
        $this->access->requirePermission($user, 'recipe.manage');

        // 해당 제품의 점포 및 관리 부서에 대한 접근 권한 확인
        $this->access->assertStoreDepartment(
            $user,
            $product->store_id,
            $product->management_department
        );

        // 레시피 입력값 검증
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'ingredients' => [
                'array',
            ],
            'ingredients.*.name' => [
                'required',
                'string',
            ],
            'ingredients.*.quantity' => [
                'required',
                'numeric',
                'min:0',
            ],
            'ingredients.*.unit' => [
                'required',
                'string',
            ],
            'steps' => [
                'array',
            ],
            'steps.*.description' => [
                'required',
                'string',
            ],
        ]);

        // 레시피 기본 정보 생성
        $recipe = Recipe::create([
            'store_id' => $product->store_id,
            'product_id' => $product->id,
            'department' => $product->management_department,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        // 레시피 재료 등록
        foreach ($validated['ingredients'] ?? [] as $index => $ingredient) {
            $recipe->ingredients()->create([
                ...$ingredient,
                'sort_order' => $index,
            ]);
        }

        // 레시피 작업 순서 등록
        foreach ($validated['steps'] ?? [] as $index => $step) {
            $recipe->steps()->create([
                ...$step,
                'sort_order' => $index,
            ]);
        }

        // 레시피 등록 감사 로그 기록
        $this->audit->log(
            $user,
            'recipe',
            'create',
            Recipe::class,
            $recipe->id,
            null,
            $recipe->toArray(),
            '레시피 등록'
        );

        return response()->json([
            'message' => '레시피가 등록되었습니다.',
        ], 201);
    }
}