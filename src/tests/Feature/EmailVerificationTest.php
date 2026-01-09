<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 会員登録後、認証メールが送信される()
    {
        // メール送信を偽装
        Notification::fake();

        $response = $this->post(route('register'), [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // バリデーションエラーがないことを確認
        $response->assertSessionDoesntHaveErrors();

        // DBに登録されていることを確認
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'テストユーザー',
        ]);

        // 登録されたユーザーを取得
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo($user, VerifyEmail::class);

    }

        /** @test */
    public function メール認証誘導画面で「認証はこちらから」ボタンを押下するとメール認証サイトに遷移する()
    {
        // ユーザー作成。まだメール未認証
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // 誘導画面にアクセス
        $response = $this->actingAs($user)->get(route('verification.notice'));
        // 画面表示成功
        $response->assertStatus(200);

        // 認証リンクを作成（署名付きURL）
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // 認証ページにアクセス
        $response = $this->actingAs($user)->get($verificationUrl);
        $response->assertStatus(302); // メール認証ページが表示される
    }

        /** @test */
    public function メール認証を完了すると、勤怠画面に遷移する()
    {
        // 未認証ユーザーを作成
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // 署名付き認証URLを作成
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // ユーザーとして認証URLにアクセス
        $response = $this->actingAs($user)->get($verificationUrl);

        // 認証完了後に勤怠画面へリダイレクトされることを確認
        $response->assertRedirect(route('attendance.index'));

        // 実際にユーザーが認証済みになったか確認
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

}
