<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\AttendanceCorrection;
use App\Models\CorrectionBreak;
use Carbon\Carbon;

class AttendanceCorrectionsTableSeeder extends Seeder
{
    public function run()
    {
        $admin = User::where('role', 'admin')->inRandomOrder()->first();
        if (!$admin) {
            $this->command->info('管理者が存在しないためシーダーを終了します。');
            return;
        }

        $users = User::where('role', 'user')->get();

        // ★ 勤怠データ基準の「現在」
        $globalNow = AttendanceRecord::max('clock_in');
        if (!$globalNow) {
            $this->command->info('勤怠が存在しないため終了します。');
            return;
        }

        // 勤怠データ基準の「現在」
        $today = Carbon::parse(AttendanceRecord::max('date'))->endOfDay();

        foreach ($users as $user) {
            // 各ユーザーの勤怠レコードからランダムに5件取得
            $attendanceRecords = AttendanceRecord::where('user_id', $user->id)
                ->inRandomOrder()
                ->take(5)
                ->get();

            foreach ($attendanceRecords as $record) {
                $clockIn = Carbon::parse($record->clock_in);
                $clockOut = Carbon::parse($record->clock_out);
                $workMinutes = $clockIn->diffInMinutes($clockOut);

                // ±30分で修正申請時間を決定
                $adjustedClockIn  = $clockIn->copy()->addMinutes(rand(-30, 30));
                $adjustedClockOut = $clockOut->copy()->addMinutes(rand(-30, 30));

                // ★ 最低勤務時間を保証（例：30分）
                if ($adjustedClockOut->lte($adjustedClockIn)) {
                    $adjustedClockOut = $adjustedClockIn->copy()->addMinutes(30);
                }

                // pending or approved をランダムに
                $status = rand(0, 1) ? 'pending' : 'approved';

                $attendanceDate = Carbon::parse($record->date)->startOfDay();

                // 申請可能範囲
                $minApplyAt = $attendanceDate->copy()->addDay(); // 対象日の翌日
                $maxApplyAt = $today;

                // ガード
                if ($minApplyAt->gte($maxApplyAt)) {
                    $appliedAt = $maxApplyAt;
                } else {
                    $appliedAt = Carbon::createFromTimestamp(
                        rand($minApplyAt->timestamp, $maxApplyAt->timestamp)
                    );
                }

                // 修正申請作成
                $correction = new AttendanceCorrection();
                $correction->timestamps = false; // ★最重要
                $correction->requested_by_user_id = $user->id;
                $correction->approved_by_user_id  = $status === 'approved' ? $admin->id : null;
                $correction->attendance_record_id = $record->id;
                $correction->clock_in  = $adjustedClockIn;
                $correction->clock_out = $adjustedClockOut;
                $correction->remarks   = '電車遅延のため';
                $correction->status    = $status;
                $correction->created_at = $appliedAt;
                $correction->updated_at = $appliedAt;

                $correction->save();

                // ★ 承認済みなら勤怠レコードに反映
                if ($status === 'approved') {
                    $record->update([
                        'clock_in'  => $adjustedClockIn,
                        'clock_out' => $adjustedClockOut,
                        'remarks'   => $correction->remarks,
                    ]);
                }

                // 休憩をランダムで作成（最大3回）
                $breakCount = rand(0, 3);
                $lastEnd = $adjustedClockIn->copy();// 前回休憩終了を基準にする

                for ($i = 0; $i < $breakCount; $i++) {
                    // 前回終了 + 15〜120分で開始
                    $breakStartCandidate = $lastEnd->copy()->addMinutes(rand(15, 120));
                    $breakStart = $breakStartCandidate->gt($lastEnd) ? $breakStartCandidate : $lastEnd->copy();

                    if ($breakStart >= $adjustedClockOut) break; // 退勤を超えたら終了

                    // 休憩時間 15〜60分
                    $breakEnd = $breakStart->copy()->addMinutes(rand(15, 60));
                    if ($breakEnd > $adjustedClockOut) {
                        $breakEnd = $adjustedClockOut->copy();
                    }

                    CorrectionBreak::create([
                        'attendance_correction_id' => $correction->id,
                        'break_start' => $breakStart,
                        'break_end'   => $breakEnd,
                    ]);

                    $lastEnd = $breakEnd->copy();
                }
            }
        }
    }
}
