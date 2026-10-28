@props(['value' => 0, 'label' => null, 'done' => null, 'total' => null, 'unit' => 'دروس'])
@php($value = max(0, min(100, (int) round($value))))
<div {{ $attributes->merge(['class' => 'progress']) }} role="progressbar" aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $label ?? 'التقدم' }}">
    <div class="progress-track"><div class="progress-bar {{ $value >= 100 ? 'is-done' : '' }}" style="width:{{ $value }}%"></div></div>
    <span>{{ $value }}٪ @if($total !== null) · {{ $done }} من {{ $total }} {{ $unit }} @elseif($label) · {{ $label }} @endif</span>
</div>
