<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\AttendanceCorrection;

use Carbon\Carbon;

class AdminAttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 管理者の申請一覧画面で、「承認待ち」のタブに全ユーザーの未承認の修正申請が表示される()
    {
        //  管理者でログインする
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // 一般ユーザーを複数作成
        $users = User::factory()->count(5)->create([
            'role' => 'user',
        ]);

        $this->actingAs($admin);

        $dates = [
            Carbon::create(2025, 11, 10),
            Carbon::create(2025, 11, 11),
            Carbon::create(2025, 11, 14),
        ];

        foreach ($users as $user) {
            foreach ($dates as $date) {
                $attendance = AttendanceRecord::factory()->create([
                    'user_id' => $user->id,
                    'date' => $date->toDateString(),
                ]);

                AttendanceCorrection::factory()->create([
                    'attendance_record_id' => $attendance->id,
                    'requested_by_user_id' => $user->id,
                    'status' => 'pending',
                    'remarks' => '電車遅延',
                ]);
            }
        }
        //  申請一覧画面を確認
        $response = $this->get(route('admin.attendance_corrections.index',[
            'tab' => 'pending',]));

        $response->assertStatus(200);
        $response->assertSee('承認待ち');

        //  全ユーザー・全日付の申請が表示されている
        foreach ($users as $user) {
            $response->assertSee($user->name);
        }
        $response->assertSee('電車遅延');
    }

        /** @test */
    public function 管理者の申請一覧画面で、「承認済み」のタブに全ユーザーの承認済みの修正申請が表示される()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $users = User::factory()->count(5)->create([
            'role' => 'user',
        ]);

        $this->actingAs($admin);

        $dates = [
            Carbon::create(2025, 11, 10),
            Carbon::create(2025, 11, 11),
            Carbon::create(2025, 11, 14),
        ];

        foreach ($users as $user) {
            foreach ($dates as $date) {
                $attendance = AttendanceRecord::factory()->create([
                    'user_id' => $user->id,
                    'date' => $date->toDateString(),
                ]);

                AttendanceCorrection::factory()->create([
                    'attendance_record_id' => $attendance->id,
                    'requested_by_user_id' => $user->id,
                    'status' => 'approved',
                    'remarks' => '電車遅延',
                ]);
            }
        }
        //  申請一覧画面を確認
        $response = $this->get(route('admin.attendance_corrections.index',[
            'tab' => 'approved',]));

        $response->assertStatus(200);
        $response->assertSee('承認済み');

        // 全ユーザー・全日付の申請が表示されている
        foreach ($users as $user) {
            $response->assertSee($user->name);
        }
        $response->assertSee('電車遅延');
    }

    /** @test */
    public function 修正申請の詳細内容が正しく表示されている()
    {
        //  時間を固定する
        Carbon::setTestNow(Carbon::create(2025, 1, 5));

        //  管理者でログイン
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        //  一般ユーザー作成
        $user = User::factory()->create();

        //  元の勤怠レコード作成（← 必須）
        $attendance = AttendanceRecord::factory()->create([
            'user_id'   => $user->id,
            'date'      => '2025-01-05',
            'clock_in'  => '09:00',
            'clock_out' => '18:00',
        ]);

        //  元勤怠の休憩（２回）
        BreakRecord::factory()->create([
            'attendance_record_id' => $attendance->id,
            'break_start' => '11:00',
            'break_end'   => '12:00',
        ]);

        BreakRecord::factory()->create([
            'attendance_record_id' => $attendance->id,
            'break_start' => '15:00',
            'break_end'   => '15:15',
        ]);

        //  修正申請を作る（表示したい内容をすべて入れる）
        $approve = AttendanceCorrection::factory()->create([
            'attendance_record_id' => $attendance->id,
            'requested_by_user_id' => $user->id,
            'status' => 'pending',
            'clock_in'  => '09:00',
            'clock_out' => '18:00',
            'remarks'   => 'テスト申請',
        ]);

        // 修正申請の休憩も「申請内容」として作る（relation経由）
        $approve->correction_breaks()->create([
            'break_start' => '11:00',
            'break_end'   => '12:00',
        ]);

        // 詳細画面を開く
        $response = $this->get(
            route('admin.attendance_corrections.show', [
                'attendance_correct_request_id' => $approve->id,
            ])
        );

        // 表示確認（申請内容だけ）
        $response->assertStatus(200);

        // 日付
        $response->assertSee('2025年');
        $response->assertSee('1月5日');

        // 修正申請の時間
        $response->assertSee('09:00');
        $response->assertSee('18:00');

        // 修正申請の休憩
        $response->assertSee('11:00');
        $response->assertSee('12:00');

        // 備考
        $response->assertSee('テスト申請');
    }

    /** @test */
    public function 管理者が修正申請を承認すると勤怠情報が更新される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 5));

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $user = User::factory()->create();

        $attendance = AttendanceRecord::factory()->create([
            'user_id'   => $user->id,
            'date'      => '2025-01-05',
            'clock_in'  => '09:00',
            'clock_out' => '18:00',
        ]);

        // 修正申請（変更したい内容）
        $correction = AttendanceCorrection::factory()->create([
            'attendance_record_id' => $attendance->id,
            'requested_by_user_id' => $user->id,
            'status'   => 'pending',
            'clock_in' => '10:00',
            'clock_out'=> '19:00',
        ]);

        //  承認ボタンを押す（PUT）
        $response = $this->put(
            route('admin.attendance_corrections.approve',['attendance_correct_request_id' => $correction->id,
            ])
        );

        //  修正申請が承認済みになる
        $this->assertDatabaseHas('attendance_corrections', [
            'id'     => $correction->id,
            'status' => 'approved',
        ]);

        //  勤怠が更新されている
        $this->assertDatabaseHas('attendance_records', [
            'id'        => $attendance->id,
            'clock_in'  => '10:00',
            'clock_out' => '19:00',
        ]);
    }

}
