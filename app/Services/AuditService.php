<?php
namespace App\Services;
use App\Models\AuditLog;
use App\Models\User;
class AuditService {
    /** 변경 이력을 감사 로그에 기록한다. */
    public function log(User $user, string $domain, string $action, ?string $targetType, ?int $targetId, ?array $oldValues, ?array $newValues, string $description): void {
        AuditLog::create(['user_id'=>$user->id,'domain'=>$domain,'action'=>$action,'target_type'=>$targetType,'target_id'=>$targetId,'old_values'=>$oldValues,'new_values'=>$newValues,'description'=>$description,'ip_address'=>request()->ip()]);
    }
}
