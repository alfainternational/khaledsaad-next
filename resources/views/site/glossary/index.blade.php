@extends('layouts.platform')
@section('title', 'قاموس التسويق — أثر')
@section('description', 'قاموس عربي لمصطلحات التسويق الرقمي: تعريف مباشر في أول سطر، مثال محلي، والدرس أو الأداة التي تطبّق المصطلح.')
@section('canonical', route('glossary.index'))
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'DefinedTermSet', 'name' => 'قاموس التسويق — أثر', 'url' => route('glossary.index'), 'inLanguage' => 'ar', 'hasDefinedTerm' => $terms->map(fn ($t) => ['@type' => 'DefinedTerm', 'name' => $t->term, 'url' => route('glossary.show', $t)])->values()->all()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush
@section('content')
<section class="page-hero compact"><div class="shell">
    <x-athar.breadcrumbs :items="[['المجلة', route('articles.index')], ['القاموس', null]]" />
    <x-athar.kicker>قاموس التسويق</x-athar.kicker>
    <h1>{{ $terms->count() }} مصطلحاً بتعريف مباشر ومثال</h1>
    <p>كل مصطلح يبدأ بتعريف من سطر واحد، ثم مثال من السوق العربي، ثم الدرس أو الأداة التي تطبّقه.</p>
    <form class="search-form" action="{{ route('search') }}" role="search"><label class="sr-only" for="glossary-q">ابحث عن مصطلح</label><input class="input" id="glossary-q" name="q" placeholder="ابحث عن مصطلح أو مادة…"><button class="btn btn-primary" type="submit">بحث</button></form>
</div></section>
<section class="section-tight"><div class="shell">
    <nav class="filters letter-nav" aria-label="الحروف">@foreach($groups->keys() as $k)<a href="#letter-{{ $loop->index }}">{{ $k }}</a>@endforeach</nav>
    @foreach($groups as $k => $group)
    <div class="letter-group" id="letter-{{ $loop->index }}"><h2 class="letter">{{ $k }}</h2>
        <div class="grid grid-3">@foreach($group as $term)<a class="card is-link term-card" href="{{ route('glossary.show', $term) }}"><h3>{{ $term->term }}</h3><p class="card-desc">{{ $term->definition }}</p><div class="card-meta">@if($term->related_tool_id)<span>أداة مرتبطة</span>@endif @if($term->related_lesson_id)<span>درس مرتبط</span>@endif</div></a>@endforeach</div>
    </div>
    @endforeach
    @if($terms->isEmpty())<p class="muted">لا مصطلحات منشورة بعد.</p>@endif
</div></section>
@endsection
