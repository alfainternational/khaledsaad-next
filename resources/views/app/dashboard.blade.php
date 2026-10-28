@extends('layouts.platform')
@section('title', 'لوحتي — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['لوحتي', null]]" />
    <h1>مرحباً {{ $user->name }}</h1>
    @if($last)<p class="muted">أكمل من حيث توقفت: <a href="{{ route('academy.lesson', [$last->course, $last]) }}"><b>{{ $last->title }}</b></a> — {{ $last->course->title }}</p>
    @elseif($assessment && $assessment->startLesson)<p class="muted">ابدأ بالأولوية 1: <a href="{{ route('academy.lesson', [$assessment->startLesson->course, $assessment->startLesson]) }}"><b>{{ $assessment->startLesson->title }}</b></a></p>
    @else<p class="muted">ابدأ بتشخيص مشروعك في عشر دقائق: <a href="{{ route('assess.start') }}"><b>شخّص مشروعك</b></a></p>@endif
</div></section>
<section class="section-tight"><div class="shell stack" style="gap:24px">
    <x-athar.flash />
    <div class="stat-grid" aria-label="مؤشرات">
        <div class="stat"><b>{{ $stats['lessons'] }}</b><span>درس مكتمل</span></div>
        <div class="stat"><b>{{ $stats['assets'] }}</b><span>أصل محفوظ</span></div>
        <div class="stat"><b>{{ $stats['results'] }}</b><span>نتيجة واقعية مسجّلة</span></div>
        <div class="stat"><b>{{ $stats['credits'] }}</b><span>رصيد متبقٍ · {{ $subscription?->plan ?? 'مجاني' }} · <a href="{{ route('app.billing') }}">الفوترة</a></span></div>
    </div>
    <div class="dash-grid">
        <div class="stack" style="gap:24px">
            <div class="card" id="weekly-plan"><x-athar.kicker>خطة الأسبوع</x-athar.kicker><h2 style="font-size:22px">مشتقة من تقدمك والتزاماتك</h2>
                @if($plan === [])<p class="muted">لا شيء معلّق. افتح درساً جديداً أو أداة.</p>@else<ol class="plan-list">@foreach($plan as $item)<li><input type="checkbox" aria-label="تم"><a href="{{ $item['url'] }}">{{ $item['label'] }}</a><span class="muted small">هذا الأسبوع</span></li>@endforeach</ol>@endif
            </div>
            <div class="card" id="progress"><div class="split"><div><x-athar.kicker>تقدمي</x-athar.kicker><h2 style="font-size:22px">المسارات التي بدأتها</h2></div><a class="btn btn-text" href="{{ route('app.progress') }}">كل تقدمي ←</a></div>
                @forelse($started as $s)<div style="padding:12px 0;border-top:1px solid var(--line)"><div class="split"><a href="{{ route('academy.show', $s['course']) }}"><b>{{ $s['course']->title }}</b></a>@if($s['progress']['next'])<a class="small" href="{{ route('academy.lesson', [$s['course'], $s['progress']['next']]) }}">التالي: {{ $s['progress']['next']->title }} ←</a>@else<span class="small" style="color:var(--success)">مكتمل ✓</span>@endif</div><x-athar.progress :value="$s['progress']['percent']" :done="$s['progress']['done']" :total="$s['progress']['total']" /></div>
                @empty<p class="muted">لم تبدأ مساراً بعد. <a href="{{ route('academy.index') }}">افتح الأكاديمية</a>.</p>@endforelse
            </div>
            <div class="card" id="assets"><div class="split"><div><x-athar.kicker>آخر أصولي</x-athar.kicker><h2 style="font-size:22px">5 أصول محفوظة</h2></div></div>
                @if($assets->isEmpty())<p class="muted">لا أصول بعد. كل درس ينتهي بأصل تحفظه هنا.</p>@else
                <x-athar.table caption="آخر الأصول"><thead><tr><th>النوع</th><th>العنوان</th><th>الدرس</th><th>التاريخ</th></tr></thead><tbody>@foreach($assets as $asset)<tr><td>{{ $asset->output_type }}</td><td>{{ $asset->title }} <span class="muted small">v{{ $asset->version }}</span></td><td>{{ $asset->lesson?->title ?? '—' }}</td><td>{{ $asset->created_at->format('Y/m/d') }}</td></tr>@endforeach</tbody></x-athar.table>@endif
            </div>
            <div class="card" id="activity"><x-athar.kicker>النشاط</x-athar.kicker><ul class="list">@forelse($activity as $e)<li><span>{{ ['visit.lesson' => 'فتحت درساً', 'lesson.lab_generated' => 'أنشأت أصلاً', 'asset.saved' => 'حفظت أصلاً', 'question.answered' => 'أجبت عن سؤال قرار', 'checkin.committed' => 'التزمت بفعل واقعي', 'checkin.recorded' => 'سجّلت نتيجة واقعية', 'lesson.completed' => 'أكملت درساً', 'assessment.completed' => 'أكملت التشخيص', 'lesson.opening_decided' => 'سجّلت قرارك الأول', 'lesson.final_decided' => 'راجعت قرارك'][$e->event] ?? $e->event }}@if($e->lesson) · {{ $e->lesson->title }}@endif</span><span class="when">{{ $e->created_at->diffForHumans() }}</span></li>@empty<li class="muted">لا نشاط بعد.</li>@endforelse</ul></div>
        </div>
        <aside class="stack">
            <div class="card" id="maturity"><x-athar.kicker>أبعاد النضج</x-athar.kicker>
                @if($assessment)<p class="small muted">{{ $levels[$assessment->level] }} · {{ $assessment->overall }}/100 · {{ $assessment->created_at->format('Y/m/d') }}</p><div class="dim-bars">@foreach($dimensions as $key => $label)<div class="dim-bar" style="grid-template-columns:1fr;gap:4px"><span class="small">{{ $label }} · {{ $assessment->scores[$key] ?? 0 }}</span><div class="track"><div class="fill" style="width:{{ (int) ($assessment->scores[$key] ?? 0) }}%"></div></div></div>@endforeach</div><a class="btn btn-text" href="{{ route('assess.result') }}">خريطة الأولويات ←</a>
                @else<p class="muted small">لم تُشخّص مشروعك بعد.</p><x-athar.button href="{{ route('assess.start') }}" block>شخّص مشروعك</x-athar.button>@endif
            </div>
            <div class="card" id="suggestions"><x-athar.kicker>المقترحات</x-athar.kicker><ul class="list">@foreach($suggestions as $s)<li><a href="{{ $s['url'] }}">{{ $s['label'] }}</a></li>@endforeach</ul></div>
            <div class="card" id="certificate"><x-athar.kicker>الشهادة القادمة</x-athar.kicker>
                @if($certificates->isNotEmpty())<p class="small">لديك {{ $certificates->count() }} شهادة. <a href="{{ route('app.progress') }}">اعرضها</a></p>@endif
                @if($nextCertificate)<p class="small"><b>{{ $nextCertificate['course']->title }}</b></p><x-athar.progress :value="$nextCertificate['progress']['percent']" :done="$nextCertificate['progress']['done']" :total="$nextCertificate['progress']['total']" /><p class="small muted" style="margin-top:6px">تتطلب: إكمال الدروس + نتيجة واقعية واحدة {{ $hasRealResult ? '✓' : '(لم تُسجَّل بعد)' }}</p>@else<p class="muted small">ابدأ مساراً لتظهر شهادتك القادمة.</p>@endif
            </div>
            <div class="card" id="projects"><x-athar.kicker>مشاريعي</x-athar.kicker>
                <ul class="list">@foreach($workspaces as $workspace)@foreach($workspace->projects as $project)<li><a href="{{ route('app.projects.show', $project) }}">{{ $project->name }}</a><span class="muted small">{{ ! empty($project->profile['offer']) ? 'ملف جاهز' : 'بلا ملف' }}</span></li>@endforeach @endforeach</ul>
                @if($workspaces->isNotEmpty())<form method="post" action="{{ route('app.projects.store') }}" class="stack" style="margin-top:8px">@csrf<input type="hidden" name="workspace_id" value="{{ $workspaces->first()->id }}"><div class="field"><label class="sr-only" for="project_name">اسم المشروع</label><input id="project_name" name="name" required maxlength="120" placeholder="مشروع جديد"></div><x-athar.button type="submit" variant="secondary" block>إنشاء مشروع</x-athar.button></form>@endif
            </div>
        </aside>
    </div>
</div></section>
@endsection
