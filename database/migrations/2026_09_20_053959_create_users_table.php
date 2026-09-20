<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 사용자(직원) 테이블 생성
     *
     * Till White 시스템을 사용하는 직원의
     * 현재 인사정보와 로그인 정보를 관리한다.
     *
     * 직원의 현재 소속 정보를 기준으로
     * 점포, 부서, 직급/직책, 시스템 역할을 연결한다.
     *
     * 과거 근무이력이나 인사이동 이력이 필요해질 경우에는
     * users 테이블에 계속 컬럼을 추가하지 않고
     * 별도의 employee_assignments 등의 이력 테이블로
     * 확장할 수 있도록 구성한다.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            /**
             * 사용자 고유 ID
             *
             * DB 내부에서 직원을 식별하기 위한 기본키(PK)이다.
             *
             * 생산, 폐기, 근무표, 감사로그 등에서
             * 직원 정보를 참조할 때 사용한다.
             */
            $table->id();

            /**
             * 사원번호
             *
             * 회사에서 사용하는 직원의 고유 사원번호이며
             * Till White 로그인 ID로도 사용한다.
             *
             * 별도의 login_id 컬럼은 만들지 않는다.
             *
             * 로그인 식별자로 사용되므로
             * 반드시 값이 존재해야 하며 중복될 수 없다.
             */
            $table->string('employee_code')->unique();

            /**
             * 직원 이름
             *
             * 직원관리, 근무표, 생산·폐기 기록 등
             * 시스템 전반에서 표시할 직원 이름이다.
             */
            $table->string('name');

            /**
             * 비밀번호
             *
             * 실제 비밀번호 원문을 저장하지 않고
             * Laravel에서 생성한 해시값만 저장한다.
             */
            $table->string('password');

            /**
             * 비밀번호 마지막 변경 일시
             *
             * 직원이 마지막으로 비밀번호를 변경한 시간을 저장한다.
             *
             * 최초 계정 생성 후 아직 비밀번호 변경 이력이 없거나
             * 해당 정보를 알 수 없는 상태가 존재할 수 있으므로
             * nullable로 관리한다.
             *
             * 향후 비밀번호 변경 안내 또는
             * 보안 정책을 추가할 때 사용할 수 있다.
             */
            $table->timestamp('password_changed_at')->nullable();

            /**
             * 휴대폰 번호
             *
             * 직원 연락처를 저장한다.
             *
             * 모든 직원의 연락처가 반드시 등록된다고
             * 보장할 수 없으므로 nullable로 관리한다.
             *
             * 전화번호는 계산에 사용하는 숫자가 아니므로
             * 문자열로 저장한다.
             */
            $table->string('phone')->nullable();

            /**
             * 생년월일
             *
             * 직원의 생년월일을 저장한다.
             *
             * 시간 정보는 필요하지 않으므로 date 타입을 사용하며,
             * 등록되지 않은 직원도 존재할 수 있으므로 nullable이다.
             */
            $table->date('birth_date')->nullable();

            /**
             * 현재 소속 점포
             *
             * stores 테이블의 id를 참조한다.
             *
             * 일반 점포 직원:
             * store_id = 실제 근무 점포 ID
             *
             * 본사 직원:
             * store_id = NULL
             *
             * 본사는 실제 점포가 아니므로
             * stores 테이블에 가짜 본사 데이터를 만들지 않는다.
             *
             * NULL이라는 이유만으로 전체 점포 접근 권한을
             * 부여해서는 안 된다.
             *
             * 실제 접근 가능 범위는 Laravel에서
             * 부서, 역할, 권한 등을 함께 확인하여 판단한다.
             */
            $table->foreignId('store_id')
                ->nullable()
                ->constrained('stores')
                ->restrictOnDelete();

            /**
             * 현재 소속 부서
             *
             * 직원이 현재 어느 업무 조직에 소속되어 있는지 저장한다.
             *
             * 현재 사용하는 값:
             *
             * kitchen
             * → 주방
             *
             * hall
             * → 홀
             *
             * head_office
             * → 본사
             *
             * DB enum으로 제한하지 않고 문자열로 저장한다.
             * 허용되는 부서 코드는 Laravel Validation에서 관리한다.
             *
             * 이를 통해 향후 부서가 추가되어도
             * DB 구조를 변경하지 않고 확장할 수 있다.
             */
            $table->string('department');

            /**
             * 현재 직급/직책
             *
             * positions 테이블의 id를 참조한다.
             *
             * position은 회사 내 인사상 직급 또는 직책이며
             * 시스템 권한을 의미하는 role과는 별개이다.
             *
             * 예:
             * 사원
             * 주임
             * 매니저
             * 헤드셰프
             *
             * 직원 등록 시 아직 직급/직책이 확정되지 않았거나
             * 별도 직급이 없는 경우를 허용하기 위해 nullable로 관리한다.
             */
            $table->foreignId('position_id')
                ->nullable()
                ->constrained('positions')
                ->restrictOnDelete();

            /**
             * 시스템 역할
             *
             * roles 테이블의 id를 참조한다.
             *
             * 모든 시스템 사용자는 반드시 하나의 역할을 가져야 하므로
             * nullable로 만들지 않는다.
             *
             * role은 권한 묶음이며,
             * 실제 기능 접근 여부는 role_permissions와
             * permissions를 통해 판단한다.
             */
            $table->foreignId('role_id')
                ->constrained('roles')
                ->restrictOnDelete();

            /**
             * 재직 상태
             *
             * 직원의 현재 인사 상태를 저장한다.
             *
             * 초기 사용 값:
             *
             * active
             * → 재직
             *
             * leave
             * → 휴직
             *
             * resigned
             * → 퇴사
             *
             * 단순 is_employed boolean 대신 문자열 상태를 사용하여
             * 향후 필요한 상태를 추가할 수 있도록 한다.
             *
             * 신규 직원은 기본적으로 재직 상태이므로
             * active를 기본값으로 사용한다.
             *
             * 허용되는 상태값은 Laravel Validation에서 관리한다.
             */
            $table->string('employment_status')->default('active');

            /**
             * 입사일
             *
             * 직원이 회사에 입사한 날짜를 저장한다.
             *
             * 기존 직원 데이터를 등록하는 과정에서
             * 정확한 입사일을 알 수 없는 경우가 있을 수 있으므로
             * nullable로 관리한다.
             *
             * 입사년도는 이 값에서 계산할 수 있으므로
             * 별도의 hire_year 컬럼은 만들지 않는다.
             */
            $table->date('hired_at')->nullable();

            /**
             * 퇴사일
             *
             * 직원이 퇴사한 날짜를 저장한다.
             *
             * 현재 재직 중인 직원은 퇴사일이 존재하지 않으므로
             * nullable로 관리한다.
             *
             * 퇴사년도 역시 이 값에서 계산할 수 있으므로
             * 별도의 resign_year 컬럼은 만들지 않는다.
             */
            $table->date('resigned_at')->nullable();

            /**
             * 계정 활성화 여부
             *
             * 직원의 재직 상태와 별도로
             * Till White 로그인 가능 여부를 관리한다.
             *
             * true(1)
             * → 로그인 가능한 계정
             *
             * false(0)
             * → 로그인 차단된 계정
             *
             * 예를 들어 재직 중인 직원이라도
             * 계정만 일시적으로 차단할 수 있다.
             */
            $table->boolean('is_active')->default(true);

            /**
             * 마지막 로그인 일시
             *
             * 사용자가 마지막으로 로그인에 성공한 시간을 저장한다.
             *
             * 아직 한 번도 로그인하지 않은 신규 계정은
             * 값이 존재하지 않으므로 nullable로 관리한다.
             */
            $table->timestamp('last_login_at')->nullable();

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             *
             * created_at = 사용자 데이터가 등록된 시간
             * updated_at = 사용자 데이터가 마지막으로 수정된 시간
             */
            $table->timestamps();

            $table->softDeletes();

            /**
             * 직원 조회용 인덱스
             *
             * 실제 화면에서는 특정 점포 및 부서의 직원을
             * 조회하는 경우가 많으므로 복합 인덱스를 생성한다.
             *
             * 예:
             * 무역점 주방 직원 목록
             * 무역점 홀 직원 목록
             *
             * employee_code의 경우 unique 자체가
             * 인덱스 역할을 하므로 별도 인덱스를 만들지 않는다.
             */
            $table->index([
                'store_id',
                'department',
            ]);
        });
    }

    /**
     * 사용자(직원) 테이블 삭제
     *
     * Migration을 rollback할 때 users 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};