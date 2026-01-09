<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>coachtech 勤怠アプリ</title>
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/layouts/user.css') }}">
    @yield('css')
</head>
<body>
    <div class="@yield('app-class')">
        <header class="header">
            <img src="{{ asset('images/logo.svg') }}" alt="ロゴ" class="header-logo">

        @if (!Route::is('login') &&
        !Route::is('register') &&
        !Route::is('verification.notice')
        )

        <ul class="header-nav">
            <li class="header-nav__item">
                <a class="header-nav__link" href="{{ route('attendance.index') }}">勤怠</a>
            </li>
            <li class="header-nav__item">
                <a class="header-nav__link" href="{{ route('attendance.listMonth') }}">勤怠一覧</a>
            </li>
            <li class="header-nav__item">
                <a class="header-nav__link" href="{{ route('attendance_corrections.index') }}">申請</a>
            </li>


            <li class="header-nav__item">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button class="logout-link">ログアウト</button>
                </form>
            </li>
        </ul>
        @endif
        </header>
        <main class="@yield('body-class')">
            @yield('content')
        </main>
    </div>
    @yield('script')
</body>

</html>
