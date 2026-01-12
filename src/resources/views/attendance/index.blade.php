@extends('layouts.user')

@section('css')
<link rel="stylesheet" href="{{asset('css/attendance/index.css')}}">
@endsection

@section('body-class', 'bg-color')

@section('content')
@php
use Carbon\Carbon;
$today = Carbon::now();
@endphp

<div class="attendance-container">
    {{-- ステータス表示 --}}
    <div class="status">
        @switch($status)
            @case('勤務外')
                勤務外
                @break
            @case('出勤中')
                出勤中
                @break
            @case('休憩中')
                休憩中
                @break
            @case('退勤済')
                退勤済
                @break
        @endswitch
    </div>

    {{-- 今日の日付 --}}

    <div class="attendance-date" id="current-date">{{ $today->isoFormat('YYYY年M月D日（ddd）') }}</div>

    {{-- 現在時刻（数字のみ HHMM） --}}
    <div class="time" id="current-time">
        {{ $now->isoFormat('HH:mm') }}
    </div>

    {{-- 出勤 / 休憩 / 退勤ボタン --}}
    @if($status === '勤務外')
        <form method="POST" action="{{ route('attendance.clockIn') }}">
            @csrf
            <button class="submit-black" type="submit">出勤</button>
        </form>
    @elseif($status === '出勤中')
        <div class="button-group">
            <form method="POST" action="{{ route('attendance.clockOut') }}">
                @csrf
                <button class="submit-black" type="submit">退勤</button>
            </form>
            <form method="POST" action="{{ route('attendance.breakStart') }}">
                @csrf
                <button class="submit-white" type="submit">休憩入</button>
            </form>
        </div>

    @elseif($status === '休憩中')
        <form method="POST" action="{{ route('attendance.breakEnd') }}">
            @csrf
            <button class="submit-white" type="submit">休憩戻</button>
        </form>
    @elseif($status === '退勤済')
        <div class="finished-message">お疲れ様でした。</div>
    @endif
</div>

<script>
function updateTime() {
    const now = new Date();
    const hh = now.getHours().toString().padStart(2,'0');
    const mm = now.getMinutes().toString().padStart(2,'0');
    document.getElementById('current-time').textContent = hh + ':' + mm;
}
setInterval(updateTime, 1000);
updateTime();
</script>
@endsection

