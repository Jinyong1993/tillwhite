<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\DayOffRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\WorkCode;
use App\Models\WorkSchedule;
use App\Services\AccessService;
use App\Services\AuditService;
use Illuminate\Http\Request;

class WorkController extends Controller
{
    /**
     * 권한 검사와 감사 로그 서비스를 주입받습니다.
     */
    public function __construct(
        private AccessService $access,
        private AuditService $audit
    ) {
    }

    /**
     * 근무 관리 대시보드 데이터 조회
     *
     * 근무 스케줄, 출퇴근 기록, 휴가 신청,
     * 희망휴무 신청, 근무 코드를 반환합니다.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // 근무 스케줄 조회 권한 확인
        $this->access->requirePermission($user, 'schedule.view');

        // 근무 스케줄 조회
        $scheduleQuery = WorkSchedule::with([
            'user:id,name',
            'store:id,name',
            'workCode:id,code,name',
        ]);

        $this->scopeWork($scheduleQuery, $user);

        // 출퇴근 기록 조회
        $attendanceQuery = Attendance::with([
            'user:id,name',
            'store:id,name',
        ]);

        $this->scopeWork($attendanceQuery, $user);

        // 휴가 신청 조회
        $leaveQuery = LeaveRequest::with('user:id,name');

        // 희망휴무 신청 조회
        $dayOffQuery = DayOffRequest::with([
            'user:id,name',
            'dates',
        ]);

        /**
         * 점포 직원은 자신의 점포 및 부서 직원의
         * 휴가와 희망휴무 신청만 조회합니다.
         */
        if (! $user->isHeadOffice() && $user->role?->code !== 'super_admin') {
            $userIds = User::where('store_id', $user->store_id)
                ->where('department', $user->department)
                ->pluck('id');

            $leaveQuery->whereIn('user_id', $userIds);
            $dayOffQuery->whereIn('user_id', $userIds);
        }

        /**
         * 본사 직원은 본사 직원의
         * 휴가와 희망휴무 신청만 조회합니다.
         *
         * super_admin은 전체 신청을 조회합니다.
         */
        elseif ($user->role?->code !== 'super_admin') {
            $userIds = User::where('department', 'head_office')
                ->pluck('id');

            $leaveQuery->whereIn('user_id', $userIds);
            $dayOffQuery->whereIn('user_id', $userIds);
        }

        // 현재 사용자가 접근할 수 있는 활성 근무 코드 조회
        $workCodes = $this->access
            ->scopeStore(WorkCode::query(), $user)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'schedules' => $scheduleQuery
                ->orderByDesc('work_date')
                ->limit(100)
                ->get(),

            'attendances' => $attendanceQuery
                ->orderByDesc('id')
                ->limit(100)
                ->get(),

            'leave_requests' => $leaveQuery
                ->orderByDesc('id')
                ->limit(50)
                ->get(),

            'day_off_requests' => $dayOffQuery
                ->orderByDesc('id')
                ->limit(50)
                ->get(),

            'work_codes' => $workCodes,
        ]);
    }

    /**
     * 근무 스케줄 등록
     *
     * schedule.manage 권한이 필요합니다.
     * 동일한 직원과 날짜의 스케줄이 존재하면 수정합니다.
     */
    public function schedule(Request $request)
    {
        $user = $request->user();

        // 근무 스케줄 관리 권한 확인
        $this->access->requirePermission($user, 'schedule.manage');

        // 스케줄 입력값 검증
        $validated = $request->validate([
            'user_id' => [
                'required',
                'exists:users,id',
            ],
            'work_date' => [
                'required',
                'date',
            ],
            'work_code_id' => [
                'nullable',
                'exists:work_codes,id',
            ],
            'scheduled_start_time' => [
                'nullable',
            ],
            'scheduled_end_time' => [
                'nullable',
            ],
            'status' => [
                'required',
                'in:work,day_off,leave,absence',
            ],
            'note' => [
                'nullable',
                'string',
            ],
        ]);

        // 스케줄을 등록할 대상 직원 조회
        $targetUser = User::findOrFail($validated['user_id']);

        // 현재 사용자가 대상 직원의 근무를 관리할 수 있는지 확인
        $this->assertWorkManage($user, $targetUser);

        /**
         * 동일한 직원 + 근무일의 스케줄이 있으면 수정하고,
         * 없으면 새로운 스케줄을 생성합니다.
         */
        $schedule = WorkSchedule::updateOrCreate(
            [
                'user_id' => $targetUser->id,
                'work_date' => $validated['work_date'],
            ],
            [
                ...$validated,
                'store_id' => $targetUser->store_id,
                'department' => $targetUser->department,
                'scheduled_break_minutes' => 0,
                'source' => 'manual',
                'is_manually_modified' => true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]
        );

        // 근무 스케줄 저장 감사 로그 기록
        $this->audit->log(
            $user,
            'schedule',
            'save',
            WorkSchedule::class,
            $schedule->id,
            null,
            $schedule->toArray(),
            '근무 스케줄 저장'
        );

        return response()->json([
            'message' => '스케줄이 저장되었습니다.',
        ]);
    }

    /**
     * 본인 휴가 신청
     *
     * leave.view 권한이 필요합니다.
     */
    public function leave(Request $request)
    {
        $user = $request->user();

        // 휴가 기능 이용 권한 확인
        $this->access->requirePermission($user, 'leave.view');

        // 휴가 신청 입력값 검증
        $validated = $request->validate([
            'leave_type' => [
                'required',
                'in:annual,half_day',
            ],
            'day_unit' => [
                'required',
                'in:full_day,half_day',
            ],
            'start_date' => [
                'required',
                'date',
            ],
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.5',
            ],
            'reason' => [
                'required',
                'string',
            ],
        ]);

        // 휴가 신청 생성
        LeaveRequest::create([
            ...$validated,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => '휴가 신청이 등록되었습니다.',
        ], 201);
    }

    /**
     * 본인 희망휴무 신청
     *
     * 한 번에 최대 10개의 희망휴무 날짜를 신청할 수 있습니다.
     */
    public function dayOff(Request $request)
    {
        $user = $request->user();

        // 휴가 및 희망휴무 기능 이용 권한 확인
        $this->access->requirePermission($user, 'leave.view');

        // 희망휴무 입력값 검증
        $validated = $request->validate([
            'dates' => [
                'required',
                'array',
                'min:1',
                'max:10',
            ],
            'dates.*' => [
                'date',
            ],
            'reason' => [
                'nullable',
                'string',
            ],
        ]);

        // 입력된 날짜 중 가장 빠른 날짜를 기준으로 대상 연/월 결정
        $firstDate = collect($validated['dates'])
            ->sort()
            ->first();

        // 희망휴무 신청 기본 정보 생성
        $dayOffRequest = DayOffRequest::create([
            'user_id' => $user->id,
            'target_year' => (int) substr($firstDate, 0, 4),
            'target_month' => (int) substr($firstDate, 5, 2),
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
        ]);

        // 중복 날짜를 제거한 후 희망휴무 날짜 등록
        foreach (array_unique($validated['dates']) as $date) {
            $dayOffRequest->dates()->create([
                'request_date' => $date,
            ]);
        }

        return response()->json([
            'message' => '희망휴무 신청이 등록되었습니다.',
        ], 201);
    }

    /**
     * 휴가 또는 희망휴무 신청 승인/반려
     *
     * leave.manage 권한이 필요합니다.
     */
    public function review(
        Request $request,
        string $type,
        int $id
    ) {
        $user = $request->user();

        // 휴가 및 희망휴무 관리 권한 확인
        $this->access->requirePermission($user, 'leave.manage');

        // 승인/반려 정보 검증
        $validated = $request->validate([
            'status' => [
                'required',
                'in:approved,rejected',
            ],
            'review_note' => [
                'nullable',
                'string',
            ],
        ]);

        // 신청 종류에 따라 해당 모델 조회
        $requestModel = $type === 'leave'
            ? LeaveRequest::findOrFail($id)
            : DayOffRequest::findOrFail($id);

        // 신청 대상 직원 조회
        $targetUser = User::findOrFail($requestModel->user_id);

        // 현재 사용자가 해당 직원의 신청을 관리할 수 있는지 확인
        $this->assertWorkManage($user, $targetUser);

        // 승인 또는 반려 처리
        $requestModel->update([
            ...$validated,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => '신청 상태가 변경되었습니다.',
        ]);
    }

    /**
     * 근무 데이터 조회 범위 적용
     *
     * super_admin : 전체
     * 본사 직원    : 본사 근무 데이터
     * 점포 직원    : 자신의 점포 + 부서
     */
    private function scopeWork($query, User $user): void
    {
        // super_admin은 전체 근무 데이터 조회 가능
        if ($user->role?->code === 'super_admin') {
            return;
        }

        // 본사 직원은 store_id가 없는 본사 데이터만 조회
        if ($user->isHeadOffice()) {
            $query->whereNull('store_id');

            return;
        }

        // 점포 직원은 자신의 점포 및 부서 데이터만 조회
        $query
            ->where('store_id', $user->store_id)
            ->where('department', $user->department);
    }

    /**
     * 근무 관리 대상 범위 확인
     *
     * 현재 사용자가 대상 직원의 근무 정보를
     * 관리할 수 있는 범위인지 검사합니다.
     */
    private function assertWorkManage(
        User $user,
        User $targetUser
    ): void {
        // super_admin은 모든 직원의 근무 관리 가능
        if ($user->role?->code === 'super_admin') {
            return;
        }

        // 본사 관리자는 본사 직원의 근무만 관리 가능
        if ($user->isHeadOffice()) {
            abort_unless(
                $targetUser->isHeadOffice(),
                403,
                '본사 관리자는 본사 직원의 근무만 관리할 수 있습니다.'
            );

            return;
        }

        // 점포 관리자는 동일한 점포 및 부서의 직원만 관리 가능
        abort_unless(
            $targetUser->store_id === $user->store_id
                && $targetUser->department === $user->department,
            403,
            '다른 점포 또는 부서의 근무는 관리할 수 없습니다.'
        );
    }
}