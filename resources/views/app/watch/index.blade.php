@extends('layouts.platform')
@section('title', 'مراقبة المنافسين — أثر')
@section('description', 'صفحات تُفحص كل يوم: ما الذي تغيّر عند منافسك، بالأسطر، صباح اليوم التالي.')
@section('content')
<section class="page-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['حسابي', route('dashboard')], ['مراقبة المنافسين', null]]" />
    <span class="kicker">القياس بعد التنفيذ</span>
    <h1>مراقبة المنافسين</h1>
    <p class="lead">كل أداة أخرى هنا تجيب سؤالاً مرة واحدة. هذه تجيبه كل يوم: ما الذي تغيّر في صفحة أسعاره، أو عرضه، أو وعده الرئيسي — بالأسطر لا بالإحساس.</p>
    @if($lastRun)<p class="small muted">آخر دورة فحص: {{ \Carbon\Carbon::createFromTimestamp($lastRun)->diffForHumans() }}.</p>@endif
</div></section>

<section class="section-tight"><div class="shell">
    <x-athar.flash />
    @error('watch')<x-athar.alert type="warning">{{ $message }}</x-athar.alert>@enderror
    @if($unseen > 0)<x-athar.alert type="info">{{ $unseen }} تغيّراً لم تُراجعه بعد.</x-athar.alert>@endif

    <div class="tool-layout">
        <div class="stack" style="gap:24px">
            @forelse($pages as $page)
            <div class="card" data-watched="{{ $page->id }}">
                <div class="split">
                    <div>
                        <x-athar.kicker>{{ $page->host() }}@if($page->project) · {{ $page->project->name }}@endif</x-athar.kicker>
                        <h3 style="font-size:20px;margin:4px 0">{{ $page->label }}</h3>
                        <p class="small muted" style="margin:0" dir="ltr"><a href="{{ $page->url }}" target="_blank" rel="nofollow noopener">{{ Str::limit($page->url, 70) }}</a></p>
                    </div>
                    <div class="row" style="gap:8px">
                        <form method="post" action="{{ route('app.watch.check', $page) }}">@csrf<button class="btn btn-secondary" type="submit" data-action="check">افحص الآن</button></form>
                        <form method="post" action="{{ route('app.watch.destroy', $page) }}" onsubmit="return confirm('حذف الصفحة وسجلّ تغيّراتها؟')">@csrf @method('DELETE')<button class="btn btn-text" type="submit" data-action="remove">حذف</button></form>
                    </div>
                </div>

                <div class="card-meta" style="margin-top:10px">
                    <span>{{ $page->change_count }} تغيّر مرصود</span>
                    <span>آخر فحص: {{ $page->last_checked_at?->diffForHumans() ?? 'لم يُفحص بعد' }}</span>
                    @if($page->last_changed_at)<span>آخر تغيّر: {{ $page->last_changed_at->diffForHumans() }}</span>@endif
                    @if(! $page->is_active)<span class="verdict-outside">موقوفة</span>@endif
                </div>

                @if($page->last_error)
                    <x-athar.alert type="warning">{{ $page->last_error }}@if($page->isStalled()) — أُوقفت المراقبة بعد تكرار الفشل؛ احذفها أو صحّح الرابط.@endif</x-athar.alert>
                @elseif($page->checksum === null)
                    <p class="small muted" style="margin:10px 0 0">لم تُؤخذ النسخة المرجعية بعد. أول فحص يأخذها، وما بعده يُقارَن بها — فلا تتوقع تغيّراً في أول يوم.</p>
                @endif

                @forelse($page->changes as $change)
                <details class="asset-diff" data-change="{{ $change->id }}" @if(! $change->seen) open @endif>
                    <summary>
                        {{ $change->created_at->format('Y/m/d') }} — <span class="muted small">+{{ $change->added_lines }} / −{{ $change->removed_lines }} سطراً</span>
                        @if(! $change->seen)<span class="card-tag">جديد</span>@endif
                    </summary>
                    @if($change->summary)<p style="margin:8px 0"><b>الخلاصة:</b> {{ $change->summary }}</p>@endif
                    <div class="diff-cols">
                        <div><h5>أُضيف</h5>@forelse($change->added ?? [] as $line)<p class="diff-add">+ {{ Str::limit($line, 200) }}</p>@empty<p class="muted small">لا إضافات.</p>@endforelse</div>
                        <div><h5>حُذف</h5>@forelse($change->removed ?? [] as $line)<p class="diff-del">− {{ Str::limit($line, 200) }}</p>@empty<p class="muted small">لا محذوفات.</p>@endforelse</div>
                    </div>
                    @if(! $change->seen)
                    <form method="post" action="{{ route('app.watch.seen', $change) }}" style="margin-top:8px">@csrf<button class="btn btn-text" type="submit">راجعته</button></form>
                    @endif
                </details>
                @empty
                <p class="small muted" style="margin:10px 0 0">لا تغيّرات مسجّلة بعد.</p>
                @endforelse
            </div>
            @empty
            <div class="card placeholder-card"><x-athar.kicker>لا صفحات مراقَبة</x-athar.kicker>
                <p class="muted" style="margin:0 0 8px">أضف صفحة واحدة تهمّك فعلاً: صفحة أسعار منافس، أو صفحة عرضه، أو صفحته الرئيسية. الصفحات التي تتغيّر كل يوم بلا معنى (المدونة، آخر الأخبار) تُرهق ولا تفيد.</p>
            </div>
            @endforelse
        </div>

        <aside class="side stack" style="gap:16px">
            <div class="side-box"><h3>أضف صفحة</h3>
                <form method="post" action="{{ route('app.watch.store') }}" class="stack" style="gap:10px">
                    @csrf
                    <div class="field"><label for="w-project">المشروع</label>
                        @if($projects->isNotEmpty())<select id="w-project" name="project_id">@foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
                        @else<p class="muted small">أنشئ مشروعاً أولاً من <a href="{{ route('dashboard') }}#projects">حسابي</a>.</p>@endif
                    </div>
                    <div class="field"><label for="w-label">الاسم عندك</label><input id="w-label" name="label" maxlength="140" placeholder="أسعار المنافس أ" required></div>
                    <div class="field"><label for="w-url">الرابط</label><input id="w-url" name="url" type="url" dir="ltr" placeholder="https://competitor.com/pricing" required><span class="hint">صفحة عامة واحدة. العناوين الداخلية مرفوضة، وما يمنعه robots.txt لا يُجلب.</span></div>
                    <x-athar.button type="submit" block>أضف للمراقبة</x-athar.button>
                    <span class="small muted">الحد {{ $limit }} صفحات لكل مساحة عمل.</span>
                </form>
            </div>
            <div class="side-box"><h3>لماذا لا تُزعجك كل يوم</h3>
                <p class="small" style="margin:0">التواريخ والساعات وعدّادات المتابعين وبصمات الملفات تتغيّر في كل جلب بلا أن يتغيّر شيء. تُحذف قبل المقارنة عمداً: منبّهٌ يرنّ يومياً بلا سبب يُكتَم بعد أسبوع، ثم لا يُقرأ حين يرنّ لسبب.</p>
            </div>
            <div class="side-box"><h3>ما تراه أولاً</h3>
                <p class="small" style="margin:0">الأسطر المضافة والمحذوفة — وهي الجزء الذي لا يحتمل الخطأ. الخلاصة النصّية تأتي فوقها حين يتوفّر نموذج، ولا تحلّ محلّها.</p>
            </div>
        </aside>
    </div>
</div></section>
@endsection
