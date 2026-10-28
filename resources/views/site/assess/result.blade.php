@extends('layouts.platform')
@section('title', 'خريطة أولويات مشروعك — أثر')
@section('noindex', '1')
@section('content')
@php($snap = $result->project_snapshot)
<section class="page-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['شخّص مشروعك', route('assess.start')], ['خريطة أولوياتك', null]]" />
    @include('site.assess.stages')
    <x-athar.kicker>التشخيص</x-athar.kicker>
    <h1><x-athar.level :level="$levels[$result->level]" /> {{ $result->overall }}/100</h1>
    <p>{{ $diagnosis }}</p>
</div></section>
<section class="section"><div class="shell result-layout" data-token="{{ $result->session_token }}" id="assess-result">
    <div class="stack" style="gap:28px">
        <x-athar.flash />
        <x-athar.asset-card id="priorities" kicker="أصلك الأول" title="خريطة الأولويات" type="خريطة أولويات" :markdown="$markdown" filename="athar-priorities" :member="$viewer->isMember()" :saveUrl="null">
            <div class="asset-body" style="padding-top:0">
                <div class="muted small">هذه الخريطة أُنشئت من إجاباتك وبيانات مشروعك. انسخها أو نزّلها بلا تسجيل؛ احفظها بحساب لتتحول إلى ملف مشروعك.</div>
                @foreach($result->priorities as $i => $p)
                <div class="priority"><span class="when">{{ $i + 1 }} · {{ $p['when'] }}</span><div><h4>{{ $p['title'] }}</h4><p>{{ $p['reason'] }}</p>@if($p['link'])<a class="btn btn-text" href="{{ $p['link'] }}">{{ $p['link_label'] }} ←</a>@endif</div></div>
                @endforeach
                @if(($result->priorities[0]['link'] ?? null))<div class="btn-row" style="margin-top:12px"><x-athar.button href="{{ $result->priorities[0]['link'] }}">ابدأ بالأولوية 1</x-athar.button></div>@endif
            </div>
        </x-athar.asset-card>

        <div class="card">
            <x-athar.kicker>أبعادك مقابل مشاريع مشابهة</x-athar.kicker>
            <h2 style="font-size:22px">خمسة أبعاد</h2>
            @if($benchmark)<p class="muted small">العلامة الداكنة = متوسط {{ $benchmark['sample'] }} مشروعاً مشابهاً (نفس الفئة والميزانية).</p>@else<p class="muted small">المقارنة المرجعية تظهر عند توفر 30 مشروعاً مشابهاً (المتاح الآن: {{ $similar }}).</p>@endif
            <div class="dim-bars">
                @foreach($dimensions as $key => $label)
                @php($score = (int) ($result->scores[$key] ?? 0))
                <div class="dim-bar"><span>{{ $label }} @if($key === $result->weakest_dimension)<span class="card-tag">الأضعف</span>@endif</span><div class="track"><div class="fill" style="width:{{ $score }}%"></div>@if($benchmark)<span class="mark" style="inset-inline-start:{{ (int) $benchmark['scores'][$key] }}%" title="متوسط المشابهين {{ $benchmark['scores'][$key] }}"></span>@endif</div><b>{{ $score }}</b></div>
                @endforeach
            </div>
        </div>

        <div class="card" id="impact">
            <x-athar.kicker>أثرك يُقاس هنا</x-athar.kicker>
            <div class="impact-line"><span>اليوم: {{ $levels[$result->level] }} · القياس</span><b>{{ $result->overall }}</b><span>← بعد المسار:</span>@if($previous)<b>{{ $result->overall - $previous->overall >= 0 ? '+' : '' }}{{ $result->overall - $previous->overall }}</b><span class="muted small">مقارنة بتشخيصك السابق ({{ $previous->overall }})</span>@else<span class="q">؟</span>@endif</div>
            @if($improvement && $improvement['sample'] >= 10)<p class="muted">متوسط تحسّن من أكملوا المسار الأول: +{{ $improvement['points'] }} نقطة ({{ $improvement['sample'] }} متعلماً).</p>@else<p class="muted">متوسط التحسّن يظهر عندما يُكمل 10 متعلمين مساراً ويعيدون التشخيص.</p>@endif
            @if($canReassess)<x-athar.button variant="secondary" href="{{ route('assess.start') }}">أعد التشخيص الآن</x-athar.button>@else<p class="small muted">إعادة التشخيص متاحة بعد إكمال مسار كامل.</p>@endif
        </div>
    </div>
    <aside class="side stack">
        @if(! $viewer->isMember())
        <x-athar.gate title="احفظ الخريطة بحساب" text="ملف مشروعك جاهز من إجاباتك: الدروس ستُبنى عليه والأصول تتراكم فيه." cta="أنشئ حساباً واحفظ الخريطة" :href="route('register', ['assessment' => $result->session_token])" :benefits="['ملف المشروع جاهز', 'الدروس على موقفك', 'الأثر يُقاس بعد المسار']" />
        @elseif(! $isOwner)
        <form method="post" action="{{ route('assess.claim') }}" class="card">@csrf<input type="hidden" name="token" value="{{ $result->session_token }}"><h3>احفظ الخريطة في حسابك</h3><p class="muted small">تُحدَّث بيانات مشروعك من هذه الإجابات.</p><x-athar.button type="submit" block>احفظ في مشروعي</x-athar.button></form>
        @else
        <div class="card"><h3>محفوظة في حسابك</h3><p class="muted small">ملف مشروعك محدّث من هذه الإجابات.</p><x-athar.button variant="secondary" href="{{ route('dashboard') }}" block>لوحتي</x-athar.button></div>
        @endif
        @if($program)
        <div class="card"><x-athar.kicker>البرنامج المقترح</x-athar.kicker><h3>{{ $program->title }}</h3>
            <ol class="program-list" style="margin-top:8px">@foreach($program->courses->take(4) as $course)<li><b>{{ $loop->iteration }}</b><div><div>{{ $course->title }}</div><div class="small muted">تخرج بـ: {{ $course->outcome }}</div></div>@if(! $course->is_published)<span class="soon">قريباً</span>@endif</li>@endforeach</ol>
            <a class="btn btn-text" href="{{ route('academy.index') }}#program-{{ $result->audience }}">البرنامج كاملاً ←</a>
        </div>
        @endif
        @if($tool)<div class="card"><x-athar.kicker>أداة للأولوية 1</x-athar.kicker><h3>{{ $tool->name }}</h3><p class="muted small">{{ $tool->description }}</p><x-athar.button variant="secondary" href="{{ route('tools.show', $tool) }}" block>افتحها مجاناً</x-athar.button></div>@endif
    </aside>
</div></section>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">
(function(){
  var el=document.getElementById('assess-result');
  try{localStorage.setItem('athar-assessment',JSON.stringify({token:el.dataset.token,expires:Date.now()+7*864e5}))}catch(e){}
  @if($viewer->isMember() && ! $isOwner)
  fetch('{{ route('assess.claim') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({token:el.dataset.token})}).catch(function(){});
  @endif
})();
</script>
@endpush
