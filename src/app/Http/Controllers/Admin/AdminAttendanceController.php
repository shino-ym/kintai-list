<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use App\Models\AttendanceCorrection;
use App\Http\Requests\Admin\AdminAttendanceRequest;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;



class AdminAttendanceController extends Controller
{
    /**
     * 管理者
     * 勤怠一覧画面
     */

    public function index(Request $request)
    {
        if ($request->query('date')) {
            $date = Carbon::parse($request->query('date'));
        } else {
            $year  = $request->query('year', now()->year);
            $month = $request->query('month', now()->month);
            $day   = $request->query('day', now()->day);

            $date = Carbon::create($year, $month, $day);
        }

        // 前日 / 翌日
        $prevDay = $date->copy()->subDay();
        $nextDay = $date->copy()->addDay();

        $records = AttendanceRecord::with(['user', 'breaks'])
            ->where('date', $date->toDateString())
            ->get();

        return view('admin.attendance.index', compact(
            'records',
            'date',
            'prevDay',
            'nextDay'
        ));
    }

    /**
     * 管理者
     * 勤怠詳細画面
     */

    public function show(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $date = Carbon::parse($request->query('date', now()->toDateString()));

        $record = AttendanceRecord::with(['breaks', 'corrections.correction_breaks'])
            ->where('user_id', $id)
            ->whereDate('date', $date)
            ->first();

        if (!$record) {

            $record = new AttendanceRecord([
                'user_id' => $id,
                'date'    => $date,
            ]);

            $record->setRelation('user', $user);

            $record->setRelation('breaks', collect());
        }

        $pendingCorrection = $record->corrections()
            ->where('status', 'pending')
            ->latest()
            ->first();

        $approvedCorrection = $record->corrections()
            ->where('status', 'approved')
            ->latest()
            ->first();

        $correctionId = $request->query('correction_id');
        if ($correctionId) {
            $selectedCorrection = $record->corrections()
                ->where('id', $correctionId)
                ->first();
        } else {
            $selectedCorrection = $pendingCorrection ?? $approvedCorrection;
        }

        $useCorrectionValue =
            $selectedCorrection
            && $selectedCorrection->status === 'pending';

        $isPendingSelected =
            $selectedCorrection
            && $selectedCorrection->status === 'pending';

        $isAdmin = auth()->check() && auth()->user()->role === 'admin';

        $isFromApprovalTab = $request->boolean('from_approval_tab');

        $canEdit =
            is_null($selectedCorrection)
            || $selectedCorrection->status === 'approved';

        $canApprove =
            $isAdmin
            && $isPendingSelected
            && $isFromApprovalTab;

        $showPendingMessage =
            $isPendingSelected
            && !$isFromApprovalTab;

        // -------------------------------
        // 出勤
        // -------------------------------
        $clockIn = '';
        if ($useCorrectionValue && $selectedCorrection->clock_in) {
            try {
                $clockIn = Carbon::parse($selectedCorrection->clock_in)->format('H:i');
            } catch (\Exception $e) {
                 // 不正な時刻形式の場合は空欄表示
                $clockIn = '';
            }
        } elseif ($record->clock_in) {
            try {
                $clockIn = Carbon::parse($record->clock_in)->format('H:i');
            } catch (\Exception $e) {
                $clockIn = '';
            }
        }

        $clockIn = old('clock_in', $clockIn);

        // -------------------------------
        // 退勤
        // -------------------------------
        $clockOut = '';
        if ($useCorrectionValue && $selectedCorrection->clock_out) {
            try {
                $clockOut = Carbon::parse($selectedCorrection->clock_out)->format('H:i');
            } catch (\Exception $e) {
                $clockOut = '';
            }
        } elseif ($record->clock_out) {
            try {
                $clockOut = Carbon::parse($record->clock_out)->format('H:i');
            } catch (\Exception $e) {
                $clockOut = '';
            }
        }
        $clockOut = old('clock_out', $clockOut);

        $clockInValue  = old('clock_in', $clockIn ?? '');
        $clockOutValue = old('clock_out', $clockOut ?? '');

        $isReadonly = !$canEdit;



        // -------------------------------
        // 休憩
        // -------------------------------

        $baseBreaks = $useCorrectionValue
            ? $selectedCorrection->correction_breaks
            : $record->breaks;

        $oldStart = old('break_start', []);
        $oldEnd   = old('break_end', []);

        $breakList = [];

        if (!empty($oldStart)) {
            foreach ($oldStart as $i => $start) {
                $breakList[] = [
                    'start' => $start ?? '',
                    'end'   => $oldEnd[$i] ?? '',
                ];
            }
        } else {
            foreach ($baseBreaks as $b) {
                $breakList[] = [
                    'start' => $b->break_start
                        ? Carbon::parse($b->break_start)->format('H:i')
                        : '',
                    'end'   => $b->break_end
                        ? Carbon::parse($b->break_end)->format('H:i')
                        : '',
                ];
            }
        }

        // 空行が含まれていなければ 1 行だけ足す
        $hasEmptyRow = collect($breakList)->contains(function ($b) {
            return empty($b['start']) && empty($b['end']);
        });

        if (!$hasEmptyRow) {
            $breakList[] = ['start' => '', 'end' => ''];
        }


        // -------------------------------
        // 備考
        // -------------------------------

        // 未承認の修正申請があるか？
        $pendingCorrection = $record->corrections()
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($pendingCorrection) {
            // 申請中の内容を見せる
            $remarks = $pendingCorrection->remarks;
        } else {
            // 確定している勤怠を見せる
            $remarks = $record->remarks;
        }

        return view('admin.attendance.show', compact(
            'record',
            'user',
            'date',
            'clockInValue',
            'clockOutValue',
            'isReadonly',
            'breakList',
            'remarks',
            'selectedCorrection',
            'approvedCorrection',
            'canEdit',
            'canApprove',
            'showPendingMessage',
        ));
    }

    /**
     * 管理者
     * 修正登録
     */

    public function save(AdminAttendanceRequest $request)
    {
        $data = $request->validated();

        // 勤怠レコード取得 or 新規作成
        $record = AttendanceRecord::firstOrCreate(
            [
                'user_id' => $data['user_id'],
                'date'    => $data['date'],
            ],
        );

        $record->update([
            'clock_in'     => $data['clock_in'] ?? null,
            'clock_out'    => $data['clock_out'] ?? null,
            'remarks'      => $data['remarks'] ?? null,
            'is_corrected' => true,
        ]);

        // 休憩
        $record->breaks()->delete();

        $breakStarts = $request->input('break_start', []);
        $breakEnds   = $request->input('break_end', []);

        foreach ($breakStarts as $i => $start) {
            $end = $breakEnds[$i] ?? null;

            if ($start && $end) {
                $record->breaks()->create([
                    'break_start' => $start,
                    'break_end'   => $end,
                ]);
            }
        }

        $record->load('breaks');
        $record->recalcTotalTimes();

        return redirect()->back()->with('success', '勤怠を更新しました');
    }

    /**
     * 管理者
     * スタッフ別月間勤怠
     */

    public function monthly(Request $request,$id){

        $staff = User::where('role', 'user')->findOrFail($id);

        $year = $request->query('year', now()->year);
        $month = $request->query('month', now()->month);

        $date = \Carbon\Carbon::create($year, $month, 1);
        $prevMonth = $date->copy()->subMonth();
        $nextMonth = $date->copy()->addMonth();

        // 勤怠レコード取得（breaksも一緒に取得）
        $records = AttendanceRecord::withCount([
                'corrections as pending_corrections_count' => function ($q) {
                    $q->where('status', 'pending');
                }
            ])
            ->where('user_id', $staff->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->with('breaks')
            ->get()
            ->keyBy(fn($item) => $item->date->format('Y-m-d'));

        $days = [];

        for ($day = 1; $day <= $date->daysInMonth; $day++) {
            $currentDate = $date->copy()->day($day);
            $dateKey = $currentDate->format('Y-m-d');

            $attendance = $records[$dateKey] ?? null;

            $days[] = [
                'date' => $dateKey,
                'label' => $currentDate->format('m/d') . '(' . $currentDate->isoFormat('ddd') . ')',

                'attendance' => $attendance,

                'clock_in_time' => $attendance?->clock_in_time,
                'clock_out_time' => $attendance?->clock_out_time,
                'total_break_formatted' => $attendance?->total_break_formatted,
                'total_minutes_formatted' => $attendance?->total_minutes_formatted,
            ];
        }

        return view('admin.attendance.monthly', compact(
            'staff',
            'date',
            'prevMonth',
            'nextMonth',
            'days'
        ));
    }


    /**
     * 管理者
     * CSV出力
     */

    public function monthlyCsv($id, Request $request)
    {
        abort_unless(auth()->user()->role === 'admin', 403);

        $staff = User::findOrFail($id);

        // 年・月を取得
        $year  = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $start = Carbon::create($year, $month, 1);
        $end   = $start->copy()->endOfMonth();

        // 勤怠レコード取得
        $records = AttendanceRecord::where('user_id', $staff->id)
            ->whereBetween('date', [
                $start->toDateString(),
                $end->toDateString()
            ])
            ->with('breaks')
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($item) => $item->date->format('Y-m-d'));

        $stream = fopen('php://temp', 'r+b');
        fputcsv($stream, ['日付','出勤','退勤','休憩時間','勤務合計時間']);

        for ($day = 1; $day <= $start->daysInMonth; $day++) {
            $currentDate = $start->copy()->day($day)->toDateString();
            $record = $records[$currentDate] ?? null;

            fputcsv($stream, [
                $currentDate,
                $record?->clock_in_time ?? '',
                $record?->clock_out_time ?? '',
                $record?->total_break_formatted ?? '',
                $record?->total_minutes_formatted ?? '',
            ]);
        }

        rewind($stream);
        $csv = mb_convert_encoding(stream_get_contents($stream), 'SJIS-win', 'UTF-8');

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=Shift_JIS',
            'Content-Disposition' => 'attachment; filename="'.$staff->name.'_勤怠_'.$year.$month.'.csv"',
        ]);
    }

}