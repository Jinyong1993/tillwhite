<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Till White 사용자 테스트 데이터 Factory
 *
 * 실제 운영용 초기 데이터는 Seeder에서 생성하고,
 * 이 Factory는 테스트 또는 개발 과정에서
 * 임시 User 데이터를 생성할 때 사용합니다.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * 이 Factory가 생성할 모델
     */
    protected $model = User::class;

    /**
     * Factory에서 공통으로 사용할 비밀번호 Hash
     *
     * 여러 사용자를 생성할 때 동일한 테스트 비밀번호를
     * 매번 다시 Hash하지 않도록 한 번 생성한 값을 재사용합니다.
     */
    protected static ?string $password;

    /**
     * User의 기본 테스트 데이터를 정의합니다.
     *
     * Till White는 이메일이 아니라 employee_code를
     * 로그인 ID로 사용합니다.
     *
     * 기본 Factory 사용자는 일반 직원(staff)으로 생성하며,
     * 구체적인 점포, 부서, 직급 등이 필요한 테스트에서는
     * state() 등을 이용하여 추가 설정할 수 있습니다.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            /**
             * 사번 및 로그인 ID
             *
             * 테스트 사용자끼리 중복되지 않도록
             * 고유한 값을 생성합니다.
             */
            'employee_code' => fake()->unique()->numerify('TEST#####'),

            /**
             * 직원 이름
             */
            'name' => fake()->name(),

            /**
             * 로그인 비밀번호
             *
             * Factory로 생성되는 테스트 사용자용 비밀번호입니다.
             * 실제 운영 사용자 비밀번호를 의미하지 않습니다.
             */
            'password' => static::$password ??= Hash::make('password'),

            /**
             * 비밀번호 변경 시간
             *
             * 최초 생성 상태에서는 별도의 변경 이력이 없으므로
             * NULL로 설정합니다.
             */
            'password_changed_at' => null,

            /**
             * 직원 연락처 및 생년월일
             */
            'phone' => null,
            'birth_date' => null,

            /**
             * 기본 Factory 사용자는 특정 점포를
             * 강제로 지정하지 않습니다.
             *
             * 실제 점포 직원 테스트에서는
             * 필요한 store_id를 별도로 지정합니다.
             */
            'store_id' => null,

            /**
             * 기본 부서
             *
             * 필요한 테스트에서는 kitchen, hall,
             * head_office 등으로 변경할 수 있습니다.
             */
            'department' => 'kitchen',

            /**
             * 직급은 테스트 목적에 따라
             * 별도로 지정할 수 있도록 기본값을 NULL로 둡니다.
             */
            'position_id' => null,

            /**
             * 기본 시스템 역할
             *
             * 일반 직원 역할인 staff의 ID를 사용합니다.
             */
            'role_id' => Role::query()
                ->where('code', 'staff')
                ->value('id'),

            /**
             * 기본 재직 상태
             *
             * Factory로 생성되는 사용자는
             * 정상 재직 상태로 생성합니다.
             */
            'employment_status' => 'active',

            /**
             * 입사일
             */
            'hired_at' => now()->toDateString(),

            /**
             * 퇴사하지 않은 상태이므로 NULL입니다.
             */
            'resigned_at' => null,

            /**
             * 시스템 계정 활성화 여부
             */
            'is_active' => true,

            /**
             * 아직 로그인하지 않은 테스트 사용자이므로
             * 마지막 로그인 시간은 NULL입니다.
             */
            'last_login_at' => null,
        ];
    }

    /**
     * 휴직 상태의 사용자를 생성합니다.
     *
     * 예:
     * User::factory()->onLeave()->create();
     */
    public function onLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_status' => 'leave',
        ]);
    }

    /**
     * 퇴사 상태의 사용자를 생성합니다.
     *
     * 퇴사 상태와 함께 계정을 비활성화하고
     * 퇴사일을 현재 날짜로 설정합니다.
     *
     * 예:
     * User::factory()->resigned()->create();
     */
    public function resigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'employment_status' => 'resigned',
            'resigned_at' => now()->toDateString(),
            'is_active' => false,
        ]);
    }

    /**
     * 비활성화된 계정을 생성합니다.
     *
     * 재직 상태와 관계없이 시스템 로그인이
     * 차단된 사용자를 테스트할 때 사용할 수 있습니다.
     *
     * 예:
     * User::factory()->inactive()->create();
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}