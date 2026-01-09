<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use App\Models\breakRecord;


class AdminAttendanceShowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 勤怠詳細画面の内容が選択した情報と一致する()
    {
        // 日にちを固定する
        Carbon::setTestNow(Carbon::create(2025, 1, 15));

        // 管理者を作成
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        //  同じ日付を用意して、1回だけ作って使い回す
        $targetDate = Carbon::create(2025, 1, 15);


        //  ユーザー作成し、勤怠レコードを作成する
        $user = User::factory()->create(); 

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-15',
            'clock_in'  => '09:00',
            'clock_out' => '18:00'
        ]);

        //  休憩を作る
        $attendance->breaks()->createMany([
            ['break_start' => '12:00', 'break_end' => '12:30'],
        ]);

        //  管理者でログイン
        $this->actingAs($admin);

        //  勤怠詳細ページにアクセス
        $response = $this->get(route('admin.attendance.show', ['id' => $user->id,]) . '?date=2025-01-15');

        //  勤怠詳細画面の一致した内容がされていることを確認
        $response->assertStatus(200);
        $response->assertSee($user->name);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('12:00');
        $response->assertSee('12:30');
    }

    /** @test */
    public function 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 15));

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        //  対象日付
        $targetDate = Carbon::create(2025, 1, 15);

        //  ユーザー作成し、勤怠レコードを作成する
        $user = User::factory()->create(); 

        //  勤怠レコードを作成
        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date'    => $targetDate->toDateString(),
        ]);

        //  勤怠詳細画面を開く
        $response = $this->get(route('admin.attendance.show', ['id' => $user->id,]) . '?date=2025-01-15');

        $response->assertStatus(200);

        //  不正な時間で修正申請（POST）
        $response = $this->post(route('admin.attendance.save'), [
            'attendance_record_id' => $attendance->id,
            'date'=> $targetDate->toDateString(),
            'clock_start' => '16:00',
            'clock_end' => '14:00',
        ]);

        // 出勤時間が退勤時間より後になっている場合に対してバリデーションエラーがあることを確認
        $response->assertSessionHasErrors(['clock_both']);

        // 下の画面に戻ってエラーメッセージが表示される
        $response = $this->get(route('admin.attendance.show', ['id' => $user->id,]) . '?date=2025-01-15');


        $response->assertSee('出勤時間もしくは退勤時間が不適切な値です');

    }

        /** @test */
    public function 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 15));

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $targetDate = Carbon::create(2025, 1, 15);

        $user = User::factory()->create();

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date'    => $targetDate->toDateString(),
        ]);

        // 勤怠詳細画面を開く
        $response = $this->get(route('admin.attendance.show', ['id' => $user->id]) .'?date=' . $targetDate->toDateString()
        );

        $response->assertStatus(200);

        // 不正な休憩（退勤後）
        $response = $this->post(route('admin.attendance.save'), [
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

        //  エラー後に詳細画面を開く
        $response = $this->get(route('admin.attendance.show', ['id' => $user->id]) .'?date=' . $targetDate->toDateString()
        );

        //  エラーメッセージが表示されている
        $response->assertSee('休憩時間が不適切な値です');
    }

        /** @test */
    public function 勤怠詳細画面の休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 15));

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $targetDate = Carbon::create(2025, 1, 15);

        //  ユーザー作成し、勤怠レコードを作成する
        $user = User::factory()->create();

        //  勤怠レコードを作成
        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date'    => $targetDate->toDateString(),
        ]);

        //  勤怠詳細画面を開く
        $response = $this->get(route('admin.attendance.show', ['id' => $user->id]) .'?date=' . $targetDate->toDateString()
        );

        $response->assertStatus(200);

        // 不正な休憩（退勤後）
        $response = $this->post(route('admin.attendance.save'), [
            'attendance_record_id' => $attendance->id,
            'date' => $targetDate->toDateString(),
            'clock_in'  => '09:00',
            'clock_out' => '15:00',
            'break_start' => ['14:30'],
            'break_end'   => ['16:00'],
        ]);

        //  index 0 の休憩終了にエラーがある
        $response->assertSessionHasErrors([
            'break_end.0',
        ]);

        // エラー後に詳細画面を開く
        $response = $this->get(route('admin.attendance.show', ['id' => $user->id]) .'?date=' . $targetDate->toDateString()
        );

        // エラーメッセージが表示されている
        $response->assertSee('休憩時間もしくは退勤時間が不適切な値です');
    }

    /** @test */
    public function 勤怠詳細画面の備考欄が未入力の場合、エラーメッセージが表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 15));

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $targetDate = Carbon::create(2025, 1, 15);

        $user = User::factory()->create();

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date'    => $targetDate->toDateString(),
        ]);

        $response = $this->get(route('admin.attendance.show', ['id' => $user->id]) .'?date=' . $targetDate->toDateString()
        );

        $response->assertStatus(200);

        // 備考未記入
        $response = $this->post(route('admin.attendance.save'), [
            'attendance_record_id' => $attendance->id,
            'date' => $targetDate->toDateString(),
            'clock_in'  => '09:00',
            'clock_out' => '15:00',
            'remarks'=>'',
        ]);

        //  備考にエラーがある
        $response->assertSessionHasErrors([
            'remarks',
        ]);

        //  エラー後に詳細画面を開く
        $response = $this->get(route('admin.attendance.show', ['id' => $user->id]) .'?date=' . $targetDate->toDateString()
        );

        //  エラーメッセージが表示されている
        $response->assertSee('備考を記入してください');
    }
}
