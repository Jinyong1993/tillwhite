<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPrice;
use App\Models\Recipe;
use App\Models\Store;
use App\Services\AccessService;
use App\Services\AuditService;
use App\Services\AuditTrailService;
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
        private AuditService $audit,
        private AuditTrailService $auditTrail
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

        // 상세 화면에서 작업자 이력을 바로 표시할 수 있도록 기존 감사 로그를 재사용합니다.
        $product->setAttribute(
            'management_history',
            $this->auditTrail->summary(Product::class, $product->id)
        );
        $product->setAttribute(
            'audit_history',
            $this->auditTrail->history(Product::class, $product->id)
        );

        $product->recipes->each(function (Recipe $recipe) {
            $recipe->setAttribute(
                'management_history',
                $this->auditTrail->summary(Recipe::class, $recipe->id)
            );
            $recipe->setAttribute(
                'audit_history',
                $this->auditTrail->history(Recipe::class, $recipe->id)
            );
        });

        return response()->json([
            'product' => $product,
        ]);
    }

    /** 최근 본 제품/레시피를 Laravel Session에서 조회합니다. */
    public function recentViewed(Request $request)
    {
        $this->access->requirePermission($request->user(), 'product.view');

        return response()->json([
            'items' => array_values($request->session()->get('product_recent_viewed', [])),
        ]);
    }

    /** 최근 본 항목을 최대 5개까지 중복 없이 Laravel Session에 저장합니다. */
    public function rememberRecentViewed(Request $request)
    {
        $this->access->requirePermission($request->user(), 'product.view');

        $validated = $request->validate([
            'type' => ['required', Rule::in(['product', 'recipe'])],
            'id' => ['required', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
        ]);

        $items = collect($request->session()->get('product_recent_viewed', []))
            ->reject(fn ($item) => $item['type'] === $validated['type'] && (int) $item['id'] === (int) $validated['id'])
            ->prepend($validated)
            ->take(5)
            ->values()
            ->all();

        $request->session()->put('product_recent_viewed', $items);

        return response()->json(['items' => $items]);
    }

    /**
     * 현재 로그인 세션에 저장된 제품 등록 draft를 조회합니다.
     */
    public function draft(Request $request)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'product.manage');

        return response()->json([
            'draft' => $request->session()->get(
                'product_registration_draft'
            ),
        ]);
    }

    /**
     * 작성 중인 제품 등록 내용을 Laravel Session에 임시저장합니다.
     *
     * 임시저장은 완성된 제품 등록이 아니므로 대부분의 항목은 nullable로 받습니다.
     * 값이 입력된 경우에는 형식, 점포 범위, 카테고리 소속 관계를 서버에서 검증합니다.
     */
    public function saveDraft(Request $request)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'product.manage');

        $validated = $request->validate([
            'store_id' => [
                'nullable',
                'integer',
                'exists:stores,id',
            ],
            'product_category_id' => [
                'nullable',
                'integer',
                'exists:product_categories,id',
            ],
            'name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'price' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'production_department' => [
                'nullable',
                'in:kitchen,hall',
            ],
            'management_department' => [
                'nullable',
                'in:kitchen,hall',
            ],
            'sales_type' => [
                'nullable',
                'in:regular,limited',
            ],
            'sales_start_date' => [
                'nullable',
                'date',
            ],
            'sales_end_date' => [
                'nullable',
                'date',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);

        $storeId = isset($validated['store_id'])
            ? (int) $validated['store_id']
            : null;

        $categoryId = isset($validated['product_category_id'])
            ? (int) $validated['product_category_id']
            : null;

        if ($storeId !== null) {
            $this->access->assertStoreDepartment(
                $user,
                $storeId
            );
        }

        if ($categoryId !== null && $storeId !== null) {
            $this->assertCategoryBelongsToStore(
                $categoryId,
                $storeId
            );
        }

        // 점포를 아직 선택하지 않았다면 카테고리만 단독으로 저장하지 않습니다.
        if ($categoryId !== null && $storeId === null) {
            throw ValidationException::withMessages([
                'product_category_id' => '카테고리를 저장하려면 점포를 먼저 선택해주세요.',
            ]);
        }

        if (($validated['sales_type'] ?? 'regular') === 'regular') {
            $validated['sales_start_date'] = null;
            $validated['sales_end_date'] = null;
        }

        $request->session()->put(
            'product_registration_draft',
            $validated
        );

        return response()->json([
            'message' => '제품 등록 내용이 임시저장되었습니다.',
            'draft' => $validated,
        ]);
    }

    /**
     * 현재 로그인 세션의 제품 등록 draft를 삭제합니다.
     */
    public function deleteDraft(Request $request)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'product.manage');

        $request->session()->forget(
            'product_registration_draft'
        );

        return response()->json([
            'message' => '제품 등록 임시저장 내용이 삭제되었습니다.',
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

        // 최종 등록이 완료되면 현재 로그인 세션의 제품 등록 draft는 더 이상 필요하지 않습니다.
        $request->session()->forget('product_registration_draft');

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

        DB::transaction(function () use ($product, $user) {
            // 제품 삭제 시점에 살아 있던 레시피만 함께 삭제합니다.
            // 이미 사용자가 직접 삭제한 레시피는 건드리지 않아 복구 의도를 보존합니다.
            $product->recipes()
                ->whereNull('deleted_at')
                ->get()
                ->each(function (Recipe $recipe) use ($user) {
                    $recipe->forceFill([
                        'deleted_by' => $user->id,
                        'deletion_source' => 'product',
                    ])->save();
                    $recipe->delete();

                    $this->audit->log(
                        $user,
                        'recipe',
                        'delete',
                        Recipe::class,
                        $recipe->id,
                        $recipe->toArray(),
                        null,
                        '제품 삭제에 따른 레시피 삭제'
                    );
                });

            $product->delete();
        });

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

        DB::transaction(function () use ($product, $user) {
            $product->restore();

            // 제품 삭제 때문에 함께 삭제된 레시피만 자동 복구합니다.
            // 사용자가 직접 삭제한 레시피(deletion_source=manual)는 삭제 상태를 유지합니다.
            $product->recipes()
                ->onlyTrashed()
                ->where('deletion_source', 'product')
                ->get()
                ->each(function (Recipe $recipe) use ($user) {
                    $recipeOldData = $recipe->toArray();
                    $recipe->restore();
                    $recipe->forceFill([
                        'deleted_by' => null,
                        'deletion_source' => null,
                    ])->save();

                    $this->audit->log(
                        $user,
                        'recipe',
                        'update',
                        Recipe::class,
                        $recipe->id,
                        $recipeOldData,
                        $recipe->fresh()->toArray(),
                        '제품 복구에 따른 레시피 복구'
                    );
                });
        });

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

        // 삭제된 제품은 과거 레시피를 보존하되 복구 전에는 새 변경을 허용하지 않습니다.
        if ($product->trashed()) {
            throw ValidationException::withMessages([
                'product' => '삭제된 제품입니다. 복구 후 레시피를 관리해주세요.',
            ]);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('recipes', 'name')
                    ->where(fn ($query) => $query
                        ->where('store_id', $product->store_id)
                        ->where('product_id', $product->id)
                        ->whereNull('deleted_at')),
            ],
            'description' => ['nullable', 'string'],
            'ingredients' => ['array'],
            'ingredients.*.name' => ['required', 'string'],
            'ingredients.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:50'],
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

        // 삭제 상태에서는 상세 조회만 허용하고 레시피 변경은 복구 후 진행합니다.
        if ($product->trashed()) {
            throw ValidationException::withMessages([
                'product' => '삭제된 제품입니다. 복구 후 레시피를 관리해주세요.',
            ]);
        }

        abort_unless(
            $recipe->product_id === $product->id
                && $recipe->store_id === $product->store_id,
            404,
            '해당 제품의 레시피를 찾을 수 없습니다.'
        );

        if ($recipe->trashed()) {
            throw ValidationException::withMessages([
                'recipe' => '삭제된 레시피입니다. 복구 후 수정해주세요.',
            ]);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('recipes', 'name')
                    ->where(fn ($query) => $query
                        ->where('store_id', $product->store_id)
                        ->where('product_id', $product->id)
                        ->whereNull('deleted_at'))
                    ->ignore($recipe->id),
            ],
            'description' => ['nullable', 'string'],
            'ingredients' => ['array'],
            'ingredients.*.name' => ['required', 'string'],
            'ingredients.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:50'],
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
     * 레시피를 같은 점포의 다른 제품으로 복사합니다.
     * 원본 레시피는 변경하지 않으며 대상 제품에 새 레시피를 생성합니다.
     */
    public function copyRecipe(Request $request, Product $product, Recipe $recipe)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'recipe.manage');
        $this->assertProductManageableByUser($user, $product);

        if ($product->trashed()) {
            throw ValidationException::withMessages([
                'product' => '삭제된 제품의 레시피는 복사할 수 없습니다.',
            ]);
        }

        abort_unless(
            (int) $recipe->product_id === (int) $product->id,
            404,
            '레시피를 찾을 수 없습니다.'
        );

        if ($recipe->trashed()) {
            throw ValidationException::withMessages([
                'recipe' => '삭제된 레시피는 복사할 수 없습니다. 복구 후 이용해주세요.',
            ]);
        }

        $validated = $request->validate([
            'target_product_id' => ['required', 'integer', 'exists:products,id'],
        ], [
            'target_product_id.required' => '레시피를 복사할 제품을 선택해주세요.',
            'target_product_id.exists' => '선택한 제품을 찾을 수 없습니다.',
        ]);

        $target = Product::query()->findOrFail($validated['target_product_id']);
        $this->assertProductManageableByUser($user, $target);

        if ((int) $target->store_id !== (int) $product->store_id) {
            throw ValidationException::withMessages([
                'target_product_id' => '같은 점포의 제품으로만 레시피를 복사할 수 있습니다.',
            ]);
        }

        if ($target->recipes()->whereNull('deleted_at')->exists()) {
            throw ValidationException::withMessages([
                'target_product_id' => '선택한 제품에는 이미 레시피가 등록되어 있습니다.',
            ]);
        }

        $recipe->load(['ingredients', 'steps']);

        $copy = DB::transaction(function () use ($recipe, $target, $user) {
            $copy = Recipe::create([
                'store_id' => $target->store_id,
                'product_id' => $target->id,
                'department' => $target->management_department,
                'name' => $recipe->name,
                'description' => $recipe->description,
                'is_active' => $recipe->is_active,
                'created_by' => $user->id,
            ]);

            foreach ($recipe->ingredients as $ingredient) {
                $copy->ingredients()->create([
                    'name' => $ingredient->name,
                    'quantity' => $ingredient->quantity,
                    'unit' => $ingredient->unit,
                    'sort_order' => $ingredient->sort_order,
                ]);
            }

            foreach ($recipe->steps as $step) {
                $copy->steps()->create([
                    'description' => $step->description,
                    'sort_order' => $step->sort_order,
                ]);
            }

            return $copy;
        });

        $this->audit->log(
            $user,
            'recipe',
            'create',
            Recipe::class,
            $copy->id,
            null,
            $copy->fresh()->load(['ingredients', 'steps'])->toArray(),
            '레시피 복사'
        );

        return response()->json([
            'message' => '레시피를 복사했습니다.',
        ], 201);
    }

    /** 레시피를 직접 Soft Delete합니다. */
    public function destroyRecipe(Request $request, Product $product, Recipe $recipe)
    {
        $user = $request->user();
        $this->access->requirePermission($user, 'recipe.manage');
        $this->assertProductManageableByUser($user, $product);

        abort_unless((int) $recipe->product_id === (int) $product->id, 404, '레시피를 찾을 수 없습니다.');
        abort_if($recipe->trashed(), 409, '이미 삭제된 레시피입니다.');

        $oldData = $recipe->load(['ingredients', 'steps'])->toArray();
        $recipe->forceFill([
            'deleted_by' => $user->id,
            'deletion_source' => 'manual',
        ])->save();
        $recipe->delete();

        $this->audit->log($user, 'recipe', 'delete', Recipe::class, $recipe->id, $oldData, null, '레시피 삭제');

        return response()->json(['message' => '레시피를 삭제했습니다.']);
    }

    /** 직접 삭제된 레시피를 복구합니다. */
    public function restoreRecipe(Request $request, Product $product, Recipe $recipe)
    {
        $user = $request->user();
        $this->access->requirePermission($user, 'recipe.manage');
        $this->assertProductManageableByUser($user, $product);

        abort_unless((int) $recipe->product_id === (int) $product->id, 404, '레시피를 찾을 수 없습니다.');
        abort_unless($recipe->trashed(), 409, '삭제된 레시피가 아닙니다.');

        if ($product->trashed()) {
            throw ValidationException::withMessages(['recipe' => '제품을 먼저 복구해주세요.']);
        }

        if ($product->recipes()->whereNull('deleted_at')->exists()) {
            throw ValidationException::withMessages(['recipe' => '이미 등록된 레시피가 있어 복구할 수 없습니다.']);
        }

        $oldData = $recipe->toArray();
        $recipe->restore();
        $recipe->forceFill(['deleted_by' => null, 'deletion_source' => null])->save();

        $this->audit->log($user, 'recipe', 'update', Recipe::class, $recipe->id, $oldData, $recipe->fresh()->toArray(), '레시피 복구');

        return response()->json(['message' => '레시피를 복구했습니다.']);
    }

    /**
     * 기존 제품을 새 제품으로 복제합니다.
     * 레시피 복제를 선택해도 모든 행은 새 ID로 생성되어 원본과 독립적으로 관리됩니다.
     */
    public function cloneProduct(Request $request, Product $product)
    {
        $user = $request->user();
        $this->access->requirePermission($user, 'product.manage');
        $this->assertProductManageableByUser($user, $product);
        abort_if($product->trashed(), 422, '삭제된 제품은 복제할 수 없습니다.');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'name')->where(fn ($query) => $query->where('store_id', $product->store_id)),
            ],
            'copy_recipe' => ['required', 'boolean'],
        ], [
            'name.required' => '새 제품명을 입력해주세요.',
            'name.max' => '제품명은 255자 이하로 입력해주세요.',
            'name.unique' => '같은 점포에 동일한 제품명이 이미 등록되어 있습니다.',
            'copy_recipe.required' => '레시피 복사 여부를 선택해주세요.',
        ]);

        if ($validated['copy_recipe']) {
            $this->access->requirePermission($user, 'recipe.manage');
        }

        $product->load(['prices', 'recipes.ingredients', 'recipes.steps']);
        $sourceRecipe = $product->recipes->firstWhere('deleted_at', null);

        $copy = DB::transaction(function () use ($product, $sourceRecipe, $validated, $user) {
            $copy = Product::create([
                'store_id' => $product->store_id,
                'product_category_id' => $product->product_category_id,
                'name' => trim($validated['name']),
                'production_department' => $product->production_department,
                'management_department' => $product->management_department,
                'sales_type' => $product->sales_type,
                'sales_start_date' => $product->sales_start_date,
                'sales_end_date' => $product->sales_end_date,
                'sort_order' => $product->sort_order,
                'is_active' => true,
            ]);

            $price = $product->prices->first()?->price ?? 0;
            ProductPrice::create([
                'product_id' => $copy->id,
                'price' => $price,
                'effective_from' => now()->toDateString(),
                'created_by' => $user->id,
            ]);

            if ($validated['copy_recipe'] && $sourceRecipe) {
                $recipeCopy = Recipe::create([
                    'store_id' => $copy->store_id,
                    'product_id' => $copy->id,
                    'department' => $copy->management_department,
                    'name' => $sourceRecipe->name,
                    'description' => $sourceRecipe->description,
                    'is_active' => $sourceRecipe->is_active,
                    'created_by' => $user->id,
                ]);

                foreach ($sourceRecipe->ingredients as $ingredient) {
                    $recipeCopy->ingredients()->create($ingredient->only(['name', 'quantity', 'unit', 'sort_order']));
                }
                foreach ($sourceRecipe->steps as $step) {
                    $recipeCopy->steps()->create($step->only(['description', 'sort_order']));
                }
            }

            return $copy;
        });

        $this->audit->log($user, 'product', 'create', Product::class, $copy->id, null, $copy->toArray(), '제품 복제');

        return response()->json([
            'message' => '새 제품을 만들었습니다.',
            'product_id' => $copy->id,
        ], 201);
    }

    /**
     * 제품 카테고리를 등록합니다.
     */
    public function categoryStore(Request $request)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'product.manage');

        $validated = $request->validate([
            'store_id' => [
                'required',
                'integer',
                'exists:stores,id',
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_categories', 'name')
                    ->where(fn ($query) => $query->where(
                        'store_id',
                        $request->integer('store_id')
                    )),
            ],
        ], [
            'store_id.required' => '점포를 선택해주세요.',
            'store_id.exists' => '선택한 점포를 찾을 수 없습니다.',
            'name.required' => '카테고리명을 입력해주세요.',
            'name.unique' => '이미 등록된 카테고리입니다.',
            'name.max' => '카테고리명은 100자 이하로 입력해주세요.',
        ]);

        $this->access->assertStoreDepartment(
            $user,
            (int) $validated['store_id']
        );

        $lastSortOrder = ProductCategory::query()
            ->where('store_id', $validated['store_id'])
            ->max('sort_order');

        $category = ProductCategory::create([
            ...$validated,
            // 10 단위 간격을 두면 중간 삽입이나 재정렬 시 순서를 관리하기 쉽습니다.
            'sort_order' => ((int) $lastSortOrder) + 10,
            'is_active' => true,
        ]);

        $this->audit->log(
            $user,
            'product_category',
            'create',
            ProductCategory::class,
            $category->id,
            null,
            $category->toArray(),
            '제품 카테고리 등록'
        );

        return response()->json([
            'message' => '카테고리를 등록했습니다.',
        ], 201);
    }

    /**
     * 제품 카테고리 이름을 수정합니다.
     */
    public function categoryUpdate(Request $request, ProductCategory $category)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'product.manage');
        $this->access->assertStoreDepartment(
            $user,
            (int) $category->store_id
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_categories', 'name')
                    ->where(fn ($query) => $query->where(
                        'store_id',
                        $category->store_id
                    ))
                    ->ignore($category->id),
            ],
        ], [
            'name.required' => '카테고리명을 입력해주세요.',
            'name.unique' => '이미 등록된 카테고리입니다.',
            'name.max' => '카테고리명은 100자 이하로 입력해주세요.',
        ]);

        $oldData = $category->toArray();

        $category->update($validated);

        $this->audit->log(
            $user,
            'product_category',
            'update',
            ProductCategory::class,
            $category->id,
            $oldData,
            $category->toArray(),
            '제품 카테고리 수정'
        );

        return response()->json([
            'message' => '카테고리를 수정했습니다.',
        ]);
    }

    /**
     * 카테고리 사용 / 사용중단 상태를 변경합니다.
     * 연결된 제품과 과거 기록은 삭제하지 않습니다.
     */
    public function categoryToggle(Request $request, ProductCategory $category)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'product.manage');
        $this->access->assertStoreDepartment(
            $user,
            (int) $category->store_id
        );

        $oldData = $category->toArray();

        $category->update([
            'is_active' => ! $category->is_active,
        ]);

        $this->audit->log(
            $user,
            'product_category',
            'update',
            ProductCategory::class,
            $category->id,
            $oldData,
            $category->toArray(),
            '제품 카테고리 상태 변경'
        );

        return response()->json([
            'message' => $category->is_active
                ? '카테고리를 다시 사용합니다.'
                : '카테고리 사용을 중단했습니다.',
        ]);
    }

    /**
     * 카테고리를 한 칸 위/아래로 이동합니다.
     *
     * 관리 화면의 수동 순서는 sort_order를 사용하고, 사용자 선택 드롭다운의
     * 가나다/ABC 자연 정렬과는 분리하여 두 목적이 서로 충돌하지 않도록 합니다.
     */
    public function categoryReorder(Request $request, ProductCategory $category)
    {
        $user = $request->user();

        $this->access->requirePermission($user, 'product.manage');
        $this->access->assertStoreDepartment($user, (int) $category->store_id);

        $validated = $request->validate([
            'direction' => ['required', 'integer', Rule::in([-1, 1])],
        ]);

        $categories = ProductCategory::query()
            ->where('store_id', $category->store_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $currentIndex = $categories->search(
            fn (ProductCategory $item) => $item->id === $category->id
        );
        $targetIndex = $currentIndex + (int) $validated['direction'];

        if ($currentIndex === false || ! $categories->has($targetIndex)) {
            throw ValidationException::withMessages([
                'direction' => '더 이상 해당 방향으로 이동할 수 없습니다.',
            ]);
        }

        $oldData = $category->toArray();
        $orderedIds = $categories->pluck('id')->all();
        [$orderedIds[$currentIndex], $orderedIds[$targetIndex]] = [
            $orderedIds[$targetIndex],
            $orderedIds[$currentIndex],
        ];

        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $categoryId) {
                ProductCategory::whereKey($categoryId)->update([
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        });

        $this->audit->log(
            $user,
            'product_category',
            'update',
            ProductCategory::class,
            $category->id,
            $oldData,
            $category->fresh()->toArray(),
            '제품 카테고리 순서 변경'
        );

        return response()->json([
            'message' => '카테고리 순서를 변경했습니다.',
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
