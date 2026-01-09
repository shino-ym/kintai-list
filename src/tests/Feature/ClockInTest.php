<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\AttendanceRecord;


class ClockInTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 出勤ボタンが正しく機能する()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 9, 30));

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('attendance.index'));
        $response->assertStatus(200)->assertSee('出勤');

        // 出勤ボタン押下（POST）
        $response = $this->post(route('attendance.clockIn'));
        $response->assertRedirect('/attendance');

        // リダイレクト先で画面確認
        $response = $this->get('/attendance');
        $response->assertStatus(200)->assertSee('出勤中');

        // DB に出勤レコードが作成されているか確認
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'status' => 'working',
            'date' => now()->toDateString(),
        ]);
    }

    /** @test */
    public function 出勤は一日一回のみできる()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 9, 30));

        $user = User::factory()->create();

        //  勤怠レコード作成（退勤済）
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'status' => 'finished', // 退勤済
            'date' => now()->toDateString(),
        ]);

        // ログイン
        $this->actingAs($user);

        // 勤怠画面を開く → ステータスが「退勤済」と表示される
        $response = $this->get(route('attendance.index'));
        $response->assertStatus(200);
        $response->assertSee('退勤済');

        // 出勤ボタンが表示されていないことを確認
        $response->assertDontSee('出勤');
    }

    /** @test */
    public function 出勤時刻が勤怠一覧画面で確認できる()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 9, 30));

        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠レコード作成（勤務外）
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'status' => 'off',
            'date' => now()->toDateString(),
        ]);

        // 出勤ボタン押下（POST）
        $response = $this->post(route('attendance.clockIn'));
        $response->assertRedirect('/attendance');

        // DBに正しい値が入っているか確認
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'status' => 'working',
            'date' => now()->toDateString(),
            'clock_in' => now()->format('H:i:s'),
        ]);

        // 勤怠一覧画面を取得
        $response = $this->get(route('attendance.listMonth'));
        $response->assertStatus(200);

        // 出勤時刻が画面に表示されているか確認
        $response->assertSee(now()->format('H:i'));
    }

}
