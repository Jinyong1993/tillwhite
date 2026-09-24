<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    /**
     * 시스템 설정 정보
     *
     * Till White 시스템 전체에서 사용하는
     * 공통 설정값을 저장합니다.
     *
     * 각각의 설정은 key와 value 형태로 관리하며,
     * 설정 종류에 따라 type을 함께 저장합니다.
     *
     * 예:
     * key   → 특정 시스템 설정을 식별하는 고유 코드
     * value → 실제 설정값
     * type  → 설정값의 자료형 또는 처리 방식
     *
     * 시스템 설정을 코드에 직접 고정하지 않고
     * DB에서 관리해야 하는 경우 사용합니다.
     *
     * 이를 통해 관리자 화면에서 설정값을 변경하더라도
     * 프로그램 코드를 직접 수정하지 않고
     * 시스템 동작을 조정할 수 있습니다.
     */

    /**
     * 모든 컬럼의 대량 할당을 허용합니다.
     *
     * 실제 설정값의 입력 검증과
     * 시스템 설정 변경 권한 검사는
     * Controller 및 Service에서 처리합니다.
     *
     * 시스템 설정은 시스템 전체 동작에 영향을 줄 수 있으므로
     * 일반 사용자가 임의로 수정하지 못하도록
     * 권한 검사가 중요합니다.
     */
    protected $guarded = [];
}