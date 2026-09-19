<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * 로그인
     *
     * Vue LoginPage에서 전달받은
     * 아이디와 비밀번호를 확인하여 로그인
     */
    public function login(Request $request)
    {
        // 로그인 입력값 검증
        $validated = $request->validate([
            'id' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // 검증이 완료된 아이디로 사용자 조회
        $user = User::where('login_id', $validated['id'])->first();

        // 입력한 아이디와 일치하는 사용자가 없는 경우
        if (!$user) {
            return response()->json([
                'message' => '아이디 또는 비밀번호가 올바르지 않습니다.',
            ], 401);
        }

        // 입력한 비밀번호와 DB에 저장된 암호화된 비밀번호 비교
        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => '아이디 또는 비밀번호가 올바르지 않습니다.',
            ], 401);
        }

        // 인증된 사용자를 Laravel 로그인 세션에 저장
        Auth::login($user);

        // 세션 고정 공격 방지를 위해 세션 ID 재생성
        $request->session()->regenerate();

        // 아이디와 비밀번호가 모두 일치하면 로그인 성공
        return response()->json([
            'message' => '로그인에 성공했습니다.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ],
        ], 200);
    }
}