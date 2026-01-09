<?php

namespace Database\Factories;

use App\Models\BreakRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class BreakRecordFactory extends Factory
{
    protected $model = BreakRecord::class;

    public function definition()
    {
        return [
        'attendance_record_id'=>null,
        'break_start'=>$this->faker->time('H:i:s'),
        'break_end'=>$this->faker->time('H:i:s'),
        ];
    }

}

