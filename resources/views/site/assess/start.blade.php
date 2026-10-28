@extends('layouts.platform')
@section('title', 'شخّص مشروعك — أثر')
@section('description', 'تشخيص لمشروعك في ثلاث مراحل ينتهي بخريطة أولويات قابلة للنسخ والتنزيل بلا تسجيل.')
@section('content')
@php($c = \App\Domain\Assessment\Services\Catalog::class)
<section class="page-hero"><div class="narrow">
    <x-athar.breadcrumbs :items="[['شخّص مشروعك', null]]" />
    <x-athar.kicker>تشخيص مشروعك</x-athar.kicker>
    <h1>ليس اختبار مستوى، بل تشخيص لمشروعك</h1>
    <p>ثلاث مراحل في عشر دقائق: عن مشروعك، ماذا تفعل الآن، ثم خريطة أولوياتك: أصل أول بلا تسجيل، ويُعاد بعد كل مسار ليُظهر الأثر رقماً.</p>
</div></section>
<section class="section"><div class="narrow">
    @include('site.assess.stages')
    <x-athar.flash />
    <form method="post" action="{{ route('assess.project') }}" class="card" id="assess-project">
        @csrf
        <h2>المرحلة 1: عن مشروعك</h2>
        <p class="muted">حقائق قليلة تُصاغ منها أسئلة المرحلة التالية، وتصبح ملف مشروعك عند التسجيل.</p>
        <div class="field"><label for="audience">أنت</label><select id="audience" name="audience">@foreach($c::AUDIENCES as $key => $label)<option value="{{ $key }}" @selected(old('audience', $audience) === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="offer">ماذا تبيع ولمن؟</label><input id="offer" name="offer" required maxlength="200" value="{{ old('offer', $project['offer'] ?? '') }}" placeholder="مثال: منتجات عناية طبيعية للنساء 25–40 في الرياض"></div>
        <div class="grid grid-2">
            <div class="field"><label for="sector">القطاع</label><select id="sector" name="sector" required>@foreach($c::SECTORS as $key => $label)<option value="{{ $key }}" @selected(old('sector', $project['sector'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="city">المدينة</label><input id="city" name="city" maxlength="60" value="{{ old('city', $project['city'] ?? '') }}" placeholder="الرياض"></div>
        </div>
        <div class="grid grid-2">
            <div class="field"><label for="budget">الميزانية الشهرية للتسويق (ريال)</label><input id="budget" name="budget" type="number" min="0" max="10000000" required value="{{ old('budget', $project['budget'] ?? 2000) }}"></div>
            <div class="field"><label for="channel">القناة الحالية</label><select id="channel" name="channel" required>@foreach($c::CHANNELS as $key => $label)<option value="{{ $key }}" @selected(old('channel', $project['channel'] ?? 'instagram') === $key)>{{ $label }}</option>@endforeach</select></div>
        </div>
        <div class="grid grid-2">
            <div class="field"><label for="goal">الهدف</label><select id="goal" name="goal" required>@foreach($c::GOALS as $key => $label)<option value="{{ $key }}" @selected(old('goal', $project['goal'] ?? 'leads') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="goal_number">الرقم المستهدف شهرياً</label><input id="goal_number" name="goal_number" type="number" min="1" max="1000000" required value="{{ old('goal_number', $project['goal_number'] ?? 60) }}"><span class="hint">طلبات أو عملاء أو مبيعات في الشهر</span></div>
        </div>
        <div class="field"><label for="problem">أكبر مشكلة الآن (اختياري)</label><select id="problem" name="problem"><option value="">—</option>@foreach($c::PROBLEMS as $key => $label)<option value="{{ $key }}" @selected(old('problem', $project['problem'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="btn-row"><x-athar.button type="submit">التالي: ماذا تفعل الآن؟</x-athar.button></div>
    </form>
</div></section>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">(function(){try{var f=document.getElementById('assess-project');var t=JSON.parse(localStorage.getItem('athar-try')||'null');if(t&&f){if(!f.offer.value&&t.offer)f.offer.value=t.offer;if(t.problem&&!f.problem.value)f.problem.value=t.problem;if(t.budget&&f.budget.value==='2000'&&!new URLSearchParams(location.search).has('budget'))f.budget.value=t.budget}}catch(e){}})();</script>
@endpush
