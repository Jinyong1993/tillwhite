<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasFactory;

    /**
     * 대량 할당 허용 필드
     */
    protected $fillable = [
        'code',
        'name',
        'sort_order',
        'is_active',
    ];

    /**
     * 자료형 자동 변환
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 이 직급을 가진 직원 목록
     *
     * 실제 employees/users 구조에 맞춰
     * 외래키는 users.position_id를 사용한다.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}