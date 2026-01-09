<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\CustomRegisterController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\Admin\AdminAttendanceController;
use App\Http\Controllers\Admin\AdminStaffController;
use App\Http\Controllers\Admin\AdminAttendanceCorrectionController;

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\Admin\AdminAuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// ===============================
//  一般ユーザー
// ===============================

Route::post('/register', [CustomRegisterController::class, 'store'])->name('register');

// メール認証誘導画面
Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->name('verification.notice');

// メールリンククリック時
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    if (! URL::hasValidSignature($request)) {
        abort(403);
    }

    $user = User::findOrFail($id);

    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
    }

    Auth::login($user);
    $request->session()->regenerate();

    return $user->role === 'admin'
        ? redirect()->route('admin.attendance.index')
        : redirect()->route('attendance.index');

})->name('verification.verify')->middleware('signed');

// 確認メール再送信
Route::post('/email/verification-notification', function (Request $request) {
    $request->validate([
        'email' => ['required', 'email'],
    ]);

    $user = User::where('email', $request->input('email'))->first();

    if ($user && ! $user->hasVerifiedEmail()) {
        $user->sendEmailVerificationNotification();
    }

    return back();
})->name('verification.send');

// ===============================
//  ログイン後のユーザー専用ページ
// ===============================
Route::middleware(['auth'])->group(function () {
    // 出勤登録画面
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clockIn');
    Route::post('/attendance/break-start', [AttendanceController::class, 'breakStart'])->name('attendance.breakStart');
    Route::post('/attendance/break-end', [AttendanceController::class, 'breakEnd'])->name('attendance.breakEnd');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clockOut');

    // 勤怠一覧画面
    Route::get('/attendance/list', [AttendanceController::class, 'listMonth'])->name('attendance.listMonth');

    // 勤怠詳細画面
    Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show'])->name('attendance.show');

    // 修正申請送信
    Route::post('/stamp_correction_request', [AttendanceCorrectionController::class, 'store'])->name('attendance_correction.store');
    // 申請一覧画面
    Route::get('/stamp_correction_request/list', [AttendanceCorrectionController::class, 'index'])->name('attendance_corrections.index');

});


// ===============================
//  管理者
// ===============================

// ログイン画面
Route::get('/admin/login', function () {
    return view('admin.auth.login');
})->name('admin.login');

// ログイン処理（POST）
Route::post('/admin/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest')
    ->name('admin.login');

// ログアウト
Route::post('/admin/logout', [AdminAuthenticatedSessionController::class, 'destroy'])
    ->middleware(['auth', 'admin'])
    ->name('admin.logout');

Route::middleware(['auth', 'admin'])->group(function () {
    // 勤怠一覧画面
    Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index'])->name('admin.attendance.index');
    // 勤怠詳細画面
    Route::get('/admin/attendance/{id}', [AdminAttendanceController::class, 'show'])->name('admin.attendance.show');

    // 修正登録（新規or更新）
    Route::post('/admin/attendance/save', [AdminAttendanceController::class, 'save'])->name('admin.attendance.save');

    // スタッフ一覧画面
    Route::get('/admin/staff/list', [AdminStaffController::class, 'list'])->name('admin.staff.list');

    // スタッフ別月間勤怠画面
    Route::get('/admin/attendance/staff/{id}', [AdminAttendanceController::class, 'monthly'])->name('admin.attendance.monthly');

    // csv出力
    Route::get('/admin/attendance/staff/{id}/csv', [AdminAttendanceController::class, 'monthlyCsv'])->name('admin.attendance.csv');

    // 申請一覧
    Route::get('/admin/stamp_correction_request/list', [AdminAttendanceCorrectionController::class, 'index'])->name('admin.attendance_corrections.index');

    // 修正申請承認画面
    Route::get('/admin/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminAttendanceCorrectionController::class, 'showCorrection'])->name('admin.attendance_corrections.show');

    // 修正承認
    Route::put('/admin/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminAttendanceCorrectionController::class, 'approve'])->name('admin.attendance_corrections.approve');
});
