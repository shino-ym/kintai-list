@extends('layouts.user')

@section('css')
<link rel="stylesheet" href="{{ asset('css/attendance/show.css') }}">
@endsection

@section('body-class', 'bg-color')

@section('content')

<div class="attendance-container">
    <div class="center-wrapper">
        <h1 class="attendance-title">勤怠詳細</h1>
    </div>

    <form method="POST" action="{{ route('attendance_correction.store') }}">
        @csrf
        <input type="hidden" name="date" value="{{ $record->date->format('Y-m-d') }}">

        <table class="detail-table">
            <thead>
                <tr>
                    <th>名前</th>
                    <td class="user-name">{{ $user->name }}</td>
                </tr>

                <tr>
                    <th>日付</th>
                    <td class="date-cell">
                        {{ $record->date->format('Y年') }} <span class="separator"></span> {{ $record->date->format('n月j日') }}
                    </td>
                </tr>

                {{-- 出勤・退勤 --}}
                <tr>
                    <th>出勤・退勤</th>
                    <td class="time-cell">
                        <div class="input-block-wrapper">
                            <div class="input-block">
                                <input type="time" name="clock_in" class="time" value="{{ $clockInValue }}" @if($isPending) readonly @endif>
                            </div>

                            <span class="separator">〜</span>

                            <div class="input-block">
                                <input type="time" name="clock_out" class="time" value="{{ $clockOutValue }}" @if($isPending) readonly @endif>
                            </div>

                            @if($errors->has('clock_both'))
                                <div class="error-wrapper">
                                    <div class="input-error-message">{{ $errors->first('clock_both') }}</div>
                                </div>
                            @endif
                        </div>
                    </td>
                </tr>

                {{-- 休憩 --}}

                @foreach ($breakList as $i => $bk)
                <tr>
                    <th>{{ $i === 0 ? '休憩' : '休憩'.($i+1) }}</th>
                    <td class="time-cell">
                        <div class="input-block-wrapper">
                            <div class="input-block">
                                <input type="time" name="break_start[]" class="time" value="{{ old('break_start.'.$i, $bk['start']) }}" @if($isPending) readonly @endif>
                            </div>

                            <span class="separator">〜</span>

                            <div class="input-block">
                                <input type="time" name="break_end[]" class="time" value="{{ old('break_end.'.$i, $bk['end']) }}" @if($isPending) readonly @endif>
                            </div>

                            @if($errors->has("break_start.$i"))
                                <div class="error-wrapper">
                                    <div class="input-error-message">{{ $errors->first("break_start.$i") }}</div>
                                </div>
                            @endif
                            @if($errors->has("break_end.$i"))
                                <div class="error-wrapper">
                                    <div class="input-error-message">{{ $errors->first("break_end.$i") }}</div>
                                </div>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach

                {{-- 備考 --}}
                <tr>
                    <th>備考</th>
                    <td>
                        <textarea name="remarks" rows="3" @if($isPending) readonly @endif>{{ $remarks }}</textarea>
                        @error('remarks')<div class="input-error-message">{{ $message }}</div>@enderror
                    </td>
                </tr>
            </thead>
        </table>

        @if ($isPending)
            <div class="pending">
                <div class="pending-message">*承認待ちのため修正はできません。</div>
            </div>
        @else
            <div class="submit">
                <button class="submit-btn" type="submit">修正</button>
            </div>
        @endif
    </form>
</div>

@endsection

