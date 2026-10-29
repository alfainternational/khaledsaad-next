@extends('layouts.platform')
@section('title', $term->term.' — ما معناه؟ — قاموس أثر')
@section('description', Str::limit($term->definition, 155))
@section('canonical', route('glossary.show', $term))
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! $structuredJson !!}</script>
@endpush
@section('content')
<article class="term-page">
<header class="article-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['المجلة', route('articles.index')], ['القاموس', route('glossary.index')], [$term->term, null]]" />
    <x-athar.kicker>مصطلح</x-athar.kicker>
    <h1>{{ $term->term }}</h1>
    <p class="lead definition" id="definition">{{ $term->definition }}</p>
</div></header>
<div class="shell article-layout">
    <div class="article-main">
        @if($term->example)<div class="example"><x-athar.kicker>مثال</x-athar.kicker><p style="margin:0">{{ $term->example }}</p></div>@endif
        <div class="article-body prose">{!! $bodyHtml !!}</div>
        <section class="apply-read" id="apply"><x-athar.kicker>طبّق المصطلح</x-athar.kicker><h2>أين يتحول هذا إلى عمل؟</h2>
            <div class="grid grid-2">
                @if($lesson)<x-athar.card heading="h3" tag="الدرس" :title="$lesson->title" :desc="$lesson->goal" :href="route('academy.lesson', [$lesson->course, $lesson])" />@endif
                @if($tool)<x-athar.card heading="h3" tag="الأداة" :title="$tool->name" :desc="$tool->description" :href="$toolUrl" />@endif
                @if(! $lesson && ! $tool)<x-athar.card heading="h3" tag="المختبر" title="افتح المختبر" desc="اختر الأداة التي تطبّق هذا المصطلح على مشروعك." :href="route('tools.index')" />@endif
            </div>
        </section>
        @if($items->isNotEmpty())
        <section class="related-items" id="related"><h2>مواد مرتبطة</h2>
            <ul class="result-list">@foreach($items as $item)<li><a href="{{ route('articles.show', $item) }}"><span class="small muted">{{ $item->typeLabel() }}</span><strong>{{ $item->title }}</strong><span class="small muted">{{ $item->authorName() }} · {{ $item->reading_minutes }} دقائق</span></a></li>@endforeach</ul>
        </section>
        @endif
    </div>
    <aside class="article-side">
        @if($others->isNotEmpty())<div class="side-box"><h3>مصطلحات قريبة</h3><ul class="plain-list">@foreach($others as $o)<li><a href="{{ route('glossary.show', $o) }}">{{ $o->term }}</a></li>@endforeach</ul></div>@endif
        <div class="side-box"><h3>القاموس</h3><p class="small muted">تعريف مباشر، مثال، وتطبيق.</p><a class="btn btn-secondary btn-block" href="{{ route('glossary.index') }}">كل المصطلحات</a></div>
    </aside>
</div>
</article>
@endsection
