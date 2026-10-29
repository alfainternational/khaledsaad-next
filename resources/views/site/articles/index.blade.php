@extends('layouts.platform')
@section('title', ($section ? $meta['label'] : 'المجلة').($items->currentPage() > 1 ? ' — صفحة '.$items->currentPage() : '').' — أثر')
@section('description', $meta['intro'])
@section('canonical', $section ? route($section.'.index') : route('articles.index'))
@if($hasFilters || $items->currentPage() > 1)@section('noindex', '1')@endif
@section('content')
<section class="page-hero compact"><div class="shell">
    <x-athar.breadcrumbs :items="$section ? [['المجلة', route('articles.index')], [$meta['label'], null]] : [['المجلة', null]]" />
    <x-athar.kicker>{{ $meta['kicker'] }}</x-athar.kicker>
    <h1>{{ $section ? $meta['label'] : 'تسويق يمكن تطبيقه' }}</h1>
    <p>{{ $meta['intro'] }}</p>
    <nav class="filters" aria-label="أقسام المجلة">
        <a href="{{ route('articles.index') }}" @if(! $section) aria-current="page" @endif>كل المواد</a>
        @foreach($sections as $key => $s)<a href="{{ route($key.'.index') }}" @if($section === $key) aria-current="page" @endif>{{ $s['label'] }}</a>@endforeach
        <a href="{{ route('glossary.index') }}">القاموس</a>
    </nav>
</div></section>
<section class="section-tight"><div class="shell">
    <form class="filters-form" method="get" action="{{ $section ? route($section.'.index') : route('articles.index') }}" id="magazine-filters">
        @if($types !== [])<div class="field"><label for="f-type">النوع</label><select class="input" id="f-type" name="type"><option value="">الكل</option>@foreach($types as $k => $label)<option value="{{ $k }}" @selected($filters['type'] === $k)>{{ $label }}</option>@endforeach</select></div>@endif
        <div class="field"><label for="f-topic">الموضوع</label><select class="input" id="f-topic" name="topic"><option value="">الكل</option>@foreach($categories as $c)<option value="{{ $c->slug }}" @selected($filters['topic'] === $c->slug)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="field"><label for="f-author">المؤلف</label><select class="input" id="f-author" name="author"><option value="">الكل</option>@foreach($authors as $a)<option value="{{ $a->slug }}" @selected($filters['author'] === $a->slug)>{{ $a->name }}</option>@endforeach</select></div>
        <button class="btn btn-secondary" type="submit">تصفية</button>
        @if($hasFilters)<a class="btn btn-text" href="{{ $section ? route($section.'.index') : route('articles.index') }}">إلغاء التصفية</a>@endif
    </form>
    <p class="muted small" style="margin:12px 0 0">{{ $items->total() }} مادة{{ $hasFilters ? ' مطابقة' : '' }}</p>
</div></section>
<section class="section-tight"><div class="shell">
    <div class="grid grid-3 magazine-grid">
    @forelse($items as $item)
        <article class="card content-card" data-section="{{ $item->section }}">
            <div class="row between"><span class="card-tag">{{ $item->typeLabel() }}</span>@if($item->category)<a class="small muted" href="{{ route('articles.index', ['topic' => $item->category->slug]) }}">{{ $item->category->name }}</a>@endif</div>
            <h2 class="card-title"><a href="{{ route('articles.show', $item) }}">{{ $item->title }}</a></h2>
            <p class="card-desc">{{ $item->tldr ?: $item->excerpt }}</p>
            <div class="card-meta"><span>{{ $item->authorName() }}</span><time datetime="{{ $item->published_at->toDateString() }}">{{ $item->published_at->format('Y/m/d') }}</time><span>{{ $item->reading_minutes }} دقائق</span></div>
        </article>
    @empty
        <div class="card" style="grid-column:1/-1"><h2 style="font-size:20px">لا مواد مطابقة بعد</h2><p class="muted">جرّب تصفية أوسع، أو ابدأ من <a href="{{ route('glossary.index') }}">القاموس</a> أو <a href="{{ route('tools.index') }}">المختبر</a>.</p></div>
    @endforelse
    </div>
    <div class="pagination">{{ $items->links() }}</div>
</div></section>
@endsection
