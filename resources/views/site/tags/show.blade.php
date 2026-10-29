@extends('layouts.platform')
@section('title', 'وسم: '.$tag->name.' — أثر')
@section('description', 'المواد الموسومة بـ«'.$tag->name.'» في مجلة أثر.')
@section('canonical', route('tags.show', $tag))
@if($noindex || $items->currentPage() > 1)@section('noindex', '1')@endif
@section('content')
<section class="page-hero compact"><div class="shell">
    <x-athar.breadcrumbs :items="[['المجلة', route('articles.index')], ['وسم: '.$tag->name, null]]" />
    <x-athar.kicker>وسم</x-athar.kicker>
    <h1>{{ $tag->name }}</h1>
    <p>{{ $items->total() }} مادة منشورة بهذا الوسم.</p>
</div></section>
<section class="section-tight"><div class="shell">
    <div class="grid grid-3">
    @forelse($items as $item)<x-athar.card heading="h2" :tag="$item->typeLabel()" :title="$item->title" :desc="Str::limit($item->tldr ?: $item->excerpt, 160)" :href="route('articles.show', $item)"><x-slot:meta><span>{{ $item->authorName() }}</span><span>{{ $item->published_at->format('Y/m/d') }}</span></x-slot:meta></x-athar.card>
    @empty<div class="card" style="grid-column:1/-1"><p class="muted" style="margin:0">لا مواد بهذا الوسم بعد.</p></div>@endforelse
    </div>
    <div class="pagination">{{ $items->links() }}</div>
</div></section>
@endsection
