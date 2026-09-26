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
     * 권한 검사 서비스(AccessService)와
     * 감사 로그 서비스(AuditService)를 사용합니다.
     */
    public function __construct(
        private AccessService $access,
        private AuditService $audit
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
        $query = User::with([
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
         * 직원 등록 화면에서 사용할 시스템 역할(role) 목록을 조회합니다.
         *
         * 최고 관리자(super_admin)가 아닌 사용자는
         * 최고 관리자 역할(super_admin)을 다른 직원에게
         * 부여할 수 없으므로 목록에서도 제외합니다.
         *
         * 화면에서 목록을 숨기는 것과 별개로
         * 실제 직원 등록 시 서버에서도 다시 검사합니다.
         */
        $roleQuery = Role::where('is_active', true);

        if ($user->role?->code !== 'super_admin') {
            $roleQuery->where('code', '!=', 'super_admin');
        }

        // 직원 관리 화면과 직원 상세보기에 필요한 정보를 반환합니다.
        return response()->json([
            'employees' => $query->get(),

            // 현재 운영 중인 점포(status = active)만 반환합니다.
            'stores' => Store::where('status', 'active')
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
         * 최고 관리자(super_admin)가 아닌 사용자는
         * 최고 관리자 역할(super_admin)을 부여할 수 없으므로
         * 선택 목록에서도 제외합니다.
         */
        $roleQuery = Role::where('is_active', true);

        if ($user->role?->code !== 'super_admin') {
            $roleQuery->where('code', '!=', 'super_admin');
        }

        return response()->json([
            'message' => '직원 등록 권한이 확인되었습니다.',

            // 현재 운영 중인 점포(status = active)만 반환합니다.
            'stores' => Store::where('status', 'active')
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
             * 최고 관리자(super_admin)가 아닌 사용자는
             * 임시저장 단계에서도 최고 관리자 역할을 지정할 수 없습니다.
             */
            abort_if(
                $selectedRole->code === 'super_admin'
                    && $user->role?->code !== 'super_admin',
                403,
                '최고 관리자 역할을 부여할 권한이 없습니다.'
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
     * - 일반 관리자의 최고 관리자(super_admin) 역할 부여
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
        $validated = $request->validate([
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
        ]);

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
         * 최고 관리자(super_admin) 역할은
         * 현재 로그인한 사용자 역시 최고 관리자(super_admin)인 경우에만
         * 다른 직원에게 부여할 수 있습니다.
         *
         * 본사 관리자(head_office_manager)가 요청 내용을 조작하여
         * 최고 관리자(super_admin)의 역할 번호(role_id)를
         * 직접 전송하는 경우에도 서버에서 차단합니다.
         */
        abort_if(
            $selectedRole->code === 'super_admin'
                && $user->role?->code !== 'super_admin',
            403,
            '최고 관리자 역할을 부여할 권한이 없습니다.'
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
            $employee->toArray(),
            '직원 등록'
        );

        /**
         * 직원 등록이 정상적으로 완료된 경우에만
         * 현재 로그인 세션의 직원 등록 임시저장(draft)을 삭제합니다.
         *
         * 입력 검증 실패나 권한 오류 등으로 등록이 실패하면
         * 이 코드까지 실행되지 않으므로 기존 임시저장 내용은 유지됩니다.
         */
        $request->session()->forget(
            'employee_registration_draft'
        );

        return response()->json([
            'message' => '직원이 등록되었습니다.',
        ], 201);
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