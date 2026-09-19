<?php

namespace App\Http\Controllers\Auth;

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
        // 입력받은 로그인 아이디로 사용자 조회
        // 해당 사용자가 존재하지 않으면 null 반환
        $user = User::where('login_id', $request->id)->first();

        // 입력한 아이디와 일치하는 사용자가 없는 경우
        if (!$user) {
            return response()->json([
                'message' => '아이디 또는 비밀번호가 올바르지 않습니다.',
            ], 401);
        }

        // 입력한 비밀번호와 DB에 저장된 암호화된 비밀번호 비교
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => '아이디 또는 비밀번호가 올바르지 않습니다.',
            ], 401);
        }

        return;
    }
}