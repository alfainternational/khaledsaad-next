@extends('layouts.platform')
@section('title', 'مصدر: '.Str::limit($item->title, 60).' — الإدارة — أثر')
@section('noindex', '1')
@section('content')
@php($stageIndex = array_search($item->stage, $gates, true))
@php($v = $item->verification ?? [])
<section class="app-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['الإدارة', route('admin.index')], ['صندوق المصادر', route('admin.sources.index')], [Str::limit($item->title, 50), null]]" />
    <div class="split"><div><x-athar.kicker>{{ $item->publisherName() }} · {{ strtoupper($item->language) }}@if($item->published_on) · {{ $item->published_on->format('Y/m/d') }}@endif · درجة الصلة {{ $item->relevance_score }}</x-athar.kicker><h1 style="font-size:26px">{{ $item->title }}</h1><a class="small" dir="ltr" href="{{ $item->url }}" target="_blank" rel="noopener nofollow">{{ Str::limit($item->url, 90) }}</a></div>
        <span class="status-pill" data-stage="{{ $item->stage }}" style="font-size:14px;padding:8px 14px">{{ $labels[$item->stage] }}</span></div>
    <ol class="filters" aria-label="البوابات" style="margin-top:14px">@foreach($gates as $i => $g)<li style="list-style:none"><a href="#gate-{{ $g }}" @if($item->stage === $g) aria-current="page" @endif>{{ $i + 1 }}. {{ $labels[$g] }}</a></li>@endforeach</ol>
</div></section>
<section class="section-tight"><div class="shell source-detail">
    <div class="stack" style="gap:18px">
        <x-athar.flash />
        @if($item->stage === 'ignored')<x-athar.alert type="info">متجاهل: {{ $item->ignored_reason }}</x-athar.alert>@endif
        @if($item->excerpt)<div class="card"><h2 style="font-size:16px;margin:0 0 6px">المقتطف الملتقط</h2><p class="small" style="margin:0">{{ $item->excerpt }}</p>@if($item->matched_keywords)<p class="muted small" style="margin:8px 0 0">كلمات الصلة: {{ implode('، ', $item->matched_keywords) }}</p>@endif</div>@endif

        {{-- Gate 2 --}}
        <form method="post" action="{{ route('admin.sources.verify', $item) }}" class="card stack" id="gate-verified" @if(! in_array($item->stage, ['captured', 'verified'], true)) data-locked @endif>@csrf
            <h2 style="font-size:18px;margin:0">2. تحقق</h2><p class="muted small" style="margin:0">{{ $rules['verified'] }}</p>
            @if($stageIndex !== false && $stageIndex >= 1)<p class="small" style="color:var(--success);margin:0">✓ تحقق {{ $v['verified_by'] ?? '' }} في {{ $v['verified_at'] ?? '' }}</p>@endif
            <div class="editor-grid">
                <div class="field"><label for="v-title">العنوان المعتمد</label><input class="input" id="v-title" name="title" value="{{ old('title', $item->title) }}" maxlength="290"></div>
                <div class="field"><label for="v-publisher">الناشر</label><input class="input" id="v-publisher" name="publisher" value="{{ old('publisher', $item->publisher) }}" maxlength="160"></div>
                <div class="field"><label for="v-date">تاريخ المصدر</label><input class="input" type="date" id="v-date" name="published_on" value="{{ old('published_on', $item->published_on?->toDateString()) }}" required></div>
                <div class="field"><label class="row" style="gap:6px;font-weight:700"><input type="checkbox" name="has_numbers" value="1" @checked(old('has_numbers', $v['has_numbers'] ?? false))> المصدر يحوي أرقاماً</label><span class="hint">إن حوى أرقاماً فالمصدر الأولي إلزامي.</span></div>
                <div class="field span-2"><label for="v-primary">المصدر الأولي للأرقام (الجهة + الرابط)</label><input class="input" id="v-primary" name="primary_source" value="{{ old('primary_source', $v['primary_source'] ?? '') }}" maxlength="600" placeholder="مثال: تقرير DataReportal Digital 2025 — https://datareportal.com/…">@error('primary_source')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="field span-2"><label for="v-market">انطباق على السوق العربي</label><textarea class="input" id="v-market" name="market_fit" rows="2" required maxlength="600">{{ old('market_fit', $v['market_fit'] ?? '') }}</textarea></div>
            </div>
            <div><button class="btn btn-primary" type="submit" @if(! in_array($item->stage, ['captured', 'verified'], true)) disabled @endif>{{ $stageIndex !== false && $stageIndex >= 1 ? 'حدّث التحقق' : 'مرّر من بوابة التحقق' }}</button></div>
        </form>

        {{-- Gate 3 --}}
        <div class="card stack" id="gate-summarized">
            <h2 style="font-size:18px;margin:0">3. ملخص</h2><p class="muted small" style="margin:0">{{ $rules['summarized'] }}</p>
            @if($item->summary)<div class="coach-answer" data-summary>{!! \App\Support\Markdown::toHtml($item->summary, literalLevels: true) !!}</div><p class="muted small" style="margin:0">الوضع: {{ ['rules' => 'قواعد', 'ai' => 'ذكاء اصطناعي', 'editor' => 'محرر'][$item->summary_mode] ?? $item->summary_mode }}</p>@endif
            @if(in_array($item->stage, ['verified', 'summarized'], true))
            <div class="row">
                <form method="post" action="{{ route('admin.sources.summarize', $item) }}">@csrf<button class="btn btn-secondary" type="submit" id="summarize-rules">ولّد ملخصاً بالقواعد</button></form>
                <form method="post" action="{{ route('admin.sources.summarize', $item) }}">@csrf<input type="hidden" name="ai" value="1"><button class="btn btn-secondary" type="submit" @if(! $aiEnabled) title="لا مفتاح ذكاء اصطناعي؛ سيُستخدم ملخص القواعد" @endif>ولّد بالذكاء الاصطناعي{{ $aiEnabled ? '' : ' (بلا مفتاح → قواعد)' }}</button></form>
            </div>
            <form method="post" action="{{ route('admin.sources.summarize', $item) }}" class="stack">@csrf<div class="field"><label for="s-summary">أو اكتب/حرّر الملخص يدوياً</label><textarea class="input" id="s-summary" name="summary" rows="5" maxlength="8000">{{ old('summary', $item->summary) }}</textarea></div><div><button class="btn btn-secondary" type="submit">احفظ الملخص</button></div></form>
            @elseif($stageIndex !== false && $stageIndex < 1)<p class="muted small" style="margin:0">تفتح بعد بوابة التحقق.</p>@endif
        </div>

        {{-- Gate 4 --}}
        <form method="post" action="{{ route('admin.sources.contextualize', $item) }}" class="card stack" id="gate-contextualized">@csrf
            <h2 style="font-size:18px;margin:0">4. سياق عربي</h2><p class="muted small" style="margin:0">{{ $rules['contextualized'] }}</p>
            @if($item->contentItem)<p class="small" style="margin:0">المادة المرتبطة: <a href="{{ route('admin.content.edit', $item->contentItem) }}">{{ $item->contentItem->title }}</a> <span class="status-pill" data-status="{{ $item->contentItem->status }}">{{ $item->contentItem->status }}</span></p>@endif
            @if(in_array($item->stage, ['summarized', 'contextualized'], true))
            <div class="editor-grid">
                <div class="field span-2"><label for="c-title">عنوان المادة العربية (سؤال حقيقي)</label><input class="input" id="c-title" name="title" value="{{ old('title', $item->contentItem?->title ?? '') }}" required maxlength="200"></div>
                <div class="field span-2"><label for="c-tldr">«في دقيقة»</label><textarea class="input" id="c-tldr" name="tldr" rows="2" required maxlength="600">{{ old('tldr', $item->contentItem?->tldr ?? '') }}</textarea></div>
                <div class="field span-2"><label for="c-context">السياق العربي (≥ {{ $minWords }} كلمة)</label><textarea class="input" id="c-context" name="arabic_context" rows="8" required maxlength="8000" data-word-count-for="c-count">{{ old('arabic_context', $item->arabic_context) }}</textarea><span class="word-count" id="c-count" data-min="{{ $minWords }}">{{ $item->contextWordCount() }} كلمة</span>@error('arabic_context')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="field span-2"><label for="c-example">مثال محلي</label><textarea class="input" id="c-example" name="local_example" rows="2" required maxlength="600">{{ old('local_example', $item->local_example) }}</textarea></div>
                <div class="field"><label for="c-lesson">الدرس المرتبط</label><select class="input" id="c-lesson" name="related_lesson_id" required><option value="">—</option>@foreach($lessons as $l)<option value="{{ $l['id'] }}" @selected((int) old('related_lesson_id', $item->related_lesson_id) === $l['id'])>{{ $l['label'] }}</option>@endforeach</select></div>
                <div class="field"><label for="c-tool">الأداة المرتبطة</label><select class="input" id="c-tool" name="related_tool_id" required><option value="">—</option>@foreach($tools as $t)<option value="{{ $t->id }}" @selected((int) old('related_tool_id', $item->related_tool_id) === $t->id)>{{ $t->name }} ({{ $t->kindLabel() }})</option>@endforeach</select></div>
                <div class="field"><label for="c-category">التصنيف</label><select class="input" id="c-category" name="category_id"><option value="">—</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected((int) old('category_id', $item->contentItem?->category_id) === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                <div class="field"><label for="c-author">المؤلف</label><select class="input" id="c-author" name="author_id"><option value="">— (أنت)</option>@foreach($authors as $a)<option value="{{ $a->id }}" @selected((int) old('author_id', $item->contentItem?->author_id) === $a->id)>{{ $a->name }}</option>@endforeach</select></div>
            </div>
            <div><button class="btn btn-primary" type="submit">{{ $item->contentItem ? 'حدّث المادة' : 'أنشئ مادة «رؤية عالمية»' }}</button></div>
            @else<p class="muted small" style="margin:0">تفتح بعد بوابة الملخص.</p>@endif
        </form>

        {{-- Gate 5 --}}
        <form method="post" action="{{ route('admin.sources.review', $item) }}" class="card stack" id="gate-reviewed">@csrf
            <h2 style="font-size:18px;margin:0">5. مراجعة ونشر</h2><p class="muted small" style="margin:0">{{ $rules['reviewed'] }}</p>
            @if(in_array($item->stage, ['contextualized', 'reviewed'], true))
            <ul class="checklist">@foreach($checklist as $key => $text)<li><input type="checkbox" id="chk-{{ $key }}" name="checks[{{ $key }}]" value="1" @checked(old("checks.$key"))><label for="chk-{{ $key }}">{{ $text }}</label></li>@endforeach</ul>
            @error('checks')<span class="error">{{ $message }}</span>@enderror @error('review')<span class="error">{{ $message }}</span>@enderror
            <div class="field"><label for="r-name">اسم المراجع</label><input class="input" id="r-name" name="reviewer_name" value="{{ old('reviewer_name', $item->contentItem?->reviewer_name ?? auth()->user()->name) }}" required maxlength="120"></div>
            <div class="row"><button class="btn btn-secondary" type="submit" name="action" value="approve">اعتمد المراجعة</button><button class="btn btn-primary" type="submit" name="action" value="publish" id="publish-from-sources">اعتمد وانشر</button>@if($item->contentItem)<a class="btn btn-text" href="{{ route('admin.content.edit', $item->contentItem) }}">افتح المادة في المحرر</a>@endif</div>
            @elseif($item->stage === 'published')<p class="small" style="color:var(--success);margin:0">✓ نُشرت المادة: <a href="{{ route('articles.show', $item->contentItem) }}">{{ $item->contentItem?->title }}</a></p>
            @else<p class="muted small" style="margin:0">تفتح بعد بوابة السياق العربي.</p>@endif
        </form>
    </div>

    <aside class="stack">
        @if(! in_array($item->stage, ['published', 'ignored'], true))
        <form method="post" action="{{ route('admin.sources.ignore', $item) }}" class="card stack">@csrf<h2 style="font-size:16px;margin:0">تجاهل</h2><div class="field"><label for="ig-reason">السبب</label><input class="input" id="ig-reason" name="reason" required maxlength="160" placeholder="إعادة تدوير، لا ينطبق، بلا مصدر أولي…"></div><button class="btn btn-secondary" type="submit">تجاهل المصدر</button></form>
        @endif
        <div class="card"><h2 style="font-size:16px;margin:0 0 6px">البوابات</h2><ol class="plain-list" style="padding-inline-start:0">@foreach($gates as $i => $g)<li @if($item->stage === $g) style="color:var(--orange);font-weight:700" @elseif($stageIndex !== false && $i < $stageIndex) style="color:var(--success)" @endif>{{ $i + 1 }}. {{ $labels[$g] }}{{ $stageIndex !== false && $i < $stageIndex ? ' ✓' : '' }}</li>@endforeach</ol></div>
        @if($audit->isNotEmpty())<div class="card"><h2 style="font-size:16px;margin:0 0 6px">سجل التدقيق</h2><ul class="plain-list small">@foreach($audit as $a)<li><b>{{ $a->action }}</b> <span class="muted">{{ \Illuminate\Support\Carbon::parse($a->created_at)->format('Y/m/d H:i') }}</span></li>@endforeach</ul></div>@endif
    </aside>
</div></section>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">
(function(){var t=document.querySelector('[data-word-count-for]');if(!t)return;var c=document.getElementById(t.dataset.wordCountFor),min=parseInt(c.dataset.min,10);
function upd(){var n=(t.value.trim().match(/\S+/g)||[]).length;c.textContent=n+' كلمة'+(n<min?' (الحد الأدنى '+min+')':' ✓');c.classList.toggle('ok',n>=min)}t.addEventListener('input',upd);upd()})();
</script>
@endpush
