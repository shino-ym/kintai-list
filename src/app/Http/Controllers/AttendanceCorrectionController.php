<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceCorrection;
use App\Models\CorrectionBreak;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceCorrectionController extends Controller
{
    /**
     * 一般ユーザー
     * 修正申請
     */

    public function store(AttendanceCorrectionRequest $request)
    {
        $userId = Auth::id();

        // 修正したい日付を取得
        $date = $request->date;

        // 勤怠レコード取得（存在しない場合は null）
        $record = AttendanceRecord::where('user_id', $userId)
            ->whereDate('date', $date)
            ->first();

        // $record が 存在しなかった場合、勤怠レコードを新しく作る
        if (!$record) {
            $record = AttendanceRecord::create([
                'user_id' => $userId,
                'date'    => $date,
                'clock_in' => null,
                'clock_out' => null,
                'status' => 'finished',
                'total_seconds' => 0,
                'total_break_seconds' => 0,
            ]);
        }

        // 未承認の修正申請があるかチェック
        $hasPending = $record?->corrections()
            ->where('status', 'pending')
            ->where('requested_by_user_id', $userId)
            ->exists();

        // 未承認があったら処理終了
        if ($hasPending) {
            return redirect()->back();
        }

        $data = $request->validated();

        // Carbon変換
        $clockIn  = $this->toCarbon($date, $data['clock_in'] ?? null);
        $clockOut = $this->toCarbon($date, $data['clock_out'] ?? null);

        // 修正申請作成（AttendanceRecord は触らない）
        $correction = AttendanceCorrection::create([
            'attendance_record_id'  => $record->id,
            'requested_by_user_id'  => $userId,
            'clock_in'              => $clockIn,
            'clock_out'             => $clockOut,
            'remarks'               => $data['remarks'],
            'status'                => 'pending',
        ]);

        // 複数休憩の保存
        $breakStarts = $data['break_start'] ?? [];
        $breakEnds   = $data['break_end'] ?? [];

        foreach ($breakStarts as $i => $start) {
            $end = $breakEnds[$i] ?? null;

            $breakStart = $this->toCarbon($date, $start);
            $breakEnd   = $this->toCarbon($date, $end);

            if ($breakStart || $breakEnd) {
                CorrectionBreak::create([
                    'attendance_correction_id' => $correction->id,
                    'break_start' => $breakStart,
                    'break_end'   => $breakEnd,
                ]);
            }
        }

        return redirect()
            ->route('attendance.show', [
                'id' => $record?->id ?? 0,
                'date' => $date,
            ]);
    }

    /**
     * 文字列または null を Carbon に変換
     */
    protected function toCarbon(string $date, $time): ?Carbon
    {
        if (!$time) return null;

        $cleanDate = Carbon::parse($date)->format('Y-m-d');

        return Carbon::createFromFormat(
            'Y-m-d H:i',
            $cleanDate.' '.$time
        );
    }

    /**
     * 一般ユーザー
     * 修正申請一覧画面
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // tab に応じて「承認待ち / 承認済み」の最新申請のみを取得
        $tab = $request->query('tab', 'pending');

        // 1日の勤怠ごとに最新の修正申請のみ取得
        $latestCorrections = AttendanceCorrection::with('attendanceRecord')
            ->join('attendance_records', 'attendance_corrections.attendance_record_id', '=', 'attendance_records.id')
            ->where('attendance_corrections.requested_by_user_id', $user->id)
            ->where('attendance_corrections.status', $tab)
            ->whereIn('attendance_corrections.id', function ($query) {
                $query->selectRaw('MAX(id)')
                    ->from('attendance_corrections')
                    ->groupBy('attendance_record_id');
            })
            ->orderBy('attendance_records.date', 'asc')
            ->select('attendance_corrections.*')
            ->get();

        return view('attendance_corrections.index', [
            'tab' => $tab,
            'corrections' => $latestCorrections,
        ]);
    }
}
