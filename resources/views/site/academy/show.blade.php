@extends('layouts.platform')
@section('title', $course->title.' — أكاديمية أثر')
@section('description', Str::limit($course->summary, 155))
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! $structuredJson !!}</script>
@endpush
@section('content')
@php($program = $course->programs->first())
@php($nextLesson = $progress['next'] ?? $course->lessons->first())
<section class="page-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['الأكاديمية', route('academy.index')], [$course->title, null]]" />
    <x-athar.kicker>{{ $program?->title ?? 'الأكاديمية' }} · <x-athar.level :level="$course->level" /> · {{ $course->duration_minutes }} دقيقة</x-athar.kicker>
    <h1>{{ $course->title }}</h1>
    <p>{{ $course->summary }}</p>
    <p class="outcome" style="font-size:17px"><b>ما ستخرج به:</b> {{ $course->outcome }}</p>
    @if($progress)<div style="max-width:420px;margin-bottom:14px"><x-athar.progress :value="$progress['percent']" :done="$progress['done']" :total="$progress['total']" /></div>@endif
    @if($nextLesson)<div class="btn-row"><x-athar.button href="{{ route('academy.lesson', [$course, $nextLesson]) }}">{{ $progress && $progress['done'] > 0 ? 'أكمل: الدرس '.$nextLesson->position : 'ابدأ الدرس الأول' }}</x-athar.button></div>@endif
</div></section>

<section class="section"><div class="shell result-layout">
    <div>
        <div class="section-heading"><x-athar.kicker>قائمة الدروس</x-athar.kicker><h2>{{ $course->lessons->count() }} دروس بالشكل الكامل</h2></div>
        <ol class="lesson-list">
            @foreach($course->lessons as $lesson)
            @php($done = $progress && isset($progress['completed_ids'][$lesson->id]))
            @php($current = $progress && $progress['next']?->id === $lesson->id)
            <li class="{{ $done ? 'is-done' : '' }} {{ $current ? 'is-current' : '' }}">
                <span class="n">{{ $done ? '✓' : $lesson->position }}</span>
                <div><h3><a href="{{ route('academy.lesson', [$course, $lesson]) }}">{{ $lesson->title }}</a></h3><p>{{ $lesson->objective }}</p><p class="outcome small"><b>الأصل:</b> {{ $lesson->outcome_title }}</p></div>
                <span class="state">{{ $lesson->duration_minutes }} د · {{ $done ? 'مكتمل' : ($current ? 'جارٍ' : ($progress ? 'قادم' : 'متاح')) }}</span>
            </li>
            @endforeach
        </ol>

        <div class="section-heading" style="margin-top:40px"><x-athar.kicker>القوالب</x-athar.kicker><h2>قوالب المسار</h2></div>
        <div class="grid grid-2">@foreach($templates as $template)<x-athar.card heading="h3" :title="$template->name" :desc="$template->description" tag="Markdown"><x-slot:meta><a class="btn btn-secondary" href="{{ route('resources.download', $template) }}">تنزيل</a>@if($viewer->isMember())<span class="muted small">الحفظ من داخل الدرس</span>@else<span class="muted small">الحفظ في مشروعي بحساب</span>@endif</x-slot:meta></x-athar.card>@endforeach</div>
    </div>
    <aside class="side stack">
        <div class="card"><x-athar.kicker>المتطلبات والتالي</x-athar.kicker>
            <p class="small"><b>قبل هذا المسار:</b> @if($course->prerequisite)@if($course->prerequisite->is_published)<a href="{{ route('academy.show', $course->prerequisite) }}">{{ $course->prerequisite->title }}</a>@else{{ $course->prerequisite->title }} (قريباً)@endif @else لا شيء: هذا المسار هو البداية.@endif</p>
            <p class="small" style="margin:0"><b>بعده:</b> @if($course->next)@if($course->next->is_published)<a href="{{ route('academy.show', $course->next) }}">{{ $course->next->title }}</a>@else{{ $course->next->title }} (قريباً)@endif @else —@endif</p>
        </div>
        <div class="card"><x-athar.kicker>من المجلة</x-athar.kicker><ul class="list">@foreach($articles as $article)<li><a href="{{ route('articles.show', $article) }}">{{ $article->title }}</a></li>@endforeach</ul></div>
    </aside>
</div></section>
@endsection
