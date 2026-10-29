@extends('layouts.platform')
@section('title', ($creating ? 'مادة جديدة' : 'تعديل: '.$item->title).' — الإدارة — أثر')
@section('noindex', '1')
@section('content')
@php($selectedTags = collect(old('tags', $item->exists ? $item->tags->pluck('id')->all() : []))->map(fn ($v) => (int) $v)->all())
@php($oldSources = old('sources', $item->exists ? $item->sources->map(fn ($s) => ['publisher' => $s->publisher, 'title' => $s->title, 'url' => $s->url, 'date' => $s->published_on?->toDateString(), 'language' => $s->language])->all() : []))
@php($oldFaq = old('faq', $item->faq ?? []))
<section class="app-hero"><div class="shell">
    <div class="split"><div><x-athar.kicker>الإدارة · المحتوى</x-athar.kicker><h1 style="font-size:26px">{{ $creating ? 'مادة جديدة' : $item->title }}</h1></div>
        <div class="row">@if($item->exists && $item->status === 'published')<x-athar.button variant="secondary" href="{{ route('articles.show', $item) }}" target="_blank" rel="noopener">عرض على الموقع</x-athar.button>@endif<x-athar.button variant="secondary" href="{{ route('admin.content.index') }}">كل المواد</x-athar.button></div></div>
    @include('admin.partials.nav')
</div></section>
<section class="section-tight"><div class="shell">
    <x-athar.flash />
    @if($stale)<x-athar.alert type="info"><b>تذكير تحديث:</b> أرقام هذه المادة تعتمد مصدراً أقدم من 12 شهراً (أقدم مصدر {{ $item->oldestSourceDate()?->format('Y/m') }}). حدّث المصادر أو علّم التاريخ في المتن ثم «علّمها مُراجَعة اليوم».</x-athar.alert>@endif
    <div class="editor-layout">
    <form method="post" action="{{ $creating ? route('admin.content.store') : route('admin.content.update', $item) }}" class="card stack" id="content-editor" style="gap:18px">
        @csrf @if(! $creating)@method('PUT')@endif
        <div class="editor-grid">
            <div class="field span-2"><label for="title">العنوان (سؤال حقيقي يطرحه القارئ)</label><input class="input" id="title" name="title" value="{{ old('title', $item->title) }}" required maxlength="200"></div>
            <div class="field"><label for="slug">الرابط (slug)</label><input class="input" dir="ltr" id="slug" name="slug" value="{{ old('slug', $item->slug) }}" required maxlength="180"><span class="hint">حروف وأرقام وشرطات. تغييره بعد النشر يكسر الروابط القديمة.</span></div>
            <div class="field"><label for="status">الحالة</label>
                @if($item->status === 'published')<input type="hidden" name="status" value="published"><p class="status-pill" data-status="published" style="font-size:14px;padding:8px 12px">منشور منذ {{ $item->published_at?->format('Y-m-d') }}</p>
                @else<select class="input" id="status" name="status">@foreach($statuses as $s)<option value="{{ $s }}" @selected(old('status', $item->status) === $s)>{{ $statusLabels[$s] }}</option>@endforeach</select><span class="hint">النشر يتطلب «جاهزة للمراجعة» أو «مراجعة SEO».</span>@endif</div>
            <div class="field"><label for="section">القسم</label><select class="input" id="section" name="section">@foreach($sections as $k => $s)<option value="{{ $k }}" @selected(old('section', $item->section) === $k)>{{ $s['label'] }}</option>@endforeach</select></div>
            <div class="field"><label for="type">النوع</label><select class="input" id="type" name="type">@foreach($types as $k => $label)<option value="{{ $k }}" @selected(old('type', $item->type) === $k)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="category_id">التصنيف</label><select class="input" id="category_id" name="category_id"><option value="">—</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected((int) old('category_id', $item->category_id) === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div class="field"><label for="translation_stage">مرحلة الترجمة</label><select class="input" id="translation_stage" name="translation_stage"><option value="">—</option>@foreach($stages as $k => $label)<option value="{{ $k }}" @selected(old('translation_stage', $item->translation_stage) === $k)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="author_id">المؤلف</label><select class="input" id="author_id" name="author_id"><option value="">— (اسم حر)</option>@foreach($authors as $a)<option value="{{ $a->id }}" @selected((int) old('author_id', $item->author_id) === $a->id)>{{ $a->name }} — {{ $a->role }}</option>@endforeach</select></div>
            <div class="field"><label for="author_name">اسم الكاتب (يظهر إن لم يُختر مؤلف)</label><input class="input" id="author_name" name="author_name" value="{{ old('author_name', $item->author_name) }}" required maxlength="120"></div>
            <div class="field"><label for="reviewer_name">المراجع</label><input class="input" id="reviewer_name" name="reviewer_name" value="{{ old('reviewer_name', $item->reviewer_name) }}" maxlength="120"></div>
            <div class="field"><label for="reading_minutes">دقائق القراءة</label><input class="input" type="number" min="1" max="120" id="reading_minutes" name="reading_minutes" value="{{ old('reading_minutes', $item->reading_minutes) }}" required></div>
            <div class="field"><label for="eyebrow">الوسم العلوي</label><input class="input" id="eyebrow" name="eyebrow" value="{{ old('eyebrow', $item->eyebrow) }}" maxlength="80"></div>
            <div class="field"><label for="topic">الموضوع (نص)</label><input class="input" id="topic" name="topic" value="{{ old('topic', $item->topic) }}" maxlength="80"></div>
            <div class="field"><label for="related_lesson_id">الدرس المرتبط</label><select class="input" id="related_lesson_id" name="related_lesson_id"><option value="">—</option>@foreach($lessons as $l)<option value="{{ $l['id'] }}" @selected((int) old('related_lesson_id', $item->related_lesson_id) === $l['id'])>{{ $l['label'] }}</option>@endforeach</select></div>
            <div class="field"><label for="related_tool_id">الأداة المرتبطة</label><select class="input" id="related_tool_id" name="related_tool_id"><option value="">—</option>@foreach($tools as $t)<option value="{{ $t->id }}" @selected((int) old('related_tool_id', $item->related_tool_id) === $t->id)>{{ $t->name }} ({{ $t->kindLabel() }})</option>@endforeach</select></div>
            <div class="field span-2"><label for="tldr">«في دقيقة» (إجابة مباشرة من 2–3 جمل)</label><textarea class="input" id="tldr" name="tldr" rows="2" maxlength="600">{{ old('tldr', $item->tldr) }}</textarea></div>
            <div class="field span-2"><label for="excerpt">الملخص (للبطاقات ونتائج البحث)</label><textarea class="input" id="excerpt" name="excerpt" rows="2" required maxlength="600">{{ old('excerpt', $item->excerpt) }}</textarea></div>
            <div class="field span-2"><label for="body">المتن (Markdown)</label><textarea class="input" id="body" name="body" rows="24" required style="font-family:ui-monospace,Consolas,monospace;font-size:14px;direction:rtl">{{ old('body', $item->body) }}</textarea>
                <span class="hint">عناوين <code>##</code> كأسئلة، جداول بالأنابيب، ومربع «طبّق الآن»: <code>:::apply customer-persona</code> … <code>:::</code>. فقرات مفصولة بسطر فارغ.</span></div>
            <div class="field span-2"><label for="arabic_context">السياق العربي (إلزامي للرؤى والأخبار والأبحاث)</label><textarea class="input" id="arabic_context" name="arabic_context" rows="3" maxlength="2000">{{ old('arabic_context', data_get($item->meta, 'arabic_context')) }}</textarea></div>
        </div>

        <fieldset class="stack" style="border:0;padding:0;margin:0"><legend style="font-weight:700;margin-bottom:6px">الوسوم</legend>
            <div class="row" style="gap:6px">@foreach($tags as $tag)<label class="chip" style="cursor:pointer;gap:6px"><input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $selectedTags, true))> {{ $tag->name }}</label>@endforeach</div>
        </fieldset>

        <fieldset class="stack" style="border:0;padding:0;margin:0" id="sources-fieldset"><legend style="font-weight:700;margin-bottom:6px">المصادر (الناشر، العنوان، الرابط، التاريخ، اللغة)</legend>
            <div class="repeat-rows">
            @for($i = 0; $i < max(3, count($oldSources) + 1); $i++)@php($s = $oldSources[$i] ?? [])
                <div class="repeat-row">
                    <div class="field"><label class="sr-only" for="src-pub-{{ $i }}">الناشر</label><input class="input" id="src-pub-{{ $i }}" name="sources[{{ $i }}][publisher]" value="{{ $s['publisher'] ?? '' }}" placeholder="الناشر"></div>
                    <div class="field"><label class="sr-only" for="src-title-{{ $i }}">العنوان</label><input class="input" id="src-title-{{ $i }}" name="sources[{{ $i }}][title]" value="{{ $s['title'] ?? '' }}" placeholder="عنوان المصدر"></div>
                    <div class="field"><label class="sr-only" for="src-url-{{ $i }}">الرابط</label><input class="input" dir="ltr" id="src-url-{{ $i }}" name="sources[{{ $i }}][url]" value="{{ $s['url'] ?? '' }}" placeholder="https://"></div>
                    <div class="field"><label class="sr-only" for="src-date-{{ $i }}">التاريخ</label><input class="input" type="date" id="src-date-{{ $i }}" name="sources[{{ $i }}][date]" value="{{ $s['date'] ?? '' }}"></div>
                    <div class="field"><label class="sr-only" for="src-lang-{{ $i }}">اللغة</label><select class="input" id="src-lang-{{ $i }}" name="sources[{{ $i }}][language]">@foreach(['en' => 'EN', 'ar' => 'AR', 'fr' => 'FR', 'de' => 'DE', 'es' => 'ES'] as $k => $l)<option value="{{ $k }}" @selected(($s['language'] ?? 'en') === $k)>{{ $l }}</option>@endforeach</select></div>
                </div>
            @endfor
            </div>
        </fieldset>

        <fieldset class="stack" style="border:0;padding:0;margin:0"><legend style="font-weight:700;margin-bottom:6px">الأسئلة الشائعة (أسئلة فعلية فقط؛ تولّد FAQPage)</legend>
            <div class="repeat-rows">
            @for($i = 0; $i < max(3, count($oldFaq) + 1); $i++)@php($f = $oldFaq[$i] ?? [])
                <div class="repeat-row faq">
                    <div class="field"><label class="sr-only" for="faq-q-{{ $i }}">السؤال</label><input class="input" id="faq-q-{{ $i }}" name="faq[{{ $i }}][q]" value="{{ $f['q'] ?? '' }}" placeholder="السؤال؟" maxlength="300"></div>
                    <div class="field"><label class="sr-only" for="faq-a-{{ $i }}">الجواب</label><input class="input" id="faq-a-{{ $i }}" name="faq[{{ $i }}][a]" value="{{ $f['a'] ?? '' }}" placeholder="الجواب في جملة أو جملتين" maxlength="1000"></div>
                </div>
            @endfor
            </div>
        </fieldset>

        <div class="row between">
            <div class="row"><button class="btn btn-primary" type="submit">{{ $creating ? 'إنشاء المسودة' : 'حفظ' }}</button>
                @if(! $creating)<label class="row small" style="gap:6px"><input type="checkbox" name="mark_reviewed" value="1"> علّمها مُراجَعة اليوم</label>@endif</div>
        </div>
    </form>

    <aside class="stack">
        @if(! $creating && in_array($item->status, ['review', 'seo_review'], true))
            <form method="post" action="{{ route('admin.content.publish', $item) }}" class="card stack">@csrf<h2 style="font-size:18px;margin:0">جاهزة للنشر</h2><p class="muted small" style="margin:0">النشر يحفظ نسخة ويجعلها ظاهرة للزوار فوراً.</p><button class="btn btn-primary" type="submit">نشر الآن</button></form>
        @endif
        @if(! $creating)
            <form method="post" action="{{ route('admin.content.clean', $item) }}" class="card stack">@csrf<h2 style="font-size:18px;margin:0">نظّف الفقرات</h2><p class="muted small" style="margin:0">للمواد المستوردة: يدمج الأسطر المكسورة في فقرات نظيفة ويحتفظ بالنسخة السابقة.</p><button class="btn btn-secondary" type="submit" id="clean-paragraphs">نظّف الفقرات</button></form>
            @if(data_get($item->meta, 'legacy'))
                <details class="card"><summary style="cursor:pointer;font-weight:700">النسخة الأصلية من الموقع القديم (للمقارنة)</summary>
                    <p class="muted small" style="margin:10px 0 0">عنوان SEO القديم: {{ data_get($item->meta, 'legacy.seo_title') ?: '—' }}</p>
                    <div class="small" style="margin-top:8px">{!! strip_tags((string) data_get($item->meta, 'legacy.body_html'), '<p><h2><h3><ul><ol><li><strong><em><blockquote><br>') !!}</div>
                </details>
            @endif
            <form method="post" action="{{ route('admin.content.destroy', $item) }}" class="card stack" data-confirm-delete>@csrf @method('DELETE')
                <h2 style="font-size:18px;margin:0">حذف المادة</h2><label class="row small" style="gap:6px"><input type="checkbox" name="confirm" value="1" required> أؤكد حذف «{{ Str::limit($item->title, 40) }}» نهائياً</label>
                <button class="btn btn-secondary danger" type="submit">احذف</button></form>
            @if($audit->isNotEmpty())<div class="card"><h2 style="font-size:16px;margin:0 0 6px">سجل التدقيق</h2><ul class="plain-list small">@foreach($audit as $a)<li><b>{{ $a->action }}</b> <span class="muted">{{ \Illuminate\Support\Carbon::parse($a->created_at)->format('Y/m/d H:i') }}</span></li>@endforeach</ul></div>@endif
        @endif
    </aside>
    </div>
</div></section>
@endsection
