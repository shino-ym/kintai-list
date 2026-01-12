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
        $user = app(CreateNewUser::class)->create($request->validated());

        session(['registered_email' => $user->email]);

        return redirect()->route('verification.notice');
    }
}
