<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreProduct extends Model
{
    /**
     * 대량 할당 가능한 속성
     *
     * 점포와 제품의 연결 정보 및
     * 현재 취급 여부를 저장할 수 있도록 한다.
     */
    protected $fillable = [
        'store_id',
        'product_id',
        'is_active',
    ];

    /**
     * 속성 타입 변환
     *
     * is_active 값을 항상 boolean 타입으로
     * 사용할 수 있도록 변환한다.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * 이 제품 관계가 속한 점포
     *
     * store_products.store_id를 기준으로
     * stores 테이블의 점포 정보를 가져온다.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * 이 제품 관계가 가리키는 제품
     *
     * store_products.product_id를 기준으로
     * products 테이블의 제품 정보를 가져온다.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}