<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = $request->user();

        // admin はメール認証不要
        if ($user->role === 'admin') {
            return redirect()->route('admin.attendance.index');
        }

        // 一般ユーザーで、メール未認証なら verify へ
        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->route('attendance.index');
    }
}

