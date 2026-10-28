@extends('layouts.platform')
@section('title', 'أثر — تعلّم. طبّق. قِس الأثر.')
@section('description', 'منصة تعلّم بالممارسة للتسويق والذكاء الاصطناعي: جرّب على مشروعك الآن بلا تسجيل، شخّص مشروعك، واخرج من كل درس بأصل يُقاس في الواقع.')
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! json_encode([['@'.'context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'أثر', 'url' => url('/'), 'logo' => url('/favicon.ico')], ['@'.'context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'أثر', 'url' => url('/'), 'potentialAction' => ['@type' => 'SearchAction', 'target' => route('search').'?q={search_term_string}', 'query-input' => 'required name=search_term_string']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush
@section('content')
@php($problems = \App\Domain\Assessment\Services\Catalog::PROBLEMS)
@php($budgets = \App\Domain\Assessment\Services\Catalog::BUDGETS)
<section class="hero"><div class="shell hero-grid">
    <div>
        <x-athar.kicker>منصة تعلّم بالممارسة للتسويق والذكاء الاصطناعي</x-athar.kicker>
        <h1>تعلّم. طبّق.<br><em>قِس الأثر.</em></h1>
        <p class="lead">كل درس ينتهي بأصل لمشروعك أنت، وكل أصل يُقاس في الواقع.</p>
        <div class="btn-row"><x-athar.button href="{{ route('assess.start') }}">شخّص مشروعك مجاناً</x-athar.button><x-athar.button variant="secondary" href="{{ route('academy.index') }}">تصفّح الأكاديمية</x-athar.button></div>
    </div>
    @if($continue)
    <div class="try-box" id="continue-box">
        <x-athar.kicker>أكمل من حيث توقفت</x-athar.kicker>
        <h2>{{ $continue['lesson']->title }}</h2>
        <p class="muted">{{ $continue['lesson']->course->title }} · الدرس {{ $continue['lesson']->position }} من {{ $continue['progress']['total'] }}</p>
        <x-athar.progress :value="$continue['progress']['percent']" :done="$continue['progress']['done']" :total="$continue['progress']['total']" />
        <div class="btn-row" style="margin-top:14px"><x-athar.button href="{{ route('academy.lesson', [$continue['lesson']->course, $continue['lesson']]) }}">أكمل الدرس</x-athar.button><x-athar.button variant="text" href="{{ route('dashboard') }}">لوحتي</x-athar.button></div>
    </div>
    @else
    <div class="try-box" id="try-box">
        <x-athar.kicker>جرّب على مشروعك الآن</x-athar.kicker>
        <h2>أول خطوة لمشروعك خلال ثانية، بلا تسجيل</h2>
        <p class="muted small">ثلاثة حقول → خطوة مقترحة بسبب ودرس محدد. بقواعد لا ذكاء اصطناعي.</p>
        <form id="try-form" method="get" action="{{ route('home') }}" data-rules="{{ json_encode($rules, JSON_UNESCAPED_UNICODE) }}" data-assess="{{ route('assess.start') }}">
            <div class="fields">
                <div class="field"><label for="try-offer">ماذا تبيع ولمن؟</label><input id="try-offer" name="offer" required maxlength="120" placeholder="مثال: منتجات عناية طبيعية للنساء في الرياض" value="{{ request('offer') }}"></div>
                <div class="field"><label for="try-problem">أكبر مشكلة الآن</label><select id="try-problem" name="problem" required><option value="">اختر…</option>@foreach($problems as $key => $label)<option value="{{ $key }}" @selected(request('problem') === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="field"><label for="try-budget">الميزانية الشهرية للتسويق</label><select id="try-budget" name="budget">@foreach($budgets as $key => $label)<option value="{{ $key }}" @selected(request('budget', '2000') === (string) $key)>{{ $label }}</option>@endforeach</select></div>
            </div>
            <x-athar.button type="submit" block>أعطني أول خطوة</x-athar.button>
        </form>
        <div class="first-step" id="first-step" @if(! $firstStep) hidden @endif aria-live="polite">
            <x-athar.kicker>أصلك الأول</x-athar.kicker>
            <h3 data-step>{{ $firstStep['step'] ?? '' }}</h3>
            <p data-reason>{{ $firstStep['reason'] ?? '' }}</p>
            <p class="small"><b>الدرس:</b> <span data-lesson-title>{{ $firstStep['lesson_title'] ?? '' }}</span></p>
            <div class="btn-row"><a class="btn btn-primary" data-assess-link href="{{ $firstStep ? route('assess.start', ['offer' => request('offer'), 'problem' => request('problem'), 'budget' => request('budget')]) : route('assess.start') }}">أكمل التشخيص</a><a class="btn btn-secondary" data-lesson-link href="{{ $firstStep['lesson_url'] ?? route('academy.index') }}">افتح الدرس</a></div>
        </div>
    </div>
    @endif
</div></section>

<section class="section section-tight" aria-label="أرقام حيّة"><div class="shell">
    <div class="numbers">
        @if($numbers['assets_week'] !== null)<div class="number"><b>{{ number_format($numbers['assets_week']) }}</b><span>أصل أُنشئ هذا الأسبوع</span></div>@endif
        @if($numbers['results'] !== null)<div class="number"><b>{{ number_format($numbers['results']) }}</b><span>نتيجة واقعية سُجّلت</span></div>@endif
        @if($numbers['improvement'] !== null)<div class="number"><b>+{{ $numbers['improvement'] }}</b><span>نقطة متوسط التحسّن بعد المسار الأول</span></div>@endif
        @if($numbers['last_asset'])<div class="number"><b style="font-size:20px">{{ $numbers['last_asset']['type'] }}</b><span>آخر أصل: {{ $numbers['last_asset']['sector'] }} · {{ $numbers['last_asset']['city'] }} · {{ $numbers['last_asset']['when'] }}</span></div>@endif
        @if($numbers['assets_week'] === null && $numbers['results'] === null && $numbers['improvement'] === null && ! $numbers['last_asset'])<div class="number"><b>0</b><span>الأرقام الحيّة تظهر هنا عندما تتجاوز 10 (أصول، نتائج واقعية، متوسط التحسّن)</span></div>@endif
    </div>
</div></section>

<section class="section"><div class="shell">
    <div class="section-heading"><x-athar.kicker>المساران</x-athar.kicker><h2>اختر مسارك</h2></div>
    <div class="audience-grid">
        <a class="audience-card owner" href="{{ route('academy.index') }}#program-owner"><div><h3>أصحاب المشاريع</h3><ol><li>تشخيص مشروعك</li><li>خريطة أولويات</li><li>دروس عن مشروعك</li><li>أثر يُقاس</li></ol></div><span class="arrow">برنامج أصحاب المشاريع ←</span></a>
        <a class="audience-card marketer" href="{{ route('academy.index') }}#program-marketer"><div><h3>المسوّقون والوكالات</h3><ol><li>سلاسل أصول لكل عميل</li><li>مشاركة بروابط للعملاء</li><li>مسارات متقدمة في القياس والأتمتة</li></ol></div><span class="arrow">برنامج المسوّقين ←</span></a>
    </div>
</div></section>

<section class="section section-dark"><div class="shell">
    <div class="section-heading"><x-athar.kicker>النظام المتصل</x-athar.kicker><h2>خمس خطوات، لا تتوقف عند القراءة</h2><p>كل خطوة تقود إلى التالية حتى تصبح المعرفة أصلاً يُقاس.</p></div>
    <ol class="steps"><li><b>1</b><h3>اكتشف</h3><p>تشخيص لمشروعك ينتهي بخريطة أولويات.</p></li><li><b>2</b><h3>تعلّم</h3><p>درس قصير يبدأ بقرارك قبل الشرح.</p></li><li><b>3</b><h3>طبّق</h3><p>مختبر مضمّن يحوّل الدرس إلى أصل.</p></li><li><b>4</b><h3>احفظ</h3><p>الأصل في مشروعك بإصداراته.</p></li><li><b>5</b><h3>قِس</h3><p>التزام واقعي ونتيجة تُسجَّل بعد أسبوع.</p></li></ol>
</div></section>

<section class="section"><div class="shell">
    <div class="section-heading split"><div><x-athar.kicker>المسارات المميزة</x-athar.kicker><h2>تخرج من كل مسار بأصل محدد</h2></div><x-athar.button variant="text" href="{{ route('academy.index') }}">كل المسارات ←</x-athar.button></div>
    <div class="grid grid-3">@foreach($courses as $course)<x-athar.card :title="$course->title" :desc="$course->summary" :tag="$course->level" :href="$course->is_published ? route('academy.show', $course) : null" :soon="! $course->is_published" :number="str_pad($course->position, 2, '0', STR_PAD_LEFT)"><p class="outcome"><b>تخرج بـ:</b> {{ $course->outcome }}</p><x-slot:meta><span>{{ $course->duration_minutes }} دقيقة</span><span>{{ $course->is_published ? 'متاح الآن' : 'قريباً' }}</span></x-slot:meta></x-athar.card>@endforeach</div>
</div></section>

<section class="section section-soft"><div class="shell">
    <div class="section-heading split"><div><x-athar.kicker>المختبر</x-athar.kicker><h2>أدوات تغذّي سلسلة أصولك</h2></div><x-athar.button variant="text" href="{{ route('tools.index') }}">افتح المختبر ←</x-athar.button></div>
    <div class="grid grid-3">@foreach($tools as $tool)<x-athar.card :title="$tool->name" :desc="$tool->description" :tag="$tool->category" :href="route('tools.show', $tool)"><p class="outcome"><b>يغذّي:</b> {{ ['التخطيط' => 'خطة القناة الأولى وخطة التسويق', 'المحتوى' => 'تقويم المحتوى والرسالة الأساسية', 'التحويل' => 'صفحة الهبوط وخطة القياس'][$tool->category] ?? 'الأصول التالية في السلسلة' }}</p><x-slot:meta><span>{{ $tool->credit_cost ? $tool->credit_cost.' رصيد' : 'مجاني بلا AI' }}</span></x-slot:meta></x-athar.card>@endforeach</div>
</div></section>

<section class="section"><div class="shell">
    <div class="section-heading split"><div><x-athar.kicker>من المجلة</x-athar.kicker><h2>اقرأ ثم طبّق بأداة</h2></div><x-athar.button variant="text" href="{{ route('articles.index') }}">كل المواد ←</x-athar.button></div>
    <div class="grid grid-3">@foreach($articles as $article)<x-athar.card heading="h3" :title="$article->title" :desc="$article->excerpt" :tag="$article->eyebrow" :href="route('articles.show', $article)"><p class="outcome"><b>طبّق بـ:</b> {{ $tools->first()?->name ?? 'المختبر' }}</p><x-slot:meta><span>{{ $article->reading_minutes }} دقائق</span><span>{{ $article->published_at?->format('Y/m/d') }}</span></x-slot:meta></x-athar.card>@endforeach</div>
</div></section>

<section class="section section-tight"><div class="shell"><div class="cta-band"><div><x-athar.kicker>دعوة أخيرة</x-athar.kicker><h2>شخّص مشروعك مجاناً</h2><p class="muted" style="margin:0">ثلاث مراحل، عشر دقائق، وتخرج بخريطة أولويات قابلة للتنزيل بلا تسجيل.</p></div><x-athar.button href="{{ route('assess.start') }}">ابدأ التشخيص</x-athar.button></div></div></section>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">
(function(){
  var f=document.getElementById('try-form');if(!f)return;
  var rules=JSON.parse(f.dataset.rules||'{}'),box=document.getElementById('first-step');
  f.addEventListener('submit',function(e){
    var offer=f.offer.value.trim(),problem=f.problem.value,budget=f.budget.value;
    if(!problem)return;
    e.preventDefault();
    var r=rules[problem]||rules.no_target;var v=r&&(budget==='0'&&r.budget_zero?r.budget_zero:(r.default||r));
    if(!v)return;
    var name=offer?offer.slice(0,80):'مشروعك';
    box.querySelector('[data-step]').textContent=v.step.replace('{offer}',name);
    box.querySelector('[data-reason]').textContent=v.reason;
    box.querySelector('[data-lesson-title]').textContent=v.title;
    box.querySelector('[data-lesson-link]').href=v.url;
    var q=new URLSearchParams({offer:offer,problem:problem,budget:budget});
    box.querySelector('[data-assess-link]').href=f.dataset.assess+'?'+q.toString();
    try{localStorage.setItem('athar-try',JSON.stringify({offer:offer,problem:problem,budget:budget,at:Date.now()}))}catch(err){}
    box.hidden=false;box.scrollIntoView({behavior:'smooth',block:'nearest'});
    window.atharEvent&&window.atharEvent('home.first_step',{problem:problem,budget:budget});
  });
})();
</script>
@endpush
