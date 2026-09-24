<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeStep extends Model
{
    /**
     * 레시피 작업 순서 정보
     *
     * 하나의 레시피를 실제로 제조할 때 필요한
     * 개별 작업 단계와 순서를 저장합니다.
     *
     * 예:
     * 1. 재료 계량
     * 2. 믹싱
     * 3. 1차 발효
     * 4. 분할 및 성형
     * 5. 2차 발효
     * 6. 굽기
     *
     * 여러 RecipeStep이 하나의 Recipe에
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
     * 레시피 작업 순서 컬럼의 타입 변환 설정
     *
     * sort_order : 작업 단계의 표시 순서를 integer로 변환
     *
     * sort_order는 Recipe의 steps() 관계에서
     * 작업 단계를 순서대로 정렬할 때 사용됩니다.
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}