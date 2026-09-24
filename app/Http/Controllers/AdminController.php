<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Store;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AccessService;
use App\Services\AuditService;
use Illuminate\Http\Request;

class AdminController extends Controller
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
     * 직원 목록 조회
     *
     * employee.view 권한이 필요합니다.
     * 점포 직원은 자신의 점포 직원만 조회할 수 있습니다.
     * 본사 직원과 super_admin은 전체 직원을 조회할 수 있습니다.
     */
    public function employees(Request $request)
    {
        $user = $request->user();

        // 직원 조회 권한 확인
        $this->access->requirePermission($user, 'employee.view');

        // 직원과 소속 점포, 직급, 역할 정보를 함께 조회
        $query = User::with([
            'store:id,name',
            'position:id,name',
            'role:id,code,name',
        ])->orderBy('name');

        // 일반 점포 직원은 자신의 점포 직원만 조회
        if (! $user->isHeadOffice() && $user->role?->code !== 'super_admin') {
            $query->where('store_id', $user->store_id);
        }

        // 직원 등록 화면에서 사용할 선택 항목도 함께 반환
        return response()->json([
            'employees' => $query->get(),

            'stores' => Store::where('status', 'active')
                ->orderBy('sort_order')
                ->get(['id', 'name']),

            'positions' => Position::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']),

            'roles' => Role::where('is_active', true)
                ->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * 직원 등록
     *
     * employee.manage 권한이 필요합니다.
     */
    public function employeeStore(Request $request)
    {
        $user = $request->user();

        // 직원 관리 권한 확인
        $this->access->requirePermission($user, 'employee.manage');

        // 입력값 검증
        $validated = $request->validate([
            'employee_code' => [
                'required',
                'string',
                'max:255',
                'unique:users,employee_code',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'min:4',
            ],
            'store_id' => [
                'nullable',
                'exists:stores,id',
            ],
            'department' => [
                'required',
                'in:kitchen,hall,head_office',
            ],
            'position_id' => [
                'nullable',
                'exists:positions,id',
            ],
            'role_id' => [
                'required',
                'exists:roles,id',
            ],
        ]);

        /**
         * 본사 직원은 특정 점포에 소속되지 않습니다.
         *
         * 점포 직원은 반드시 소속 점포가 있어야 합니다.
         */
        if ($validated['department'] === 'head_office') {
            $validated['store_id'] = null;
        } else {
            abort_if(
                $validated['store_id'] === null,
                422,
                '점포 직원은 점포를 선택해야 합니다.'
            );
        }

        // 신규 직원 생성
        $employee = User::create([
            ...$validated,
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        // 직원 등록 감사 로그 기록
        $this->audit->log(
            $user,
            'employee',
            'create',
            User::class,
            $employee->id,
            null,
            $employee->toArray(),
            '직원 등록'
        );

        return response()->json([
            'message' => '직원이 등록되었습니다.',
        ], 201);
    }

    /**
     * 직원 재직 상태 및 계정 상태 변경
     *
     * employee.manage 권한이 필요합니다.
     */
    public function employeeStatus(Request $request, User $user)
    {
        $actor = $request->user();

        // 직원 관리 권한 확인
        $this->access->requirePermission($actor, 'employee.manage');

        // 변경할 상태 검증
        $validated = $request->validate([
            'employment_status' => [
                'required',
                'in:active,leave,resigned',
            ],
            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        // 변경 전 상태를 감사 로그용으로 저장
        $oldData = $user->toArray();

        // 직원 상태 변경
        $user->update([
            ...$validated,

            // 퇴사 상태라면 현재 날짜를 퇴사일로 기록
            'resigned_at' => $validated['employment_status'] === 'resigned'
                ? now()->toDateString()
                : null,
        ]);

        // 직원 상태 변경 감사 로그 기록
        $this->audit->log(
            $actor,
            'employee',
            'update',
            User::class,
            $user->id,
            $oldData,
            $user->toArray(),
            '직원 상태 변경'
        );

        return response()->json([
            'message' => '직원 상태가 변경되었습니다.',
        ]);
    }

    /**
     * 점포 목록 조회
     *
     * store.view 권한이 필요합니다.
     */
    public function stores(Request $request)
    {
        // 점포 조회 권한 확인
        $this->access->requirePermission(
            $request->user(),
            'store.view'
        );

        return response()->json([
            'stores' => Store::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * 점포 등록
     *
     * store.manage 권한이 필요합니다.
     */
    public function storeStore(Request $request)
    {
        $user = $request->user();

        // 점포 관리 권한 확인
        $this->access->requirePermission($user, 'store.manage');

        // 점포 입력값 검증
        $validated = $request->validate([
            'store_code' => [
                'required',
                'string',
                'unique:stores,store_code',
            ],
            'name' => [
                'required',
                'string',
                'unique:stores,name',
            ],
            'phone' => [
                'nullable',
                'string',
            ],
            'address' => [
                'nullable',
                'string',
            ],
            'attendance_radius_meters' => [
                'required',
                'integer',
                'min:10',
            ],
            'status' => [
                'required',
                'in:active,inactive,closed',
            ],
        ]);

        // 신규 점포 생성
        $store = Store::create($validated);

        // 점포 등록 감사 로그 기록
        $this->audit->log(
            $user,
            'store',
            'create',
            Store::class,
            $store->id,
            null,
            $store->toArray(),
            '점포 등록'
        );

        return response()->json([
            'message' => '점포가 등록되었습니다.',
        ], 201);
    }

    /**
     * 시스템 설정, 역할, 권한, 직급 조회
     *
     * system.view 권한이 필요합니다.
     */
    public function system(Request $request)
    {
        // 시스템 조회 권한 확인
        $this->access->requirePermission(
            $request->user(),
            'system.view'
        );

        return response()->json([
            // 시스템 설정
            'settings' => SystemSetting::orderBy('key')->get(),

            // 역할 및 역할별 권한
            'roles' => Role::with('permissions:id,code,name')->get(),

            // 현재 활성화된 권한
            'permissions' => Permission::where('is_active', true)
                ->orderBy('code')
                ->get(),

            // 회사 직급
            'positions' => Position::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * 시스템 설정 저장
     *
     * system.manage 권한이 필요합니다.
     */
    public function setting(Request $request)
    {
        $user = $request->user();

        // 시스템 관리 권한 확인
        $this->access->requirePermission($user, 'system.manage');

        // 시스템 설정 입력값 검증
        $validated = $request->validate([
            'key' => [
                'required',
                'string',
                'max:255',
            ],
            'value' => [
                'required',
            ],
            'type' => [
                'required',
                'in:string,integer,boolean,json',
            ],
            'description' => [
                'nullable',
                'string',
            ],
        ]);

        /**
         * 동일한 key가 존재하면 수정하고,
         * 존재하지 않으면 새로운 설정을 생성합니다.
         */
        $setting = SystemSetting::updateOrCreate(
            [
                'key' => $validated['key'],
            ],
            [
                ...$validated,

                // 배열 값은 JSON 문자열로 변환하여 저장
                'value' => is_array($validated['value'])
                    ? json_encode($validated['value'], JSON_UNESCAPED_UNICODE)
                    : (string) $validated['value'],

                'updated_by' => $user->id,
            ]
        );

        // 시스템 설정 변경 감사 로그 기록
        $this->audit->log(
            $user,
            'system',
            'save',
            SystemSetting::class,
            $setting->id,
            null,
            $setting->toArray(),
            '시스템 설정 저장'
        );

        return response()->json([
            'message' => '설정이 저장되었습니다.',
        ]);
    }

    /**
     * 역할별 권한 동기화
     *
     * system.manage 권한이 필요합니다.
     */
    public function rolePermissions(Request $request, Role $role)
    {
        $user = $request->user();

        // 시스템 관리 권한 확인
        $this->access->requirePermission($user, 'system.manage');

        // 전달받은 권한 코드 검증
        $validated = $request->validate([
            'permissions' => [
                'required',
                'array',
            ],
            'permissions.*' => [
                'string',
                'exists:permissions,code',
            ],
        ]);

        // 권한 코드를 Permission ID 목록으로 변환
        $permissionIds = Permission::whereIn(
            'code',
            $validated['permissions']
        )->pluck('id');

        // 기존 역할 권한을 전달받은 권한 목록으로 동기화
        $role->permissions()->sync($permissionIds);

        // 역할 권한 변경 감사 로그 기록
        $this->audit->log(
            $user,
            'system',
            'permissions',
            Role::class,
            $role->id,
            null,
            [
                'permissions' => $validated['permissions'],
            ],
            '역할 권한 변경'
        );

        return response()->json([
            'message' => '역할 권한이 저장되었습니다.',
        ]);
    }

    /**
     * 감사 로그 조회
     *
     * audit.view 권한이 필요합니다.
     *
     * 점포 직원은 자신의 점포와 관련된 사용자들의 로그만 조회하고,
     * 본사 직원과 super_admin은 전체 로그를 조회합니다.
     */
    public function audits(Request $request)
    {
        $user = $request->user();

        // 감사 로그 조회 권한 확인
        $this->access->requirePermission($user, 'audit.view');

        // 최근 감사 로그부터 조회
        $query = AuditLog::with('user:id,name,store_id')
            ->orderByDesc('id');

        // 점포 직원은 자신의 점포 사용자와 관련된 로그만 조회
        if (! $user->isHeadOffice() && $user->role?->code !== 'super_admin') {
            $userIds = User::where('store_id', $user->store_id)
                ->pluck('id');

            $query->whereIn('user_id', $userIds);
        }

        // 최대 최근 300건 반환
        return response()->json([
            'logs' => $query->limit(300)->get(),
        ]);
    }
}