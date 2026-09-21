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
     * employee_code를 로그인 아이디로 사용한다.
     * 계정, 재직 상태, 역할, 점포 상태를 서버에서 검증한다.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with(['store', 'position', 'role.permissions'])
            ->where('employee_code', $validated['id'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => '아이디 또는 비밀번호가 올바르지 않습니다.'], 401);
        }

        if ($error = $this->validateUserStatus($user)) {
            return $error;
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return response()->json([
            'message' => '로그인에 성공했습니다.',
            'user' => $this->userResponse($user),
        ]);
    }

    /**
     * 현재 로그인 사용자 조회
     *
     * SPA가 새로고침되어도 Laravel 세션을 기준으로
     * 현재 사용자와 권한 정보를 다시 제공한다.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => '로그인이 필요합니다.'], 401);
        }

        $user->loadMissing(['store', 'position', 'role.permissions']);

        if ($error = $this->validateUserStatus($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return $error;
        }

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
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => '로그아웃되었습니다.']);
    }

    /**
     * 사용자 시스템 이용 가능 상태 검사
     */
    private function validateUserStatus(User $user)
    {
        if (! $user->canLogin()) {
            return response()->json(['message' => '현재 로그인할 수 없는 계정입니다.'], 403);
        }

        if (! $user->role || ! $user->role->is_active) {
            return response()->json(['message' => '현재 사용할 수 없는 역할이 지정된 계정입니다.'], 403);
        }

        if ($user->isHeadOffice()) {
            if ($user->store_id !== null) {
                return response()->json(['message' => '본사 계정의 소속 정보가 올바르지 않습니다.'], 403);
            }
            return null;
        }

        if (! $user->store || $user->store->status !== 'active') {
            return response()->json(['message' => '현재 이용할 수 없는 점포의 계정입니다.'], 403);
        }

        return null;
    }

    /**
     * Vue에 전달할 사용자 정보 구성
     *
     * 프론트엔드는 메뉴 표시를 위해 permission 코드를 사용하지만,
     * 실제 데이터 접근 권한은 항상 Laravel에서 다시 검증한다.
     */
    private function userResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'employee_code' => $user->employee_code,
            'name' => $user->name,
            'department' => $user->department,
            'employment_status' => $user->employment_status,
            'store' => $user->store ? [
                'id' => $user->store->id,
                'code' => $user->store->store_code,
                'name' => $user->store->name,
            ] : null,
            'position' => $user->position ? [
                'id' => $user->position->id,
                'code' => $user->position->code,
                'name' => $user->position->name,
            ] : null,
            'role' => [
                'id' => $user->role->id,
                'code' => $user->role->code,
                'name' => $user->role->name,
            ],
            'permissions' => $user->role->permissions
                ->where('is_active', true)
                ->pluck('code')
                ->values()
                ->all(),
        ];
    }
}
