@props([
    'name',
    'id' => null,
    'value' => '08:00',
    'required' => false,
    'minuteStep' => 1,
])

@php
    $fieldId = $id ?? $name;
    $raw = trim((string) old($name, $value ?? ''));
    $hasValue = $raw !== '' && preg_match('/^(\d{1,2}):(\d{2})/', $raw, $m) === 1;
    $step = max(1, min(30, (int) $minuteStep));

    if ($hasValue) {
        $hour = max(0, min(23, (int) $m[1]));
        $minute = max(0, min(59, (int) $m[2]));
        $minute = (int) (round($minute / $step) * $step);
        if ($minute >= 60) {
            $minute = 60 - $step;
        }
        $hourStr = sprintf('%02d', $hour);
        $minuteStr = sprintf('%02d', $minute);
        $hiddenValue = $hourStr.':'.$minuteStr;
    } else {
        $hourStr = '';
        $minuteStr = '';
        $hiddenValue = $required ? '08:00' : '';
        if ($required) {
            $hourStr = '08';
            $minuteStr = '00';
            $hiddenValue = '08:00';
        }
    }
@endphp

<div
    {{ $attributes->class(['time-24-picker']) }}
    data-time-24-picker
    @if (! $required) data-time-24-optional @endif
>
    <input
        type="hidden"
        name="{{ $name }}"
        id="{{ $fieldId }}"
        value="{{ $hiddenValue }}"
        @required($required)
        data-time-24-value
    >

    <div class="time-24-picker__row">
        <label class="time-24-picker__field">
            <span class="time-24-picker__caption">Jam</span>
            <select class="form-input time-24-picker__select" data-time-24-hour aria-label="Jam (0–23)" @required($required)>
                @unless ($required)
                    <option value="" @selected($hourStr === '')>—</option>
                @endunless
                @for ($h = 0; $h < 24; $h++)
                    @php $opt = sprintf('%02d', $h); @endphp
                    <option value="{{ $opt }}" @selected($hourStr === $opt)>{{ $opt }}</option>
                @endfor
            </select>
        </label>

        <span class="time-24-picker__sep" aria-hidden="true">:</span>

        <label class="time-24-picker__field">
            <span class="time-24-picker__caption">Menit</span>
            <select class="form-input time-24-picker__select" data-time-24-minute aria-label="Menit" @required($required)>
                @unless ($required)
                    <option value="" @selected($minuteStr === '')>—</option>
                @endunless
                @for ($m = 0; $m < 60; $m += $step)
                    @php $opt = sprintf('%02d', $m); @endphp
                    <option value="{{ $opt }}" @selected($minuteStr === $opt)>{{ $opt }}</option>
                @endfor
            </select>
        </label>
    </div>
</div>
