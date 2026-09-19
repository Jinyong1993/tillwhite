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
     * Vue의 LoginPage에서 전달받은 아이디와 비밀번호를 검증한 뒤
     * users 테이블에 저장된 사용자 정보와 비교하여 로그인을 처리한다.
     *
     * 로그인 과정에서는 아이디와 비밀번호뿐만 아니라
     * 계정 활성 상태, 재직 상태, 소속 점포 상태, 역할 상태까지 확인한다.
     */
    public function login(Request $request)
    {
        /**
         * 로그인 입력값 검증
         *
         * id:
         * 로그인 화면에서 입력한 로그인 아이디이며
         * users.login_id 컬럼과 비교한다.
         *
         * password:
         * 사용자가 입력한 평문 비밀번호이며
         * DB에 저장된 해시 비밀번호와 비교한다.
         */
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        /**
         * 사용자 조회
         *
         * 로그인 과정에서 점포와 역할 정보를 사용하므로
         * store와 role 관계를 함께 조회한다.
         */
        $user = User::with(['store', 'role'])
            ->where('login_id', $validated['id'])
            ->first();

        /**
         * 사용자 존재 여부 확인
         *
         * 아이디 존재 여부를 외부에서 구분할 수 없도록
         * 비밀번호 오류와 동일한 메시지를 반환한다.
         */
        if (!$user) {
            return response()->json([
                'message' => '아이디 또는 비밀번호가 올바르지 않습니다.',
            ], 401);
        }

        /**
         * 비밀번호 확인
         *
         * 입력한 평문 비밀번호와 users.password에 저장된
         * 해시 비밀번호를 안전하게 비교한다.
         */
        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => '아이디 또는 비밀번호가 올바르지 않습니다.',
            ], 401);
        }

        /**
         * 사용자 상태 검사
         *
         * 실제 로그인을 허용하기 전에 계정, 재직 상태,
         * 소속 점포 및 역할이 모두 정상인지 확인한다.
         */
        $statusError = $this->validateUserStatus($user);

        if ($statusError) {
            return $statusError;
        }

        /**
         * Laravel 인증 세션 생성
         *
         * 모든 검사를 통과한 사용자를
         * 현재 로그인 사용자로 등록한다.
         */
        Auth::login($user);

        /**
         * 세션 ID 재생성
         *
         * 로그인 전 세션 ID를 새로운 값으로 변경하여
         * 세션 고정 공격을 방지한다.
         */
        $request->session()->regenerate();

        /**
         * 마지막 로그인 시간 기록
         *
         * 모든 인증 절차를 정상적으로 통과한 경우에만
         * users.last_login_at을 현재 시간으로 갱신한다.
         */
        $user->last_login_at = now();
        $user->save();

        return response()->json([
            'message' => '로그인에 성공했습니다.',
            'user' => $this->userResponse($user),
        ], 200);
    }

    /**
     * 현재 로그인 사용자 조회
     *
     * Laravel 세션에 저장된 인증 정보를 기준으로
     * 현재 로그인되어 있는 사용자의 정보를 반환한다.
     *
     * Vue에서 페이지를 새로고침하거나 직접 URL로 접근했을 때
     * 기존 로그인 세션이 유지되고 있는지 확인하는 데 사용한다.
     */
    public function me(Request $request)
    {
        // 현재 Laravel 세션에 인증된 사용자 조회
        $user = $request->user();

        /**
         * 로그인 세션이 존재하지 않는 경우
         *
         * 인증된 사용자가 없으므로 HTTP 401 응답을 반환한다.
         */
        if (!$user) {
            return response()->json([
                'message' => '로그인이 필요합니다.',
            ], 401);
        }

        /**
         * 점포 및 역할 관계 조회
         *
         * 현재 사용자 상태 검사와 Vue 사용자 정보 반환에
         * 필요한 관계 데이터를 불러온다.
         */
        $user->loadMissing(['store', 'role']);

        /**
         * 현재 사용자 상태 재검사
         *
         * 로그인 이후 관리자가 계정을 중지하거나 퇴사 처리하거나,
         * 점포 또는 역할을 비활성화했을 가능성이 있으므로
         * 현재 DB 상태를 기준으로 다시 검사한다.
         */
        $statusError = $this->validateUserStatus($user);

        if ($statusError) {
            /**
             * 더 이상 시스템을 이용할 수 없는 사용자라면
             * 기존 로그인 세션도 함께 종료한다.
             */
            Auth::logout();

            // 기존 세션 데이터를 무효화한다.
            $request->session()->invalidate();

            // CSRF 토큰을 새로 발급한다.
            $request->session()->regenerateToken();

            return $statusError;
        }

        return response()->json([
            'message' => '로그인 상태입니다.',
            'user' => $this->userResponse($user),
        ], 200);
    }

    /**
     * 사용자 로그아웃 처리
     *
     * 현재 Laravel 인증 세션에서 사용자를 로그아웃시키고
     * 기존 세션 데이터를 완전히 무효화한다.
     *
     * 로그아웃 후에는 새로운 CSRF 토큰을 생성하여
     * 이전 로그인 세션에서 사용하던 인증 정보를 재사용할 수 없도록 한다.
     */
    public function logout(Request $request)
    {
        // 현재 Laravel 인증 세션에서 사용자를 로그아웃한다.
        Auth::logout();

        /**
         * 현재 세션 무효화
         *
         * 로그인 과정에서 생성되었던 세션 데이터를 제거하여
         * 기존 세션 ID를 통한 인증 상태가 유지되지 않도록 한다.
         */
        $request->session()->invalidate();

        /**
         * CSRF 토큰 재생성
         *
         * 로그아웃 이전에 사용하던 CSRF 토큰을 폐기하고
         * 새로운 토큰을 발급한다.
         */
        $request->session()->regenerateToken();

        return response()->json([
            'message' => '로그아웃되었습니다.',
        ], 200);
    }

    /**
     * 사용자 시스템 이용 가능 상태 검사
     *
     * 로그인과 로그인 상태 확인에서 동일한 기준을 사용하도록
     * 사용자 상태 검사 로직을 한 곳에서 관리한다.
     *
     * 검사 대상:
     * - 계정 활성 상태
     * - 재직 상태
     * - 소속 점포 존재 여부
     * - 소속 점포 운영 상태
     * - 역할 존재 여부
     * - 역할 활성 상태
     */
    private function validateUserStatus(User $user)
    {
        // 시스템 사용이 중지된 계정인지 확인
        if (!$user->is_active) {
            return response()->json([
                'message' => '사용이 중지된 계정입니다.',
            ], 403);
        }

        // 퇴사 처리된 사용자인지 확인
        if (!$user->is_employed) {
            return response()->json([
                'message' => '퇴사 처리된 계정입니다.',
            ], 403);
        }

        // 사용자에게 정상적인 소속 점포가 존재하는지 확인
        if (!$user->store) {
            return response()->json([
                'message' => '소속 점포 정보를 확인할 수 없습니다.',
            ], 403);
        }

        // 현재 소속 점포가 운영 가능한 상태인지 확인
        if (!$user->store->status) {
            return response()->json([
                'message' => '현재 이용할 수 없는 점포의 계정입니다.',
            ], 403);
        }

        // 사용자에게 정상적인 역할이 지정되어 있는지 확인
        if (!$user->role) {
            return response()->json([
                'message' => '사용자 역할 정보를 확인할 수 없습니다.',
            ], 403);
        }

        // 현재 지정된 역할을 사용할 수 있는지 확인
        if (!$user->role->is_active) {
            return response()->json([
                'message' => '현재 사용할 수 없는 역할이 지정된 계정입니다.',
            ], 403);
        }

        // 모든 상태 검사를 통과하면 오류가 없음을 반환
        return null;
    }

    /**
     * Vue에 전달할 사용자 정보 구성
     *
     * User 모델 전체를 그대로 반환하지 않고
     * 프론트엔드에서 실제로 필요한 정보만 명시적으로 구성한다.
     *
     * login()과 me()에서 동일한 응답 구조를 사용하여
     * 사용자 정보 형식이 서로 달라지는 문제를 방지한다.
     */
    private function userResponse(User $user): array
    {
        return [
            // 사용자 고유 번호
            'id' => $user->id,

            // 로그인에 사용하는 사용자 아이디
            'login_id' => $user->login_id,

            // 사용자 이름
            'name' => $user->name,

            // 현재 소속 부서
            'department' => $user->department,

            /**
             * 현재 소속 점포 정보
             *
             * 생산 및 폐기 데이터의 점포 범위를 표시하거나
             * 화면에 현재 점포명을 표시할 때 사용한다.
             */
            'store' => [
                'id' => $user->store->id,
                'code' => $user->store->store_code,
                'name' => $user->store->name,
            ],

            /**
             * 현재 사용자 역할 정보
             *
             * Vue 화면 구성에 사용할 수 있는 역할 정보이다.
             *
             * 실제 생산/폐기 등의 서버 권한 검사는
             * role.code만 신뢰하지 않고 permissions 및
             * Laravel의 권한 검사 로직을 통해 처리한다.
             */
            'role' => [
                'id' => $user->role->id,
                'code' => $user->role->code,
                'name' => $user->role->name,
            ],
        ];
    }
}