<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

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
        // 입력받은 로그인 아이디로 사용자 조회
        // 해당 사용자가 존재하지 않으면 null 반환
        $user = User::where('login_id', $request->id)->first();

        // 입력한 아이디와 일치하는 사용자가 없는 경우
        if (!$user) {
            return response()->json([
                'message' => '아이디 또는 비밀번호가 올바르지 않습니다.',
            ], 401);
        }

        return;
    }
}