@extends('layouts.platform')
@section('title', $author->name.' — '.$author->role.' — أثر')
@section('description', Str::limit($author->bio, 155))
@section('canonical', route('authors.show', $author))
@if($items->currentPage() > 1)@section('noindex', '1')@endif
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! $structuredJson !!}</script>
@endpush
@section('content')
<section class="page-hero compact"><div class="shell">
    <x-athar.breadcrumbs :items="[['المجلة', route('articles.index')], [$author->name, null]]" />
    <div class="author-hero">
        <div class="avatar" aria-hidden="true">{{ mb_substr($author->name, 0, 1) }}</div>
        <div>
            <x-athar.kicker>{{ $author->role }}</x-athar.kicker>
            <h1>{{ $author->name }}</h1>
            <p class="bio">{{ $author->bio }}</p>
            @if($author->safeLinks() !== [])<div class="row" style="gap:8px">@foreach($author->safeLinks() as $link)<a class="chip" href="{{ $link['url'] }}" rel="me noopener" target="_blank">{{ $link['label'] ?? parse_url($link['url'], PHP_URL_HOST) }}</a>@endforeach</div>@endif
            <dl class="author-facts"><div><dt>المواد المنشورة</dt><dd>{{ $items->total() }}</dd></div>@foreach($sections as $key => $n)<div><dt>{{ \App\Domain\Content\Models\ContentItem::SECTIONS[$key]['label'] ?? $key }}</dt><dd>{{ $n }}</dd></div>@endforeach</dl>
        </div>
    </div>
</div></section>
<section class="section-tight"><div class="shell">
    <div class="section-heading"><h2 style="font-size:24px">مواد {{ $author->name }}</h2><p>الأحدث أولاً. كل مادة تحمل مراجعاً وتاريخ آخر مراجعة.</p></div>
    <div class="grid grid-3">
    @forelse($items as $item)<x-athar.card heading="h3" :tag="$item->typeLabel()" :title="$item->title" :desc="Str::limit($item->tldr ?: $item->excerpt, 160)" :href="route('articles.show', $item)"><x-slot:meta><span>{{ $item->published_at->format('Y/m/d') }}</span><span>{{ $item->reading_minutes }} دقائق</span></x-slot:meta></x-athar.card>
    @empty<div class="card" style="grid-column:1/-1"><p class="muted" style="margin:0">لا مواد منشورة بعد.</p></div>@endforelse
    </div>
    <div class="pagination">{{ $items->links() }}</div>
</div></section>
@endsection
