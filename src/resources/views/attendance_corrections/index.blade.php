@extends('layouts.user')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance_corrections/index.css') }}">
@endsection

@section('body-class', 'bg-color')

@section('content')

<div class="correction-container">
    <div class="center-wrapper">
        <h1 class="correction-title">申請一覧</h1>
    </div>

    {{-- タブメニュー --}}
    <div class="tab-menu">
        <a href="{{ route('attendance_corrections.index', ['tab' => 'pending']) }}"
            class="tab {{ $tab === 'pending' ? 'active' : '' }}">
            承認待ち
        </a>

        <a href="{{ route('attendance_corrections.index', ['tab' => 'approved']) }}"
            class="tab {{ $tab === 'approved' ? 'active' : '' }}">
            承認済み
        </a>
    </div>
    <hr class="separator">

        <table class="correction-table">
        <thead>
            <tr>
                <th>状態</th>
                <th>名前</th>
                <th>対象日時</th>
                <th>申請理由</th>
                <th>申請日時</th>
                <th>詳細</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($corrections as $correction)
            <tr>
                <td>
                    @if ($correction->status === 'pending')
                        承認待ち
                    @elseif ($correction->status === 'approved')
                        承認済み
                    @endif
                </td>

                {{-- 申請者 --}}
                <td>{{ $correction->requestedBy->name }}</td>

                {{-- 対象日時（AttendanceRecord の date） --}}
                <td>{{ $correction->attendanceRecord->date->format('Y/m/d') }}</td>

                {{-- 申請理由 --}}
                <td>{{ $correction->remarks }}</td>

                {{-- 申請日時（created_at） --}}
                <td>{{ $correction->created_at->format('Y/m/d') }}</td>

                {{-- 詳細（勤怠詳細へ飛ぶ） --}}
                <td>
                    <a class="detail" href="{{ route('attendance.show', [
                        'id' => $correction->attendanceRecord->id,
                        'date' => $correction->attendanceRecord->date->format('Y-m-d'),
                        'correction_id' => $correction->id,
                    ]) }}">
                        詳細
                    </a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>


@endsection

