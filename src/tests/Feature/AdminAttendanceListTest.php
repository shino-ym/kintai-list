<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;


class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function その日の全ユーザーの勤怠情報が正確な値になっている()
    {
        // 時間を固定する
        Carbon::setTestNow(Carbon::create(2025, 1, 15));

        // 管理者を作成
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // ユーザーを３人作る
        $users = User::factory()->count(3)->create();

        //  同じ日付を用意して、1回だけ作って使い回す
        $targetDate = Carbon::create(2025, 1, 15);

        // 3人それぞれに勤怠レコードを作る
        foreach ($users as $user) {
            $record = AttendanceRecord::factory()->create([
                'user_id' => $user->id,
                'date' => $targetDate->toDateString(),
                'clock_in'  => '09:00',
                'clock_out' => '18:00',
            ]);

            //  休憩を2回作る（合計45分）
            $record->breaks()->createMany([
                ['break_start' => '12:00', 'break_end' => '12:30'], // 30分
                ['break_start' => '15:00', 'break_end' => '15:15'], // 15分
            ]);

            $record->recalcTotalTimes();
        }

        //  管理者でログイン
        $this->actingAs($admin);

        // 勤怠一覧ページを開く
        $response = $this->get(route('admin.attendance.index'));
        $response->assertStatus(200);

        foreach ($users as $user) {
            $response->assertSee('2025/01/15');
            $response->assertSee($user->name);
            $response->assertSee('09:00');
            $response->assertSee('18:00');
            $response->assertSee('0:45');
            $response->assertSee('詳細');

        }
    }

    /** @test */
    public function 勤怠一覧画面に遷移した際に現在の日付が表示されている()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 5));

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $this->actingAs($admin);


        //  勤怠一覧画面にアクセス
        $response = $this->get(route('admin.attendance.index'));
        $response->assertStatus(200);

        //  現在の月が表示されていることを確認
        $response->assertSee('2025/01/05');
    }

    /** @test */
    public function 勤怠一覧画面の「前日」を押下した時に表示つきの前の日の情報が表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        // 勤怠一覧ページを開く
        $response = $this->get(route('admin.attendance.index'));
        $response->assertStatus(200);
        $response->assertSee('2025/01/01');

        $response->assertSee('前日');

        // 「前日」を押した想定でアクセス
        $response = $this->get(route('admin.attendance.index', [
            'year' => 2024,
            'month' => 12,
            'day'=>31
        ]));

        //  前日が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee('2024/12/31');
    }

    /** @test */
    public function 勤怠一覧画面の「翌日」を押下した時に表示つきの次の日の情報が表示される()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $response = $this->get(route('admin.attendance.index'));
        $response->assertStatus(200);
        $response->assertSee('2025/01/01');

        $response->assertSee('翌日');

        // 「翌日」を押した想定でアクセス
        $response = $this->get(route('admin.attendance.index', [
            'year' => 2025,
            'month' => 1,
            'day'=>2
        ]));

        // 翌日が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee('2025/01/02');
    }

    /** @test */
    public function 勤怠一覧画面の「詳細」を押下するとその日の勤怠詳細画面に遷移する()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1));

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $this->actingAs($admin);

        $user = User::factory()->create();

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => '2025-01-01',
        ]);

        $response = $this->get(route('admin.attendance.index'));
        $response->assertStatus(200);
        $response->assertSee('詳細');

        // 「詳細」を押下した想定で詳細ページにアクセス
        $response = $this->get(route('admin.attendance.show', [
            'id' => $user->id,
        ]));

        // 勤怠詳細画面が表示されていることを確認
        $response->assertStatus(200);
        $response->assertSee('2025-01-01');
    }

}