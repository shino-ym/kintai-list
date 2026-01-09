@extends('layouts.admin')

@section('css')
<link rel="stylesheet" href="{{asset('css/admin/attendance/monthly.css')}}">
@endsection

@section('body-class', 'bg-color')

@section('content')

<div class="attendance-container">
    <div class="center-wrapper">
        <h1 attendance-title>{{$staff->name}}さんの勤怠</h1>
    </div>

    <div class="attendance-header">
        <a href="{{ route('admin.attendance.monthly', [
            'id' => $staff->id,
            'year' => $prevMonth->year,
            'month' => $prevMonth->month
        ]) }}" class="month-link prev-month">
            ←前月
        </a>

        <div class="date-container">
            <img src="{{ asset('images/calendar-icon.png') }}" alt="カレンダー" style="width:16px; height:16px; vertical-align:middle;">
            <h2>{{ $date->format('Y/m') }}</h2>
        </div>

        <a href="{{ route('admin.attendance.monthly', [
            'id' => $staff->id,
            'year' => $nextMonth->year,
            'month' => $nextMonth->month
        ]) }}" class="month-link next-month">
            翌月→
        </a>

    </div>

    <table class="attendance-table">
        <thead>
            <tr>
                <th>日付</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>休憩</th>
                <th>合計</th>
                <th>詳細</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($days as $day)
            <tr>
                <td>{{ $day['label'] }}</td>
                <td>{{ $day['clock_in_time'] }}</td>
                <td>{{ $day['clock_out_time'] }}</td>
                <td>{{ $day['total_break_formatted'] }}</td>
                <td>{{ $day['total_minutes_formatted'] }}</td>
                <td>
                    <a class="detail" href="{{ route('admin.attendance.show', [
                        'id' => $staff->id,
                        'date' => $day['date']
                    ]) }}">詳細</a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class=csv-btn>
        <a href="{{ route('admin.attendance.csv', [
        'id' => $staff->id,
        'year' => $date->year,
        'month' => $date->month
    ]) }}" class="csv-link">
        CSV出力
    </a>
    </div>
</div>


@endsection
