@extends('layouts.platform')
@section('title', $article->title.' — أثر')
@section('description', Str::limit($article->tldr ?: $article->excerpt, 155))
@section('og_type', 'article')
@section('body_class', 'reading-surface')
@section('canonical', route('articles.show', $article))
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! $structuredJson !!}</script>
@if($faqJson)<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! $faqJson !!}</script>@endif
@endpush
@section('content')
<article class="magazine-article reading-page reading-page--{{ $readingIndexed ? 'indexed' : 'focused' }}" id="article" data-item="{{ $article->id }}">
<header class="article-hero reading-hero"><div class="reading-frame"><div class="reading-heading">
    <x-athar.breadcrumbs :items="[['المجلة', route('articles.index')], [$article->sectionLabel(), route($article->section.'.index')], [$article->title, null]]" />
    <div class="row" style="gap:8px"><span class="card-tag">{{ $article->typeLabel() }}</span>@if($article->category)<a class="small" href="{{ route('articles.index', ['topic' => $article->category->slug]) }}">{{ $article->category->name }}</a>@endif</div>
    <h1>{{ $article->title }}</h1>
    <p class="lead">{{ $article->excerpt }}</p>
    <div class="byline">
        <span>بقلم @if($article->author)<a href="{{ route('authors.show', $article->author) }}">{{ $article->authorName() }}</a>@else{{ $article->authorName() }}@endif</span>
        @if($article->reviewer_name)<span>راجعه {{ $article->reviewer_name }}</span>@endif
        <span>نُشر <time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->format('Y/m/d') }}</time></span>
        @if($article->last_reviewed_at && ! $article->last_reviewed_at->isSameDay($article->published_at))<span>آخر مراجعة <time datetime="{{ $article->last_reviewed_at->toDateString() }}">{{ $article->last_reviewed_at->format('Y/m/d') }}</time></span>@endif
        <span>{{ $readingMinutes }} {{ $readingMinutes === 1 ? 'دقيقة' : 'دقائق' }} قراءة</span>
    </div>
    @if($staleWarning)<div class="alert alert-info editor-note" role="status"><b>تنبيه للمحرر فقط:</b> أرقام هذه المادة تعتمد مصدراً من {{ $oldestSource?->format('Y/m') }} (أقدم من 12 شهراً). <a href="{{ route('admin.content.edit', $article) }}">حدّث المصادر</a>.</div>@endif
</div></div></header>

<div class="reading-frame article-layout reading-layout reading-layout--{{ $readingIndexed ? 'indexed' : 'focused' }}">
    <div class="article-main">
        <x-athar.flash />
        @if($article->tldr)<section class="reading-summary" id="tldr" aria-label="خلاصة المادة"><x-athar.kicker>في دقيقة</x-athar.kicker><p>{{ $article->tldr }}</p></section>@endif
        @if($readingIndexed)<details class="toc-mobile-box"><summary>في هذه المادة</summary><ol>@foreach($headings as $id => $text)<li><a href="#{{ $id }}">{{ $text }}</a></li>@endforeach</ol></details>@endif
        <div class="article-body prose">{!! $bodyHtml !!}</div>

        <div class="reading-trust">
        <section class="article-sources" id="sources" aria-labelledby="sources-h"><x-athar.kicker>مراجع القراءة</x-athar.kicker><h2 id="sources-h">المصادر</h2>
            @if($article->sources->isNotEmpty())
            <ol class="sources-list">@foreach($article->sources as $source)<li><a href="{{ $source->url }}" rel="nofollow noopener" target="_blank"><strong>{{ $source->title }}</strong></a><span class="muted small">{{ $source->publisher }}@if($source->published_on) · {{ $source->published_on->format('Y/m/d') }}@endif · {{ strtoupper($source->language) }}</span></li>@endforeach</ol>
            @if($article->requiresSources())<p class="muted small">المواد العالمية تخضع لمراجعة بشرية وإضافة سياق عربي، ولا تمثل نسخاً كاملاً من المصدر. الأرقام منسوبة إلى مصدرها الأولي.</p>@endif
            @else
            <p class="muted small" style="margin:0">مادة أصيلة من خبرة الكاتب وممارسته؛ لا تستند إلى أرقام خارجية، وأي رقم فيها مثال توضيحي لا إحصاء.</p>
            @endif
        </section>
        <section class="reading-author" aria-label="عن الكاتب"><span class="reading-author-mark" aria-hidden="true">{{ mb_substr($article->authorName(), 0, 1) }}</span><div><span class="small muted">عن الكاتب</span><h2>@if($article->author)<a href="{{ route('authors.show', $article->author) }}">{{ $article->authorName() }}</a>@else{{ $article->authorName() }}@endif</h2>@if($article->author)<p>{{ $article->author->role }}</p>@if($article->author->bio)<p class="reading-author-bio">{{ Str::limit($article->author->bio, 150) }}</p>@endif<a class="reading-secondary" href="{{ route('authors.show', $article->author) }}">المزيد عن الكاتب ←</a>@endif</div></section>
        @if($article->tags->isNotEmpty())<div class="reading-tags" aria-label="موضوعات المادة">@foreach($article->tags as $tag)<a href="{{ route('tags.show', $tag) }}">{{ $tag->name }}</a>@endforeach</div>@endif
        </div>

        @if($faq !== [])
        <section class="article-faq" id="faq"><h2>أسئلة شائعة</h2>@foreach($faq as $f)<details class="faq-item"><summary>{{ $f['q'] }}</summary><p>{{ $f['a'] }}</p></details>@endforeach</section>
        @endif

        <section class="apply-read reading-application" id="apply"><div><x-athar.kicker>طبّق ما قرأت</x-athar.kicker><span class="small muted">{{ $tool ? 'الأداة المرتبطة' : ($lesson ? 'الدرس المرتبط' : 'خطوتك التالية') }}</span>
            <h2>{{ $tool ? $tool->name : ($lesson ? $lesson->title : 'حدد خطوتك التالية') }}</h2>
            <p>{{ $tool ? $tool->description : ($lesson ? $lesson->goal : 'حوّل الفكرة إلى قرار يناسب مشروعك، ثم ارجع بما تعلمته من التطبيق.') }}</p></div>
            <div class="reading-actions">
                <a class="btn btn-primary reading-action-primary" href="{{ $tool ? $toolUrl : ($lesson ? route('academy.lesson', [$lesson->course, $lesson]) : route('assess.start')) }}">{{ $tool ? 'افتح الأداة وطبّق' : ($lesson ? 'ابدأ الدرس' : 'شخّص مشروعك') }} ←</a>
                @if($tool && $lesson)<a class="reading-secondary" href="{{ route('academy.lesson', [$lesson->course, $lesson]) }}">الدرس المرتبط: {{ $lesson->title }}</a>
                @elseif($topicCourse)<a class="reading-secondary" href="{{ $topicCourse->is_published ? route('academy.show', $topicCourse) : route('topics.show', $topicCourse->slug) }}">استكشف مسار {{ $topicCourse->title }}</a>
                @else<a class="reading-secondary" href="{{ route('tools.index') }}">استكشف أدوات المختبر</a>@endif
            </div>
        </section>

        <nav class="prev-next" aria-label="التنقل بين المواد">
            @if($previous)<a class="prev" href="{{ route('articles.show', $previous) }}"><span class="small muted">السابق</span><strong>{{ $previous->title }}</strong></a>@else<span></span>@endif
            @if($next)<a class="next" href="{{ route('articles.show', $next) }}"><span class="small muted">التالي</span><strong>{{ $next->title }}</strong></a>@endif
        </nav>

        <details class="report-box" id="report"><summary>بلّغ عن مشكلة في هذه المادة</summary>
            <form method="post" action="{{ route('articles.report', $article) }}" class="stack">@csrf
                <div class="field"><label for="report-reason">ما المشكلة؟</label><select class="input" id="report-reason" name="reason" required>@foreach($reportReasons as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
                <div class="field"><label for="report-note">تفاصيل (اختياري)</label><textarea class="input" id="report-note" name="note" maxlength="800" rows="3"></textarea></div>
                <div><button class="btn btn-secondary" type="submit">أرسل البلاغ</button></div>
            </form>
        </details>
    </div>

    @if($readingIndexed)<aside class="article-side reading-index"><nav class="toc" aria-label="فهرس المادة"><p class="small muted">في هذه المادة</p><ol>@foreach($headings as $id => $text)<li><a href="#{{ $id }}" data-toc="{{ $id }}"><b>{{ $loop->iteration }}</b><span>{{ $text }}</span></a></li>@endforeach</ol></nav><a class="reading-index-end" href="#apply">انتقل إلى التطبيق ←</a></aside>@endif
</div>

@if($related->isNotEmpty())
<section class="reading-related" id="related"><div class="reading-frame">
    <div class="section-heading"><x-athar.kicker>واصل الاستكشاف</x-athar.kicker><h2>مواد مرتبطة</h2></div>
    <ol class="reading-related-list">@foreach($related as $r)<li><a href="{{ route('articles.show', $r) }}"><span class="reading-related-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><span class="small muted">{{ $r->typeLabel() }} · {{ $r->authorName() }}</span><h3>{{ $r->title }}</h3><p>{{ Str::limit($r->excerpt, 125) }}</p></div><span aria-hidden="true">←</span></a></li>@endforeach</ol>
</div></section>
@endif
</article>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">
(function(){
  var links=document.querySelectorAll('[data-toc]');if(!links.length||!('IntersectionObserver' in window))return;
  var io=new IntersectionObserver(function(entries){entries.forEach(function(en){if(en.isIntersecting){var id=en.target.id;links.forEach(function(a){a.classList.toggle('is-active',a.dataset.toc===id)})}})},{rootMargin:'-15% 0px -70% 0px',threshold:0});
  links.forEach(function(a){var t=document.getElementById(a.dataset.toc);if(t)io.observe(t)});
})();
</script>
@endpush
