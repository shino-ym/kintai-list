<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Auth\Events\Registered;
use App\Http\Requests\RegisterRequest;


class CustomRegisterController extends Controller
{
    public function store(RegisterRequest $request)
    {
        // ユーザー登録処理
        $user = app(CreateNewUser::class)->create($request->validated());

        // 登録したメールをセッションに保存
        session(['registered_email' => $user->email]);

        // メール認証通知ページへリダイレクト
        return redirect()->route('verification.notice');
    }
}
