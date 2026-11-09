@extends('layouts.platform')
@section('title', $term->term.' — ما معناه؟ — قاموس أثر')
@section('description', Str::limit($term->definition, 155))
@section('canonical', route('glossary.show', $term))
@section('body_class', 'reading-surface')
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! $structuredJson !!}</script>
@endpush
@section('content')
<article class="term-page reading-page reading-page--reference reading-page--focused">
<header class="article-hero reading-hero"><div class="reading-frame">
    <x-athar.breadcrumbs :items="[['المجلة', route('articles.index')], ['القاموس', route('glossary.index')], [$term->term, null]]" />
    <x-athar.kicker>مصطلح</x-athar.kicker>
    <h1>{{ $term->term }}</h1>
    <p class="lead definition" id="definition">{{ $term->definition }}</p>
</div></header>
<div class="reading-frame article-layout reading-layout reading-layout--focused">
    <div class="article-main">
        @if($term->example)<div class="example"><x-athar.kicker>مثال</x-athar.kicker><p style="margin:0">{{ $term->example }}</p></div>@endif
        <div class="article-body prose">{!! $bodyHtml !!}</div>
        <section class="apply-read reading-application" id="apply"><div><x-athar.kicker>طبّق المصطلح</x-athar.kicker><h2>{{ $tool ? $tool->name : ($lesson ? $lesson->title : 'أين يتحول هذا إلى عمل؟') }}</h2><p>{{ $tool ? $tool->description : ($lesson ? $lesson->goal : 'اختر الأداة التي تساعدك على تطبيق هذا المصطلح في مشروعك.') }}</p></div>
            <div class="reading-actions"><a class="btn btn-primary reading-action-primary" href="{{ $tool ? $toolUrl : ($lesson ? route('academy.lesson', [$lesson->course, $lesson]) : route('tools.index')) }}">{{ $tool ? 'افتح الأداة وطبّق' : ($lesson ? 'ابدأ الدرس' : 'استكشف المختبر') }} ←</a>
            @if($tool && $lesson)<a class="reading-secondary" href="{{ route('academy.lesson', [$lesson->course, $lesson]) }}">تعلّم الخطوات: {{ $lesson->title }}</a>@endif</div>
        </section>
        @if($items->isNotEmpty())
        <section class="related-items" id="related"><h2>مواد مرتبطة</h2>
            <ul class="result-list">@foreach($items as $item)<li><a href="{{ route('articles.show', $item) }}"><span class="small muted">{{ $item->typeLabel() }}</span><strong>{{ $item->title }}</strong><span class="small muted">{{ $item->authorName() }} · {{ $item->reading_minutes }} دقائق</span></a></li>@endforeach</ul>
        </section>
        @endif
        <section class="reading-reference-next"><h2>{{ $others->isNotEmpty() ? 'مصطلحات قريبة' : 'واصل التعلّم' }}</h2>@if($others->isNotEmpty())<div class="reading-tags">@foreach($others as $o)<a href="{{ route('glossary.show', $o) }}">{{ $o->term }}</a>@endforeach</div>@endif<a class="reading-secondary" href="{{ route('glossary.index') }}">ارجع إلى القاموس ←</a></section>
    </div>
</div>
</article>
@endsection
