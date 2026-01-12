<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\AttendanceCorrection;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;


class AttendanceController extends Controller
{
    /**
     * 一般ユーザー
     * 勤怠登録画面
     */

    public function index(Request $request)
    {
        $user = auth()->user();
        $now = now();

        $today = now()->toDateString();

        // 今日の勤怠レコード
        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        // ============================
        // ステータス判定
        // ============================
        $status = '勤務外';

        if ($attendance) {
            switch ($attendance->status) {
                case 'off':
                    $status = '勤務外';
                    break;

                case 'working':
                    $status = '出勤中';
                    break;

                case 'break':
                    $status = '休憩中';
                    break;

                case 'finished':
                    $status = '退勤済';
                    break;
            }
        }

        return view('attendance.index', [
            'now' => $now,
            'today' => $today,
            'attendance' => $attendance,
            'status' => $status,
        ]);
    }

    // ===== 出勤 =====
    public function clockIn(Request $request)
    {
        $user = auth()->user();

        // 勤怠レコード新規作成
        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => now(),
            'status' => 'working',
        ]);

        $record->recalcTotalTimes();
        $record->save();

        return redirect('/attendance');
    }

    // ===== 休憩開始 =====
    public function breakStart(Request $request)
    {
        $user = auth()->user();

        // 今日の勤怠レコード取得
        $record = AttendanceRecord::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->firstOrFail();

        // 休憩レコード作成
        BreakRecord::create([ 
            'attendance_record_id' => $record->id,
            'break_start' => now(),
        ]);

        // 勤務時間を再計算
        $record->recalcTotalTimes();

        $record->save();

        // 勤務ステータスを休憩中に変更
        $record->update(['status' => 'break']);
        // 勤怠画面へリダイレクト
        return redirect('/attendance');
    }

    // ===== 休憩終了 =====
    public function breakEnd(Request $request)
    {
        $user = auth()->user();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->firstOrFail();

        // 一番新しい休憩レコードを取得して終了時間を入れる
        $break = BreakRecord::where('attendance_record_id', $record->id)
            ->whereNull('break_end')
            ->latest()
            ->first();

        if ($break) {
            $break->update([
                'break_end' => now(),
            ]);
        }

        $record->recalcTotalTimes();
        $record->save();

        // 勤務中へ戻す
        $record->update(['status' => 'working']);

        return redirect('/attendance');
    }

    // ===== 退勤 =====
    public function clockOut(Request $request)
    {
        $user = auth()->user();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->where('date', now()->toDateString())
            ->firstOrFail();

        $record->update([
            'clock_out' => now(),
            'status' => 'finished',
        ]);

        $record->recalcTotalTimes();
        $record->save();

        return redirect('/attendance');
    }

    /**
     * 一般ユーザー
     * 勤怠一覧画面
     */

    public function listMonth(Request $request)
    {
        // デフォルトは今月
        $year = $request->query('year', now()->year);
        $month = $request->query('month', now()->month);

        // 基準日・前月・翌月の作成
        $date = \Carbon\Carbon::create($year, $month, 1);
        $prevMonth = $date->copy()->subMonth();
        $nextMonth = $date->copy()->addMonth();

        // ログインユーザー取得
        $user = auth()->user();

        // 勤怠レコード取得（breaksも一緒に取得）
        $records = AttendanceRecord::withCount([
            'corrections as pending_corrections_count' => function ($q) {
                $q->where('status', 'pending');
            }
        ])
            ->where('user_id', $user->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->with('breaks')
            ->get()
            ->keyBy(fn($item) => $item->date->format('Y-m-d'));

        $days = [];

        for ($day = 1; $day <= $date->daysInMonth; $day++) {
            $currentDate = $date->copy()->day($day);
            $dateKey = $currentDate->format('Y-m-d');

            $attendance = $records[$dateKey] ?? null;

            $days[] = [
                'date' => $dateKey,
                'label' => $currentDate->format('m/d') . '(' . $currentDate->isoFormat('ddd') . ')',

                'attendance' => $attendance,

                'clock_in_time' => $attendance?->clock_in_time,
                'clock_out_time' => $attendance?->clock_out_time,
                'total_break_formatted' => $attendance?->total_break_formatted,
                'total_minutes_formatted' => $attendance?->total_minutes_formatted,
            ];
        }

        return view('attendance.list', compact(
            'date',
            'prevMonth',
            'nextMonth',
            'days'
        ));

    }

    /**
     * 一般ユーザー
     * 勤怠詳細画面
     */

    public function show($id, Request $request)
    {
        $user = auth()->user();

        // 日付をURLから取得（なければ今日）
        $date = Carbon::parse($request->query('date', now()->toDateString()));

        // 勤怠レコード取得（休憩・修正もまとめて取得）
        $record = AttendanceRecord::where('user_id', $user->id)
            ->where('date', $date->toDateString())
            ->with(['breaks', 'corrections.correction_breaks'])
            ->first();

        // レコードがなければ仮作成
        if (!$record) {
            $record = new AttendanceRecord([
                'user_id' => $user->id,
                'date'    => $date,
            ]);
            $record->setRelation('breaks', collect());
        }

        // URLに correction_id があれば優先
        $correctionId = $request->query('correction_id');

        // 未承認（自分の申請）を優先
        $selectedCorrection = $record->corrections()
            ->where('status', 'pending')
            ->where('requested_by_user_id', $user->id)
            ->latest()
            ->first();

        // 出退勤（H:i 形式）
        $clockIn = '';
        if ($selectedCorrection && $selectedCorrection->clock_in) {
            $clockIn = Carbon::parse($selectedCorrection->clock_in)->format('H:i');
        } elseif ($record->clock_in) {
            $clockIn = Carbon::parse($record->clock_in)->format('H:i');
        }
        $clockIn = old('clock_in', $clockIn);

        $clockOut = '';
        if ($selectedCorrection && $selectedCorrection->clock_out) {
            $clockOut = Carbon::parse($selectedCorrection->clock_out)->format('H:i');
        } elseif ($record->clock_out) {
            $clockOut = Carbon::parse($record->clock_out)->format('H:i');
        }
        $clockOut = old('clock_out', $clockOut);

        $isNew = !$record->exists;
        $clockInValue  = $isNew ? '' : $clockIn;
        $clockOutValue = $isNew ? '' : $clockOut;

        // 休憩
        $baseBreaks = $selectedCorrection
            ? ($selectedCorrection->status === 'approved'
                ? $record->breaks
                : $selectedCorrection->correction_breaks)
            : $record->breaks;

        $breakList = [];
        $oldStart = old('break_start');
        $oldEnd   = old('break_end');


        if (is_array($oldStart)) {
            foreach ($oldStart as $i => $start) {
                $breakList[] = [
                    'start' => $start ?? '',
                    'end'   => $oldEnd[$i] ?? '',
                ];
            }
        } else {
            foreach ($baseBreaks as $b) {
                $breakList[] = [
                    'start' => $b->break_start ? Carbon::parse($b->break_start)->format('H:i') : '',
                    'end'   => $b->break_end   ? Carbon::parse($b->break_end)->format('H:i')   : '',
                ];
            }
        }

        // 空行がなければ1件追加
        $hasEmptyRow = collect($breakList)->contains(fn($b) => empty($b['start']) && empty($b['end']));
        if (!$hasEmptyRow) {
            $breakList[] = ['start' => '', 'end' => ''];
        }

        // 備考
        $pendingCorrection = $record->corrections()
            ->where('status', 'pending')
            ->where('requested_by_user_id', $user->id)
            ->latest()
            ->first();

        $isPending = (bool) $pendingCorrection;

        $remarks = $pendingCorrection
            ? $pendingCorrection->remarks
            : $record->remarks;

        return view('attendance.show', compact(
            'record', 'user', 'date',
            'clockInValue', 'clockOutValue',
            'breakList', 'remarks',
            'isPending', 'selectedCorrection'
        ));
    }
}
