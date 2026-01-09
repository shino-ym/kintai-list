<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;
use App\Models\AttendanceRecord;


class ClockOutTest extends TestCase
{
    /** @test */
    public function 退勤ボタンが正しく機能する()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 9, 30));

        $user = User::factory()->create();

        // 勤怠レコード作成（出勤中）
        $attendance = AttendanceRecord::factory()->working()->create([
            'user_id' => $user->id,
        ]);

        // ログイン
        $this->actingAs($user);

        // 勤怠画面を開く → 「退勤」ボタンが表示されているか確認
        $response = $this->get(route('attendance.index'));
        $response->assertStatus(200)->assertSee('退勤');

        // 出勤ボタン押下（POST）
        $response = $this->post(route('attendance.clockOut'))->assertRedirect('/attendance');

        // リダイレクト先で画面確認
        $response = $this->get('/attendance');
        $response->assertStatus(200)->assertSee('退勤済');

        // DB に出勤レコードが作成されているか確認
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'status' => 'finished',
            'date' => now()->toDateString(),
        ]);
    }

    /** @test */
    public function 退勤時刻が勤怠一覧画面で確認できる()
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
        $this->post(route('attendance.clockIn'))->assertRedirect('/attendance');

        // 出勤時刻が正しく入っているか確認
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'status' => 'working',
            'clock_in' => now()->format('H:i:s'),
            'date' => now()->toDateString(),
        ]);

        // 退勤ボタン押下（POST）
        $this->post(route('attendance.clockOut'))->assertRedirect('/attendance');

        // 退勤時刻とステータスを確認
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'status' => 'finished',
            'clock_out' => now()->format('H:i:s'),
            'date' => now()->toDateString(),
        ]);

        // 勤怠一覧画面を取得
        $response = $this->get(route('attendance.listMonth'));
        $response->assertStatus(200);

        // 退勤時刻が画面に表示されているか確認
        $response->assertSee(now()->format('H:i'));
    }

}
