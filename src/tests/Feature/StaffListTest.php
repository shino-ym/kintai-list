<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use Carbon\Carbon;



class StaffListTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 管理者ユーザーが全一般ユーザーの「氏名」「メールアドレス」を確認できる()
    {
        // 管理者用ユーザー作成
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // 一般ユーザーを複数作成
        $users = User::factory()->count(5)->create([
            'role' => 'user',
        ]);

        // 管理者でログイン
        $this->actingAs($admin);

        // スタッフ一覧ページを開く
        $response = $this->get(route('admin.staff.list'));

        $response->assertStatus(200);

        // 全ての一般ユーザーの氏名・メールアドレスが表示されている
        foreach ($users as $user) {
            $response->assertSee($user->name);
            $response->assertSee($user->email);
        }
    }

        /** @test */
    public function 管理者がユーザーの月次勤怠情報を正しく確認できる()
    {

        Carbon::setTestNow(Carbon::create(2025, 1, 30));

        // 管理者用ユーザー作成
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // 一般ユーザー作成
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        // 出勤日を数日作成し、勤怠レコードと休憩を設定
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

        // 管理者でログイン
        $this->actingAs($admin);

        // 一般ユーザーの月次勤怠を表示
        $response = $this->get(route('admin.attendance.monthly',[
            'id'=>$user->id,
        ]));

        // 画面が表示されていることを確認
        $response->assertStatus(200);


        // 出勤日が表示されていることを確認
        foreach ($dates as $date) {
            $response->assertSee($date->format('m/d'));
        }

        // 出勤・退勤・休憩・労働時間が表示されているか確認
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('0:45');
        $response->assertSee('8:15');
        $response->assertSee('詳細');
    }

    /** @test */
    public function ユーザーの勤怠一覧画面の「前月」を押下した時に表示つきの前月の情報が表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        // 管理者を作成
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // 一般ユーザー作成
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        // 管理者ログイン
        $this->actingAs($admin);

        // 勤怠一覧ページを開く
        $response = $this->get(route('admin.attendance.monthly',[
            'id'=>$user->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee('2025/01');

        $response->assertSee('前月');

        // 「前月」を押した想定でアクセス
        $response = $this->get(route('admin.attendance.monthly', [
            'id'=>$user->id,
            'year' => 2024,
            'month' => 12,
        ]));

        // 前月が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee('2024/12');
    }


        /** @test */
    public function ユーザーの勤怠一覧画面の「翌月」を押下した時に表示月の翌月の情報が表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        // 管理者作成
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // 一般ユーザー作成
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        // 管理者ログイン
        $this->actingAs($admin);

        // 勤怠一覧ページを開く（初期表示）
        $response = $this->get(route('admin.attendance.monthly',[
            'id'=>$user->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee('2025/01');

        $response->assertSee('翌月');

        // 「翌月」を押した想定でアクセス
        $response = $this->get(route('admin.attendance.monthly', [
            'id'=>$user->id,
            'year' => 2025,
            'month' => 2,
        ]));

        // 翌月が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee('2025/02');

    }

    /** @test */
    public function ユーザーの勤怠一覧画面の「詳細」を押下するとその日の勤怠詳細画面に遷移する()
    {
        // 時間を固定する
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        // 管理者作成
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // 一般ユーザー作成
        $user = User::factory()->create([
            'role' => 'user',
        ]);

        // 管理者ログイン
        $this->actingAs($admin);

        // 勤怠レコードを作成する（当日分）
        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-01',
        ]);

        // 勤怠一覧ページを開く
        $response = $this->get(route('admin.attendance.monthly',[
            'id'=>$user->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee('詳細');

        // 「詳細」を押下した想定で詳細ページにアクセス
        $response = $this->get(route('admin.attendance.show', [
            'id' => $user->id,
            'date' => $attendance->date,
        ]));

        // 勤怠詳細画面が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee('2025年');
        $response->assertSee('1月1日');

    }

}
