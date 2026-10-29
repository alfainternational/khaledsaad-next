@extends('layouts.platform')
@section('title', $article->title.' — أثر')
@section('description', Str::limit($article->tldr ?: $article->excerpt, 155))
@section('og_type', 'article')
@section('canonical', route('articles.show', $article))
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! $structuredJson !!}</script>
@if($faqJson)<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! $faqJson !!}</script>@endif
@endpush
@section('content')
<article class="magazine-article" id="article" data-item="{{ $article->id }}">
<header class="article-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['المجلة', route('articles.index')], [$article->sectionLabel(), route($article->section.'.index')], [$article->title, null]]" />
    <div class="row" style="gap:8px"><span class="card-tag">{{ $article->typeLabel() }}</span>@if($article->category)<a class="small" href="{{ route('articles.index', ['topic' => $article->category->slug]) }}">{{ $article->category->name }}</a>@endif</div>
    <h1>{{ $article->title }}</h1>
    @if($article->tldr)<div class="tldr" id="tldr"><x-athar.kicker>في دقيقة</x-athar.kicker><p>{{ $article->tldr }}</p></div>@else<p class="lead">{{ $article->excerpt }}</p>@endif
    <div class="byline">
        <span>بقلم @if($article->author)<a href="{{ route('authors.show', $article->author) }}">{{ $article->authorName() }}</a>@else{{ $article->authorName() }}@endif</span>
        @if($article->reviewer_name)<span>راجعه {{ $article->reviewer_name }}</span>@endif
        <span>نُشر <time datetime="{{ $article->published_at->toDateString() }}">{{ $article->published_at->format('Y/m/d') }}</time></span>
        @if($article->last_reviewed_at && ! $article->last_reviewed_at->isSameDay($article->published_at))<span>آخر مراجعة <time datetime="{{ $article->last_reviewed_at->toDateString() }}">{{ $article->last_reviewed_at->format('Y/m/d') }}</time></span>@endif
        <span>{{ $article->reading_minutes }} دقائق قراءة</span>
    </div>
    @if($staleWarning)<div class="alert alert-info editor-note" role="status"><b>تنبيه للمحرر فقط:</b> أرقام هذه المادة تعتمد مصدراً من {{ $oldestSource?->format('Y/m') }} (أقدم من 12 شهراً). <a href="{{ route('admin.content.edit', $article) }}">حدّث المصادر</a>.</div>@endif
</div></header>

<div class="shell article-layout">
    <div class="article-main">
        <x-athar.flash />
        @if(count($headings) > 1)<details class="toc-mobile-box"><summary>في هذه المادة</summary><ol>@foreach($headings as $id => $text)<li><a href="#{{ $id }}">{{ $text }}</a></li>@endforeach</ol></details>@endif
        <div class="article-body prose">{!! $bodyHtml !!}</div>

        <section class="article-sources" id="sources" aria-labelledby="sources-h"><x-athar.kicker>الشفافية</x-athar.kicker><h2 id="sources-h">المصادر</h2>
            @if($article->sources->isNotEmpty())
            <ol class="sources-list">@foreach($article->sources as $source)<li><a href="{{ $source->url }}" rel="nofollow noopener" target="_blank"><strong>{{ $source->title }}</strong></a><span class="muted small">{{ $source->publisher }}@if($source->published_on) · {{ $source->published_on->format('Y/m/d') }}@endif · {{ strtoupper($source->language) }}</span></li>@endforeach</ol>
            @if($article->requiresSources())<p class="muted small">المواد العالمية تخضع لمراجعة بشرية وإضافة سياق عربي، ولا تمثل نسخاً كاملاً من المصدر. الأرقام منسوبة إلى مصدرها الأولي.</p>@endif
            @else
            <p class="muted small" style="margin:0">مادة أصيلة من خبرة الكاتب وممارسته؛ لا تستند إلى أرقام خارجية، وأي رقم فيها مثال توضيحي لا إحصاء.</p>
            @endif
        </section>

        @if($faq !== [])
        <section class="article-faq" id="faq"><h2>أسئلة شائعة</h2>@foreach($faq as $f)<details class="faq-item"><summary>{{ $f['q'] }}</summary><p>{{ $f['a'] }}</p></details>@endforeach</section>
        @endif

        <section class="apply-read" id="apply"><x-athar.kicker>طبّق ما قرأت</x-athar.kicker><h2>من القراءة إلى أصل لمشروعك</h2>
            <div class="grid grid-2">
                @if($lesson)<x-athar.card heading="h3" tag="الدرس المرتبط" :title="$lesson->title" :desc="$lesson->goal" :href="route('academy.lesson', [$lesson->course, $lesson])"><x-slot:meta><span>{{ $lesson->course->title }}</span><span>{{ $lesson->duration_minutes }} دقيقة</span></x-slot:meta></x-athar.card>
                @elseif($topicCourse)<x-athar.card heading="h3" tag="المسار المرتبط" :title="$topicCourse->title" :desc="$topicCourse->outcome" :href="$topicCourse->is_published ? route('academy.show', $topicCourse) : route('topics.show', $topicCourse->slug)"><x-slot:meta><span>{{ $topicCourse->level }}</span><span>{{ $topicCourse->duration_minutes }} دقيقة</span></x-slot:meta></x-athar.card>
                @else<x-athar.card heading="h3" tag="ابدأ من هنا" title="شخّص مشروعك" desc="عشر دقائق تعطيك خريطة أولويات ونقطة بداية في الأكاديمية." :href="route('assess.start')" />@endif
                @if($tool)<x-athar.card heading="h3" tag="الأداة المرتبطة" :title="$tool->name" :desc="$tool->description" :href="$toolUrl"><x-slot:meta><span>{{ $tool->kindLabel() }}</span><span>{{ $tool->kind === 'generator' ? 'توليد قاعدي مجاني' : 'مجانية' }}</span></x-slot:meta></x-athar.card>
                @else<x-athar.card heading="h3" tag="المختبر" title="اختر أداة من المختبر" desc="مولدات وحاسبات ومحاكٍ تحوّل الفكرة إلى أصل محفوظ." :href="route('tools.index')" />@endif
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

    <aside class="article-side">
        @if(count($headings) > 1)<nav class="toc" aria-label="فهرس المادة"><p class="small muted" style="margin:0 0 6px">في هذه المادة</p><ol>@foreach($headings as $id => $text)<li><a href="#{{ $id }}" data-toc="{{ $id }}"><b>{{ $loop->iteration }}</b><span>{{ $text }}</span></a></li>@endforeach</ol></nav>@endif
        @if($article->tags->isNotEmpty())<div class="side-box"><h3>الوسوم</h3><div class="row" style="gap:6px">@foreach($article->tags as $tag)<a class="chip" href="{{ route('tags.show', $tag) }}">{{ $tag->name }}</a>@endforeach</div></div>@endif
        @if($article->author)<div class="side-box author-box"><h3>عن الكاتب</h3><p><a href="{{ route('authors.show', $article->author) }}"><strong>{{ $article->author->name }}</strong></a><br><span class="muted small">{{ $article->author->role }}</span></p><p class="small">{{ Str::limit($article->author->bio, 160) }}</p></div>@endif
    </aside>
</div>

@if($related->isNotEmpty())
<section class="section-soft section-tight"><div class="shell">
    <div class="section-heading"><x-athar.kicker>التالي</x-athar.kicker><h2>مواد مرتبطة</h2></div>
    <div class="grid grid-3">@foreach($related as $r)<x-athar.card heading="h3" :tag="$r->typeLabel()" :title="$r->title" :desc="Str::limit($r->tldr ?: $r->excerpt, 140)" :href="route('articles.show', $r)"><x-slot:meta><span>{{ $r->authorName() }}</span><span>{{ $r->reading_minutes }} دقائق</span></x-slot:meta></x-athar.card>@endforeach</div>
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
