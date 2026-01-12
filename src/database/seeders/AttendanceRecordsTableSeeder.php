<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use Carbon\Carbon;

class AttendanceRecordsTableSeeder extends Seeder
{
    public function run()
    {
        $users = User::where('role', 'user')->get();

        // 先月と今月のループ
        for ($m = -1; $m <= 0; $m++) {

            $monthStart = Carbon::now()->addMonth($m)->startOfMonth();
            $monthEnd = Carbon::now()->addMonth($m)->endOfMonth();

            // 今月だけ今日までに制限
            if ($m === 0) {
                $monthEnd =  Carbon::now();
            }

            foreach ($users as $user) {
                $date = $monthStart->copy();

                while ($date->lte($monthEnd)) {

                    // =====出勤する日の中身を決める====
                    if (rand(1, 100) > 30) {

                        $clockIn = $date->copy()
                            ->setTime(rand(8, 10), rand(0, 59), 0);

                        $clockOut = $clockIn->copy()
                            ->addHours(rand(7, 9))
                            ->addMinutes(rand(0, 30));

                        $breaks = [];
                        $totalBreakSeconds = 0;

                        $breakCount = rand(0, 3);
                        $lastEnd = $clockIn->copy();

                        for ($b = 0; $b < $breakCount; $b++) {

                            // 勤務時間内の残り分を計算
                            $remainingMinutes = $clockOut->diffInMinutes($lastEnd);

                            // 15分未満なら終了
                            if ($remainingMinutes < 15) break;

                            // 次の休憩開始までも「間」を決める
                            $minGap = 15;
                            $maxGap = max($minGap, intval($remainingMinutes / 2));

                            //休憩開始時間を決める
                            $breakStart = (clone $lastEnd)->addMinutes(rand($minGap, $maxGap));

                            // 休憩の長さを決める
                            $maxDuration = min(60, $clockOut->diffInMinutes($breakStart));

                            // 15分未満なら中止
                            if ($maxDuration < 15) break;

                            // 実際の休憩時間を決める
                            $breakDuration = rand(15, $maxDuration);
                            $breakEnd = (clone $breakStart)->addMinutes($breakDuration);

                            // 休憩時間を足す
                            $breakSeconds = $breakEnd->diffInSeconds($breakStart);
                            $totalBreakSeconds += $breakSeconds;

                            // 休憩リストに保存
                            $breaks[] = [
                                'break_start' => $breakStart,
                                'break_end' => $breakEnd,
                            ];

                            // 次の基準時間を更新
                            $lastEnd = $breakEnd->copy();
                        }

                        // 実際に働いた時間を計算する
                        $workSeconds = max(
                            0,
                            $clockOut->diffInSeconds($clockIn, false) - $totalBreakSeconds
                        );

                        // 出勤レコード作成
                        $attendanceRecord = AttendanceRecord::create([

                            'user_id' => $user->id,
                            'date' => $date->toDateString(),
                            'clock_in' => $clockIn,
                            'clock_out' => $clockOut,
                            'status' => 'finished',
                            'remarks' => '',
                            'is_corrected' => false,
                        ]);

                        // 休憩レコード作成
                        foreach ($breaks as $b) {
                            $attendanceRecord->breaks()->create([
                                'break_start' => $b['break_start'],
                                'break_end'   => $b['break_end'],
                            ]);
                        }

                        $attendanceRecord->load('breaks');
                        $attendanceRecord->recalcTotalTimes();
                    }

                    $date->addDay();
                }
            }
        }
    }
}

