<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RegisterValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 名前が未入力だとバリデーションエラーになる()
    {
        $response = $this->post(route('register'), [
            'name' => '',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // name に対してバリデーションエラーがあることを確認
        $response->assertSessionHasErrors(['name']);
        $this->assertDatabaseMissing('users',['email'=>'test@example.com']);

        $response = $this->get('/register');
        $response->assertSee('お名前を入力してください');
    }

    /** @test */
    public function メールアドレスが未入力だとバリデーションエラーになる()
    {
        $response = $this->post(route('register'), [
            'name' => 'テストユーザー',
            'email' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // email に対してバリデーションエラーがあることを確認
        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseMissing('users',['name'=>'テストユーザー']);

        $response = $this->get('/register');
        $response->assertSee('メールアドレスを入力してください');
    }

    /** @test */
    public function パスワードが8文字未満だとバリデーションエラーになる()
    {
        $response = $this->post(route('register'), [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'pass123',
            'password_confirmation' => 'pass123',
        ]);

        // パスワードに対してバリデーションエラーがあることを確認
        $response->assertSessionHasErrors(['password']);
        $this->assertDatabaseMissing('users',['email'=>'test@example.com']);

        $response = $this->get('/register');
        $response->assertSee('パスワードは8文字以上で入力してください');
    }

    /** @test */
    public function パスワードが未入力だとバリデーションエラーになる()
    {
        $response = $this->post(route('register'), [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => '',
            'password_confirmation' => 'password123',
        ]);

        // パスワードに対してバリデーションエラーがあることを確認
        $response->assertSessionHasErrors(['password']);
        $this->assertDatabaseMissing('users',['email'=>'test@example.com']);

        $response = $this->get('/register');
        $response->assertSee('パスワードを入力してください');
    }

    /** @test */
    public function フォームに内容が正しく入力されていた場合、データが正常に保存される()
    {
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
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
        ]);
    }






}
