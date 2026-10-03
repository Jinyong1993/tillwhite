<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditTrailService
{
    /**
     * 상세 화면에서 공통으로 사용하는 등록/수정/삭제 작업자 정보를 반환합니다.
     *
     * 업무 테이블에 작업자 컬럼을 중복 추가하지 않고 이미 기록 중인 감사 로그를
     * 단일 진실 공급원으로 사용합니다. 상태 변경도 update로 기록되므로 마지막 수정자에 포함됩니다.
     */
    public function summary(string $targetType, int $targetId): array
    {
        $logs = AuditLog::query()
            ->with('user:id,name')
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->whereIn('action', ['create', 'update', 'delete'])
            ->orderBy('created_at')
            ->get();

        return [
            'created' => $this->entry($logs->firstWhere('action', 'create')),
            'updated' => $this->entry($logs->where('action', 'update')->last()),
            'deleted' => $this->entry($logs->where('action', 'delete')->last()),
        ];
    }

    private function entry($log): ?array
    {
        if (! $log) {
            return null;
        }

        return [
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
            ] : null,
            'at' => $log->created_at?->toISOString(),
        ];
    }
}
