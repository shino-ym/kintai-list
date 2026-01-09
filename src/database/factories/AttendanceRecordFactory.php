<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition()
    {
        return [
            'user_id' => null,
            'date' => now()->toDateString(),
            'clock_in' => null,
            'clock_out' => null,
            'status' => 'off',
            'total_break_seconds'=> 0,
            'total_seconds'=> 0,
            'is_corrected' => false,
        ];
    }

    // 出勤中状態にするファクトリメソッド
    public function working()
    {
        return $this->state(fn () => [
            'status' => 'working',
            'clock_in' => now()->format('H:i:s'),
        ]);
    }

    // 休憩中状態にするファクトリメソッド
    public function onBreak()
    {
        return $this->state(fn () => [
            'status' => 'break',
        ]);
    }

    // 退勤済み状態にする
    public function finished()
    {
        return $this->state(fn () => [
            'status' => 'finished',
            'clock_out' => now()->format('H:i:s'),
        ]);
    }
}


