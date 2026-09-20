<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /**
     * 시스템에서 사용할 기본 직급 데이터를 생성한다.
     *
     * 직급(position)은 부서(department)나 시스템 역할(role)과
     * 서로 다른 개념으로 분리해서 관리한다.
     *
     * 예를 들어 '과장'이라는 직급을 가진 직원이라도
     * 주방, 홀, 본사 중 어느 부서에든 소속될 수 있으며,
     * 실제 시스템에서 사용할 수 있는 기능은 role과 permission이 결정한다.
     */
    public function run(): void
    {
        /**
         * 기본 직급 목록
         *
         * code는 프로그램 내부에서 사용하는 고정 식별값이다.
         * name은 직원 관리 화면 등에 표시되는 실제 직급명이다.
         *
         * sort_order는 직급 선택 화면이나 직원 정보 화면에서
         * 낮은 직급부터 높은 직급 순으로 정렬하기 위해 사용한다.
         *
         * 회사에서 사용하지 않는 직급은 데이터를 삭제하지 않고
         * is_active를 false로 변경하여 비활성화할 수 있다.
         */
        $positions = [
            [
                'code' => 'part_timer',
                'name' => '파트타이머',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'code' => 'intern',
                'name' => '인턴',
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'code' => 'staff',
                'name' => '사원',
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'code' => 'senior_staff',
                'name' => '주임',
                'sort_order' => 40,
                'is_active' => true,
            ],
            [
                'code' => 'assistant_manager',
                'name' => '대리',
                'sort_order' => 50,
                'is_active' => true,
            ],
            [
                'code' => 'manager',
                'name' => '과장',
                'sort_order' => 60,
                'is_active' => true,
            ],
            [
                'code' => 'deputy_general_manager',
                'name' => '차장',
                'sort_order' => 70,
                'is_active' => true,
            ],
            [
                'code' => 'general_manager',
                'name' => '부장',
                'sort_order' => 80,
                'is_active' => true,
            ],
            [
                'code' => 'director',
                'name' => '이사',
                'sort_order' => 90,
                'is_active' => true,
            ],
            [
                'code' => 'managing_director',
                'name' => '상무',
                'sort_order' => 100,
                'is_active' => true,
            ],
            [
                'code' => 'senior_managing_director',
                'name' => '전무',
                'sort_order' => 110,
                'is_active' => true,
            ],
            [
                'code' => 'vice_president',
                'name' => '부사장',
                'sort_order' => 120,
                'is_active' => true,
            ],
            [
                'code' => 'president',
                'name' => '사장',
                'sort_order' => 130,
                'is_active' => true,
            ],
            [
                'code' => 'vice_chairman',
                'name' => '부회장',
                'sort_order' => 140,
                'is_active' => true,
            ],
            [
                'code' => 'chairman',
                'name' => '회장',
                'sort_order' => 150,
                'is_active' => true,
            ],
        ];

        /**
         * 직급 데이터를 생성하거나 갱신한다.
         *
         * code를 기준으로 기존 데이터가 존재하는지 확인한다.
         * 이미 존재하면 name, sort_order, is_active를 갱신하고,
         * 존재하지 않으면 새로운 직급 데이터를 생성한다.
         *
         * updateOrCreate()를 사용하므로 Seeder를 여러 번 실행해도
         * 같은 직급이 중복으로 생성되지 않는다.
         */
        foreach ($positions as $position) {
            Position::updateOrCreate(
                [
                    'code' => $position['code'],
                ],
                [
                    'name' => $position['name'],
                    'sort_order' => $position['sort_order'],
                    'is_active' => $position['is_active'],
                ]
            );
        }
    }
}