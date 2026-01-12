<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use App\Models\AttendanceCorrection;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;



class AdminAttendanceCorrectionController extends Controller
{
    /**
     * 管理者
     * 勤怠修正申請一覧（承認待ち／承認済み）
     */

    public function index(Request $request)
    {
        // tab に応じて「承認待ち / 承認済み」の最新申請のみを取得
        $tab = $request->query('tab', 'pending');

        $corrections = AttendanceCorrection::with('attendanceRecord')
            ->join('attendance_records', 'attendance_corrections.attendance_record_id', '=', 'attendance_records.id')
            ->where('attendance_corrections.status', $tab)
            ->whereIn('attendance_corrections.id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('attendance_corrections')
                    ->groupBy('attendance_record_id'); 
            })
            ->orderBy('attendance_records.date', 'asc')
            ->select('attendance_corrections.*')
            ->get();

        return view('admin.corrections.index', compact('tab', 'corrections'));
    }

    /**
     * 承認画面
     */

    public function showCorrection(Request $request, $correctionId)
    {

        $selectedCorrection = AttendanceCorrection::with([
            'attendanceRecord.breaks',
            'correction_breaks',
            'requestedBy',
        ])->findOrFail($correctionId);

        $record = $selectedCorrection->attendanceRecord;

        $user   = $record->user;
        $date   = $record->date;


        // ===============================
        // 状態判定
        // ===============================

        $isAdmin = auth()->check() && auth()->user()->role === 'admin';

        $isPendingCorrection  = $selectedCorrection->status === 'pending';
        $isApprovedCorrection = $selectedCorrection->status === 'approved';

        // 承認ボタンは管理者かつ pending の場合のみ
        $canApprove = $isAdmin && $isPendingCorrection;

        // 修正できるのは承認済みのみ
        $canEdit = $isApprovedCorrection;

        $showPendingMessage = false;

        // -------------------------------
        // 出退勤
        // -------------------------------
        $clockIn  = $selectedCorrection->clock_in
            ? Carbon::parse($selectedCorrection->clock_in)->format('H:i')
            : ($record->clock_in ? Carbon::parse($record->clock_in)->format('H:i') : '');

        $clockOut = $selectedCorrection->clock_out
            ? Carbon::parse($selectedCorrection->clock_out)->format('H:i')
            : ($record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '');

        // 出退勤の入力値
        $clockInValue  = old('clock_in', $clockIn ?? '');
        $clockOutValue = old('clock_out', $clockOut ?? '');

        // 入力 readonly 判定
        $isReadonly = !$canEdit;


        // -------------------------------
        // 休憩
        // -------------------------------
        $breakList = [];

        // ★承認済みの詳細画面なら AttendanceRecord.breaks を使う
        $breakSource = $selectedCorrection->status === 'approved'
            ? $record->breaks
            : $selectedCorrection->correction_breaks;

        foreach ($breakSource as $b) {
            $breakList[] = [
                'start' => $b->break_start ? Carbon::parse($b->break_start)->format('H:i') : '',
                'end'   => $b->break_end   ? Carbon::parse($b->break_end)->format('H:i')   : '',
            ];
        }

        // 空行がなければ 1 行追加
        $hasEmptyRow = collect($breakList)->contains(function ($b) {
            return empty($b['start']) && empty($b['end']);
        });

        if (!$hasEmptyRow) {
            $breakList[] = ['start' => '', 'end' => ''];
        }
        // -------------------------------
        // 備考
        // -------------------------------
        if ($selectedCorrection->status === 'approved') {
            $record = $selectedCorrection->attendanceRecord->fresh();
            $remarks = $record->remarks;
        } else {
            $remarks = $selectedCorrection->remarks;
        }

        return view('admin.attendance.show', compact(
            'record',
            'user',
            'date',
            'clockInValue',
            'clockOutValue',
            'isReadonly',
            'breakList',
            'remarks',
            'selectedCorrection',
            'canEdit',
            'canApprove',
            'showPendingMessage',
        ));
    }


    public function approve($id)
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $correction = AttendanceCorrection::with('correction_breaks', 'attendanceRecord')->findOrFail($id);

            if ($correction->status !== 'pending') {
            return response()->json([
                'status' => 'already_approved',
            ], 400);
        }

        DB::transaction(function () use ($correction) {

            $correction->update([
                'status' => 'approved',
                'approved_by_user_id' => auth()->id(),
            ]);

            //  勤怠本体を更新
            $record = $correction->attendanceRecord;

            $record->update([
                'clock_in'  => $correction->clock_in,
                'clock_out' => $correction->clock_out,
                'remarks'   => $correction->remarks,
            ]);

            // 既存休憩を削除
            $record->breaks()->delete();

            // 修正申請の休憩を反映
            foreach ($correction->correction_breaks as $break) {
                $record->breaks()->create([
                    'break_start' => $break->break_start,
                    'break_end'   => $break->break_end,
                ]);
            }

            // 勤務時間再計算
            $record->recalcTotalTimes();
        });

        return response()->json([
            'status' => 'approved',
        ]);
    }

}
