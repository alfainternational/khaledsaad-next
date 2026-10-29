@extends('layouts.platform')
@section('title', 'من المصادر إلى الرؤى — الإدارة — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell">
    <div class="split"><div><x-athar.kicker>الإدارة · صندوق المصادر</x-athar.kicker><h1>من المصادر إلى الرؤى</h1><p class="muted">خمس بوابات: التقاط ← تحقق ← ملخص ← سياق عربي ← مراجعة ونشر. رقم بلا مصدر أولي لا يمر، وملخص آلي لا يُنشر أبداً.</p></div>
        <form method="post" action="{{ route('admin.sources.fetch') }}" class="row">@csrf<button class="btn btn-secondary" type="submit" id="fetch-now">اجلب المصادر الآن</button><span class="muted small">آخر جلب: {{ $fetchedAt ? \Illuminate\Support\Carbon::createFromTimestamp($fetchedAt)->diffForHumans() : 'لم يُجلب بعد' }} · يومياً 05:30</span></form></div>
    @include('admin.partials.nav')
    @include('admin.content.partials.subnav', ['active' => 'sources'])
</div></section>
<section class="section-tight"><div class="shell stack" style="gap:24px">
    <x-athar.flash />
    <div class="gates" id="gates">
        @foreach($gates as $gate => $items)
        <div class="gate" data-gate="{{ $gate }}"><h3>{{ $labels[$gate] }}</h3><span class="n">{{ $counts[$gate] ?? 0 }}</span><p class="rule">{{ $rules[$gate] }}</p>
            @forelse($items as $item)
                <div class="gate-item" data-source-item="{{ $item->id }}"><a href="{{ route('admin.sources.show', $item) }}"><strong>{{ Str::limit($item->title, 70) }}</strong></a>
                    <span class="meta">{{ $item->publisherName() }}@if($item->published_on) · {{ $item->published_on->format('Y/m/d') }}@endif <span class="score" title="درجة الصلة">{{ $item->relevance_score }}</span></span></div>
            @empty<p class="muted small" style="margin:0">لا عناصر في هذه البوابة.</p>@endforelse
            @if(($counts[$gate] ?? 0) > $items->count())<p class="muted small" style="margin:8px 0 0">+{{ ($counts[$gate] ?? 0) - $items->count() }} أخرى</p>@endif
        </div>
        @endforeach
    </div>

    <div class="grid grid-2">
        <form method="post" action="{{ route('admin.sources.store') }}" class="card stack" id="manual-link">@csrf
            <h2 style="font-size:18px;margin:0">رابط يدوي</h2><p class="muted small" style="margin:0">يدخل بوابة الالتقاط بدرجة صلة آلية؛ المكرر يُرفض تلقائياً.</p>
            <div class="field"><label for="m-url">الرابط</label><input class="input" dir="ltr" id="m-url" name="url" type="url" required placeholder="https://" value="{{ old('url') }}">@error('url')<span class="error">{{ $message }}</span>@enderror</div>
            <div class="editor-grid">
                <div class="field"><label for="m-title">العنوان</label><input class="input" id="m-title" name="title" maxlength="290" value="{{ old('title') }}"></div>
                <div class="field"><label for="m-publisher">الناشر</label><input class="input" id="m-publisher" name="publisher" maxlength="160" value="{{ old('publisher') }}"></div>
                <div class="field"><label for="m-date">تاريخ النشر</label><input class="input" type="date" id="m-date" name="published_on" value="{{ old('published_on') }}"></div>
                <div class="field"><label for="m-lang">اللغة</label><select class="input" id="m-lang" name="language">@foreach(['en' => 'EN', 'ar' => 'AR', 'fr' => 'FR', 'de' => 'DE', 'es' => 'ES'] as $k => $l)<option value="{{ $k }}" @selected(old('language', 'en') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field span-2"><label for="m-excerpt">مقتطف (يُستخدم في درجة الصلة والملخص)</label><textarea class="input" id="m-excerpt" name="excerpt" rows="3" maxlength="2000">{{ old('excerpt') }}</textarea></div>
            </div>
            <div><button class="btn btn-primary" type="submit">التقط الرابط</button></div>
        </form>
        <div class="card stack" id="stale-reminders"><h2 style="font-size:18px;margin:0">تذكيرات تحديث ({{ $stale->count() }})</h2><p class="muted small" style="margin:0">مواد منشورة تعتمد أرقاماً من مصادر أقدم من 12 شهراً؛ تظهر أيضاً كإشارة جودة في غرفة التحكم.</p>
            @forelse($stale->take(12) as $s)<div class="gate-item"><a href="{{ route('admin.content.edit', $s) }}"><strong>{{ $s->title }}</strong></a><span class="meta">أقدم مصدر {{ $s->oldestSourceDate()?->format('Y/m/d') }} · {{ $s->sectionLabel() }}</span></div>@empty<p class="muted small" style="margin:0">لا مواد تحتاج تحديثاً الآن.</p>@endforelse
        </div>
    </div>

    <div class="card" style="padding:0">
        <x-athar.table caption="المصادر المراقبة" class="admin-table"><thead><tr><th>المصدر</th><th>الموضوع</th><th>العناصر</th><th>آخر جلب</th><th>الحالة</th><th></th></tr></thead><tbody>
        @foreach($feeds as $feed)
            <tr data-feed="{{ $feed->id }}"><td><b>{{ $feed->name }}</b><br><a class="small muted" dir="ltr" href="{{ $feed->site_url ?: $feed->url }}" target="_blank" rel="noopener">{{ $feed->site_url ?: $feed->url }}</a></td>
                <td>{{ $feed->topic }}</td><td>{{ $feed->items_count }}</td>
                <td>{{ $feed->last_fetched_at?->diffForHumans() ?? '—' }}@if($feed->last_error)<br><span class="small" style="color:var(--danger)">{{ $feed->last_error }}</span>@endif</td>
                <td>{{ $feed->is_active ? 'نشط' : 'موقوف' }}</td>
                <td><form method="post" action="{{ route('admin.sources.feeds.toggle', $feed) }}">@csrf @method('PATCH')<button class="btn btn-text" type="submit">{{ $feed->is_active ? 'أوقف' : 'فعّل' }}</button></form></td></tr>
        @endforeach
        </tbody></x-athar.table>
    </div>

    @if($published->isNotEmpty())<div class="card"><h2 style="font-size:18px;margin:0 0 8px">نُشر من الصندوق</h2><ul class="plain-list">@foreach($published as $p)<li><a href="{{ $p->contentItem ? route('articles.show', $p->contentItem) : route('admin.sources.show', $p) }}">{{ $p->contentItem?->title ?? $p->title }}</a> <span class="muted small">— من {{ $p->publisherName() }}</span></li>@endforeach</ul></div>@endif
    <p class="muted small">متجاهل: {{ $ignored }} · الذكاء الاصطناعي: {{ $aiEnabled ? 'مفعّل للملخصات' : 'بلا مفتاح؛ الملخص بالقواعد' }}</p>
</div></section>
@endsection
