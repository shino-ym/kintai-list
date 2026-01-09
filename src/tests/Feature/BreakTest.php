<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use Carbon\Carbon;


class BreakTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 休憩ボタンが正しく機能する()
    {
        //  テスト日時を固定
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 9, 30));

        //  ユーザー作成
        $user = User::factory()->create();

        //  勤怠レコード作成（出勤中）
        $attendance = AttendanceRecord::factory()->working()->create([
            'user_id' => $user->id,
        ]);

        // ログイン
        $this->actingAs($user);

        //  勤怠画面を開く → 「休憩入」ボタンが表示されているか確認
        $response = $this->get(route('attendance.index'));
        $response->assertStatus(200)->assertSee('休憩入');

        //  休憩入ボタン押下（POST）
        $response = $this->post(route('attendance.breakStart'))
            ->assertRedirect('/attendance');

        //  リダイレクト先で画面確認
        $response = $this->get('/attendance');
        $response->assertStatus(200)->assertSee('休憩中'); 

        //  DB に休憩レコードが作成されているか確認
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'status' => 'break',
            'date' => now()->toDateString(),
        ]);
    }

    /** @test */
    public function 休憩は一日に何回でもできる()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 9, 30));

        $user = User::factory()->create();
        $attendance = AttendanceRecord::factory()->working()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        for ($i = 0; $i < 2; $i++) {
            // 休憩開始
            $this->post(route('attendance.breakStart'))->assertRedirect('/attendance');
            $response = $this->get('/attendance');
            $response->assertSee('休憩戻');

            $this->assertDatabaseHas('attendance_records', [
                'id' => $attendance->id,
                'status' => 'break',
            ]);

            // 休憩終了
            $this->post(route('attendance.breakEnd'))->assertRedirect('/attendance');
            $response = $this->get('/attendance');
            $response->assertSee('休憩入');

            $this->assertDatabaseHas('attendance_records', [
                'id' => $attendance->id,
                'status' => 'working',
            ]);
        }
    }

    /** @test */
    public function 休憩戻ボタンが正しく機能する()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 9, 30));

        $user = User::factory()->create();

        $attendance = AttendanceRecord::factory()->working()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user);

        // 休憩入
        $this->post(route('attendance.breakStart'))->assertRedirect('/attendance');

        $response = $this->get('/attendance');
        $response->assertStatus(200)->assertSee('休憩戻'); 

        // DBのステータスも確認
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'status' => 'break',
            'date' => now()->toDateString(),
        ]);

        // 休憩戻
        $this->post(route('attendance.breakEnd'))->assertRedirect('/attendance');

        $response = $this->get('/attendance');
        $response->assertStatus(200);
        $response->assertSee('出勤中');


        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'status' => 'working',
            'date' => now()->toDateString(),
        ]);
    }

    /** @test */
    public function 休憩戻は一日に何回でもできる()
    {
        // テスト日時を固定
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 9, 30));

        // ユーザー作成
        $user = User::factory()->create();

        // 勤怠レコード作成（出勤中）
        $attendance = AttendanceRecord::factory()->working()->create([
            'user_id' => $user->id,
        ]);

        // ログイン
        $this->actingAs($user);

        // 休憩を繰り返すが、最後は休憩中の状態で終わる
        for ($i = 0; $i < 2; $i++) {
            // 休憩開始
            $this->post(route('attendance.breakStart'))->assertRedirect('/attendance');
            $response = $this->get('/attendance');
            $response->assertSee('休憩戻');

            $this->assertDatabaseHas('attendance_records', [
                'id' => $attendance->id,
                'status' => 'break',
            ]);

            // 休憩終了は最終回以外のみ実行
            if ($i < 1) { // 最後の回は休憩戻状態で終わらせたいので skip
                $this->post(route('attendance.breakEnd'))->assertRedirect('/attendance');
                $response = $this->get('/attendance');
                $response->assertSee('休憩入');

                $this->assertDatabaseHas('attendance_records', [
                    'id' => $attendance->id,
                    'status' => 'working',
                ]);
            }
        }
    }

    /** @test */
    public function 休憩時間が勤怠一覧画面で確認できる()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 9, 0));

        $user = User::factory()->create();
        $this->actingAs($user);

        $attendance = AttendanceRecord::factory()->working()->create(['user_id' => $user->id]);

        // 複数回休憩を作成
        BreakRecord::factory()->create([
            'attendance_record_id' => $attendance->id,
            'break_start' => '10:00:00',
            'break_end'   => '10:15:00',
        ]);

        BreakRecord::factory()->create([
            'attendance_record_id' => $attendance->id,
            'break_start' => '12:00:00',
            'break_end'   => '12:30:00',
        ]);

        // 合計休憩時間を更新
        $attendance->update([
            'total_break_seconds' => 15*60 + 30*60,
        ]);

        $response = $this->get(route('attendance.listMonth'));
        $response->assertStatus(200);

        $response->assertSee('0:45'); // H:i形式で合計休憩時間を表示確認
    }
}
