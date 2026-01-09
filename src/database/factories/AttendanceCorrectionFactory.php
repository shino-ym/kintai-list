<?php

namespace Database\Factories;

use App\Models\AttendanceCorrection;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceCorrectionFactory extends Factory
{
    protected $model = AttendanceCorrection::class;

    public function definition()
    {
        return [
            'status' => 'pending',
            'remarks' => 'テスト申請',
            'created_at' => now(),
        ];
    }

    public function pending()
    {
        return $this->state(['status' => 'pending']);
    }

    public function approved()
    {
        return $this->state(['status' => 'approved']);
    }


    public function rejected()
    {
        return $this->state(['status' => 'rejected']);
    }

}

