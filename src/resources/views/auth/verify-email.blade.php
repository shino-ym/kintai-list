@extends('layouts.user')

@section('css')
<link rel="stylesheet" href="{{asset('css/auth/verify-email.css')}}">
@endsection

@section('app-class', 'bg-default')
@section('body-class', 'bg-default')

@section('content')
<div class="verify-container">
    <div class="verify-form">
        <div class="verify-message">
            <p>登録していただいたメールアドレスに認証メールを送付しました。</p>
            <p>メール認証を完了してください。</p>
        </div>
        {{-- 開発環境のみ：Mailhog を開くボタン --}}
            @if(app()->environment('local'))
            <a href="http://localhost:8025/" target="_blank" class="approve-btn">
                認証はこちらから
            </a>
            @endif

        <form method="post" action="{{route('verification.send')}}" >
            @csrf
            <input type="hidden" name="email" value="{{ session('registered_email')}}">
            <button type="submit" class="mail-submit">認証メールを再送する</button>
        </form>
    </div>
</div>

@endsection