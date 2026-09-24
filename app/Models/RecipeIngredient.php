<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeIngredient extends Model
{
    /**
     * 레시피 재료 정보
     *
     * 하나의 레시피에 사용되는 개별 재료의
     * 정보와 사용량을 저장합니다.
     *
     * 예:
     * 강력분 → 500.000g
     * 물     → 320.000g
     * 소금   → 10.000g
     *
     * 여러 RecipeIngredient가 하나의 Recipe에
     * 속하는 구조로 사용됩니다.
     */

    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 입력값 검증은
     * Controller 및 Service에서 처리합니다.
     */
    protected $guarded = [];

    /**
     * 레시피 재료 컬럼의 타입 변환 설정
     *
     * quantity   : 재료 사용량을 소수점 3자리 형식으로 변환
     * sort_order : 재료 표시 순서를 integer로 변환
     *
     * quantity를 소수점 3자리까지 관리하여
     * 소량의 재료도 비교적 정밀하게 기록할 수 있습니다.
     *
     * sort_order는 Recipe의 ingredients() 관계에서
     * 재료 목록을 정렬할 때 사용됩니다.
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }
}