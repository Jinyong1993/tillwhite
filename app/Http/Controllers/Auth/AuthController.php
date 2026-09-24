<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * 사용자 로그인 처리
     *
     * employee_code를 로그인 아이디로 사용합니다.
     *
     * 로그인 과정에서 다음 항목을 확인합니다.
     * - 아이디 및 비밀번호
     * - 계정 이용 가능 상태
     * - 재직 상태
     * - 시스템 역할 상태
     * - 본사 계정의 소속 정보
     * - 점포 계정의 점포 운영 상태
     */
    public function login(Request $request)
    {
        // 로그인 입력값 검증
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // 사번(employee_code)을 기준으로 사용자 및 관련 정보 조회
        $user = User::with(['store', 'position', 'role.permissions'])
            ->where('employee_code', $validated['id'])
            ->first();

        // 사용자가 없거나 비밀번호가 일치하지 않는 경우
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => '아이디 또는 비밀번호가 올바르지 않습니다.',
            ], 401);
        }

        // 계정, 역할, 소속 점포 등의 시스템 이용 가능 상태 검사
        if ($error = $this->validateUserStatus($user)) {
            return $error;
        }

        // Laravel 인증 세션에 사용자 로그인 처리
        Auth::login($user);

        // 세션 고정 공격 방지를 위해 세션 ID 재생성
        $request->session()->regenerate();

        // 마지막 로그인 시간 갱신
        $user->update([
            'last_login_at' => now(),
        ]);

        // 로그인 성공 결과 및 사용자 정보 반환
        return response()->json([
            'message' => '로그인에 성공했습니다.',
            'user' => $this->userResponse($user),
        ]);
    }

    /**
     * 현재 로그인 사용자 조회
     *
     * SPA가 새로고침되어도 Laravel 세션을 기준으로
     * 현재 로그인 사용자와 권한 정보를 다시 제공합니다.
     */
    public function me(Request $request)
    {
        // 현재 Laravel 세션에 로그인된 사용자 조회
        $user = $request->user();

        // 로그인된 사용자가 없는 경우
        if (! $user) {
            return response()->json([
                'message' => '로그인이 필요합니다.',
            ], 401);
        }

        // 사용자 응답 및 상태 검사에 필요한 관계 데이터 조회
        $user->loadMissing([
            'store',
            'position',
            'role.permissions',
        ]);

        // 로그인 이후 계정 상태가 변경되었는지 다시 검사
        if ($error = $this->validateUserStatus($user)) {
            // 더 이상 이용할 수 없는 계정이면 현재 세션 종료
            Auth::logout();

            // 기존 세션 무효화
            $request->session()->invalidate();

            // 새로운 CSRF 토큰 생성
            $request->session()->regenerateToken();

            return $error;
        }

        // 현재 로그인 사용자 및 권한 정보 반환
        return response()->json([
            'message' => '로그인 상태입니다.',
            'user' => $this->userResponse($user),
        ]);
    }

    /**
     * 사용자 로그아웃 처리
     */
    public function logout(Request $request)
    {
        // Laravel 인증 세션에서 로그아웃
        Auth::logout();

        // 기존 세션 무효화
        $request->session()->invalidate();

        // 새로운 CSRF 토큰 생성
        $request->session()->regenerateToken();

        return response()->json([
            'message' => '로그아웃되었습니다.',
        ]);
    }

    /**
     * 사용자가 현재 시스템을 이용할 수 있는 상태인지 검사합니다.
     *
     * 검사 결과 문제가 있으면 오류 Response를 반환하고,
     * 정상적인 사용자라면 null을 반환합니다.
     */
    private function validateUserStatus(User $user)
    {
        // 계정 자체가 로그인 가능한 상태인지 확인
        if (! $user->canLogin()) {
            return response()->json([
                'message' => '현재 로그인할 수 없는 계정입니다.',
            ], 403);
        }

        // 역할이 없거나 비활성화된 역할인 경우 이용 차단
        if (! $user->role || ! $user->role->is_active) {
            return response()->json([
                'message' => '현재 사용할 수 없는 역할이 지정된 계정입니다.',
            ], 403);
        }

        /**
         * 본사 직원은 특정 점포에 소속되지 않습니다.
         *
         * 따라서 본사 계정인데 store_id가 존재하면
         * 잘못된 소속 정보로 판단합니다.
         */
        if ($user->isHeadOffice()) {
            if ($user->store_id !== null) {
                return response()->json([
                    'message' => '본사 계정의 소속 정보가 올바르지 않습니다.',
                ], 403);
            }

            return null;
        }

        /**
         * 점포 직원은 반드시 정상적인 점포에 소속되어야 하며,
         * 해당 점포의 상태가 active여야 시스템을 이용할 수 있습니다.
         */
        if (! $user->store || $user->store->status !== 'active') {
            return response()->json([
                'message' => '현재 이용할 수 없는 점포의 계정입니다.',
            ], 403);
        }

        return null;
    }

    /**
     * Vue에 전달할 사용자 정보를 구성합니다.
     *
     * 프론트엔드는 메뉴 및 버튼 표시를 위해
     * permission 코드를 사용합니다.
     *
     * 다만 프론트엔드의 권한 확인은 화면 표시를 위한 것이며,
     * 실제 데이터 접근 권한은 Laravel에서 다시 검증합니다.
     */
    private function userResponse(User $user): array
    {
        return [
            // 사용자 기본 정보
            'id' => $user->id,
            'employee_code' => $user->employee_code,
            'name' => $user->name,
            'department' => $user->department,
            'employment_status' => $user->employment_status,

            // 소속 점포 정보
            'store' => $user->store ? [
                'id' => $user->store->id,
                'code' => $user->store->store_code,
                'name' => $user->store->name,
            ] : null,

            // 회사 직급 정보
            'position' => $user->position ? [
                'id' => $user->position->id,
                'code' => $user->position->code,
                'name' => $user->position->name,
            ] : null,

            // 시스템 역할 정보
            'role' => [
                'id' => $user->role->id,
                'code' => $user->role->code,
                'name' => $user->role->name,
            ],

            /**
             * 현재 역할에 연결된 활성화 권한만 추출하여
             * permission code 배열 형태로 Vue에 전달합니다.
             *
             * 예:
             * [
             *     'production.view',
             *     'production.create',
             *     'schedule.view',
             * ]
             */
            'permissions' => $user->role->permissions
                ->where('is_active', true)
                ->pluck('code')
                ->values()
                ->all(),
        ];
    }
}