<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\Recipe;
use App\Models\Store;
use App\Services\AccessService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
     * 제품 관리 화면에 필요한 데이터를 조회합니다.
     *
     * 점포 직원은 자신의 점포만 조회하고,
     * 본사 계정과 최고 관리자는 권한 범위 안에서 전체 점포를 조회합니다.
     * 삭제된 제품도 목록에 포함하여 복구 기능을 제공하되,
     * 화면에서는 기본적으로 삭제 상태 필터를 선택해야 확인할 수 있습니다.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'product.view');

        $products = $this->access
            ->scopeStore(Product::withTrashed(), $user)
            ->with([
                'store:id,name',
                'category:id,store_id,name',
                'prices' => fn ($query) => $query
                    ->orderByDesc('effective_from')
                    ->orderByDesc('id'),
                'recipes.ingredients',
                'recipes.steps',
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = $this->access
            ->scopeStore(ProductCategory::query(), $user)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        /**
         * 제품 등록 권한이 있는 최고 관리자는 등록 대상 점포를 선택할 수 있습니다.
         * 일반 점포 직원은 프론트에서 자신의 점포를 고정해서 사용하므로
         * 별도의 전체 점포 목록이 필요하지 않습니다.
         */
        $stores = $user->role?->code === 'super_admin'
            ? Store::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect($user->store ? [[
                'id' => $user->store->id,
                'name' => $user->store->name,
            ]] : []);

        return response()->json([
            'products' => $products,
            'categories' => $categories,
            'stores' => $stores,
        ]);
    }

    /**
     * 제품 상세정보를 최신 서버 상태로 조회합니다.
     */
    public function show(Request $request, Product $product)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'product.view');
        $this->assertProductVisibleToUser($user, $product);

        $product->load([
            'store:id,name',
            'category:id,store_id,name',
            'prices' => fn ($query) => $query
                ->orderByDesc('effective_from')
                ->orderByDesc('id'),
            'recipes.ingredients',
            'recipes.steps',
        ]);

        return response()->json([
            'product' => $product,
        ]);
    }

    /**
     * 신규 제품을 등록합니다.
     *
     * 제품 기본정보와 최초 가격은 하나의 transaction에서 저장하여
     * 가격 저장 실패 시 제품만 남는 불완전한 상태를 방지합니다.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'product.manage');

        $validated = $this->validateProduct($request);

        // 제품 담당 부서는 제품 자체의 속성입니다.
        // product.manage 권한이 있는 점포 사용자는 자기 점포 안에서
        // 주방/홀 담당 제품을 등록할 수 있고, 다른 점포는 계속 차단합니다.
        $this->access->assertStoreDepartment(
            $user,
            (int) $validated['store_id']
        );

        $this->assertCategoryBelongsToStore(
            (int) $validated['product_category_id'],
            (int) $validated['store_id']
        );

        $price = (int) $validated['price'];
        unset($validated['price']);

        $product = DB::transaction(function () use ($validated, $price, $user) {
            $product = Product::create([
                ...$validated,
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => true,
            ]);

            ProductPrice::create([
                'product_id' => $product->id,
                'price' => $price,
                'effective_from' => now()->toDateString(),
                'created_by' => $user->id,
            ]);

            return $product;
        });

        $this->audit->log(
            $user,
            'product',
            'create',
            Product::class,
            $product->id,
            null,
            $product->fresh()->toArray(),
            '제품 등록'
        );

        return response()->json([
            'message' => '제품이 등록되었습니다.',
        ], 201);
    }

    /**
     * 제품 기본정보와 판매정보를 수정합니다.
     *
     * 가격이 변경된 경우 기존 가격 행을 덮어쓰지 않고
     * 기존 가격의 종료일을 닫은 뒤 새 가격 이력을 추가합니다.
     */
    public function update(Request $request, Product $product)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'product.manage');
        $this->assertProductManageableByUser($user, $product);

        $validated = $this->validateProduct($request, $product);

        // 제품 담당 부서는 제품 자체의 속성입니다.
        // product.manage 권한이 있는 점포 사용자는 자기 점포 안에서
        // 주방/홀 담당 제품을 등록할 수 있고, 다른 점포는 계속 차단합니다.
        $this->access->assertStoreDepartment(
            $user,
            (int) $validated['store_id']
        );

        $this->assertCategoryBelongsToStore(
            (int) $validated['product_category_id'],
            (int) $validated['store_id']
        );

        $oldData = $product->load('prices')->toArray();
        $newPrice = (int) $validated['price'];
        unset($validated['price']);

        DB::transaction(function () use ($product, $validated, $newPrice, $user) {
            $product->update($validated);

            $currentPrice = $product->prices()
                ->whereNull('effective_to')
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if (! $currentPrice || (int) $currentPrice->price !== $newPrice) {
                $today = now()->toDateString();

                /**
                 * 같은 날짜에 가격을 여러 번 정정하면
                 * (product_id, effective_from) unique 제약과 충돌할 수 있습니다.
                 * 오늘 생성한 가격은 새 이력을 추가하지 않고 해당 행을 정정합니다.
                 */
                if (
                    $currentPrice
                    && $currentPrice->effective_from?->toDateString() === $today
                ) {
                    $currentPrice->update([
                        'price' => $newPrice,
                        'updated_by' => $user->id,
                    ]);
                } else {
                    if ($currentPrice) {
                        $currentPrice->update([
                            'effective_to' => now()->subDay()->toDateString(),
                            'updated_by' => $user->id,
                        ]);
                    }

                    ProductPrice::create([
                        'product_id' => $product->id,
                        'price' => $newPrice,
                        'effective_from' => $today,
                        'created_by' => $user->id,
                    ]);
                }
            }
        });

        $this->audit->log(
            $user,
            'product',
            'update',
            Product::class,
            $product->id,
            $oldData,
            $product->fresh()->load('prices')->toArray(),
            '제품 정보 수정'
        );

        return response()->json([
            'message' => '제품 정보를 수정했습니다.',
        ]);
    }

    /**
     * 제품의 현재 취급 여부를 변경합니다.
     */
    public function toggle(Request $request, Product $product)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'product.manage');
        $this->assertProductManageableByUser($user, $product);

        $oldData = $product->toArray();

        $product->update([
            'is_active' => ! $product->is_active,
        ]);

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
     * 제품을 Soft Delete합니다.
     *
     * 생산·폐기·가격·레시피 등 과거 연결 기록을 보존하기 위해
     * 실제 행은 삭제하지 않습니다.
     */
    public function destroy(Request $request, Product $product)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'product.manage');
        $this->assertProductManageableByUser($user, $product);

        $oldData = $product->toArray();
        $product->delete();

        $this->audit->log(
            $user,
            'product',
            'delete',
            Product::class,
            $product->id,
            $oldData,
            null,
            '제품 삭제'
        );

        return response()->json([
            'message' => '제품을 삭제했습니다.',
        ]);
    }

    /**
     * Soft Delete된 제품을 복구합니다.
     */
    public function restore(Request $request, Product $product)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'product.manage');
        $this->assertProductManageableByUser($user, $product);

        $oldData = $product->toArray();
        $product->restore();

        $this->audit->log(
            $user,
            'product',
            'update',
            Product::class,
            $product->id,
            $oldData,
            $product->fresh()->toArray(),
            '제품 복구'
        );

        return response()->json([
            'message' => '제품을 복구했습니다.',
        ]);
    }

    /**
     * 레시피를 등록합니다.
     * 기존 기능은 유지하면서 제품 접근 범위 검사를 공통 메서드로 통일합니다.
     */
    public function recipe(Request $request, Product $product)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'recipe.manage');
        $this->assertProductManageableByUser($user, $product);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('recipes', 'name')
                    ->where(fn ($query) => $query
                        ->where('store_id', $product->store_id)
                        ->where('product_id', $product->id)),
            ],
            'description' => ['nullable', 'string'],
            'ingredients' => ['array'],
            'ingredients.*.name' => ['required', 'string'],
            'ingredients.*.quantity' => ['required', 'numeric', 'min:0'],
            'ingredients.*.unit' => ['required', 'string'],
            'steps' => ['array'],
            'steps.*.description' => ['required', 'string'],
        ]);

        $recipe = DB::transaction(function () use ($validated, $product, $user) {
            $recipe = Recipe::create([
                'store_id' => $product->store_id,
                'product_id' => $product->id,
                'department' => $product->management_department,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => true,
                'created_by' => $user->id,
            ]);

            foreach ($validated['ingredients'] ?? [] as $index => $ingredient) {
                $recipe->ingredients()->create([
                    ...$ingredient,
                    'sort_order' => $index,
                ]);
            }

            foreach ($validated['steps'] ?? [] as $index => $step) {
                $recipe->steps()->create([
                    ...$step,
                    'sort_order' => $index,
                ]);
            }

            return $recipe;
        });

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

    /**
     * 제품에 이미 등록된 레시피를 수정합니다.
     *
     * 현재 제품 상세 UI에서는 대표 레시피 한 건을 등록/수정하는 흐름을 사용합니다.
     * 기존 재료와 공정은 transaction 안에서 교체하여 일부만 수정되는 상태를 방지합니다.
     */
    public function updateRecipe(Request $request, Product $product, Recipe $recipe)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'recipe.manage');
        $this->assertProductManageableByUser($user, $product);

        abort_unless(
            $recipe->product_id === $product->id
                && $recipe->store_id === $product->store_id,
            404,
            '해당 제품의 레시피를 찾을 수 없습니다.'
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('recipes', 'name')
                    ->where(fn ($query) => $query
                        ->where('store_id', $product->store_id)
                        ->where('product_id', $product->id))
                    ->ignore($recipe->id),
            ],
            'description' => ['nullable', 'string'],
            'ingredients' => ['array'],
            'ingredients.*.name' => ['required', 'string'],
            'ingredients.*.quantity' => ['required', 'numeric', 'min:0'],
            'ingredients.*.unit' => ['required', 'string'],
            'steps' => ['array'],
            'steps.*.description' => ['required', 'string'],
        ]);

        $oldData = $recipe->load(['ingredients', 'steps'])->toArray();

        DB::transaction(function () use ($validated, $product, $recipe, $user) {
            $recipe->update([
                'department' => $product->management_department,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'updated_by' => $user->id,
            ]);

            $recipe->ingredients()->delete();
            $recipe->steps()->delete();

            foreach ($validated['ingredients'] ?? [] as $index => $ingredient) {
                $recipe->ingredients()->create([
                    ...$ingredient,
                    'sort_order' => $index,
                ]);
            }

            foreach ($validated['steps'] ?? [] as $index => $step) {
                $recipe->steps()->create([
                    ...$step,
                    'sort_order' => $index,
                ]);
            }
        });

        $this->audit->log(
            $user,
            'recipe',
            'update',
            Recipe::class,
            $recipe->id,
            $oldData,
            $recipe->fresh()->load(['ingredients', 'steps'])->toArray(),
            '레시피 수정'
        );

        return response()->json([
            'message' => '레시피를 수정했습니다.',
        ]);
    }

    /**
     * 제품 등록/수정에 공통으로 사용하는 입력값 검증입니다.
     */
    private function validateProduct(Request $request, ?Product $product = null): array
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'product_category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')
                    ->where(fn ($query) => $query->where('store_id', $request->integer('store_id')))
                    ->ignore($product?->id),
            ],
            'production_department' => ['required', 'in:kitchen,hall'],
            'management_department' => ['required', 'in:kitchen,hall'],
            'sales_type' => ['required', 'in:regular,limited'],
            'sales_start_date' => [
                Rule::requiredIf($request->input('sales_type') === 'limited'),
                'nullable',
                'date',
            ],
            'sales_end_date' => [
                Rule::requiredIf($request->input('sales_type') === 'limited'),
                'nullable',
                'date',
                'after_or_equal:sales_start_date',
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'price' => ['required', 'integer', 'min:0'],
        ]);

        if ($validated['sales_type'] === 'regular') {
            $validated['sales_start_date'] = null;
            $validated['sales_end_date'] = null;
        }

        return $validated;
    }

    /**
     * 선택한 카테고리가 실제 선택 점포의 카테고리인지 확인합니다.
     */
    private function assertCategoryBelongsToStore(int $categoryId, int $storeId): void
    {
        $exists = ProductCategory::query()
            ->whereKey($categoryId)
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'product_category_id' => '선택한 점포에서 사용할 수 없는 카테고리입니다.',
            ]);
        }
    }

    /**
     * 현재 사용자가 제품을 조회할 수 있는 점포 범위인지 확인합니다.
     */
    private function assertProductVisibleToUser($user, Product $product): void
    {
        if ($user->role?->code === 'super_admin' || $user->isHeadOffice()) {
            return;
        }

        abort_unless(
            $user->store_id === $product->store_id,
            403,
            '다른 점포의 제품은 조회할 수 없습니다.'
        );
    }

    /**
     * 현재 사용자가 제품을 실제 변경할 수 있는 범위인지 확인합니다.
     */
    private function assertProductManageableByUser($user, Product $product): void
    {
        // 제품 관리는 부서가 아니라 점포 범위로 제한합니다.
        // 이를 통해 주방/홀 담당 제품을 한 점포의 제품 관리자가 함께 관리할 수 있습니다.
        $this->access->assertStoreDepartment(
            $user,
            $product->store_id
        );
    }
}
