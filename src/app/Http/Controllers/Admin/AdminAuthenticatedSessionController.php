<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController as FortifyController;
use App\Http\Responses\AdminLogoutResponse;

class AdminAuthenticatedSessionController extends FortifyController
{
    public function destroy(Request $request): LogoutResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return new AdminLogoutResponse();
    }
}
