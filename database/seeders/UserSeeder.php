<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * 기본 사용자 데이터 생성
     *
     * 시스템 초기 설정 및 로그인 테스트에 사용할
     * 기본 사용자 계정을 생성한다.
     */
    public function run(): void
    {
        /**
         * 기본 소속 점포 조회
         *
         * StoreSeeder에서 생성한 무역점을
         * store_code를 기준으로 조회한다.
         *
         * 점포가 존재하지 않는 상태에서 사용자를 생성하면
         * 잘못된 데이터가 만들어질 수 있으므로 firstOrFail()을 사용한다.
         */
        $store = Store::where('store_code', '1')->firstOrFail();

        /**
         * 기본 사용자 역할 조회
         *
         * RoleSeeder에서 생성한 일반 직원 역할을
         * 역할 code를 기준으로 조회한다.
         */
        $role = Role::where('code', 'staff')->firstOrFail();

        User::updateOrCreate(
            [
                /**
                 * 로그인 아이디
                 *
                 * 동일한 Seeder를 여러 번 실행하더라도
                 * 같은 사용자가 중복 생성되지 않도록 조회 기준으로 사용한다.
                 */
                'login_id' => 'admin',
            ],
            [
                /**
                 * 사용자 이름
                 *
                 * 시스템 화면에 표시되는
                 * 실제 사용자 이름이다.
                 */
                'name' => '홍길동',

                /**
                 * 로그인 비밀번호
                 *
                 * 개발 단계에서 로그인 기능을 테스트하기 위한
                 * 임시 비밀번호이다.
                 *
                 * User 모델의 hashed cast에 의해
                 * DB에는 평문이 아닌 해시된 값으로 저장된다.
                 */
                'password' => '1234',

                /**
                 * 현재 소속 점포
                 *
                 * StoreSeeder에서 생성한 무역점의
                 * 실제 PK 값을 저장한다.
                 */
                'store_id' => $store->id,

                /**
                 * 현재 소속 부서
                 *
                 * kitchen = 주방
                 */
                'department' => 'kitchen',

                /**
                 * 사용자 역할
                 *
                 * RoleSeeder에서 생성한 일반 직원 역할의
                 * 실제 PK 값을 저장한다.
                 */
                'role_id' => $role->id,

                /**
                 * 재직 상태
                 *
                 * true = 현재 재직중인 직원
                 */
                'is_employed' => true,

                /**
                 * 계정 사용 가능 여부
                 *
                 * true = 현재 로그인 및 시스템 사용 가능
                 */
                'is_active' => true,

                /**
                 * 입사일
                 *
                 * 현재 정확한 입사일을 초기 데이터에
                 * 지정하지 않으므로 null로 설정한다.
                 */
                'hired_at' => null,

                /**
                 * 퇴사일
                 *
                 * 현재 재직중인 직원이므로
                 * 퇴사일은 null로 설정한다.
                 */
                'resigned_at' => null,

                /**
                 * 마지막 로그인 일시
                 *
                 * 아직 새 DB에서 로그인한 기록이 없으므로
                 * null로 시작한다.
                 */
                'last_login_at' => null,
            ]
        );
    }
}