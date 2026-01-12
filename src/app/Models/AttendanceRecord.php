<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'status',
        'total_break_seconds',
        'total_seconds',
        'is_corrected',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date',
        'is_corrected' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function breaks()
    {
        return $this->hasMany(BreakRecord::class);
    }

    public function corrections()
    {
        return $this->hasMany(AttendanceCorrection::class,'attendance_record_id' );
    }

    public function pendingCorrections()
    {
        return $this->hasMany(AttendanceCorrection::class, 'attendance_record_id')
            ->where('status', 'pending');
    }


    // -------------------------------
    // <アクセサ>
    // -------------------------------

    public function getDisplayYearAttribute()
    {
        return $this->date ? $this->date->format('Y年') : '';
    }

    // 月日＋曜日（日本語）
    public function getDisplayMonthDayWithWeekAttribute()
    {
        return $this->date ? $this->date->format('n月j日') . '(' . $this->date->isoFormat('ddd') . ')' : '';
    }

    // 月日だけ
    public function getDisplayMonthDayAttribute()
    {
        return $this->date ? $this->date->format('n月j日') : '';
    }

    // 出勤時刻（H:i 表示）
    public function getClockInTimeAttribute()
    {
        return $this->clock_in ? Carbon::parse($this->clock_in)->format('H:i') : '';
    }

    // 退勤時間（H:i 表示）
    public function getClockOutTimeAttribute()
    {
        return $this->clock_out ? Carbon::parse($this->clock_out)->format('H:i') : '';
    }

    //  勤務時間（分・時間表記）
    public function getTotalMinutesFormattedAttribute()
    {
        return $this->formatSecondsToHoursMinutes($this->total_seconds);
    }

    //  休憩時間の表示
    public function getTotalBreakFormattedAttribute()
    {
        return $this->formatSecondsToHoursMinutes($this->total_break_seconds);
    }

    //  未承認修正申請があるか？
    public function getHasPendingCorrectionAttribute()
    {
        return $this->pendingCorrections()->exists();
    }

    // -------------------------------
    // 秒を H:MM 形式に変換
    protected function formatSecondsToHoursMinutes($seconds)
    {
        $seconds = $seconds ?? 0;
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        // 1分未満も切り上げ
        if ($remainingSeconds > 0) {
            $minutes++;
            if ($minutes === 60) {
                $hours++;
                $minutes = 0;
            }
        }

        return sprintf('%d:%02d', $hours, $minutes);
    }

    // 勤務時間・休憩時間を再計算して保存
    public function recalcTotalTimes()
    {

        if (!$this->clock_in || !$this->clock_out) {
        return;
        }

        // clock_in / clock_out を Carbon に変換
        $clockIn  = Carbon::parse($this->clock_in);
        $clockOut = Carbon::parse($this->clock_out);

        if ($clockOut->lte($clockIn)) {
        return;
        }

        // 勤務時間計算
        $workSeconds = $clockIn->diffInSeconds($clockOut);

        // 休憩合計
        $breakSeconds = $this->breaks->sum(function ($break) {
            if (!$break->break_start || !$break->break_end) return 0;

            return Carbon::parse($break->break_end)
                        ->diffInSeconds(Carbon::parse($break->break_start));
        });

        $this->total_seconds = max($workSeconds - $breakSeconds, 0);
        $this->total_break_seconds = $breakSeconds;

        $this->save();
    }

}
