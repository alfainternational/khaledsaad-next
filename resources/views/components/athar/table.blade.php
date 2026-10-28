{{-- الجدول القابل للتمرير على الجوال (§1.4 رقم 12) --}}
@props(['caption' => null])
<div {{ $attributes->merge(['class' => 'table-wrap']) }}><table class="table">@if($caption)<caption class="sr-only">{{ $caption }}</caption>@endif{{ $slot }}</table></div>
