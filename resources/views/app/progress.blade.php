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
            @if($certificates->isEmpty())<p class="muted small">تُصدر شهادة المسار عند إكمال كل دروسه وتسجيل نتيجة واقعية واحدة على الأقل.</p>@else<ul class="list">@foreach($certificates as $cert)<li><span>{{ $cert->title }}<br><span class="small muted">رمز التحقق: <code>{{ $cert->verification_code }}</code></span></span><span class="when">{{ \Illuminate\Support\Carbon::parse($cert->issued_at)->format('Y/m/d') }}</span></li>@endforeach</ul>@endif
        </div>
    </aside>
</div></section>
@endsection
