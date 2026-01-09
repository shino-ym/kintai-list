@extends('layouts.user')

@section('css')
<link rel="stylesheet" href="{{asset('css/auth/register.css')}}">
@endsection

@section('app-class', 'bg-default')
@section('body-class', 'bg-default')

@section('content')

<div class="register-form">
    <div class="register-form__content">
        <div class="register-form__heading">
            <h1>会員登録</h1>
        </div>
        <form class="form" action="/register" method="post">
            @csrf
            <div class="form-group">
                <label for="name" class="form-label">名前</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}"/>
                @error('name')
                    <div class="input-error-message">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="email" class="form-label">メールアドレス</label>
                <input type="text" id="email" name="email" value="{{ old('email') }}"/>
                @error('email')
                    <div class="input-error-message">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="password" class="form-label">パスワード</label>
                <input type="password" id="password" name="password">
                @error('password')
                    <div class="input-error-message">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation" class="form-label">パスワード確認</label>
                <input type="password" id="password_confirmation" name="password_confirmation"/>
            </div>

            <div class="form-btn">
                <button class="submit-btn" type="submit">登録する</button>
            </div>
        </form>

        <a href="{{ url('/login') }}">ログインはこちら</a>
    </div>
</div>
@endsection



