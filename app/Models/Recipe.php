<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    /**
     * 제품 레시피 정보
     *
     * 특정 점포의 제품에 사용되는 레시피를 관리합니다.
     *
     * 하나의 레시피에는 여러 재료와 작업 순서가
     * 연결될 수 있습니다.
     *
     * Recipe
     * ├─ RecipeIngredient : 레시피 재료
     * └─ RecipeStep       : 레시피 작업 순서
     */

    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증과 권한 검사는
     * Controller 및 Service에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 레시피 컬럼의 타입 변환 설정
     *
     * is_active : 레시피 사용 여부를 boolean으로 변환
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * 레시피가 등록된 점포
     *
     * recipes.store_id를 기준으로
     * Recipe와 Store를 연결합니다.
     *
     * 레시피가 어느 점포에서 사용하는 것인지
     * 확인할 때 사용합니다.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 레시피가 적용되는 제품
     *
     * recipes.product_id를 기준으로
     * Recipe와 Product를 연결합니다.
     *
     * 하나의 제품에는 여러 레시피가
     * 등록될 수 있습니다.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 레시피에 사용되는 재료 목록
     *
     * recipe_ingredients.recipe_id를 기준으로
     * Recipe와 RecipeIngredient를 연결합니다.
     *
     * sort_order를 기준으로 오름차순 정렬하여
     * 설정된 순서대로 재료 목록을 반환합니다.
     *
     * 예:
     * $recipe->ingredients
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)
            ->orderBy('sort_order');
    }

    /**
     * 레시피 작업 순서 목록
     *
     * recipe_steps.recipe_id를 기준으로
     * Recipe와 RecipeStep을 연결합니다.
     *
     * sort_order를 기준으로 오름차순 정렬하여
     * 실제 작업 순서대로 반환합니다.
     *
     * 예:
     * $recipe->steps
     */
    public function steps(): HasMany
    {
        return $this->hasMany(RecipeStep::class)
            ->orderBy('sort_order');
    }
}