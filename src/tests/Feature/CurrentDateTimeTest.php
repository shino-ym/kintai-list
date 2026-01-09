<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;
use App\Models\User;


class CurrentDateTimeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 画面上に表示されている日時が現在の日時と一致する()
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 9, 30));

        // ログインユーザーを用意
        $user = User::factory()->create();

        // ログインした状態にする
        $this->actingAs($user);

        // 勤怠画面を開く
        $response = $this->get(route('attendance.index'));

        // 表示確認
        $response->assertStatus(200);
        $response->assertSee(Carbon::now()->isoFormat('YYYY年M月D日（ddd）'));
        $response->assertSee(Carbon::now()->isoFormat('HH:mm'));
    }

}
