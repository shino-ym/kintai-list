<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;


class AdminLoginValidationTest extends TestCase
{
    use RefreshDatabase;

     /** @test */
    public function （管理者）メールアドレスが入力されていない場合、「メールアドレスを入力してください」というバリデーションメッセージが表示される ()
    {
        // ログインページを開く
        $response = $this->get('/admin/login');

        // メールアドレスを入力せずに他の必要項目を入力する
        $response = $this->post('/admin/login', [
            'email' => '',
            'password' => 'password123',
        ]);

        // email に対してバリデーションエラーがあることを確認
        $response->assertSessionHasErrors(['email']);

        $response = $this->get('/admin/login');
        $response->assertSee('メールアドレスを入力してください');
    }

    /** @test */
    public function （管理者）パスワードが未入力だと「パスワードを入力してください」というバリデーションメッセージが表示される()
    {
        $response = $this->get('/admin/login');


        $response = $this->post('/admin/login', [
            'email' => 'admintest@example.com',
            'password' => '',
        ]);

        // パスワードに対してバリデーションエラーがあることを確認
        $response->assertSessionHasErrors(['password']);

        $response = $this->get('/admin/login');
        $response->assertSee('パスワードを入力してください');
    }

    /** @test */
    public function （管理者）入力情報が間違っている場合、「ログイン情報が登録されていません。」というバリデーションメッセージが表示される()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admintest@example.com',
            'password' => 'password123',
        ]);

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'noneadmintest@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect('/admin/login');

        $response = $this->get('/admin/login');
        $response->assertSee('ログイン情報が登録されていません。');

    }

}
