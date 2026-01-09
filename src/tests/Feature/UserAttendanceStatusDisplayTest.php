<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;
use App\Models\AttendanceRecord;
use App\Models\User;


class UserAttendanceStatusDisplayTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 勤務外の場合_勤怠ステータスが正しく表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 9, 30));

        // ログインユーザー作成
        $user = User::factory()->create();

        // 勤怠レコードを作成（勤務外）
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'status'  => 'off', // 勤務外
            'date'    => now()->toDateString(),
        ]);

        // ログインした状態にする
        $this->actingAs($user);

        // 勤怠画面を開く
        $response = $this->get(route('attendance.index'));

        // ステータス表示を確認
        $response->assertStatus(200);
        $response->assertSee('勤務外');
    }

    /** @test */
    public function 出勤中の場合_勤怠ステータスが正しく表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 9, 30));

        $user = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'status'  => 'working', //出勤中
            'date'    => now()->toDateString(),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertSee('出勤中');

    }

    /** @test */
    public function 休憩中の場合_勤怠ステータスが正しく表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 9, 30));

        $user = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'status'  => 'break', //休憩中
            'date'    => now()->toDateString(),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertSee('休憩中');

    }

    /** @test */
    public function 退勤済の場合_勤怠ステータスが正しく表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 9, 30));

        $user = User::factory()->create();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'status'  => 'finished', // 退勤済
            'date'    => now()->toDateString(),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('attendance.index'));

        $response->assertStatus(200);
        $response->assertSee('退勤済');


    }
}
