<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * 점포 기본 데이터 생성
     *
     * 시스템을 처음 설치하거나 데이터베이스를 초기화했을 때
     * 기본적으로 필요한 점포 데이터를 생성한다.
     */
    public function run(): void
    {
        Store::updateOrCreate(
            [
                /**
                 * 점포 코드
                 *
                 * 동일한 Seeder를 여러 번 실행하더라도
                 * 같은 점포가 중복 생성되지 않도록 조회 기준으로 사용한다.
                 */
                'store_code' => '1',
            ],
            [
                /**
                 * 점포명
                 *
                 * 사용자 화면에 표시되는 실제 점포 이름이다.
                 */
                'name' => '무역점',

                /**
                 * 점포 운영 상태
                 *
                 * true = 현재 영업중인 점포
                 */
                'status' => true,

                /**
                 * 점포 영업 시작일
                 *
                 * 현재 정확한 영업 시작일을 입력하지 않았으므로
                 * null 상태로 생성한다.
                 */
                'opened_at' => null,

                /**
                 * 점포 폐점일
                 *
                 * 현재 영업중인 점포이므로
                 * 폐점일은 null로 설정한다.
                 */
                'closed_at' => null,
            ]
        );
    }
}