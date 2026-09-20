<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /**
     * Soft Delete 사용
     *
     * 제품 데이터를 실제 DB에서 바로 삭제하지 않고
     * deleted_at 값을 이용하여 삭제 상태로 관리한다.
     *
     * 판매가 종료된 제품이더라도 과거 생산 및 폐기 기록과
     * 통계 데이터를 유지할 수 있도록 제품 정보를 보존한다.
     */
    use SoftDeletes;

    /**
     * 대량 할당 가능한 속성
     *
     * Product::create(), fill(), update() 등을 사용할 때
     * 한 번에 입력하거나 수정할 수 있는 컬럼을 정의한다.
     */
    protected $fillable = [

        /**
         * 제품 코드
         *
         * 시스템 내부에서 제품을 식별하기 위한
         * 고유한 코드이다.
         *
         * 제품명이 변경되더라도 동일한 제품을 안정적으로
         * 식별할 수 있도록 이름과 별도로 관리한다.
         *
         * products 테이블에서 unique 제약조건으로
         * 동일한 제품 코드의 중복 등록을 방지한다.
         */
        'product_code',

        /**
         * 제품명
         *
         * 사용자 화면에 표시되는 실제 제품 이름이다.
         *
         * 예)
         * 소금빵
         * 크루아상
         * 식빵
         *
         * 생산량, 폐기량 및 각종 통계 화면에서
         * 사용자가 제품을 확인할 때 사용한다.
         */
        'name',

        /**
         * 제품 카테고리
         *
         * 제품을 종류별로 구분하기 위한 값이다.
         *
         * 예)
         * 식빵
         * 조리빵
         * 페이스트리
         * 디저트
         *
         * 향후 제품 검색, 필터링 및 카테고리별
         * 생산·폐기 통계 등에 활용할 수 있다.
         *
         * 카테고리를 지정하지 않는 제품도 허용하기 위해
         * DB에서는 null 값을 사용할 수 있다.
         */
        'category',

        /**
         * 수량 단위
         *
         * 해당 제품의 수량을 어떤 단위로 관리하는지 나타낸다.
         *
         * 기본값)
         * ea = 개
         *
         * 현재 완제품의 생산량과 폐기량은 정수 수량을
         * 기준으로 관리하기 때문에 기본 단위는 ea를 사용한다.
         *
         * 향후 재료 및 재고 기능에서 사용하는 kg, g 등의
         * 중량 단위와 완제품 수량 단위를 구분할 수 있다.
         */
        'unit',

        /**
         * 제품 사용 여부
         *
         * true(1)  = 현재 사용하는 제품
         * false(0) = 현재 사용하지 않는 제품
         *
         * 단종 또는 판매 중단된 제품이라도 기존 생산·폐기
         * 기록과 통계를 유지하기 위해 데이터를 삭제하지 않고
         * 사용 여부를 별도로 관리한다.
         */
        'is_active',
    ];

    /**
     * 속성 타입 변환
     *
     * DB에서 조회한 값을 애플리케이션에서 사용할 때
     * 각 컬럼에 맞는 타입으로 자동 변환한다.
     */
    protected function casts(): array
    {
        return [

            /**
             * 제품 사용 여부
             *
             * DB의 1/0 값을 PHP의 true/false 값으로
             * 자동 변환한다.
             */
            'is_active' => 'boolean',
        ];
    }

    /**
     * 제품의 생산 기록 목록
     *
     * 하나의 제품에는 날짜와 작업자에 따라 여러 개의
     * 생산 기록이 생성될 수 있다.
     *
     * production_records.product_id가
     * products.id를 참조한다.
     *
     * 예)
     * $product->productionRecords
     *
     * 위와 같이 사용하면 해당 제품에 등록된
     * 모든 생산 기록을 조회할 수 있다.
     */
    public function productionRecords()
    {
        return $this->hasMany(ProductionRecord::class);
    }

    /**
     * 제품의 폐기 기록 목록
     *
     * 하나의 제품에는 날짜와 작업자에 따라 여러 개의
     * 폐기 기록이 생성될 수 있다.
     *
     * waste_records.product_id가
     * products.id를 참조한다.
     *
     * 예)
     * $product->wasteRecords
     *
     * 위와 같이 사용하면 해당 제품에 등록된
     * 모든 폐기 기록을 조회할 수 있다.
     */
    public function wasteRecords()
    {
        return $this->hasMany(WasteRecord::class);
    }

    /**
     * 제품이 등록된 점포별 제품 관계 목록
     *
     * 하나의 제품은 여러 점포에서 취급할 수 있으므로
     * Product와 StoreProduct는 일대다(One-to-Many) 관계를 가진다.
     *
     * store_products 테이블의 product_id가
     * products 테이블의 id를 참조한다.
     *
     * 예)
     * $product->storeProducts
     *
     * 위와 같이 사용하면 해당 제품이 등록된
     * 모든 점포별 제품 관계 정보를 조회할 수 있다.
     */
    public function storeProducts(): HasMany
    {
        return $this->hasMany(StoreProduct::class);
    }
}