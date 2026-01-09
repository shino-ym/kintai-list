<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\breakRecord;
use App\Models\AttendanceCorrection;
use Carbon\Carbon;


class UserAttendanceDetailCorrectionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 勤怠詳細画面の出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される()
    {
        // 時間を固定する
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        // ユーザーを作ってログインする
        $user = User::factory()->create();
        $this->actingAs($user);

        // 対象日付
        $targetDate = Carbon::create(2025, 1, 5);

        // 勤怠レコードを作成
        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date'    => $targetDate->toDateString(),
        ]);

        // 勤怠詳細画面を開く
        $response = $this->get(route('attendance.show', [
            'id' => $attendance->id,
            'date' => $targetDate->toDateString(),
        ]));
        $response->assertStatus(200);

        // 不正な時間で修正申請（POST）
        $response = $this->post(route('attendance_correction.store'), [
            'attendance_record_id' => $attendance->id,
            'date'=> $targetDate->toDateString(),
            'clock_start' => '16:00',
            'clock_end' => '14:00',
        ]);

        // 出勤時間が退勤時間より後になっている場合に対してバリデーションエラーがあることを確認
        $response->assertSessionHasErrors(['clock_both']);

        // 下の画面に戻ってエラーメッセージが表示される
        $response = $this->get(route('attendance.show', [
            'id' => $attendance->id,
            'date' => $targetDate->toDateString(),
        ]));

        $response->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }

    /** @test */
    public function 勤怠詳細画面の休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        $user = User::factory()->create();
        $this->actingAs($user);

        $targetDate = Carbon::create(2025, 1, 5);

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date'    => $targetDate->toDateString(),
        ]);

        // 不正な休憩（退勤後）
        $response = $this->post(route('attendance_correction.store'), [
            'attendance_record_id' => $attendance->id,
            'date' => $targetDate->toDateString(),
            'clock_in'  => '09:00',
            'clock_out' => '15:00',
            'break_start' => ['15:30'], // ← index 0
            'break_end'   => ['16:00'],
        ]);

        // index 0 の休憩開始にエラーがある
        $response->assertSessionHasErrors([
            'break_start.0',
        ]);

        // エラー後に詳細画面を開く
        $response = $this->get(route('attendance.show', [
            'id' => $attendance->id,
            'date' => $targetDate->toDateString(),
        ]));

        // エラーメッセージが表示されている
        $response->assertSee('休憩時間が不適切な値です');
    }

    /** @test */
    public function 勤怠詳細画面の休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        $user = User::factory()->create();
        $this->actingAs($user);

        $targetDate = Carbon::create(2025, 1, 5);

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date'    => $targetDate->toDateString(),
        ]);

        // 不正な休憩（退勤後）
        $response = $this->post(route('attendance_correction.store'), [
            'attendance_record_id' => $attendance->id,
            'date' => $targetDate->toDateString(),
            'clock_in'  => '09:00',
            'clock_out' => '15:00',
            'break_start' => ['14:30'],
            'break_end'   => ['16:00'],
        ]);

        // index 0 の休憩終了にエラーがある
        $response->assertSessionHasErrors([
            'break_end.0',
        ]);

        // エラー後に詳細画面を開く
        $response = $this->get(route('attendance.show', [
            'id' => $attendance->id,
            'date' => $targetDate->toDateString(),
        ]));

        // エラーメッセージが表示されている
        $response->assertSee('休憩時間もしくは退勤時間が不適切な値です');
    }

    /** @test */
    public function 勤怠詳細画面の備考欄が未入力の場合、エラーメッセージが表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        $user = User::factory()->create();
        $this->actingAs($user);

        $targetDate = Carbon::create(2025, 1, 5);

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date'    => $targetDate->toDateString(),
        ]);

        // 備考未記入
        $response = $this->post(route('attendance_correction.store'), [
            'attendance_record_id' => $attendance->id,
            'date' => $targetDate->toDateString(),
            'clock_in'  => '09:00',
            'clock_out' => '15:00',
            'remarks'=>'',
        ]);

        // 備考にエラーがある
        $response->assertSessionHasErrors([
            'remarks',
        ]);

        // エラー後に詳細画面を開く
        $response = $this->get(route('attendance.show', [
            'id' => $attendance->id,
            'date' => $targetDate->toDateString(),
        ]));

        // エラーメッセージが表示されている
        $response->assertSee('備考を記入してください');
    }

    /** @test */
    public function 修正申請処理が実行され、管理者の承認画面と申請一覧画面に表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        $user = User::factory()->create();
        $this->actingAs($user);

        $targetDate = Carbon::create(2025, 1, 5);

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date'    => $targetDate->toDateString(),
            'clock_in'  => '09:00',
            'clock_out' => '15:00',
        ]);

        // 全て記入し修正ボタンを押す
        $response = $this->post(route('attendance_correction.store'), [
            'attendance_record_id' => $attendance->id,
            'date' => $targetDate->toDateString(),
            'clock_in'  => '09:00',
            'clock_out' => '15:00',
            'break_start' => ['12:30'],
            'break_end'   => ['13:00'],
            'remarks'=>'電車遅延',
        ]);

        // リダイレクト & エラーなし
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        // 修正申請がDBに作られている
        $this->assertDatabaseHas('attendance_corrections', [
            'attendance_record_id' => $attendance->id,
            'requested_by_user_id' => $user->id,
            'status' => 'pending',
            'remarks' => '電車遅延',
        ]);

        // 管理者を作成し、管理者でログインし直す
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin);

        // 管理者の申請一覧画面を見る
        $response = $this->get(route('admin.attendance_corrections.index'));

        $response->assertStatus(200);
        $response->assertSee('電車遅延');
        $response->assertSee(
            $targetDate->format('Y/m/d')
        );

        // 管理者の承認画面
        $correction = AttendanceCorrection::where('attendance_record_id', $attendance->id)->first();

        $response = $this->get(route('admin.attendance_corrections.show', [
            'attendance_correct_request_id' => $correction->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('2025年');
        $response->assertSee('1月5日');
        $response->assertSee('09:00');
        $response->assertSee('15:00');
        $response->assertSee('12:30');
        $response->assertSee('13:00');
        $response->assertSee('電車遅延');
        $response->assertSee('承認');
    }

    /** @test */
    public function 申請一覧画面の「承認待ち」タグにログインユーザーの申請が全て表示されている()
    {
        Carbon::setTestNow(Carbon::create(2025, 11, 15));

        // 一般ユーザーを作成し、ログイン
        $user = User::factory()->create();
        $this->actingAs($user);

        // 勤怠を3日分作成
        $dates = [
            Carbon::create(2025, 11, 10),
            Carbon::create(2025, 11, 11),
            Carbon::create(2025, 11, 14),
        ];

        foreach ($dates as $date) {
            $attendance = AttendanceRecord::factory()->create([
                'user_id' => $user->id,
                'date' => $date->toDateString(),
            ]);

            // 勤怠修正 → 保存（申請）
            AttendanceCorrection::factory()->create([
                'attendance_record_id' => $attendance->id,
                'requested_by_user_id' => $user->id,
                'status' => 'pending',
                'remarks' => '電車遅延',
                'created_at' => Carbon::now(),
            ]);
        }

        // 申請一覧画面を確認
        $response = $this->get(route('attendance_corrections.index',[
            'status' => 'pending',]));

        $response->assertStatus(200);
        $response->assertSee('承認待ち');

        // 自分の申請が全て表示されていること
        foreach ($dates as $date) {
            $response->assertSee($date->format('Y/m/d'));
            $response->assertSee('電車遅延');
        }
    }

    /** @test */
    public function 承認済みに管理者が承認した申請が全て表示されている()
    {
        Carbon::setTestNow(Carbon::create(2025, 11, 15));

        // ユーザーと管理者を作成
        $user = User::factory()->create();
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // ユーザーでログイン
        $this->actingAs($user);

        //  勤怠を3日分作成
        $dates = [
            Carbon::create(2025, 11, 10),
            Carbon::create(2025, 11, 11),
            Carbon::create(2025, 11, 14),
        ];

        $approvedDates = [];

        // 勤怠 + 修正申請を作成（ループ）
        foreach ($dates as $date) {
            $attendance = AttendanceRecord::factory()->create([
                'user_id' => $user->id,
                'date' => $date->toDateString(),
                'clock_in' => '09:00',
                'clock_out' => '18:00',
            ]);

            // 勤怠修正申請を作成
            AttendanceCorrection::factory()->create([
                'attendance_record_id' => $attendance->id,
                'requested_by_user_id' => $user->id,
                'status' => 'pending',// まだ承認されていない
                'remarks' => '電車遅延',
                'created_at' => Carbon::now(),
            ]);
        }

        // 管理者でログイン
        $this->actingAs($admin);

        // 全申請を承認
        foreach (AttendanceCorrection::all() as $correction) {

            $correction->update([
                'status' => 'approved', // 承認された状態
                'approved_by_user_id' => $admin->id,
                'approved_at' => Carbon::now(),
            ]);

            // 対象日を記録
            $approvedDates[] = Carbon::parse(
                $correction->attendanceRecord->date
            );
        }

        // 申請者（一般ユーザー）としてログイン
        $this->actingAs($user);

        // 承認済み一覧を開く
        $response = $this->get(
            route('attendance_corrections.index', ['tab' => 'approved'])
        );

        // 全て表示されていることを確認
        foreach ($approvedDates as $date) {
            $response->assertSee($date->format('Y/m/d'));
        }

    }

    /** @test */
    public function 各申請の「詳細」を押下すると勤怠詳細画面に遷移する()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        $user = User::factory()->create();
        $this->actingAs($user);

        $targetDate = Carbon::create(2025, 1, 5);

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date'    => $targetDate->toDateString(),
            'clock_in'  => '09:00',
            'clock_out' => '15:00',
        ]);

        // 全て記入し修正ボタンを押す
        $response = $this->post(route('attendance_correction.store'), [
            'attendance_record_id' => $attendance->id,
            'date' => $targetDate->toDateString(),
            'clock_in'  => '09:00',
            'clock_out' => '15:00',
            'break_start' => ['12:30'],
            'break_end'   => ['13:00'],
            'remarks'=>'電車遅延',
        ]);

        // リダイレクト & エラーなし
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        // 修正申請がDBに作られている
        $this->assertDatabaseHas('attendance_corrections', [
            'attendance_record_id' => $attendance->id,
            'requested_by_user_id' => $user->id,
            'status' => 'pending',
            'remarks' => '電車遅延',
        ]);

        // 申請一覧画面を確認
        $response = $this->get(route('attendance_corrections.index'));

        $response->assertStatus(200);
        $response->assertSee('詳細');

        // 修正申請を取得
        $correction = AttendanceCorrection::first();

        // 「詳細」リンク先にアクセス
        $response = $this->get(
            route('attendance.show', [
                'id' => $attendance->id,
                'date' => $targetDate->format('Y-m-d'),
                'correction_id' => $correction->id,
            ])
        );

        // 勤怠詳細画面に遷移できていることを確認
        $response->assertStatus(200);
        $response->assertSee('2025年');
        $response->assertSee('1月5日');
        $response->assertSee('電車遅延');
    }
}





