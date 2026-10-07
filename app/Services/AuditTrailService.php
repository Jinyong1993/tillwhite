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

    /**
     * 대상의 변경 이력을 최신순으로 반환합니다.
     * 상세 화면에서는 요약 정보와 분리해 필요할 때만 펼쳐볼 수 있습니다.
     * 복구는 현재 삭제 정보에는 포함하지 않지만 과거 작업 이력에는 반드시 보존합니다.
     */
    public function history(string $targetType, int $targetId, int $limit = 30): array
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->whereIn('action', ['create', 'update', 'delete', 'restore'])
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                ] : null,
                'at' => $log->created_at?->toISOString(),
            ])
            ->all();
    }

    // 감사 로그 한 건을 화면에서 사용할 공통 이력 구조로 변환합니다.
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
