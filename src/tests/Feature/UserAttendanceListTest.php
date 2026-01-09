<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;


class UserAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 自分の勤怠情報が全て勤怠一覧に表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 30));

        $user = User::factory()->create();
        $this->actingAs($user);

        $dates = [
            Carbon::create(2025, 1, 5),
            Carbon::create(2025, 1, 10),
            Carbon::create(2025, 1, 20),
        ];

        foreach ($dates as $date) {
            $record = AttendanceRecord::factory()->create([
                'user_id' => $user->id,
                'date' => $date->toDateString(),
                'clock_in' => '09:00',
                'clock_out' => '18:00',
            ]);

            $record->breaks()->createMany([
                ['break_start' => '12:00', 'break_end' => '12:30'],
                ['break_start' => '15:00', 'break_end' => '15:15'],
            ]);

            $record->recalcTotalTimes();
        }

        $response = $this->get(route('attendance.listMonth'));
        $response->assertStatus(200);

        foreach ($dates as $date) {
            $response->assertSee($date->format('m/d'));
        }

        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('0:45');
        $response->assertSee('8:15');
        $response->assertSee('詳細');
    }


    /** @test */
    public function 勤怠一覧画面に遷移した際に現在の月が表示されている()
    {
        // 時間を固定する
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        // ユーザーを作ってログイン
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠一覧画面にアクセス
        $response = $this->get(route('attendance.listMonth'));
        $response->assertStatus(200);

        // 現在の月が表示されていることを確認
        $response->assertSee('2025/01');
    }

        /** @test */
    public function 勤怠一覧画面の「前月」を押下した時に表示つきの前月の情報が表示される()
    {
        // 時間を固定する
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        // ユーザーを作ってログインする
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠一覧ページを開く
        $response = $this->get(route('attendance.listMonth'));
        $response->assertStatus(200);
        $response->assertSee('2025/01');

        $response->assertSee('前月');

        // 「前月」を押した想定でアクセス
        $response = $this->get(route('attendance.listMonth', [
            'year' => 2024,
            'month' => 12,
        ]));

        // 前月が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee('2024/12');
    }

    /** @test */
    public function 勤怠一覧画面の「翌月」を押下した時に表示月の翌月の情報が表示される()
    {
        // 時間を固定する
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        // ユーザーを作ってログインする
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠一覧ページを開く
        $response = $this->get(route('attendance.listMonth'));
        $response->assertStatus(200);
        $response->assertSee('2025/01');

        $response->assertSee('翌月');

        // 「翌月」を押した想定でアクセス
        $response = $this->get(route('attendance.listMonth', [
            'year' => 2025,
            'month' => 2,
        ]));

        // 翌月が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee('2025/02');

    }

    /** @test */
    public function 勤怠一覧画面の「詳細」を押下するとその日の勤怠詳細画面に遷移する()
    {
        // 時間を固定する
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        // ユーザーを作ってログインする
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠レコードを作成する（当日分）
        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-01',
        ]);

        // 勤怠一覧ページを開く
        $response = $this->get(route('attendance.listMonth'));
        $response->assertStatus(200);
        $response->assertSee('詳細');

        //「詳細」を押下した想定で詳細ページにアクセス
        $response = $this->get(route('attendance.show', [
            'id' => $attendance->id,
            'date' => $attendance->date,
        ]));

        // 勤怠詳細画面が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee('2025年');
        $response->assertSee('1月1日');
    }

}