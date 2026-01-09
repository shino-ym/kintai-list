@extends('layouts.user')

@section('css')
<link rel="stylesheet" href="{{asset('css/auth/login.css')}}">
@endsection

@section('app-class', 'bg-default')
@section('body-class', 'bg-default')

@section('content')

<div class="login-form">
    <div class="login-form__content">
        <div class="login-form__heading">
            <h1>ログイン</h1>
        </div>
        <form class="form" method="post" action="{{ route('login') }}">
        @csrf

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
                    <span class="input-error">
                        <div class="input-error-message">{{$errors->first('password')}}</div>
                    </span>
                @enderror
            </div>
            @if($errors->has('login_error'))
                <div class="input-error-message">{{ $errors->first('login_error') }}</div>
            @endif


            <div class="form-btn">
                <button class="submit-btn" type="submit">ログインする</button>
            </div>
        </form>
        <a href="{{ route('register') }}">会員登録はこちら</a>
    </div>
</div>
@endsection

