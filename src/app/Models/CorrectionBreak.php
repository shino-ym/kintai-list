<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorrectionBreak extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_correction_id',
        'break_start',
        'break_end',
    ];

    public function correction()
    {
        return $this->belongsTo(AttendanceCorrection::class, 'attendance_correction_id');
    }
}
