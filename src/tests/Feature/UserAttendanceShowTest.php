<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\breakRecord;

use Carbon\Carbon;


class UserAttendanceShowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 勤怠詳細画面の「名前」がログインユーザーに氏名になっている()
    {
        // 日にちを固定する
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        // ユーザーを作ってログインする
        $user = User::factory()->create([
            'name'=>
            'ユーザー１'
        ]);
        $this->actingAs($user);

        // 勤怠レコードを作成する（当日分）
        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
        ]);

        // 勤怠詳細ページにアクセス
        $response = $this->get(route('attendance.show', [
            'id' => $attendance->id,
            'date' => $attendance->date,
        ]));

        // 勤怠詳細画面に名前が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee('ユーザー１');
    }

    /** @test */
    public function 勤怠詳細画面の「日付」が選択した日付になっている()
    {
        // 日にちを固定する
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        // ユーザーを作ってログインする
        $user = User::factory()->create();
        $this->actingAs($user);

        // 「選択した日付」を決める（1月5日）
        $targetDate = Carbon::create(2025, 1, 5);

        // その日に勤怠データがある状態を作る
        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $targetDate->toDateString(),
        ]);

        // 「1月5日を指定して」勤怠詳細画面を開く
        $response = $this->get(route('attendance.show', [
            'id' => $attendance->id,
            'date' => $targetDate->toDateString(),
        ]));

        // ページが正常に開いたか確認
        $response->assertStatus(200);

         // 画面の日付表示をチェックする
        $response->assertSee('2025年');
        $response->assertSee('1月5日');
    }

    /** @test */
    public function 勤怠詳細画面の「出勤・退勤」にて記されている時間がログインユーザーの打刻と一致している()
    {
        // 時間を固定する
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        // ユーザーを作ってログインする
        $user = User::factory()->create();
        $this->actingAs($user);

        // 「選択した日付」を決める（1月5日）
        $targetDate = Carbon::create(2025, 1, 5);

        // 勤怠レコードを「時間つき」で作る
        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $targetDate->toDateString(),
            'clock_in'  => Carbon::parse('2025-01-05 09:00'),
            'clock_out' => Carbon::parse('2025-01-05 18:00'),
        ]);

        // 勤怠詳細画面を開く
        $response = $this->get(route('attendance.show', [
            'id' => $attendance->id,
            'date' => $targetDate->toDateString(),
        ]));

        // ページが正常に開いたか確認
        $response->assertStatus(200);

         // 画面の出勤・退勤表示をチェックする
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    /** @test */
    public function 勤怠詳細画面の「休憩」にて記されている時間がログインユーザーの打刻と一致している()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        $user = User::factory()->create();
        $this->actingAs($user);

        $targetDate = Carbon::create(2025, 1, 5);

        //  勤怠レコードを作る
        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $targetDate->toDateString(),
        ]);

        //  BreakRecord を複数作る
        BreakRecord::factory()->create([
            'attendance_record_id' => $attendance->id,
            'break_start' => Carbon::parse('2025-01-05 11:00'),
            'break_end'   => Carbon::parse('2025-01-05 12:00'),
        ]);
        BreakRecord::factory()->create([
            'attendance_record_id' => $attendance->id,
            'break_start' => Carbon::parse('2025-01-05 15:00'),
            'break_end'   => Carbon::parse('2025-01-05 15:15'),
        ]);

        // 勤怠詳細画面を開く
        $response = $this->get(route('attendance.show', [
            'id' => $attendance->id,
            'date' => $targetDate->toDateString(),
        ]));

        //  ページが正常に開いたか確認
        $response->assertStatus(200);

         //  画面の休憩表示をチェックする
        $response->assertSee('11:00');
        $response->assertSee('12:00');
        $response->assertSee('15:00');
        $response->assertSee('15:15');
    }

}
