@extends('layouts.platform')
@section('title', 'الأكاديمية — البرنامجان و12 مساراً — أثر')
@section('description', 'برنامج أصحاب المشاريع وبرنامج المسوّقين: 12 مساراً تطبيقياً، كل درس بثمانية أجزاء ينتهي بأصل لمشروعك.')
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! json_encode(['@'.'context' => 'https://schema.org', '@type' => 'ItemList', 'name' => 'مسارات أكاديمية أثر', 'itemListElement' => $courses->where('is_published', true)->values()->map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c->title, 'url' => route('academy.show', $c)])->all()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush
@section('content')
<section class="page-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['الأكاديمية', null]]" />
    <x-athar.kicker>الأكاديمية</x-athar.kicker>
    <h1>مسارات تطبيقية عن مشروعك أنت</h1>
    <p>برنامجان و12 مساراً. كل درس بثمانية أجزاء يبدأ بقرارك وينتهي بأصل محفوظ وفعل يُقاس في الواقع.</p>
    @if(! $assessed)<div class="btn-row"><x-athar.button href="{{ route('assess.start') }}" id="discover-level">اكتشف مستواك: شخّص مشروعك</x-athar.button></div>@endif
</div></section>

<section class="section" id="programs"><div class="shell">
    <div class="section-heading"><x-athar.kicker>البرنامجان</x-athar.kicker><h2>اختر برنامجك</h2></div>
    <div class="tabs" role="tablist">@foreach($programs as $program)<a role="tab" href="#program-{{ $program->audience }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-tab="program-{{ $program->audience }}">{{ $program->title }}</a>@endforeach</div>
    @foreach($programs as $program)
    <div id="program-{{ $program->audience }}" class="program" data-panel @if(! $loop->first) hidden @endif>
        <p class="muted">{{ $program->summary }} <b>تخرج بـ:</b> {{ $program->outcome }}</p>
        <ol class="program-list">
            @foreach($program->courses as $course)
            <li id="program-{{ $program->audience }}-course-{{ $course->slug }}"><b>{{ $course->pivot->position }}</b><div>@if($course->is_published)<a href="{{ route('academy.show', $course) }}">{{ $course->title }}</a>@else<span>{{ $course->title }}</span>@endif<div class="small muted">تخرج بـ: {{ $course->outcome }}</div></div>@if($course->is_published)<x-athar.level :level="$course->level" />@else<span class="soon">قريباً</span>@endif</li>
            @endforeach
        </ol>
    </div>
    @endforeach
</div></section>

<section class="section section-soft"><div class="shell">
    <div class="section-heading"><x-athar.kicker>شبكة المسارات</x-athar.kicker><h2>المسارات الـ12</h2></div>
    <div class="grid grid-3" id="courses-grid">
        @foreach($courses as $course)
        <x-athar.card :title="$course->title" :desc="$course->summary" :tag="$course->level" :href="$course->is_published ? route('academy.show', $course) : null" :soon="! $course->is_published" :number="str_pad($course->position, 2, '0', STR_PAD_LEFT)" heading="h3">
            <p class="outcome"><b>ماذا ستخرج به:</b> {{ $course->outcome }}</p>
            @if(isset($progressByCourse[$course->id]))<x-athar.progress :value="$progressByCourse[$course->id]['percent']" :done="$progressByCourse[$course->id]['done']" :total="$progressByCourse[$course->id]['total']" />@endif
            <x-slot:meta><span>{{ $course->duration_minutes }} دقيقة</span><span>{{ $course->is_published ? $course->lessons_count.' دروس' : 'قريباً' }}</span></x-slot:meta>
        </x-athar.card>
        @endforeach
    </div>
</div></section>

<section class="section"><div class="shell">
    <div class="section-heading"><x-athar.kicker>كيف تُبنى الدروس</x-athar.kicker><h2>ثمانية أجزاء في كل درس</h2><p>يبدأ الدرس بقرارك قبل الشرح، ويُبنى على ملف مشروعك، وينتهي بفعل واقعي يُسجَّل بعد أسبوع.</p></div>
    <ol class="parts-strip">@foreach(array_slice($parts, 1, 8, true) as $key => $label)<li><b>{{ $loop->iteration }}</b>{{ $label }}</li>@endforeach</ol>
</div></section>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">(function(){var tabs=document.querySelectorAll('[data-tab]'),panels=document.querySelectorAll('[data-panel]');function show(id){tabs.forEach(function(t){t.setAttribute('aria-selected',String(t.dataset.tab===id))});panels.forEach(function(p){p.hidden=p.id!==id})}tabs.forEach(function(t){t.addEventListener('click',function(e){e.preventDefault();show(t.dataset.tab);history.replaceState(null,'','#'+t.dataset.tab)})});var h=location.hash.replace('#','');if(h&&document.getElementById(h)&&h.indexOf('program-')===0)show(h.split('-course-')[0]);})();</script>
@endpush
