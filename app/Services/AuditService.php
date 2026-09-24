<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditService
{
    /**
     * 시스템에서 발생한 주요 변경 이력을 감사 로그에 기록합니다.
     *
     * 직원, 점포, 생산 기록, 상품, 매출, 시스템 설정 등
     * 중요한 데이터가 생성·수정·삭제되었을 때
     * 해당 작업의 내용을 audit_logs 테이블에 저장합니다.
     *
     * 이를 통해 나중에 다음과 같은 내용을 추적할 수 있습니다.
     *
     * - 누가 작업했는지
     * - 어느 기능에서 발생한 작업인지
     * - 어떤 작업을 수행했는지
     * - 어떤 데이터를 대상으로 했는지
     * - 변경 전 값이 무엇이었는지
     * - 변경 후 값이 무엇인지
     * - 어떤 IP에서 요청했는지
     *
     * 감사 로그는 일반적인 업무 데이터처럼 수정하거나
     * 삭제하는 용도가 아니라 변경 이력을 추적하기 위한
     * 기록 데이터로 사용합니다.
     *
     * 예:
     *
     * domain
     * - production
     * - product
     * - sales
     * - employee
     * - store
     * - system
     *
     * action
     * - create
     * - update
     * - delete
     *
     * targetType
     * - 변경 대상의 종류
     *
     * targetId
     * - 변경 대상 데이터의 ID
     *
     * oldValues
     * - 변경 전 데이터
     *
     * newValues
     * - 변경 후 데이터
     *
     * description
     * - 사람이 확인하기 쉬운 작업 설명
     */
    public function log(
        User $user,
        string $domain,
        string $action,
        ?string $targetType,
        ?int $targetId,
        ?array $oldValues,
        ?array $newValues,
        string $description
    ): void {
        AuditLog::create([
            /**
             * 실제 작업을 수행한 사용자
             */
            'user_id' => $user->id,

            /**
             * 작업이 발생한 업무 영역
             *
             * 예:
             * production, product, sales, employee 등
             */
            'domain' => $domain,

            /**
             * 수행된 작업의 종류
             *
             * 예:
             * create, update, delete 등
             */
            'action' => $action,

            /**
             * 변경 대상 데이터의 종류
             */
            'target_type' => $targetType,

            /**
             * 변경 대상 데이터의 ID
             */
            'target_id' => $targetId,

            /**
             * 변경 전 데이터
             *
             * AuditLog 모델에서 array cast를 사용하므로
             * 배열 형태로 전달할 수 있습니다.
             */
            'old_values' => $oldValues,

            /**
             * 변경 후 데이터
             *
             * 신규 생성의 경우 이전 값이 없으므로
             * old_values는 NULL이 될 수 있으며,
             * 삭제의 경우 반대로 new_values가
             * NULL이 될 수 있습니다.
             */
            'new_values' => $newValues,

            /**
             * 감사 로그 화면에서 확인할 수 있는
             * 작업 설명입니다.
             */
            'description' => $description,

            /**
             * 해당 작업을 요청한 클라이언트의 IP 주소
             *
             * Laravel의 현재 HTTP Request에서
             * 요청자의 IP 주소를 가져와 저장합니다.
             */
            'ip_address' => request()->ip(),
        ]);
    }
}