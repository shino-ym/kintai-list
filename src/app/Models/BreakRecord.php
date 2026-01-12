<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BreakRecord extends Model
{
    use HasFactory;

    protected $table = 'breaks';

    protected $fillable = [
        'attendance_record_id',
        'break_start',
        'break_end',
    ];

    // ---------------------------------
    // 表示用 アクセサ（Carbon に変換）
    // ---------------------------------
    public function getBreakStartTimeAttribute()
    {
        if (!$this->break_start) return '';

        return Carbon::createFromFormat('H:i', $this->break_start)
            ->format('H:i');
    }

    public function getBreakEndTimeAttribute()
    {
        if (!$this->break_end) return '';

        return Carbon::createFromFormat('H:i', $this->break_end)
            ->format('H:i');
    }

    // ---------------------------------
    // リレーション
    // ---------------------------------
    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class);
    }
}
