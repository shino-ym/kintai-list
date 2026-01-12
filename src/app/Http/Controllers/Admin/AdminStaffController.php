<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;


class AdminStaffController extends Controller
{
    /**
     * 管理者
     * スタッフ一覧画面
     */

    public function list(Request $request)
    {
        $staffs = User::where('role', 'user')->get();

        return view('admin.staffs.list',compact('staffs'));
    }
}
