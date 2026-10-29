@extends('layouts.platform')
@section('title', $course->topic.' — مواد ومسار وأداة — أثر')
@section('description', Str::limit($course->summary ?: $course->outcome, 155))
@section('canonical', route('topics.show', $course->slug))
@if($items->currentPage() > 1)@section('noindex', '1')@endif
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! $structuredJson !!}</script>
@endpush
@section('content')
<section class="page-hero compact"><div class="shell">
    <x-athar.breadcrumbs :items="[['المجلة', route('articles.index')], ['موضوع: '.$course->topic, null]]" />
    <x-athar.kicker>موضوع</x-athar.kicker>
    <h1>{{ $course->topic }}</h1>
    <p class="definition">{{ $course->summary ?: $course->outcome }}</p>
    <div class="row" style="gap:10px;margin-top:8px">
        @if($course->is_published)<x-athar.button href="{{ route('academy.show', $course) }}">ابدأ المسار: {{ $course->title }}</x-athar.button>@else<span class="btn btn-secondary" aria-disabled="true">المسار «{{ $course->title }}» قريباً</span>@endif
        @if($tool)<x-athar.button variant="secondary" href="{{ $toolUrl }}">جرّب أداة «{{ $tool->name }}»</x-athar.button>@endif
    </div>
</div></section>
<section class="section-tight"><div class="shell topic-layout">
    <div>
        <div class="section-heading"><h2 style="font-size:24px">المواد في هذا الموضوع</h2><p>{{ $items->total() }} مادة منشورة</p></div>
        <div class="grid grid-2">
        @forelse($items as $item)<x-athar.card heading="h3" :tag="$item->typeLabel()" :title="$item->title" :desc="Str::limit($item->tldr ?: $item->excerpt, 160)" :href="route('articles.show', $item)"><x-slot:meta><span>{{ $item->authorName() }}</span><span>{{ $item->published_at->format('Y/m/d') }}</span></x-slot:meta></x-athar.card>
        @empty<div class="card" style="grid-column:1/-1"><p class="muted" style="margin:0">لا مواد في هذا الموضوع بعد. ابدأ من <a href="{{ route('articles.index') }}">كل المواد</a>.</p></div>@endforelse
        </div>
        <div class="pagination">{{ $items->links() }}</div>
    </div>
    <aside class="stack">
        <div class="side-box"><h3>المسار</h3><p class="small"><strong>{{ $course->title }}</strong><br><span class="muted">{{ $course->level }} · {{ $course->duration_minutes }} دقيقة</span></p><p class="small">{{ $course->outcome }}</p>@if($course->is_published)<a class="btn btn-secondary btn-block" href="{{ route('academy.show', $course) }}">افتح المسار</a>@endif</div>
        @if($tool)<div class="side-box"><h3>الأداة</h3><p class="small"><strong>{{ $tool->name }}</strong><br><span class="muted">{{ $tool->description }}</span></p><a class="btn btn-secondary btn-block" href="{{ $toolUrl }}">افتح الأداة</a></div>@endif
        @if($terms->isNotEmpty())<div class="side-box"><h3>مصطلحات</h3><ul class="plain-list">@foreach($terms as $t)<li><a href="{{ route('glossary.show', $t) }}">{{ $t->term }}</a></li>@endforeach</ul></div>@endif
        <div class="side-box"><h3>كل المواضيع</h3><ul class="plain-list">@foreach($topics as $t)<li><a href="{{ route('topics.show', $t->slug) }}" @if($t->slug === $course->slug) aria-current="page" @endif>{{ $t->topic }}</a></li>@endforeach</ul></div>
    </aside>
</div></section>
@endsection
