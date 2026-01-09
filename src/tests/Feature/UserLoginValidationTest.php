<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;


class UserLoginValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function （一般ユーザー）メールアドレスが入力されていない場合、「メールアドレスを入力してください」というバリデーションメッセージが表示される ()
    {
        // ログインページを開く
        $response = $this->get('/login');

        // メールアドレスを入力せずに他の必要項目を入力する
        $response = $this->post('/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        // email に対してバリデーションエラーがあることを確認
        $response->assertSessionHasErrors(['email']);

        $response = $this->get('/login');
        $response->assertSee('メールアドレスを入力してください');
    }

    /** @test */
    public function （一般ユーザー）パスワードが未入力だと「パスワードを入力してください」というバリデーションメッセージが表示される()
    {
        $response = $this->get('/login');

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => '',
        ]);

        // パスワードに対してバリデーションエラーがあることを確認
        $response->assertSessionHasErrors(['password']);

        $response = $this->get('/login');
        $response->assertSee('パスワードを入力してください');
    }

    /** @test */
    public function （一般ユーザー）入力情報が間違っている場合、「ログイン情報が登録されていません。」というバリデーションメッセージが表示される()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response = $this->get('/login');

        $response = $this->from('/login')->post('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect('/login');

        $response = $this->get('/login');
        $response->assertSee('ログイン情報が登録されていません。');
    }

}
