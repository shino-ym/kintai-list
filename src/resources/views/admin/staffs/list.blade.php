@extends('layouts.admin')

@section('css')
<link rel="stylesheet" href="{{asset('css/admin/staffs/list.css')}}">
@endsection

@section('body-class', 'bg-color')

@section('content')

<div class="staff-container">
    <div class="center-wrapper">
        <h1 class="list-title">スタッフ一覧</h1>
    </div>

    <table class="staff-table">
        <thead>
            <tr>
                <th>名前</th>
                <th>メールアドレス</th>
                <th>月次勤怠</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($staffs as $staff)
                <tr>
                    <td>{{ $staff->name  }}</td>
                    <td>{{ $staff->email }}</td>
                    <td>
                        <a class="detail" href="{{ route('admin.attendance.monthly', $staff->id) }}">詳細</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
