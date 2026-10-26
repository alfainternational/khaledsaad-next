@extends('layouts.platform')
@section('title','نتائج البحث — أثر')
@section('noindex','1')
@section('content')
<section class="page-hero compact"><div class="shell"><span class="kicker">البحث</span><h1>نتائج البحث</h1><form class="search-form" action="{{ route('search') }}"><label class="sr-only" for="q">ابحث</label><input id="q" name="q" value="{{ $term }}" placeholder="ابحث في المقالات والأدلة"><button class="button">بحث</button></form></div></section>
<section class="section"><div class="shell narrow">@forelse($results as $result)<a class="search-result" href="{{ route('articles.show',$result) }}"><span>{{ $result->eyebrow }}</span><h2>{{ $result->title }}</h2><p>{{ $result->excerpt }}</p></a>@empty<p class="empty">لا توجد نتائج مطابقة. جرّب كلمة أوسع.</p>@endforelse</div></section>
@endsection
