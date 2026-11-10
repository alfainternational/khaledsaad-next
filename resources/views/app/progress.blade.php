@extends('layouts.platform')
@section('title', 'تقدمي — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['لوحتي', route('dashboard')], ['تقدمي', null]]" />
    <h1>تقدمي</h1>
    <p class="muted">{{ $program?->title ?? 'البرنامج' }} · {{ $realResults }} نتيجة واقعية مسجّلة</p>
</div></section>
<section class="section-tight"><div class="shell dash-grid">
    <div class="stack" style="gap:24px">
        <div class="card"><x-athar.kicker>المسارات بترتيبها</x-athar.kicker>
            <ol class="program-list" style="margin-top:8px">@foreach($courses as $i => $c)<li><b>{{ $i + 1 }}</b><div>@if($c['course']->is_published)<a href="{{ route('academy.show', $c['course']) }}">{{ $c['course']->title }}</a>@else{{ $c['course']->title }}@endif @if($c['progress'])<x-athar.progress :value="$c['progress']['percent']" :done="$c['progress']['done']" :total="$c['progress']['total']" />@endif</div>@if(! $c['course']->is_published)<span class="soon">قريباً</span>@elseif($c['progress']['is_complete'])<span style="color:var(--success)">مكتمل ✓</span>@endif</li>@endforeach</ol>
        </div>
        <div class="card"><x-athar.kicker>الدروس المكتملة</x-athar.kicker>
            @if($completed->isEmpty())<p class="muted">لا دروس مكتملة بعد. الإكمال = حفظ الأصل + الإجابة عن أسئلة القرار.</p>@else
            <x-athar.table caption="الدروس المكتملة"><thead><tr><th>الدرس</th><th>المسار</th><th>التاريخ</th></tr></thead><tbody>@foreach($completed as $row)<tr><td><a href="{{ route('academy.lesson', [$row->course_slug, $row->lesson_slug]) }}">{{ $row->lesson }}</a></td><td>{{ $row->course }}</td><td>{{ \Illuminate\Support\Carbon::parse($row->completed_at)->format('Y/m/d') }}</td></tr>@endforeach</tbody></x-athar.table>@endif
        </div>
    </div>
    <aside class="stack">
        <div class="card" id="certificates"><x-athar.kicker>الشهادات</x-athar.kicker>
            @if($certificates->isEmpty() && $pendingCertificates->isEmpty())<p class="muted small">تُصدر شهادة المسار عند إكمال كل دروسه وتسجيل نتيجة واقعية واحدة على الأقل، وهي من مزايا الخطة الاحترافية.</p>@endif
            @if($certificates->isNotEmpty())<ul class="list">@foreach($certificates as $cert)<li><span>{{ $cert->title }}<br><a class="text-link" href="{{ route('certificates.show', $cert->verification_code) }}">{{ $cert->revoked_at ? 'شهادة ملغاة — عرض الحالة' : 'عرض الشهادة والتحقق' }}</a><br><span class="small muted">رمز التحقق: <code class="verification-code">{{ $cert->verification_code }}</code></span></span><span class="when">{{ \Illuminate\Support\Carbon::parse($cert->issued_at)->format('Y/m/d') }}</span></li>@endforeach</ul>@endif
            @if($pendingCertificates->isNotEmpty())<div class="pending-certificates" data-pending-certificates><p class="small"><strong>شهادة مستحقة:</strong> أكملت {{ $pendingCertificates->count() === 1 ? 'مسار «'.$pendingCertificates->first()->title.'»' : $pendingCertificates->count().' مسارات' }} بنتيجة واقعية. الشهادات من مزايا الخطة الاحترافية؛ تُصدر فوراً عند تفعيلها.</p><a class="btn btn-primary" href="{{ route('app.billing') }}">فعّل الخطة الاحترافية</a></div>@endif
            @if(! $hasPaidPlan && $certificates->isNotEmpty())<p class="small muted">شهاداتك الصادرة تبقى صالحة وقابلة للتحقق العام.</p>@endif
        </div>
    </aside>
</div></section>
<section class="section-tight" id="remeasure"><div class="shell">
    <x-athar.kicker>الحلقة المغلقة</x-athar.kicker>
    <h2 style="font-size:24px">هل تحرّك الرقم؟</h2>

    @if($remeasure)
        @php($d = $remeasure['overall_delta'])
        <div class="card">
            <div class="split">
                <div>
                    <p class="muted small" style="margin:0">{{ $remeasure['baseline']->created_at->format('Y/m/d') }} ← {{ $remeasure['latest']->created_at->format('Y/m/d') }}</p>
                    <h3 style="font-size:28px;margin:4px 0">{{ $remeasure['baseline']->overall }} <span class="muted">←</span> {{ $remeasure['latest']->overall }}
                        <span class="card-tag">{{ $d > 0 ? '+' : '' }}{{ $d }} نقطة</span>
                    </h3>
                </div>
            </div>
            <p style="margin:10px 0 14px"><b>القراءة:</b> {{ $remeasure['verdict'] }}</p>

            <x-athar.table caption="حركة البنود بين التشخيصين">
                <thead><tr><th>البند</th><th>كان</th><th>صار</th><th>الحركة</th></tr></thead>
                <tbody>
                    @foreach($remeasure['rows'] as $row)
                    <tr data-dimension="{{ $row['key'] }}">
                        <td>{{ $row['label'] }}@if($row['was_weakest']) <span class="card-tag">كانت الأولوية</span>@endif</td>
                        <td>{{ $row['from'] }}</td>
                        <td>{{ $row['to'] }}</td>
                        <td class="{{ $row['delta'] > 0 ? 'diff-add' : ($row['delta'] < 0 ? 'diff-del' : 'muted') }}">{{ $row['delta'] > 0 ? '+' : '' }}{{ $row['delta'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </x-athar.table>
        </div>
    @elseif($baseline)
        <div class="card placeholder-card">
            <p class="muted" style="margin:0 0 8px">شخّصت مشروعك مرة واحدة ({{ $baseline->overall }}/100 في {{ $baseline->created_at->format('Y/m/d') }}). المقارنة تحتاج تشخيصاً ثانياً — والتشخيص وحده لا يحرّك رقماً، التنفيذ يفعل.</p>
            <x-athar.button :href="route('assess.start')">أعد التشخيص</x-athar.button>
        </div>
    @else
        <div class="card placeholder-card">
            <p class="muted" style="margin:0 0 8px">لم تشخّص مشروعك بعد. التشخيص هو نقطة الصفر التي تُقاس عليها كل حركة لاحقة.</p>
            <x-athar.button :href="route('assess.start')">ابدأ التشخيص</x-athar.button>
        </div>
    @endif

    @if($nextPoint)
        <p class="small muted" style="margin-top:10px">نقطة القياس التالية بعد {{ $nextPoint->days }} يوماً من تشخيصك: {{ $nextPoint->due_at->isPast() ? 'مستحقّة الآن' : $nextPoint->due_at->diffForHumans() }}.</p>
    @endif
</div></section>

@endsection
