<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * 기본 점포 데이터 생성
     *
     * 시스템 초기 설치 시 사용할 기본 점포를 생성한다.
     *
     * store_code를 기준으로 updateOrCreate()를 사용하므로
     * Seeder를 여러 번 실행해도 같은 점포가 중복 생성되지 않는다.
     *
     * 점포 상태는 문자열 코드로 관리한다.
     *
     * active
     * → 운영 중
     *
     * inactive
     * → 비활성
     *
     * closed
     * → 폐점
     */
    public function run(): void
    {
        $stores = [
            [
                'store_code' => '1',
                'name' => '무역점',
                'status' => 'active',
                'opened_at' => null,
                'closed_at' => null,
            ],

            [
                'store_code' => '2',
                'name' => '더현대서울점',
                'status' => 'active',
                'opened_at' => null,
                'closed_at' => null,
            ],
        ];

        foreach ($stores as $store) {
            Store::updateOrCreate(
                [
                    'store_code' => $store['store_code'],
                ],
                [
                    'name' => $store['name'],
                    'status' => $store['status'],
                    'opened_at' => $store['opened_at'],
                    'closed_at' => $store['closed_at'],
                ]
            );
        }
    }
}