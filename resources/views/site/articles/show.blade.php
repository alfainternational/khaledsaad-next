@extends('layouts.platform')
@section('title', $article->title.' — أثر')
@section('description', $article->excerpt)
@section('og_type', 'article')
@section('canonical', route('articles.show',$article))
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! $structuredJson !!}</script>
@endpush
@section('content')
<article><header class="article-hero"><div class="shell article-shell"><div class="breadcrumbs"><a href="{{ route('articles.index') }}">المجلة</a><span>/</span><span>{{ $article->topic }}</span></div><span class="kicker">{{ $article->eyebrow }}</span><h1>{{ $article->title }}</h1><p class="lead">{{ $article->excerpt }}</p><div class="byline"><span>بقلم {{ $article->author_name }}</span>@if($article->reviewer_name)<span>راجعه {{ $article->reviewer_name }}</span>@endif<span>آخر تحديث {{ $article->updated_at->format('Y/m/d') }}</span><span>{{ $article->reading_minutes }} دقائق قراءة</span></div></div></header><div class="shell article-layout"><div class="article-body">@foreach(preg_split('/\R\R+/', $article->body) as $paragraph)<p>{{ $paragraph }}</p>@endforeach<h2>ماذا تفعل الآن؟</h2><p>حوّل الفكرة إلى تجربة صغيرة لها هدف ومؤشر قرار، ثم احفظ النتيجة داخل مشروعك لتعود إليها.</p><a class="button" href="{{ route('tools.index') }}">اختر أداة مرتبطة</a></div><aside class="source-panel"><span class="kicker">الشفافية</span><h2>المصادر</h2>@foreach($article->sources as $source)<a href="{{ $source->url }}" rel="nofollow noopener" target="_blank"><strong>{{ $source->title }}</strong><span>{{ $source->publisher }} · {{ $source->language }}</span></a>@endforeach<p>تخضع المواد المترجمة أو الملخصة لمراجعة بشرية وإضافة سياق عربي، ولا تمثل نسخاً كاملاً من المصدر.</p></aside></div></article>
@endsection
