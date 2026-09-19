<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 사용자 테이블 생성
     *
     * Till White 시스템을 사용하는 직원의 계정 및
     * 현재 소속 정보를 관리한다.
     *
     * 사용자의 점포, 부서, 역할, 재직 상태와 계정 상태를 관리하며
     * 생산, 폐기, 스케줄 등의 업무 데이터에서는 user_id를 통해
     * 실제 작업자 또는 입력자를 식별할 수 있도록 한다.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {

            /**
             * 사용자 고유 ID
             *
             * DB 내부에서 사용자를 구분하기 위한 기본키(PK)이다.
             *
             * 향후 생산, 폐기, 스케줄, 변경 이력 등의 테이블에서
             * worker_id, created_by, updated_by 등의 값으로
             * 이 사용자 ID를 참조할 수 있다.
             */
            $table->id();

            /**
             * 로그인 아이디
             *
             * 사용자가 Till White 시스템에 로그인할 때 사용하는
             * 고유한 아이디이다.
             *
             * 동일한 로그인 아이디를 여러 사용자가 사용할 수 없도록
             * unique 제약조건을 사용한다.
             */
            $table->string('login_id')->unique();

            /**
             * 사용자 이름
             *
             * 시스템 화면에서 표시되는 직원의 이름이다.
             *
             * 생산자, 폐기 작업자, 입력자, 수정자 등의
             * 사용자 정보를 표시할 때 사용한다.
             */
            $table->string('name');

            /**
             * 로그인 비밀번호
             *
             * 사용자의 로그인 인증에 사용하는 비밀번호이다.
             *
             * 실제 비밀번호 원문을 저장하지 않고
             * Laravel의 Hash 기능을 통해 암호화된 값만 저장한다.
             */
            $table->string('password');

            /**
             * 소속 점포
             *
             * 사용자가 현재 소속되어 있는 점포를 나타낸다.
             *
             * stores 테이블의 id를 참조하며,
             * 로그인 이후 기본적으로 사용자가 접근할 수 있는
             * 점포 범위를 판단할 때 사용한다.
             *
             * 사용자가 다른 점포로 이동하더라도 과거 생산 및 폐기 기록은
             * 각 업무 기록에 저장된 store_id를 기준으로 유지한다.
             */
            $table->foreignId('store_id')
                ->constrained('stores');

            /**
             * 담당 부서
             *
             * 사용자가 현재 담당하고 있는 업무 부서를 나타낸다.
             *
             * 기본적으로 다음 값을 사용한다.
             *
             * kitchen    = 주방
             * hall       = 홀
             * operations = 운영진 / 운영관리
             *
             * 역할(Role)과 부서는 서로 다른 개념이다.
             *
             * department는 사용자가 어느 업무 영역에 소속되어 있는지를 나타내고,
             * role은 해당 사용자가 어떤 권한 묶음을 가지는지를 나타낸다.
             */
            $table->string('department');

            /**
             * 사용자 역할
             *
             * roles 테이블의 id를 참조한다.
             *
             * 기존처럼 users 테이블에 admin, user 등의 문자열을
             * 직접 저장하지 않고 역할을 별도의 테이블에서 관리한다.
             *
             * 역할과 permissions를 연결하여 생산, 폐기, 스케줄 등
             * 각 기능에 대한 권한을 확장할 수 있도록 한다.
             */
            $table->foreignId('role_id')
                ->constrained('roles');

            /**
             * 재직 상태
             *
             * true(1)  = 현재 재직중
             * false(0) = 퇴사
             *
             * 직원이 퇴사하더라도 과거 생산, 폐기, 스케줄 및
             * 변경 이력 등을 보존해야 하므로 사용자 데이터를 삭제하지 않고
             * 재직 상태를 통해 퇴사 여부를 관리한다.
             */
            $table->boolean('is_employed')->default(true);

            /**
             * 계정 사용 가능 여부
             *
             * true(1)  = 로그인 및 시스템 사용 가능
             * false(0) = 계정 사용 중지
             *
             * is_employed와는 별개의 값이다.
             *
             * 예를 들어 현재 재직중인 직원이라도 계정을 일시 정지해야 한다면
             * is_employed = true
             * is_active = false
             * 상태로 관리할 수 있다.
             */
            $table->boolean('is_active')->default(true);

            /**
             * 입사일
             *
             * 직원이 실제로 입사한 날짜를 기록한다.
             *
             * 기존 직원의 정확한 입사일을 알 수 없는 경우도
             * 있을 수 있으므로 nullable로 관리한다.
             *
             * 시간까지 관리할 필요는 없으므로
             * timestamp가 아닌 date 타입을 사용한다.
             */
            $table->date('hired_at')->nullable();

            /**
             * 퇴사일
             *
             * 직원이 실제로 퇴사한 날짜를 기록한다.
             *
             * 재직중:
             * is_employed = true
             * resigned_at = null
             *
             * 퇴사:
             * is_employed = false
             * resigned_at = 실제 퇴사일
             *
             * 시간까지 관리할 필요는 없으므로
             * timestamp가 아닌 date 타입을 사용한다.
             */
            $table->date('resigned_at')->nullable();

            /**
             * 마지막 로그인 일시
             *
             * 사용자가 시스템에 마지막으로 정상 로그인한
             * 날짜와 시간을 기록한다.
             *
             * 계정 사용 여부 확인이나 관리자 화면에서
             * 최근 시스템 이용 여부를 확인할 때 사용할 수 있다.
             *
             * 아직 로그인한 적이 없는 사용자는
             * 값이 없으므로 nullable로 관리한다.
             */
            $table->timestamp('last_login_at')->nullable();

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             *
             * created_at = 사용자 계정이 시스템에 등록된 시간
             * updated_at = 사용자 정보가 마지막으로 수정된 시간
             */
            $table->timestamps();

            /**
             * 시스템상 삭제 일시 (Soft Delete)
             *
             * deleted_at = null
             * → 정상적으로 존재하는 사용자 데이터
             *
             * deleted_at에 값이 존재
             * → 시스템에서 삭제 처리된 사용자 데이터
             *
             * 퇴사와 시스템상 삭제는 서로 다른 개념이다.
             *
             * resigned_at
             * → 실제 직원이 퇴사한 날짜
             *
             * deleted_at
             * → 시스템에서 사용자 데이터를 삭제 처리한 시간
             *
             * 사용자가 작성하거나 작업한 생산, 폐기 등의 과거 기록을
             * 보존하기 위해 실제 DB 행을 바로 삭제하지 않고
             * Soft Delete를 사용한다.
             */
            $table->softDeletes();
        });

        /**
         * 세션 테이블 생성
         *
         * Laravel의 데이터베이스 세션을 저장하기 위한 테이블이다.
         *
         * 로그인한 사용자와 세션 정보, 접속 환경 및
         * 마지막 활동 시간 등을 저장한다.
         */
        Schema::create('sessions', function (Blueprint $table) {

            // 세션 고유 ID
            $table->string('id')->primary();

            /**
             * 로그인한 사용자 ID
             *
             * 로그인하지 않은 방문자의 세션도 존재할 수 있으므로
             * nullable로 관리한다.
             */
            $table->foreignId('user_id')
                ->nullable()
                ->index();

            // 접속 IP 주소
            $table->string('ip_address', 45)->nullable();

            // 브라우저 및 접속 환경 정보
            $table->text('user_agent')->nullable();

            // Laravel에서 관리하는 실제 세션 데이터
            $table->longText('payload');

            // 세션의 마지막 활동 시간
            $table->integer('last_activity')->index();
        });
    }

    /**
     * 사용자 및 세션 테이블 삭제
     *
     * Migration을 rollback할 때 sessions 테이블을 먼저 제거한 후
     * users 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};