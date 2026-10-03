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
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * 권한 검사 서비스(AccessService)와
     * 감사 로그 서비스(AuditService)를 사용합니다.
     */
    public function __construct(
        private AccessService $access,
        private AuditService $audit,
        private AuditTrailService $auditTrail
    ) {
    }

    /**
     * 직원 목록을 조회합니다.
     *
     * 직원 조회 권한(employee.view)이 있는 사용자만
     * 직원 관리 화면의 데이터를 조회할 수 있습니다.
     *
     * 화면에서 접근 제한을 우회하거나 API를 직접 호출하더라도
     * 서버에서 직원 조회 권한(employee.view)을 다시 확인합니다.
     */
    public function employees(Request $request)
    {
        // 현재 로그인한 사용자 정보를 가져옵니다.
        $user = $request->user();

        /**
         * 직원 조회 권한(employee.view)을 확인합니다.
         *
         * 화면의 메뉴 표시 여부와 관계없이
         * 실제 직원 정보 접근은 서버에서 최종적으로 판단합니다.
         */
        $this->access->requirePermission($user, 'employee.view');

        /**
         * 직원 목록과 함께 소속 점포(store),
         * 직급(position), 시스템 역할(role)을 조회합니다.
         *
         * 직원 상세보기에서도 이 정보를 사용합니다.
         */
        $query = User::withTrashed()->with([
            'store:id,name',
            'position:id,name',
            'role:id,code,name',
        ])->orderBy('name');

        /**
         * 점포 사용자가 나중에 직원 조회 권한(employee.view)을
         * 가지게 되더라도 자신의 소속 점포(store_id)의 직원만 조회합니다.
         *
         * 본사 사용자와 최고 관리자(super_admin)는
         * 전체 직원 정보를 조회할 수 있습니다.
         */
        if (! $user->isHeadOffice() && $user->role?->code !== 'super_admin') {
            $query->where('store_id', $user->store_id);
        }

        /**
         * 직원 등록 화면에서 현재 사용자가 선택할 수 있는
         * 점포(store)와 권한 역할(role)의 범위를 제한합니다.
         *
         * 본사 사용자와 최고 관리자(super_admin)는
         * 전체 운영 점포와 일반 직원 등록용 역할을 선택할 수 있습니다.
         *
         * 점포 사용자는 자신의 소속 점포 직원만 등록할 수 있으므로
         * 다른 점포나 본사 직원을 등록할 수 없도록
         * 점포와 역할 선택 범위를 서버에서 제한합니다.
         */
        $roleQuery = Role::where('is_active', true)
            ->where('code', '!=', 'super_admin');

        $storeQuery = Store::where('status', 'active');

        if (! $user->isHeadOffice() && $user->role?->code !== 'super_admin') {
            $storeQuery->whereKey($user->store_id);
            $roleQuery->whereNotIn('code', ['head_office_staff', 'head_office_manager']);
        }

        // 직원 관리 화면과 직원 상세보기에 필요한 정보를 반환합니다.
        return response()->json([
            'employees' => $query->get(),

            // 현재 사용자가 관리할 수 있는 운영 점포만 반환합니다.
            'stores' => $storeQuery
                ->orderBy('sort_order')
                ->get(['id', 'name']),

            // 현재 사용 중인 직급(is_active = true)만 반환합니다.
            'positions' => Position::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']),

            // 현재 사용자가 선택할 수 있는 시스템 역할(role)을 반환합니다.
            'roles' => $roleQuery
                ->orderBy('id')
                ->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * 직원 등록 화면을 열기 전에
     * 직원 관리 권한(employee.manage)을 확인합니다.
     *
     * 화면에서 직원 등록 버튼이 비활성화되어 있더라도
     * 개발자 도구 등을 이용해 버튼을 강제로 활성화할 수 있으므로
     * 실제 등록 다이얼로그를 열기 전에 서버에서 다시 확인합니다.
     *
     * 권한 확인이 완료되면 직원 등록에 필요한
     * 점포(store), 직급(position), 권한 역할(role) 목록과
     * 현재 로그인 세션에 임시저장된 직원 등록 내용(draft)을 반환합니다.
     */
    public function employeeCreate(Request $request)
    {
        // 현재 로그인한 사용자 정보를 가져옵니다.
        $user = $request->user();

        /**
         * 직원 관리 권한(employee.manage)을 확인합니다.
         *
         * 권한이 없는 사용자가 API를 직접 호출하거나
         * 화면의 직원 등록 버튼을 강제로 활성화해도
         * 서버에서 접근을 차단합니다.
         */
        $this->access->requirePermission($user, 'employee.manage');

        /**
         * 직원 등록 화면에서 사용할 권한 역할(role) 목록을 조회합니다.
         *
         * 최고 관리자(super_admin)는 일반 직원 등록 기능으로 생성하지 않고
         * 개발 단계의 별도 관리 절차로 생성하므로 선택 목록에서 항상 제외합니다.
         */
        $roleQuery = Role::where('is_active', true)
            ->where('code', '!=', 'super_admin');

        /**
         * 직원 등록 화면에서 현재 사용자가 선택할 수 있는
         * 점포(store) 범위를 제한합니다.
         *
         * 본사 사용자와 최고 관리자(super_admin)는
         * 전체 운영 점포를 선택할 수 있습니다.
         *
         * 점포 사용자는 자신의 소속 점포 직원만 등록할 수 있으므로
         * 다른 점포를 선택할 수 없도록 자신의 소속 점포만 반환합니다.
         *
         * 실제 직원 등록 시에는 employeeStore()에서도
         * 동일한 권한 범위를 다시 검사합니다.
         */
        $storeQuery = Store::where('status', 'active');

        if (! $user->isHeadOffice() && $user->role?->code !== 'super_admin') {
            $storeQuery->whereKey($user->store_id);
        }

        return response()->json([
            'message' => '직원 등록 권한이 확인되었습니다.',

            // 현재 운영 중인 점포(status = active)만 반환합니다.
            'stores' => $storeQuery
                ->orderBy('sort_order')
                ->get(['id', 'name']),

            // 현재 사용 중인 직급(is_active = true)만 반환합니다.
            'positions' => Position::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']),

            // 현재 사용자가 부여할 수 있는 권한 역할(role)을 반환합니다.
            'roles' => $roleQuery
                ->orderBy('id')
                ->get(['id', 'code', 'name']),

            /**
             * 현재 로그인 세션에 임시저장된 직원 등록 내용(draft)을 반환합니다.
             *
             * 비밀번호(password)는 임시저장 대상이 아니므로
             * draft 데이터에 포함되지 않습니다.
             */
            'draft' => $request->session()->get(
                'employee_registration_draft'
            ),
        ]);
    }

    /**
     * 직원 등록 내용을 현재 로그인 세션에 임시저장합니다.
     *
     * 직원 관리 권한(employee.manage)이 있는 사용자만
     * 직원 등록 내용을 임시저장할 수 있습니다.
     *
     * 임시저장은 작성 중인 내용을 보관하기 위한 기능이므로
     * 최종 등록과 달리 모든 입력값을 필수로 요구하지 않습니다.
     *
     * 사원번호(employee_code)와 휴대폰 번호(phone)는
     * 아직 입력 중인 값도 임시저장할 수 있습니다.
     *
     * 다만 임시저장 데이터라도 서버에서 기본적인 형식과
     * 데이터베이스 참조값, 권한 관련 보안 검사는 수행합니다.
     *
     * 비밀번호(password)는 보안을 위해
     * 임시저장 대상에 포함하지 않습니다.
     */
    public function employeeDraft(Request $request)
    {
        // 현재 로그인한 사용자 정보를 가져옵니다.
        $user = $request->user();

        /**
         * 직원 관리 권한(employee.manage)을 확인합니다.
         *
         * 화면을 조작하거나 API를 직접 호출하더라도
         * 권한이 없으면 서버에서 임시저장을 차단합니다.
         */
        $this->access->requirePermission($user, 'employee.manage');

        /**
         * 임시저장할 수 있는 입력값을 검사합니다.
         *
         * 임시저장은 작성 중인 내용을 보관하는 기능이므로
         * 최종 등록처럼 완성된 값을 요구하지 않습니다.
         *
         * 사원번호(employee_code)
         * - 비어 있어도 저장 가능
         * - 입력 중인 숫자값 저장 가능
         * - 숫자만 허용
         * - 최대 20자리
         *
         * 휴대폰 번호(phone)
         * - 비어 있어도 저장 가능
         * - 01012 같은 입력 중인 값도 저장 가능
         * - 숫자만 허용
         * - 최대 11자리
         *
         * 날짜 값은 비어 있어도 되지만
         * 값이 있다면 올바른 날짜여야 합니다.
         *
         * 실제 직원 등록 시에는 employeeStore()에서
         * 완성된 값에 대한 엄격한 검증을 다시 수행합니다.
         */
        $validated = $request->validate(
            [
                'employee_code' => [
                    'nullable',
                    'string',
                    'regex:/^\d{1,20}$/',
                ],
                'name' => [
                    'nullable',
                    'string',
                    'max:50',
                ],
                'phone' => [
                    'nullable',
                    'string',
                    'regex:/^\d{1,11}$/',
                ],
                'birth_date' => [
                    'nullable',
                    'date',
                    'before_or_equal:today',
                ],
                'store_id' => [
                    'nullable',
                    'exists:stores,id',
                ],
                'department' => [
                    'nullable',
                    'in:kitchen,hall,head_office',
                ],
                'position_id' => [
                    'nullable',
                    'exists:positions,id',
                ],
                'role_id' => [
                    'nullable',
                    'exists:roles,id',
                ],
                'hired_at' => [
                    'nullable',
                    'date',
                ],
            ],
            [
                'employee_code.string' =>
                    '사원번호 형식이 올바르지 않습니다.',
                'employee_code.regex' =>
                    '사원번호는 숫자만 최대 20자리까지 입력할 수 있습니다.',

                'name.string' =>
                    '이름 형식이 올바르지 않습니다.',
                'name.max' =>
                    '이름은 최대 50자까지 입력할 수 있습니다.',

                'phone.string' =>
                    '휴대폰 번호 형식이 올바르지 않습니다.',
                'phone.regex' =>
                    '휴대폰 번호는 숫자만 최대 11자리까지 입력할 수 있습니다.',

                'birth_date.date' =>
                    '생년월일 형식이 올바르지 않습니다.',
                'birth_date.before_or_equal' =>
                    '생년월일은 오늘 이후 날짜를 선택할 수 없습니다.',

                'store_id.exists' =>
                    '선택한 점포를 찾을 수 없습니다.',

                'department.in' =>
                    '올바른 부서를 선택해주세요.',

                'position_id.exists' =>
                    '선택한 직급을 찾을 수 없습니다.',

                'role_id.exists' =>
                    '선택한 권한 역할을 찾을 수 없습니다.',

                'hired_at.date' =>
                    '입사일 형식이 올바르지 않습니다.',
            ],
        );

        /**
         * 점포 사용자의 직원 등록 임시저장 범위를 제한합니다.
         *
         * 본사 사용자와 최고 관리자(super_admin)는
         * 전체 직원 등록 내용을 임시저장할 수 있습니다.
         *
         * 점포 사용자는 자신의 소속 점포 직원만 등록할 수 있으므로
         * 본사(head_office) 직원 등록 내용이나
         * 다른 점포(store_id)의 직원 등록 내용을
         * 임시저장할 수 없도록 서버에서 차단합니다.
         *
         * 임시저장은 작성 중인 내용을 저장하는 기능이므로
         * 아직 점포(store_id)를 선택하지 않은 상태 자체는 허용합니다.
         * 다만 점포가 입력되어 있다면 반드시
         * 현재 사용자의 소속 점포와 일치해야 합니다.
         */
        if (! $user->isHeadOffice() && $user->role?->code !== 'super_admin') {
            abort_if(
                ($validated['department'] ?? null) === 'head_office',
                403,
                '본사 직원을 등록할 권한이 없습니다.'
            );

            if (! empty($validated['store_id'])) {
                abort_if(
                    (int) $validated['store_id'] !== (int) $user->store_id,
                    403,
                    '다른 점포의 직원을 등록할 권한이 없습니다.'
                );
            }
        }

        /**
         * 권한 역할(role_id)이 입력되어 있다면
         * 현재 사용할 수 있는 역할인지 서버에서 다시 확인합니다.
         */
        if (! empty($validated['role_id'])) {
            $selectedRole = Role::find($validated['role_id']);

            abort_if(
                ! $selectedRole || ! $selectedRole->is_active,
                422,
                '현재 사용할 수 없는 권한 역할입니다.'
            );

            /**
             * 점포 사용자는 본사 직원용 권한 역할을 부여할 수 없습니다.
             *
             * 임시저장 단계에서 부서(department)가 아직 입력되지 않았더라도
             * 본사 직원용 역할(head_office_staff, head_office_manager)을
             * 직접 지정하여 저장하는 요청은 서버에서 차단합니다.
             *
             * 화면의 역할 목록을 조작하거나 API를 직접 호출하는 경우에도
             * 동일한 직원 등록 권한 범위를 적용합니다.
             */
            if (! $user->isHeadOffice() && $user->role?->code !== 'super_admin') {
                abort_if(
                    in_array(
                        $selectedRole->code,
                        ['head_office_staff', 'head_office_manager'],
                        true
                    ),
                    403,
                    '본사 직원용 권한 역할을 부여할 권한이 없습니다.'
                );
            }

            /**
             * 임시저장 단계에서도 최고 관리자(super_admin)는 지정할 수 없으며,
             * 부서(department)가 입력되어 있다면 해당 부서에서
             * 사용할 수 있는 권한 역할인지 서버에서 확인합니다.
             */
            $this->ensureEmployeeRoleMatchesDepartment(
                $validated['department'] ?? null,
                $selectedRole
            );
        }

        /**
         * 직급(position_id)이 입력되어 있다면
         * 현재 사용 중인 직급인지 확인합니다.
         */
        if (! empty($validated['position_id'])) {
            $selectedPosition = Position::find(
                $validated['position_id']
            );

            abort_if(
                ! $selectedPosition || ! $selectedPosition->is_active,
                422,
                '현재 사용할 수 없는 직급입니다.'
            );
        }

        /**
         * 본사(head_office) 직원은 특정 점포에 소속되지 않으므로
         * 점포(store_id)가 전달되어도 NULL로 변경합니다.
         */
        if (($validated['department'] ?? null) === 'head_office') {
            $validated['store_id'] = null;
        } elseif (! empty($validated['store_id'])) {
            /**
             * 점포(store_id)가 입력되어 있다면
             * 현재 운영 중인 점포인지 확인합니다.
             */
            $selectedStore = Store::find(
                $validated['store_id']
            );

            abort_if(
                ! $selectedStore || $selectedStore->status !== 'active',
                422,
                '현재 이용할 수 없는 점포입니다.'
            );
        }

        /**
         * 비밀번호(password)는 검증 목록에도 포함하지 않았으며
         * Laravel Session에도 저장하지 않습니다.
         */
        unset($validated['password']);

        // 현재 로그인 세션에 직원 등록 내용을 임시저장합니다.
        $request->session()->put(
            'employee_registration_draft',
            $validated
        );

        return response()->json([
            'message' => '직원 등록 내용이 임시저장되었습니다.',
            'draft' => $validated,
        ]);
    }

    /**
     * 직원 등록 임시저장 내용을 전체 삭제합니다.
     *
     * 직원 등록 화면에서 "전체 삭제"를 선택했을 때
     * 현재 로그인 세션에 저장되어 있는
     * 직원 등록 임시저장 내용(draft)을 삭제합니다.
     *
     * 이 기능은 실제 등록된 직원 정보를 삭제하는 기능이 아닙니다.
     * 아직 등록되지 않은 직원 등록 작성 내용만 삭제합니다.
     *
     * 직원 관리 권한(employee.manage)이 있는 사용자만
     * 임시저장 내용을 삭제할 수 있습니다.
     *
     * 화면의 버튼이나 요청 내용을 조작하여
     * API를 직접 호출하는 경우에도
     * Laravel 서버에서 권한을 다시 확인합니다.
     *
     * 비밀번호(password)는 원래 임시저장 대상이 아니므로
     * 서버에서 별도로 삭제할 비밀번호 데이터는 없습니다.
     */
    public function employeeDraftDelete(Request $request)
    {
        // 현재 로그인한 사용자 정보를 가져옵니다.
        $user = $request->user();

        /**
         * 직원 관리 권한(employee.manage)을 확인합니다.
         *
         * 직원 조회 권한(employee.view)만 있는 사용자나
         * 직원 관리 권한이 없는 사용자가 API를 직접 호출하더라도
         * 서버에서 임시저장 전체 삭제를 차단합니다.
         */
        $this->access->requirePermission(
            $user,
            'employee.manage'
        );

        /**
         * 현재 로그인 세션에 저장된
         * 직원 등록 임시저장 내용(draft)을 삭제합니다.
         *
         * 세션 키가 존재하지 않는 경우에도 오류를 발생시키지 않고
         * 삭제된 상태로 정상 처리합니다.
         */
        $request->session()->forget(
            'employee_registration_draft'
        );

        return response()->json([
            'message' => '직원 등록 내용이 모두 삭제되었습니다.',
        ]);
    }

    /**
     * 직원 상세정보를 조회합니다.
     *
     * 상세보기 버튼을 눌렀을 때
     * 화면에 이미 저장되어 있는 직원 정보를 그대로 사용하지 않고
     * Laravel 서버에서 권한을 확인한 뒤 최신 직원 정보를 다시 조회합니다.
     *
     * 직원 조회 권한(employee.view)이 없는 사용자가
     * 상세보기 버튼을 강제로 활성화하거나 API를 직접 호출해도
     * 서버에서 접근을 차단합니다.
     *
     * 상세정보 응답에서는
     * 직원 화면에서 실제로 사용하는 정보만 명시적으로 반환합니다.
     *
     * 이렇게 하면 나중에 users 테이블에 새로운 민감 정보가 추가되어도
     * User 모델 전체가 자동으로 화면에 노출되는 것을 방지할 수 있습니다.
     */
    public function employeeShow(Request $request, User $user)
    {
        // 현재 상세정보 조회를 요청한 로그인 사용자를 가져옵니다.
        $actor = $request->user();

        /**
         * 직원 조회 권한(employee.view)을 확인합니다.
         *
         * 화면에서 상세보기 버튼을 강제로 활성화하거나
         * API 주소를 직접 호출하더라도
         * 직원 조회 권한(employee.view)이 없으면 서버에서 차단합니다.
         */
        $this->access->requirePermission($actor, 'employee.view');

        /**
         * 점포 사용자의 직원 조회 범위를 다시 확인합니다.
         *
         * 점포 사용자가 직원 조회 권한(employee.view)을 가지더라도
         * 자신의 소속 점포(store_id)에 속한 직원만 조회할 수 있습니다.
         *
         * 본사 사용자와 최고 관리자(super_admin)는
         * 전체 직원의 상세정보를 조회할 수 있습니다.
         */
        if (! $actor->isHeadOffice() && $actor->role?->code !== 'super_admin') {
            abort_if(
                $user->store_id !== $actor->store_id,
                403,
                '해당 직원 정보를 열람할 권한이 없습니다.'
            );
        }

        /**
         * 화면에 남아 있는 기존 데이터를 사용하지 않고
         * 데이터베이스에서 직원의 최신 관계 정보를 다시 조회합니다.
         *
         * 소속 점포(store)
         * 직급(position)
         * 시스템 역할(role)
         */
        $user->load([
            'store:id,name',
            'position:id,name',
            'role:id,code,name',
        ]);

        /**
         * 직원 상세보기에서 사용할 정보를 반환합니다.
         *
         * ------------------------------------------------------------
         * 기본 정보
         * ------------------------------------------------------------
         *
         * id
         * - 시스템 내부 직원 번호
         *
         * employee_code
         * - 사번 및 로그인 ID
         *
         * name
         * - 직원 이름
         *
         * phone
         * - 연락처
         *
         * birth_date
         * - 생년월일
         *
         * ------------------------------------------------------------
         * 소속 정보
         * ------------------------------------------------------------
         *
         * store
         * - 현재 소속 점포
         * - 본사 직원은 NULL
         *
         * department
         * - 주방(kitchen), 홀(hall), 본사(head_office)
         *
         * position
         * - 회사 조직상의 직급
         *
         * role
         * - 시스템에서 사용하는 역할 및 권한 묶음
         *
         * ------------------------------------------------------------
         * 재직 정보
         * ------------------------------------------------------------
         *
         * employment_status
         * - 재직(active), 휴직(leave), 퇴사(resigned)
         *
         * hired_at
         * - 입사일
         *
         * resigned_at
         * - 퇴사일
         *
         * ------------------------------------------------------------
         * 시스템 정보
         * ------------------------------------------------------------
         *
         * is_active
         * - 계정 활성 상태
         *
         * last_login_at
         * - 마지막 로그인 시간
         *
         * password_changed_at
         * - 마지막 비밀번호 변경 시간
         *
         * created_at
         * - 직원 계정 생성 시간
         *
         * updated_at
         * - 직원 정보 마지막 수정 시간
         *
         * 비밀번호(password)와 로그인 유지 토큰(remember_token)은
         * 어떠한 경우에도 이 응답에 포함하지 않습니다.
         */
        return response()->json([
            'employee' => [
                'id' => $user->id,
                'employee_code' => $user->employee_code,
                'name' => $user->name,
                'phone' => $user->phone,
                'birth_date' => $user->birth_date?->format('Y-m-d'),

                'store' => $user->store
                    ? [
                        'id' => $user->store->id,
                        'name' => $user->store->name,
                    ]
                    : null,

                'department' => $user->department,

                'position' => $user->position
                    ? [
                        'id' => $user->position->id,
                        'name' => $user->position->name,
                    ]
                    : null,

                'role' => $user->role
                    ? [
                        'id' => $user->role->id,
                        'code' => $user->role->code,
                        'name' => $user->role->name,
                    ]
                    : null,

                'employment_status' => $user->employment_status,
                'hired_at' => $user->hired_at?->format('Y-m-d'),
                'resigned_at' => $user->resigned_at?->format('Y-m-d'),
                'is_active' => $user->is_active,

                'last_login_at' => $user->last_login_at?->toISOString(),
                'password_changed_at' => $user->password_changed_at?->toISOString(),

                'created_at' => $user->created_at?->toISOString(),
                'updated_at' => $user->updated_at?->toISOString(),
                'deleted_at' => $user->deleted_at?->toISOString(),
                'management_history' => $this->auditTrail->summary(User::class, $user->id),
                'audit_history' => $this->auditTrail->history(User::class, $user->id),
            ],
        ]);
    }

    /**
     * 새로운 직원을 등록합니다.
     *
     * 직원 관리 권한(employee.manage)이 있는 사용자만
     * 새로운 직원을 등록할 수 있습니다.
     *
     * 화면에서 직원 등록 버튼을 강제로 활성화하거나
     * API를 직접 호출하는 경우에도 서버에서 다시 검사합니다.
     *
     * 프론트 입력 검사는 사용자 편의를 위한 검사이며
     * 실제 직원 등록에 대한 최종 검증과 보안 처리는
     * Laravel 서버에서 수행합니다.
     *
     * 서버에서 다음과 같은 비정상적인 요청을 차단합니다.
     *
     * - 직원 관리 권한(employee.manage)이 없는 사용자의 등록 요청
     * - 숫자가 아니거나 20자리를 초과하는 사번(employee_code)
     * - 이미 사용 중인 사번(employee_code)
     * - 대한민국 휴대폰 번호 형식이 아닌 전화번호(phone)
     * - 미래 날짜로 입력된 생년월일(birth_date)
     * - 8자 미만 또는 72자를 초과하는 초기 비밀번호(password)
     * - 허용되지 않은 부서(department) 지정
     * - 사용 중지 또는 폐점된 점포(store_id) 지정
     * - 본사(head_office) 직원에게 임의의 점포(store_id) 지정
     * - 점포 직원인데 소속 점포(store_id)를 지정하지 않는 요청
     * - 사용 중지된 직급(position_id) 지정
     * - 사용 중지된 권한 역할(role_id) 지정
     * - 일반 직원 등록을 통한 최고 관리자(super_admin) 역할 지정
     * - 소속 부서(department)에서 허용되지 않는 권한 역할(role_id) 지정
     *
     * 신규 직원은 기본적으로
     * 재직 상태(employment_status)를 재직(active)으로 등록하고
     * 계정 활성 상태(is_active)를 활성(true)으로 설정합니다.
     *
     * 직원 등록이 정상적으로 완료되면
     * 현재 로그인 세션의 직원 등록 임시저장(draft)을 삭제합니다.
     */
    public function employeeStore(Request $request)
    {
        // 실제 직원 등록을 요청한 로그인 사용자를 가져옵니다.
        $user = $request->user();

        /**
         * 직원 관리 권한(employee.manage)을 확인합니다.
         *
         * 직원 조회 권한(employee.view)만 있는 사용자가
         * 화면의 등록 버튼을 강제로 활성화하거나
         * API 주소를 직접 호출하더라도
         * 직원 관리 권한(employee.manage)이 없으면 서버에서 차단합니다.
         */
        $this->access->requirePermission($user, 'employee.manage');

        /**
         * 직원 등록 정보를 검사합니다.
         *
         * ------------------------------------------------------------
         * 사번(employee_code)
         * ------------------------------------------------------------
         *
         * - 필수 입력
         * - 문자열로 처리하여 앞자리 0을 유지
         * - 숫자만 허용
         * - 최대 20자리
         * - 기존 직원과 중복 불가
         *
         * ------------------------------------------------------------
         * 이름(name)
         * ------------------------------------------------------------
         *
         * - 필수 입력
         * - 문자열
         * - 최대 50자
         *
         * ------------------------------------------------------------
         * 전화번호(phone)
         * ------------------------------------------------------------
         *
         * - 필수 입력
         * - 문자열로 처리하여 앞자리 0을 유지
         * - 하이픈(-) 없이 숫자만 입력
         * - 010으로 시작하는 대한민국 휴대폰 번호
         * - 총 11자리
         *
         * 예:
         * 01012341234
         *
         * ------------------------------------------------------------
         * 생년월일(birth_date)
         * ------------------------------------------------------------
         *
         * - 필수 입력
         * - 날짜 형식
         * - 오늘보다 미래 날짜는 입력 불가
         *
         * ------------------------------------------------------------
         * 초기 비밀번호(password)
         * ------------------------------------------------------------
         *
         * - 필수 입력
         * - 최소 8자
         * - 최대 72자
         *
         * ------------------------------------------------------------
         * 소속 점포(store_id)
         * ------------------------------------------------------------
         *
         * - 본사(head_office)는 NULL
         * - 주방(kitchen), 홀(hall)은 필수
         * - 실제 데이터베이스에 존재하는 점포만 허용
         *
         * ------------------------------------------------------------
         * 부서(department)
         * ------------------------------------------------------------
         *
         * 허용 값:
         * - 주방(kitchen)
         * - 홀(hall)
         * - 본사(head_office)
         *
         * ------------------------------------------------------------
         * 직급(position_id)
         * ------------------------------------------------------------
         *
         * - 신규 직원 등록에서는 필수
         * - 실제 데이터베이스에 존재하는 직급만 허용
         *
         * ------------------------------------------------------------
         * 권한 역할(role_id)
         * ------------------------------------------------------------
         *
         * - 필수 입력
         * - 실제 데이터베이스에 존재하는 역할만 허용
         *
         * ------------------------------------------------------------
         * 입사일(hired_at)
         * ------------------------------------------------------------
         *
         * - 필수 입력
         * - 날짜 형식
         */
        $validated = $request->validate(
            [
                'employee_code' => [
                    'required',
                    'string',
                    'regex:/^\d{1,20}$/',
                    'unique:users,employee_code',
                ],
                'name' => [
                    'required',
                    'string',
                    'max:50',
                ],
                'phone' => [
                    'required',
                    'string',
                    'regex:/^010\d{8}$/',
                ],
                'birth_date' => [
                    'required',
                    'date',
                    'before_or_equal:today',
                ],
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'max:72',
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
                    'required',
                    'exists:positions,id',
                ],
                'role_id' => [
                    'required',
                    'exists:roles,id',
                ],
                'hired_at' => [
                    'required',
                    'date',
                ],
            ],
            [
                'employee_code.required' =>
                    '사원번호를 입력해주세요.',
                'employee_code.string' =>
                    '사원번호 형식이 올바르지 않습니다.',
                'employee_code.regex' =>
                    '사원번호는 숫자만 최대 20자리까지 입력할 수 있습니다.',
                'employee_code.unique' =>
                    '이미 사용 중인 사원번호입니다.',

                'name.required' =>
                    '이름을 입력해주세요.',
                'name.string' =>
                    '이름 형식이 올바르지 않습니다.',
                'name.max' =>
                    '이름은 최대 50자까지 입력할 수 있습니다.',

                'phone.required' =>
                    '휴대폰 번호를 입력해주세요.',
                'phone.string' =>
                    '휴대폰 번호 형식이 올바르지 않습니다.',
                'phone.regex' =>
                    '휴대폰 번호는 010으로 시작하는 숫자 11자리로 입력해주세요.',

                'birth_date.required' =>
                    '생년월일을 입력해주세요.',
                'birth_date.date' =>
                    '생년월일 형식이 올바르지 않습니다.',
                'birth_date.before_or_equal' =>
                    '생년월일은 오늘 이후 날짜를 선택할 수 없습니다.',

                'password.required' =>
                    '초기 비밀번호를 입력해주세요.',
                'password.string' =>
                    '비밀번호 형식이 올바르지 않습니다.',
                'password.min' =>
                    '비밀번호는 최소 8자 이상이어야 합니다.',
                'password.max' =>
                    '비밀번호는 최대 72자까지 입력할 수 있습니다.',

                'store_id.exists' =>
                    '선택한 점포를 찾을 수 없습니다.',

                'department.required' =>
                    '부서를 선택해주세요.',
                'department.in' =>
                    '올바른 부서를 선택해주세요.',

                'position_id.required' =>
                    '직급을 선택해주세요.',
                'position_id.exists' =>
                    '선택한 직급을 찾을 수 없습니다.',

                'role_id.required' =>
                    '권한 역할을 선택해주세요.',
                'role_id.exists' =>
                    '선택한 권한 역할을 찾을 수 없습니다.',

                'hired_at.required' =>
                    '입사일을 입력해주세요.',
                'hired_at.date' =>
                    '입사일 형식이 올바르지 않습니다.',
            ],
        );

        /**
         * 요청받은 권한 역할(role_id)을
         * 데이터베이스에서 다시 조회합니다.
         *
         * 사용자가 개발자 도구나 직접 API 호출을 통해
         * 화면에 표시되지 않는 권한 역할(role_id)을 전송할 수 있으므로
         * 화면에서 전달된 값을 그대로 신뢰하지 않습니다.
         */
        $selectedRole = Role::findOrFail($validated['role_id']);

        /**
         * 사용 중지된 권한 역할(is_active = false)은
         * 새로운 직원에게 부여할 수 없습니다.
         */
        abort_if(
            ! $selectedRole->is_active,
            422,
            '현재 사용할 수 없는 권한 역할입니다.'
        );

        /**
         * 점포 사용자의 직원 등록 범위를 서버에서 최종 확인합니다.
         *
         * 화면의 점포 선택 목록을 조작하거나
         * API를 직접 호출하더라도
         * 점포 사용자는 자신의 소속 점포 직원만 등록할 수 있습니다.
         *
         * 또한 점포 사용자는 본사(head_office) 직원을 등록하거나
         * 본사 직원용 권한 역할을 부여할 수 없습니다.
         *
         * employeeCreate()의 선택 목록 제한과
         * employeeDraft()의 임시저장 제한을 우회하더라도
         * 실제 직원 생성 직전에 서버에서 다시 차단합니다.
         */
        if (! $user->isHeadOffice() && $user->role?->code !== 'super_admin') {
            abort_if(
                $validated['department'] === 'head_office',
                403,
                '본사 직원을 등록할 권한이 없습니다.'
            );

            if ($validated['store_id'] !== null) {
                abort_if(
                    (int) $validated['store_id'] !== (int) $user->store_id,
                    403,
                    '다른 점포의 직원을 등록할 권한이 없습니다.'
                );
            }

            abort_if(
                in_array(
                    $selectedRole->code,
                    ['head_office_staff', 'head_office_manager'],
                    true
                ),
                403,
                '본사 직원용 권한 역할을 부여할 권한이 없습니다.'
            );
        }

        /**
         * 최고 관리자(super_admin)는 일반 직원 등록 기능으로 생성하지 않습니다.
         *
         * 또한 화면을 조작하거나 API를 직접 호출하여
         * 현재 소속 부서(department)에서 사용할 수 없는 권한 역할(role_id)을
         * 전송하는 경우에도 서버에서 차단합니다.
         */
        $this->ensureEmployeeRoleMatchesDepartment(
            $validated['department'],
            $selectedRole
        );

        /**
         * 소속 부서(department)가 본사(head_office)인 직원은
         * 특정 점포(store_id)에 소속되지 않습니다.
         *
         * 사용자가 임의의 점포(store_id)를 함께 전송하더라도
         * 서버에서 소속 점포(store_id)를 NULL로 변경합니다.
         */
        if ($validated['department'] === 'head_office') {
            $validated['store_id'] = null;
        } else {
            /**
             * 소속 부서(department)가 주방(kitchen) 또는 홀(hall)인 직원은
             * 반드시 소속 점포(store_id)가 있어야 합니다.
             */
            abort_if(
                $validated['store_id'] === null,
                422,
                '점포 직원은 점포를 선택해야 합니다.'
            );

            /**
             * 선택한 소속 점포(store_id)가
             * 실제로 운영 중인 점포인지 확인합니다.
             *
             * 점포가 데이터베이스에 존재하더라도
             * 사용 중지(inactive) 또는 폐점(closed) 상태라면
             * 새로운 직원의 소속 점포로 지정할 수 없습니다.
             */
            $selectedStore = Store::find($validated['store_id']);

            abort_if(
                ! $selectedStore || $selectedStore->status !== 'active',
                422,
                '현재 이용할 수 없는 점포입니다.'
            );
        }

        /**
         * 신규 직원 등록에서는 직급(position_id)이 필수입니다.
         *
         * 화면에 표시되지 않는 사용 중지된 직급을
         * 직접 전송하는 경우에도 서버에서 차단합니다.
         */
        $selectedPosition = Position::find($validated['position_id']);

        abort_if(
            ! $selectedPosition || ! $selectedPosition->is_active,
            422,
            '현재 사용할 수 없는 직급입니다.'
        );

        /**
         * 직원 생성과 감사 로그(audit log) 기록은
         * 하나의 데이터베이스 트랜잭션(transaction)으로 처리합니다.
         *
         * 직원 생성 후 감사 로그 기록 중 오류가 발생하면
         * 직원 생성도 함께 롤백되어 데이터가 일부만 저장되는 것을 방지합니다.
         */
        DB::transaction(function () use ($validated, $user) {
            /**
             * 검사가 완료된 정보로 새로운 직원을 생성합니다.
             *
             * 신규 직원은 기본적으로
             * 재직 상태(employment_status)를 재직(active)으로 등록하고
             * 계정 활성 상태(is_active)도 활성(true)으로 설정합니다.
             *
             * 퇴사일(resigned_at)은 아직 퇴사한 직원이 아니므로
             * NULL로 저장합니다.
             *
             * 비밀번호(password)는 User 모델의
             * hashed cast를 통해 해시 처리됩니다.
             */
            $employee = User::create([
                ...$validated,
                'employment_status' => 'active',
                'is_active' => true,
                'resigned_at' => null,
            ]);

            /**
             * 직원 등록 내용을 감사 로그(audit log)에 기록합니다.
             *
             * 누가 어떤 직원 계정을 생성했는지
             * 나중에 확인할 수 있도록 기록을 남깁니다.
             */
            $this->audit->log(
                $user,
                'employee',
                'create',
                User::class,
                $employee->id,
                null,
                [
                    'employee_code' => $employee->employee_code,
                    'name' => $employee->name,
                    'phone' => $employee->phone,
                    'birth_date' => $employee->birth_date?->format('Y-m-d'),
                    'store_id' => $employee->store_id,
                    'department' => $employee->department,
                    'position_id' => $employee->position_id,
                    'role_id' => $employee->role_id,
                    'employment_status' => $employee->employment_status,
                    'hired_at' => $employee->hired_at?->format('Y-m-d'),
                    'resigned_at' => $employee->resigned_at?->format('Y-m-d'),
                    'is_active' => $employee->is_active,
                ],
                '직원 등록'
            );
        });

        /**
         * 직원 생성과 감사 로그 기록이 모두 정상적으로 완료되어
         * 데이터베이스 트랜잭션(transaction)이 성공한 경우에만
         * 현재 로그인 세션의 직원 등록 임시저장(draft)을 삭제합니다.
         *
         * 입력 검증, 권한 검사, 직원 생성 또는 감사 로그 기록 중
         * 오류가 발생하면 이 코드까지 실행되지 않으므로
         * 기존 임시저장 내용은 유지됩니다.
         */
        $request->session()->forget(
            'employee_registration_draft'
        );

        return response()->json([
            'message' => '직원이 등록되었습니다.',
        ], 201);
    }

    /**
     * 직원 기본정보 및 소속정보를 수정합니다.
     *
     * 화면의 입력값을 그대로 신뢰하지 않고 직원 관리 권한, 점포 범위,
     * 역할/부서 조합, 보호 대상 계정을 서버에서 다시 검증합니다.
     * 비밀번호는 별도 기능에서 관리하고, 재직 상태는 직원 정보와 함께 한 번에 수정합니다.
     */
    public function employeeUpdate(Request $request, User $user)
    {
        $actor = $request->user();
        $this->access->requirePermission($actor, 'employee.manage');

        abort_if($user->trashed(), 409, '삭제된 직원은 정보를 수정할 수 없습니다. 먼저 복구해주세요.');
        $user->loadMissing('role');
        abort_if($user->role?->code === 'super_admin', 403, '최고 관리자 정보는 직원 관리에서 수정할 수 없습니다.');

        if (! $actor->isHeadOffice() && $actor->role?->code !== 'super_admin') {
            abort_if($user->store_id !== $actor->store_id, 403, '해당 직원 정보를 수정할 권한이 없습니다.');
        }

        $validated = $request->validate([
            // 기존 레거시 사원번호를 유지하는 수정은 허용하되 새 값으로 바꿀 때는 현재 숫자 규칙을 적용합니다.
            'employee_code' => array_values(array_filter([
                'required',
                'string',
                $request->input('employee_code') === $user->employee_code ? null : 'regex:/^\\d{1,20}$/',
                'unique:users,employee_code,' . $user->id,
            ])),
            'name' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'regex:/^010\\d{8}$/'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'department' => ['required', 'in:kitchen,hall,head_office'],
            'position_id' => ['required', 'exists:positions,id'],
            'role_id' => ['required', 'exists:roles,id'],
            'hired_at' => ['nullable', 'date'],
            'employment_status' => ['required', 'string', 'in:active,leave,resigned'],
        ], [
            'employee_code.required' => '사원번호를 입력해주세요.',
            'employee_code.regex' => '사원번호는 숫자만 최대 20자리까지 입력할 수 있습니다.',
            'employee_code.unique' => '이미 사용 중인 사원번호입니다.',
            'name.required' => '이름을 입력해주세요.',
            'name.max' => '이름은 최대 50자까지 입력할 수 있습니다.',
            'phone.regex' => '휴대폰 번호는 010으로 시작하는 숫자 11자리로 입력해주세요.',
            'birth_date.date' => '생년월일 형식이 올바르지 않습니다.',
            'birth_date.before_or_equal' => '생년월일은 오늘 이후 날짜를 선택할 수 없습니다.',
            'store_id.exists' => '선택한 점포를 찾을 수 없습니다.',
            'department.required' => '부서를 선택해주세요.',
            'department.in' => '올바른 부서를 선택해주세요.',
            'position_id.required' => '직급을 선택해주세요.',
            'position_id.exists' => '선택한 직급을 찾을 수 없습니다.',
            'role_id.required' => '권한 역할을 선택해주세요.',
            'role_id.exists' => '선택한 권한 역할을 찾을 수 없습니다.',
            'hired_at.date' => '입사일 형식이 올바르지 않습니다.',
            'employment_status.required' => '재직 상태를 선택해주세요.',
            'employment_status.in' => '올바른 재직 상태를 선택해주세요.',
        ]);

        $selectedRole = Role::findOrFail($validated['role_id']);
        abort_if(! $selectedRole->is_active, 422, '현재 사용할 수 없는 권한 역할입니다.');
        $this->ensureEmployeeRoleMatchesDepartment($validated['department'], $selectedRole);

        if ($validated['department'] === 'head_office') {
            abort_if($validated['store_id'] !== null, 422, '본사 직원은 소속 점포를 선택할 수 없습니다.');
        } else {
            abort_if($validated['store_id'] === null, 422, '점포 직원은 소속 점포를 선택해주세요.');
        }

        if (! $actor->isHeadOffice() && $actor->role?->code !== 'super_admin') {
            abort_if($validated['department'] === 'head_office', 403, '본사 직원으로 변경할 권한이 없습니다.');
            abort_if((int) $validated['store_id'] !== (int) $actor->store_id, 403, '다른 점포로 직원을 변경할 권한이 없습니다.');
            abort_if(
                in_array(
                    $selectedRole->code,
                    ['head_office_staff', 'head_office_manager'],
                    true
                ),
                403,
                '본사 직원용 권한 역할을 부여할 수 없습니다.'
            );
        }

        $changed = DB::transaction(function () use ($actor, $user, $validated) {
            $lockedUser = User::query()->with('role')->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedUser->role?->code === 'super_admin', 403, '최고 관리자 정보는 직원 관리에서 수정할 수 없습니다.');
            if (! $actor->isHeadOffice() && $actor->role?->code !== 'super_admin') {
                abort_if($lockedUser->store_id !== $actor->store_id, 403, '해당 직원 정보를 수정할 권한이 없습니다.');
            }

            $fields = [
                'employee_code',
                'name',
                'phone',
                'birth_date',
                'store_id',
                'department',
                'position_id',
                'role_id',
                'hired_at',
                'employment_status',
                'is_active',
                'resigned_at',
            ];
            $before = collect($fields)
                ->mapWithKeys(fn ($field) => [
                    $field => $lockedUser->{$field} instanceof \DateTimeInterface
                        ? $lockedUser->{$field}->format('Y-m-d')
                        : $lockedUser->{$field},
                ])
                ->all();
            // 일반 직원 정보와 재직 상태를 같은 트랜잭션에서 반영합니다.
            // 재직 상태에 따라 로그인 가능 여부와 퇴사일도 서버가 일관되게 결정합니다.
            $employmentStatus = $validated['employment_status'];
            $lockedUser->fill(collect($validated)->except('employment_status')->all());
            $lockedUser->employment_status = $employmentStatus;
            $lockedUser->is_active = $employmentStatus === 'active';

            if ($employmentStatus === 'resigned') {
                if ($lockedUser->resigned_at === null) {
                    $lockedUser->resigned_at = now()->toDateString();
                }
            } else {
                $lockedUser->resigned_at = null;
            }

            if (! $lockedUser->isDirty($fields)) {
                return false;
            }
            $lockedUser->save();
            $after = collect($fields)
                ->mapWithKeys(fn ($field) => [
                    $field => $lockedUser->{$field} instanceof \DateTimeInterface
                        ? $lockedUser->{$field}->format('Y-m-d')
                        : $lockedUser->{$field},
                ])
                ->all();
            $this->audit->log($actor, 'employee', 'update', User::class, $lockedUser->id, $before, $after, '직원 정보 수정');
            return true;
        });

        return response()->json(['message' => $changed ? '직원 정보가 수정되었습니다.' : '변경된 직원 정보가 없습니다.']);
    }

    /** 상세 화면의 상태 칩에서 재직 상태만 빠르게 변경합니다. */
    public function employeeStatus(Request $request, User $user)
    {
        $actor = $request->user();
        $this->access->requirePermission($actor, 'employee.manage');
        abort_if($user->trashed(), 409, '삭제된 직원의 상태는 변경할 수 없습니다. 먼저 복구해주세요.');
        $user->loadMissing('role');
        abort_if($user->role?->code === 'super_admin', 403, '최고 관리자 상태는 직원 관리에서 변경할 수 없습니다.');
        if (! $actor->isHeadOffice() && $actor->role?->code !== 'super_admin') {
            abort_if($user->store_id !== $actor->store_id, 403, '해당 직원의 상태를 변경할 권한이 없습니다.');
        }
        $validated = $request->validate(['employment_status' => ['required', 'in:active,leave,resigned']]);
        $old = $user->only(['employment_status', 'is_active', 'resigned_at']);
        $user->employment_status = $validated['employment_status'];
        $user->is_active = $validated['employment_status'] === 'active';
        $user->resigned_at = $validated['employment_status'] === 'resigned' ? ($user->resigned_at ?? now()->toDateString()) : null;
        $user->save();
        $this->audit->log(
            $actor,
            'employee',
            'update',
            User::class,
            $user->id,
            $old,
            $user->only([
                'employment_status',
                'is_active',
                'resigned_at',
            ]),
            '직원 재직 상태 변경'
        );
        return response()->json(['message' => '직원 재직 상태를 변경했습니다.']);
    }

    /** 직원 로그인 비밀번호를 관리자가 새 비밀번호로 초기화합니다. */
    public function employeePasswordReset(Request $request, User $user)
    {
        $actor = $request->user();
        $this->access->requirePermission($actor, 'employee.manage');
        abort_if($user->trashed(), 409, '삭제된 직원의 비밀번호는 초기화할 수 없습니다.');
        $user->loadMissing('role');
        abort_if($user->role?->code === 'super_admin', 403, '최고 관리자 비밀번호는 직원 관리에서 초기화할 수 없습니다.');
        if (! $actor->isHeadOffice() && $actor->role?->code !== 'super_admin') {
            abort_if($user->store_id !== $actor->store_id, 403, '해당 직원의 비밀번호를 초기화할 권한이 없습니다.');
        }
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ], [
            'password.required' => '새 비밀번호를 입력해주세요.',
            'password.min' => '비밀번호는 최소 8자 이상이어야 합니다.',
            'password.max' => '비밀번호는 최대 72자까지 입력할 수 있습니다.',
            'password.confirmed' => '비밀번호 확인이 일치하지 않습니다.',
        ]);

        DB::transaction(function () use ($actor, $user, $validated) {
            $lockedUser = User::query()->with('role')->whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if(
                $lockedUser->role?->code === 'super_admin',
                403,
                '최고 관리자 비밀번호는 직원 관리에서 초기화할 수 없습니다.'
            );
            if (! $actor->isHeadOffice() && $actor->role?->code !== 'super_admin') {
                abort_if($lockedUser->store_id !== $actor->store_id, 403, '해당 직원의 비밀번호를 초기화할 권한이 없습니다.');
            }
            $lockedUser->password = $validated['password'];
            $lockedUser->password_changed_at = now();
            $lockedUser->save();
            // 비밀번호 원문과 해시값은 감사 로그에 절대 기록하지 않습니다.
            $this->audit->log(
                $actor,
                'employee',
                'password_reset',
                User::class,
                $lockedUser->id,
                null,
                [
                    'password_changed_at' => $lockedUser
                        ->password_changed_at
                        ->toISOString(),
                ],
                '직원 비밀번호 초기화'
            );
        });

        return response()->json(['message' => '직원 비밀번호를 초기화했습니다.']);
    }

    /** 직원을 영구 삭제하지 않고 deleted_at만 기록하여 복구 가능 상태로 전환합니다. */
    public function employeeDelete(Request $request, User $user)
    {
        $actor = $request->user();
        $this->access->requirePermission($actor, 'employee.manage');
        abort_if($actor->id === $user->id, 403, '현재 로그인한 자기 자신은 삭제할 수 없습니다.');
        $user->loadMissing('role');
        abort_if($user->role?->code === 'super_admin', 403, '최고 관리자는 직원 관리에서 삭제할 수 없습니다.');
        if (! $actor->isHeadOffice() && $actor->role?->code !== 'super_admin') {
            abort_if($user->store_id !== $actor->store_id, 403, '해당 직원을 삭제할 권한이 없습니다.');
        }
        if ($user->trashed()) {
            return response()->json(['message' => '이미 삭제된 직원입니다.']);
        }

        DB::transaction(function () use ($actor, $user) {
            $lockedUser = User::withTrashed()->with('role')->whereKey($user->id)->lockForUpdate()->firstOrFail();

            // 중복 삭제 요청이면 감사로그를 다시 남기지 않고 정상 종료합니다.
            if ($lockedUser->trashed()) {
                return;
            }

            abort_if($actor->id === $lockedUser->id, 403, '현재 로그인한 자기 자신은 삭제할 수 없습니다.');
            abort_if($lockedUser->role?->code === 'super_admin', 403, '최고 관리자는 직원 관리에서 삭제할 수 없습니다.');
            if (! $actor->isHeadOffice() && $actor->role?->code !== 'super_admin') {
                abort_if($lockedUser->store_id !== $actor->store_id, 403, '해당 직원을 삭제할 권한이 없습니다.');
            }
            $before = $this->employeeAuditSnapshot($lockedUser);
            $lockedUser->delete();
            $this->audit->log(
                $actor,
                'employee',
                'delete',
                User::class,
                $lockedUser->id,
                $before,
                [
                    'deleted_at' => $lockedUser->deleted_at?->toISOString(),
                ],
                '직원 삭제'
            );
        });

        return response()->json(['message' => '직원을 삭제했습니다.']);
    }

    /** Soft Delete된 직원을 복구합니다. 기존 재직/계정 상태는 변경하지 않습니다. */
    public function employeeRestore(Request $request, User $user)
    {
        $actor = $request->user();
        $this->access->requirePermission($actor, 'employee.manage');
        abort_unless($user->trashed(), 409, '삭제되지 않은 직원입니다.');
        $user->loadMissing('role');
        abort_if($user->role?->code === 'super_admin', 403, '최고 관리자는 직원 관리 복구 대상이 아닙니다.');
        if (! $actor->isHeadOffice() && $actor->role?->code !== 'super_admin') {
            abort_if($user->store_id !== $actor->store_id, 403, '해당 직원을 복구할 권한이 없습니다.');
        }

        DB::transaction(function () use ($actor, $user) {
            $lockedUser = User::withTrashed()->with('role')->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $lockedUser->trashed()) {
                return;
            }
            abort_if($lockedUser->role?->code === 'super_admin', 403, '최고 관리자는 직원 관리 복구 대상이 아닙니다.');
            if (! $actor->isHeadOffice() && $actor->role?->code !== 'super_admin') {
                abort_if($lockedUser->store_id !== $actor->store_id, 403, '해당 직원을 복구할 권한이 없습니다.');
            }
            $before = ['deleted_at' => $lockedUser->deleted_at?->toISOString()];
            $lastUpdatedAt = $lockedUser->updated_at;
            $lockedUser->restore();

            // 복구는 삭제 상태만 되돌리므로 마지막 실제 직원 정보 수정일은 유지합니다.
            $lockedUser->timestamps = false;
            $lockedUser->forceFill(['updated_at' => $lastUpdatedAt])->saveQuietly();
            $lockedUser->timestamps = true;

            $this->audit->log($actor, 'employee', 'restore', User::class, $lockedUser->id, $before, ['deleted_at' => null], '직원 복구');
        });

        return response()->json(['message' => '직원을 복구했습니다.']);
    }

    /** 직원 변경 이력 비교에 필요한 업무 필드만 안전하게 추출합니다. */
    private function employeeAuditSnapshot(User $user): array
    {
        return [
            'employee_code' => $user->employee_code,
            'name' => $user->name,
            'phone' => $user->phone,
            'birth_date' => $user->birth_date?->format('Y-m-d'),
            'store_id' => $user->store_id,
            'department' => $user->department,
            'position_id' => $user->position_id,
            'role_id' => $user->role_id,
            'employment_status' => $user->employment_status,
            'hired_at' => $user->hired_at?->format('Y-m-d'),
            'resigned_at' => $user->resigned_at?->format('Y-m-d'),
            'is_active' => $user->is_active,
            'deleted_at' => $user->deleted_at?->toISOString(),
        ];
    }

    /** 선택한 역할이 직원의 부서 규칙과 일치하는지 서버에서 최종 검증합니다. */
    private function ensureEmployeeRoleMatchesDepartment(
        ?string $department,
        Role $role
    ): void {
        abort_if(
            $role->code === 'super_admin',
            422,
            '최고 관리자 역할은 직원 등록에서 사용할 수 없습니다.'
        );

        if ($department === null) {
            return;
        }

        $departmentRoleCodes = [
            'kitchen' => [
                'staff',
                'kitchen_head',
            ],
            'hall' => [
                'staff',
                'hall_manager',
            ],
            'head_office' => [
                'head_office_staff',
                'head_office_manager',
            ],
        ];

        abort_if(
            ! in_array(
                $role->code,
                $departmentRoleCodes[$department] ?? [],
                true
            ),
            422,
            '선택한 부서에서 사용할 수 없는 권한 역할입니다.'
        );
    }

    /**
     * 시스템 설정과 시스템 역할(role),
     * 권한(permission), 직급(position)을 조회합니다.
     *
     * 시스템 조회 권한(system.view)이 있는 사용자만
     * 시스템 관리 정보를 조회할 수 있습니다.
     */
    public function system(Request $request)
    {
        // 시스템 조회 권한(system.view)을 확인합니다.
        $this->access->requirePermission(
            $request->user(),
            'system.view'
        );

        return response()->json([
            // 시스템 설정(system settings)을 반환합니다.
            'settings' => SystemSetting::orderBy('key')->get(),

            // 시스템 역할(role)과 해당 역할의 권한(permission)을 반환합니다.
            'roles' => Role::with('permissions:id,code,name')->get(),

            // 현재 사용 중인 권한(is_active = true)을 반환합니다.
            'permissions' => Permission::where('is_active', true)
                ->orderBy('code')
                ->get(),

            // 회사 직급(position)을 정렬 순서(sort_order)에 따라 반환합니다.
            'positions' => Position::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * 시스템 설정(system setting)을 저장합니다.
     *
     * 시스템 관리 권한(system.manage)이 있는 사용자만
     * 시스템 설정을 저장할 수 있습니다.
     */
    public function setting(Request $request)
    {
        // 실제 시스템 설정 변경을 요청한 로그인 사용자를 가져옵니다.
        $user = $request->user();

        // 시스템 관리 권한(system.manage)을 확인합니다.
        $this->access->requirePermission($user, 'system.manage');

        // 시스템 설정의 입력 정보를 검사합니다.
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
         * 동일한 설정 키(key)가 이미 존재하면 기존 설정을 수정하고,
         * 존재하지 않으면 새로운 시스템 설정을 생성합니다.
         */
        $setting = SystemSetting::updateOrCreate(
            [
                'key' => $validated['key'],
            ],
            [
                ...$validated,

                /**
                 * 설정 값(value)이 배열인 경우
                 * 데이터베이스에 저장할 수 있도록 JSON 문자열로 변환합니다.
                 */
                'value' => is_array($validated['value'])
                    ? json_encode($validated['value'], JSON_UNESCAPED_UNICODE)
                    : (string) $validated['value'],

                // 마지막으로 설정을 변경한 사용자(updated_by)를 기록합니다.
                'updated_by' => $user->id,
            ]
        );

        /**
         * 시스템 설정 변경 내용을 감사 로그(audit log)에 기록합니다.
         */
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
     * 시스템 역할(role)에 부여된 권한(permission)을 변경합니다.
     *
     * 시스템 관리 권한(system.manage)이 있는 사용자만
     * 역할별 권한을 변경할 수 있습니다.
     */
    public function rolePermissions(Request $request, Role $role)
    {
        // 실제 역할 권한 변경을 요청한 로그인 사용자를 가져옵니다.
        $user = $request->user();

        // 시스템 관리 권한(system.manage)을 확인합니다.
        $this->access->requirePermission($user, 'system.manage');

        /**
         * 역할(role)에 부여할 권한 코드(permission code)를 검사합니다.
         *
         * 전달된 모든 권한 코드는 실제 권한 테이블(permissions)에
         * 존재하는 값이어야 합니다.
         */
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

        /**
         * 전달받은 권한 코드(permission code)를
         * 실제 권한 번호(permission id) 목록으로 변환합니다.
         */
        $permissionIds = Permission::whereIn(
            'code',
            $validated['permissions']
        )->pluck('id');

        /**
         * 해당 시스템 역할(role)에 연결된 기존 권한을 제거하고
         * 전달받은 권한(permission) 목록으로 동기화합니다.
         */
        $role->permissions()->sync($permissionIds);

        /**
         * 역할별 권한 변경 내용을 감사 로그(audit log)에 기록합니다.
         */
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
     * 감사 로그(audit log)를 조회합니다.
     *
     * 감사 로그 조회 권한(audit.view)이 있는 사용자만
     * 시스템에서 발생한 변경 기록을 조회할 수 있습니다.
     *
     * 점포 사용자는 자신의 소속 점포(store_id)와 관련된
     * 사용자들의 감사 로그만 조회합니다.
     *
     * 본사 사용자와 최고 관리자(super_admin)는
     * 전체 감사 로그를 조회할 수 있습니다.
     */
    public function audits(Request $request)
    {
        // 현재 로그인한 사용자 정보를 가져옵니다.
        $user = $request->user();

        // 감사 로그 조회 권한(audit.view)을 확인합니다.
        $this->access->requirePermission($user, 'audit.view');

        // 최근에 생성된 감사 로그부터 조회합니다.
        $query = AuditLog::with('user:id,name,store_id')
            ->orderByDesc('id');

        /**
         * 점포 사용자는 자신의 소속 점포(store_id)에 속한
         * 사용자와 관련된 감사 로그만 조회합니다.
         *
         * 본사 사용자와 최고 관리자(super_admin)는
         * 이 제한을 적용하지 않습니다.
         */
        if (! $user->isHeadOffice() && $user->role?->code !== 'super_admin') {
            $userIds = User::where('store_id', $user->store_id)
                ->pluck('id');

            $query->whereIn('user_id', $userIds);
        }

        // 가장 최근 감사 로그를 최대 300건까지 반환합니다.
        return response()->json([
            'logs' => $query->limit(300)->get(),
        ]);
    }
}