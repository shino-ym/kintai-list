@extends('layouts.admin')

@section('css')
<link rel="stylesheet" href="{{asset('css/admin/attendance/show.css')}}">
@endsection

@section('body-class', 'bg-color')

@section('content')

<div class="attendance-container">
    <div class="center-wrapper">
        <h1 class="attendance-title">勤怠詳細</h1>
    </div>

    <form method="POST" action="{{ route('admin.attendance.save') }}">
        @csrf
        <input type="hidden" name="date" value="{{ $record->date->format('Y-m-d') }}">
        <input type="hidden" name="user_id" value="{{ $record->user_id }}">

        @if(isset($selectedCorrection))
            <input type="hidden" name="correction_id" value="{{ $selectedCorrection->id }}">
        @endif

        <table class="detail-table">
            <thead>
                <tr>
                    <th>名前</th>
                    <td class="user-name">{{ $user->name }}</td>
                </tr>

                <tr>
                    <th>日付</th>
                    <td class="date-cell">
                        {{ $date->format('Y年') }} <span class="separator"></span> {{ $date->format('n月j日') }}
                    </td>
                </tr>

                {{-- 出勤・退勤 --}}
                <tr>
                    <th>出勤・退勤</th>
                    <td class="time-cell">
                        <div class="input-block-wrapper">
                            <div class="input-block">
                                <input type="time" name="clock_in" class="time" value="{{ $clockInValue }}" @if(!$canEdit) readonly @endif>
                            </div>

                            <span class="separator">〜</span>

                            <div class="input-block">
                                <input type="time" name="clock_out" class="time" value="{{ $clockOutValue }}" @if(!$canEdit) readonly @endif>
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
                                <input type="time" name="break_start[]" class="time"
                                    value="{{ $bk['start'] }}" @if(!$canEdit) readonly @endif>
                            </div>

                            <span class="separator">〜</span>

                            <div class="input-block">
                                <input type="time" name="break_end[]" class="time"
                                    value="{{ $bk['end'] }}" @if(!$canEdit) readonly @endif>
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
                        <textarea name="remarks" rows="3" @if($isReadonly) readonly @endif>{{ $remarks}}</textarea>
                        @error('remarks')<div class="input-error-message">{{ $message }}</div>@enderror
                    </td>
                </tr>
            </thead>
        </table>
        @if($canEdit)
            <div class="submit">
                <button type="submit" class="submit-btn">修正</button>
            </div>
        @endif
    </form>

    {{-- 承認ボタンまたは修正不可メッセージ --}}
    <div class="submit">

        {{-- 承認待ち × 承認画面 --}}

        @if($showPendingMessage)
            <div class="pending-message">
                ＊承認待ちのため修正はできません。
            </div>
        @endif

        @if($canApprove)
            <button
                id="approve-btn"
                class="submit-btn"
                type="button"
                data-url="{{ route('admin.attendance_corrections.approve', $selectedCorrection->id) }}"
            >
                承認
            </button>

            <div
                id="approved-message"
                class="status status-approved"
                style="display:none;"
            >
                承認済み
            </div>
        @endif

    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('approve-btn');
        if (!btn) return;

        btn.addEventListener('click', () => {
            btn.disabled = true;

            fetch(btn.dataset.url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-HTTP-Method-Override': 'PUT',
                    'Accept': 'application/json',
                },
            })
            .then(res => {
                if (!res.ok) throw new Error();
                return res.json();
            })
            .then(data => {
                if (data.status === 'approved') {
                    btn.style.display = 'none';
                    document.getElementById('approved-message').style.display = 'block';
                }
            })
            .catch(() => {
                btn.disabled = false;
                alert('承認に失敗しました');
            });
        });
    });
    </script>


    </div>
</div>
@endsection

