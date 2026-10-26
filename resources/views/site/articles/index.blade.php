@extends('layouts.platform')
@section('title', isset($insights) ? 'الرؤى العالمية — أثر' : 'مجلة أثر')
@section('description', 'مقالات وأدلة وتحليلات عملية في التسويق الرقمي والذكاء الاصطناعي.')
@section('content')
<section class="page-hero"><div class="shell"><span class="kicker">{{ isset($insights) ? 'من العالم إلى سياقك' : 'المجلة' }}</span><h1>{{ isset($insights) ? 'الرؤى العالمية' : 'تسويق يمكن تطبيقه' }}</h1><p>{{ isset($insights) ? 'تلخيص وتحليل لمصادر موثوقة، بمراجعة بشرية وإضافة عملية للسوق العربي.' : 'أدلة، تحليلات ودراسات حالة تربط الفكرة بالقرار والأداة.' }}</p></div></section>
<section class="section"><div class="shell"><div class="cards three">@forelse($articles as $article)<article class="content-card"><span class="tag">{{ $article->eyebrow }}</span><h2><a href="{{ route('articles.show',$article) }}">{{ $article->title }}</a></h2><p>{{ $article->excerpt }}</p><div class="card-meta"><span>{{ $article->topic }}</span><span>{{ $article->reading_minutes }} دقائق</span></div></article>@empty<p>لا توجد مواد منشورة بعد.</p>@endforelse</div><div class="pagination">{{ $articles->links() }}</div></div></section>
@endsection
