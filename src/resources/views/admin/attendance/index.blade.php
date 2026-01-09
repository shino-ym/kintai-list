@extends('layouts.admin')

@section('css')
<link rel="stylesheet" href="{{asset('css/admin/attendance/index.css')}}">
@endsection

@section('body-class', 'bg-color')

@section('content')

<div class="attendance-container">
    <div class="center-wrapper">
        <h1 attendance-title>{{ $date->format('Y年n月j日') }}の勤怠</h1>
    </div>

    <div class="attendance-header">
        <a href="{{ url('/admin/attendance/list?year=' . $prevDay->year . '&month=' . $prevDay->month . '&day=' . $prevDay->day) }}"
            class="day-link prev-day">←前日</a>

        <div class="date-container">
            <img src="{{ asset('images/calendar-icon.png') }}" alt="カレンダー" style="width:16px; height:16px; vertical-align:middle;">
            <h2>{{ $date->format('Y/m/d') }}</h2>
        </div>

        <a href="{{ url('/admin/attendance/list?year=' . $nextDay->year . '&month=' . $nextDay->month . '&day=' . $nextDay->day) }}"
            class="day-link next-month">翌日→</a>
    </div>

    <table class="attendance-table">
        <thead>
            <tr>
                <th>名前</th>
                <th>出勤</th>
                <th>退勤</th>
                <th>休憩</th>
                <th>合計</th>
                <th>詳細</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($records as $record)
                <tr>
                    <td>{{$record->user->name  }}</td>
                    <td>{{ $record->clock_in_time }}</td>
                    <td>{{ $record->clock_out_time }}</td>
                    <td>{{ $record->total_break_formatted }}</td>
                    <td>{{ $record->total_minutes_formatted }}</td>
                    <td>
                        <a class="detail" href="{{ route('admin.attendance.show', [
                            'id' => $record->user->id,
                            'date' => $record->date->format('Y-m-d')
                        ]) }}">詳細</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
