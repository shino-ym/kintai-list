<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');// 勤務日
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->enum('status',['off','working','break','finished'])->comment('off:勤務外, working:出勤中, break:休憩中, finished:退勤済');
            $table->integer('total_seconds')->default(0)->comment('勤務時間（秒単位）');
            $table->integer('total_break_seconds')->default(0)->comment('休憩時間（秒単位）');
            $table->string('remarks',255)->nullable();
            $table->boolean('is_corrected')->default(false);
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attendance_records');
    }
}
