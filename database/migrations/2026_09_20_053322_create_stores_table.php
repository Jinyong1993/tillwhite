<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 점포 테이블 생성
     *
     * Till White에서 운영하는 실제 점포의 기본 정보를 관리한다.
     *
     * 생산, 폐기, 제품, 매출, 직원 등의 업무 데이터는
     * store_id를 통해 어느 점포의 데이터인지 구분할 수 있다.
     *
     * 본사는 실제 점포가 아니므로 stores 테이블에 등록하지 않는다.
     * 본사 직원은 users.store_id를 null로 관리한다.
     */
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {

            /**
             * 점포 고유 ID
             *
             * DB 내부에서 점포를 구분하기 위한 기본키(PK)이다.
             *
             * users, products 관련 데이터, 생산, 폐기, 매출 등의
             * 여러 업무 테이블에서 store_id로 참조할 수 있다.
             */
            $table->id();

            /**
             * 점포 고유 코드
             *
             * 프로그램 내부에서 점포를 안정적으로 식별하기 위한 코드이다.
             *
             * 점포명이 변경되더라도 이 코드는 유지할 수 있으며,
             * 동일한 점포 코드를 중복 등록할 수 없도록 unique로 관리한다.
             *
             * 예:
             *
             * MUYEOK
             * THE_HYUNDAI_SEOUL
             */
            $table->string('store_code')->unique();

            /**
             * 점포명
             *
             * 사용자 화면에 표시되는 실제 점포 이름이다.
             *
             * 예:
             *
             * 무역점
             * 더현대서울점
             *
             * 동일한 점포명이 중복 등록되어 혼동되는 것을 방지하기 위해
             * unique로 관리한다.
             */
            $table->string('name')->unique();

            /**
             * 점포 대표 전화번호
             *
             * 점포관리 화면에서 연락처 정보로 사용할 수 있다.
             *
             * 모든 점포의 전화번호를 반드시 알고 있어야 하는
             * 핵심 업무 데이터는 아니므로 nullable로 관리한다.
             */
            $table->string('phone')->nullable();

            /**
             * 점포 주소
             *
             * 점포관리 화면에서 점포의 위치 정보를 표시하기 위해 사용한다.
             *
             * 점포 등록 시 정확한 주소가 아직 확정되지 않았거나
             * 기존 점포의 주소 정보가 없는 경우를 고려하여 nullable로 관리한다.
             */
            $table->string('address')->nullable();

            /**
             * 점포 GPS 위도
             *
             * 직원이 QR을 이용하여 출퇴근할 때
             * 현재 위치가 실제 점포 주변인지 확인하기 위한
             * 기준 좌표로 사용한다.
             *
             * 점포 등록 시 좌표 정보가 아직 설정되지 않은
             * 경우를 고려하여 nullable로 관리한다.
             *
             * 좌표가 설정되지 않은 점포에서는
             * GPS 출퇴근 검증을 정상 처리하지 않고
             * 점포 위치 설정이 필요하도록 Laravel에서 처리한다.
             */
            $table->decimal('latitude', 10, 7)->nullable();

            /**
             * 점포 GPS 경도
             *
             * latitude와 함께 직원의 현재 GPS 위치와
             * 점포 사이의 거리를 계산하는 데 사용한다.
             *
             * 점포 등록 시 좌표 정보가 아직 설정되지 않은
             * 경우를 고려하여 nullable로 관리한다.
             */
            $table->decimal('longitude', 10, 7)->nullable();

            /**
             * 출퇴근 GPS 허용 반경
             *
             * 직원의 현재 위치가 점포 좌표로부터
             * 몇 미터 이내에 있어야 출퇴근을 허용할지 설정한다.
             *
             * 예:
             *
             * 50  = 점포 기준 50m 이내
             * 100 = 점포 기준 100m 이내
             * 150 = 점포 기준 150m 이내
             *
             * 기본값은 100m로 설정한다.
             *
             * 실제 GPS에는 건물 내부, 지하, 주변 고층건물 등에 따라
             * 위치 오차가 발생할 수 있으므로 점포별로
             * 허용 반경을 변경할 수 있도록 한다.
             */
            $table->unsignedInteger('attendance_radius_meters')
                ->default(100);

            /**
             * 점포 운영 상태
             *
             * 점포의 현재 운영 상태를 나타낸다.
             *
             * 기본값은 active이며 다음 값을 사용한다.
             *
             * active
             * → 정상 운영중
             *
             * inactive
             * → 일시 비활성
             *
             * closed
             * → 폐점
             *
             * 폐점한 점포도 과거 생산, 폐기, 매출 등의 기록과
             * 연결되어 있으므로 실제 데이터를 삭제하지 않고
             * 상태값으로 관리한다.
             */
            $table->string('status')->default('active');

            /**
             * 점포 표시 순서
             *
             * 점포관리 목록이나 본사 직원이 사용하는
             * 점포 선택 목록에서 일정한 순서로
             * 표시하기 위해 사용한다.
             *
             * 별도의 순서를 지정하지 않은 점포는 0을 사용한다.
             *
             * null과 0을 별도로 처리할 필요가 없도록
             * nullable이 아닌 기본값 0으로 관리한다.
             */
            $table->unsignedInteger('sort_order')->default(0);

            /**
             * 점포 오픈일
             *
             * 해당 점포가 실제 영업을 시작한 날짜를 기록한다.
             *
             * 기존 점포를 시스템에 처음 등록하는 경우
             * 정확한 오픈일을 알 수 없을 수도 있으므로
             * nullable로 관리한다.
             */
            $table->date('opened_at')->nullable();

            /**
             * 점포 폐점일
             *
             * 점포가 실제 영업을 종료한 날짜를 기록한다.
             *
             * 정상 운영중인 점포는 폐점일이 존재하지 않으므로
             * nullable로 관리한다.
             *
             * 점포가 폐점하면 일반적으로
             *
             * status = closed
             * closed_at = 실제 폐점일
             *
             * 상태로 관리한다.
             */
            $table->date('closed_at')->nullable();

            /**
             * 데이터 생성 일시 / 마지막 수정 일시
             *
             * created_at
             * → 점포가 시스템에 등록된 시간
             *
             * updated_at
             * → 점포 정보가 마지막으로 수정된 시간
             */
            $table->timestamps();

            /**
             * 시스템상 삭제 일시
             *
             * 잘못 등록한 점포 등을 시스템상 삭제 처리할 때 사용한다.
             *
             * 실제 영업했던 점포가 폐점했다는 이유만으로
             * Soft Delete 처리하지 않는다.
             *
             * 실제 폐점은 status와 closed_at으로 관리하여
             * 과거 생산, 폐기, 매출, 직원 등의 기록에서
             * 해당 점포 정보를 계속 참조할 수 있도록 한다.
             */
            $table->softDeletes();
        });
    }

    /**
     * 점포 테이블 삭제
     *
     * Migration을 rollback할 때
     * stores 테이블을 제거한다.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};