<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 시스템 설정 테이블 생성
     *
     * 애플리케이션 전체에서 공통으로 사용하는
     * 운영 설정값을 관리한다.
     *
     * 운영 중 변경될 가능성이 있는 값을
     * 코드에 직접 하드코딩하지 않고 DB에서 관리하여
     * 시스템 관리 화면에서 변경할 수 있도록 한다.
     *
     * 점포별로 서로 다른 설정이 필요한 경우에는
     * 이 테이블에 store_id를 추가하지 않고
     * 향후 별도의 store_settings 구조로 분리한다.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {

            /**
             * 시스템 설정 고유 ID
             */
            $table->id();

            /**
             * 설정 키
             *
             * 애플리케이션에서 설정값을 조회할 때
             * 사용하는 고유한 식별자이다.
             *
             * 예:
             *
             * overtime_unit_minutes
             * day_off_request_months_ahead
             * monthly_day_off_limit
             * recipe_image_max_size_mb
             * recipe_image_max_count
             *
             * 같은 설정이 중복 등록되면 안 되므로
             * UNIQUE 제약조건을 적용한다.
             */
            $table->string('key')
                ->unique();

            /**
             * 설정값
             *
             * 설정마다 값의 자료형이 다를 수 있으므로
             * 문자열 형태로 저장한다.
             *
             * 실제 사용할 때는 type 값을 기준으로
             * Laravel에서 적절한 자료형으로 변환한다.
             *
             * 예:
             *
             * 15
             * 3
             * 10
             * true
             * Till White
             */
            $table->text('value');

            /**
             * 설정값 자료형
             *
             * value를 Laravel에서 어떤 자료형으로
             * 변환할지 판단하기 위한 값이다.
             *
             * 예:
             *
             * string
             * integer
             * decimal
             * boolean
             * json
             *
             * DB enum으로 고정하지 않고 문자열로 저장한다.
             *
             * 허용 가능한 자료형과 변환 방법은
             * Laravel에서 관리한다.
             */
            $table->string('type');

            /**
             * 설정 설명
             *
             * 시스템 관리자가 해당 설정의
             * 용도를 쉽게 이해할 수 있도록
             * 설명을 저장한다.
             *
             * 필수 정보는 아니므로 NULL을 허용한다.
             */
            $table->text('description')
                ->nullable();

            /**
             * 마지막 수정자
             *
             * 해당 설정을 마지막으로 변경한
             * users.id를 저장한다.
             *
             * 초기 Seeder에서 생성되어 아직 사용자가
             * 수정하지 않은 설정도 존재할 수 있으므로
             * NULL을 허용한다.
             */
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             */
            $table->timestamps();
        });
    }

    /**
     * 시스템 설정 테이블 삭제
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};