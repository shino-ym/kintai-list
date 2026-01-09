<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\AttendanceRecord;
use Carbon\Carbon;

class AttendanceCorrectionRequest extends FormRequest
{
    protected $recordExists = false;

    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        // clock_in / clock_out 正規化
        $this->merge([
            'clock_in'  => $this->normalizeTime($this->clock_in),
            'clock_out' => $this->normalizeTime($this->clock_out),
        ]);

        // break_start / break_end は配列で正規化
        $this->merge([
            'break_start' => $this->normalizeArray($this->break_start),
            'break_end'   => $this->normalizeArray($this->break_end),
        ]);

        $this->recordExists = AttendanceRecord::where('user_id', $this->user()->id)
            ->where('date', $this->date)
            ->exists();
    }

    private function normalizeTime($value)
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }
        if ($value === null) return null;
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function normalizeArray($arr)
    {
        $arr = (array) $arr;
        return array_map(function ($v) {
            if ($v === null) return null;
            $v = trim($v);
            return $v === '' ? null : $v;
        }, $arr);
    }

    public function rules()
    {
        return [
            'clock_in'       => 'nullable',
            'clock_out'      => 'nullable',

            'break_start.*'  => 'nullable',
            'break_end.*'    => 'nullable',

            'remarks' => 'required|string|max:255',
            'date'    => 'required|date',
        ];
    }

    public function messages()
    {
        return [
            'remarks.required' => '備考を記入してください',
        ];
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $clockIn  = $this->clock_in;
            $clockOut = $this->clock_out;

            // ==========================================================
            // ① 出勤・退勤どちらか欠けている → clock_both のみエラー
            // ==========================================================
            if (empty($clockIn) || empty($clockOut)) {
                $validator->errors()->add(
                    'clock_both',
                    '出勤時間もしくは退勤時間が不適切な値です'
                );
                return;
            }

            // ==========================================================
            // ② 時刻としてパースできるか
            // ==========================================================
            try {
                $clockInCarbon  = Carbon::createFromFormat('H:i', $clockIn);
                $clockOutCarbon = Carbon::createFromFormat('H:i', $clockOut);
            } catch (\Exception $e) {
                $validator->errors()->add(
                    'clock_both',
                    '出勤時間もしくは退勤時間が不適切な値です'
                );
                return;
            }

            // ==========================================================
            // ③ 出勤 >= 退勤のとき
            // ==========================================================
            if ($clockInCarbon >= $clockOutCarbon) {
                $validator->errors()->add(
                    'clock_both',
                    '出勤時間もしくは退勤時間が不適切な値です'
                );
                return;
            }

            // ==========================================================
            // ④ 「休憩チェック」
            // ==========================================================
            $starts = is_array($this->break_start) ? $this->break_start : [];
            $ends   = is_array($this->break_end) ? $this->break_end : [];

            $invalidBreakIndexes = [];

            $prevEnd = null;

            // ---- 休憩開始 ----
            foreach ($starts as $i => $s) {
                if (!$s) continue;

                try {
                    $bs = Carbon::createFromFormat('H:i', $s);
                } catch (\Exception $e) {
                    $validator->errors()->add("break_start.$i", "休憩時間が不適切な値です");
                    continue;
                }

                if ($bs < $clockInCarbon) {
                    $validator->errors()->add("break_start.$i", "休憩時間が不適切な値です");
                    $invalidBreakIndexes[] = $i;
                    continue;
                }

                if ($bs > $clockOutCarbon) {
                    $validator->errors()->add("break_start.$i", "休憩時間が不適切な値です");
                    $invalidBreakIndexes[] = $i;
                    continue;
                }

                if ($prevEnd && $bs < $prevEnd) {
                    $validator->errors()->add("break_start.$i", "休憩時間が不適切な値です");
                    $invalidBreakIndexes[] = $i;
                    continue;
                }

                if (isset($ends[$i]) && $ends[$i]) {
                    try {
                        $be = Carbon::createFromFormat('H:i', $ends[$i]);
                    } catch (\Exception $e) {
                        $validator->errors()->add("break_end.$i", "休憩終了時間が不適切な値です");
                        continue;
                    }

                    if ($bs >= $be) {
                        $validator->errors()->add("break_start.$i", "休憩時間が不適切な値です");
                        $invalidBreakIndexes[] = $i;
                        continue;
                    }
                    $prevEnd = $be;
                }
            }
            // ---- 休憩終了 ----
            foreach ($ends as $i => $e) {

                // ★開始でエラーのある休憩は終了を見ない
                if (in_array($i, $invalidBreakIndexes, true)) {
                    continue;
                }

                if (!$e) continue;

                try {
                    $be = Carbon::createFromFormat('H:i', $e);
                } catch (\Exception $ex) {
                    $validator->errors()->add("break_end.$i", "休憩終了時間が不適切な値です");
                    continue;
                }

                if ($be > $clockOutCarbon) {
                    $validator->errors()->add(
                        "break_end.$i",
                        "休憩時間もしくは退勤時間が不適切な値です"
                    );
                }
            }
            // ==========================================================
            // ⑤ 新規レコードでエラーがある場合、全フィールドをクリア
            // ==========================================================
            if (!$this->recordExists && $validator->errors()->any()) {
                $this->merge([
                    'clock_in'    => null,
                    'clock_out'   => null,
                    'break_start' => array_fill(0, count($starts), null),
                    'break_end'   => array_fill(0, count($ends), null),
                    'remarks'     => null,
                ]);
            }
        });
    }
}


