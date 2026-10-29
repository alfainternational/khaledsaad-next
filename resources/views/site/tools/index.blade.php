@extends('layouts.platform')
@section('title', 'المختبر التسويقي — أثر')
@section('description', 'عشرة مولدات تعمل بقوالب بلا ذكاء اصطناعي، أربع حاسبات ومحاكي حملة إعلانية: من السؤال إلى أصل قابل للاستخدام يُحفظ في مشروعك.')
@section('content')
<section class="page-hero tools-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['المختبر', null]]" />
    <span class="kicker">مختبر التسويق</span>
    <h1>من السؤال إلى أصل قابل للاستخدام</h1>
    <p class="lead">كل أداة حلقة في سلسلة: تُغذّى من ملف مشروعك وأصولك السابقة، تفحص مخرجها قبل أن تعتمده، وتغذّي الأداة التالية. تعمل بلا ذكاء اصطناعي وبلا تسجيل.</p>
    <nav class="filters" aria-label="فلاتر المختبر" id="tool-filters">
        <a href="{{ route('tools.index') }}" data-filter="" @if($category === '') aria-current="true" @endif>الكل</a>
        @foreach($categories as $cat)<a href="{{ route('tools.index', ['cat' => $cat]) }}" data-filter="{{ $cat }}" @if($category === $cat) aria-current="true" @endif>{{ $cat }}</a>@endforeach
    </nav>
</div></section>

<section class="section-tight" id="generators"><div class="shell">
    <div class="split"><div><span class="kicker">المولدات</span><h2>عشرة مولدات بقوالب قاعدية</h2><p class="muted">تُدخل بياناتك، تخرج أصلاً منسقاً مع فحص قابلية التنفيذ. الرصيد 0 بلا ذكاء اصطناعي؛ «حسّن بالذكاء الاصطناعي» اختياري بحساب.</p></div></div>
    <div class="grid grid-3 tool-grid" data-tool-grid>
        @foreach($generators as $tool)
        <a class="card is-link tool-card" href="{{ route('tools.show', $tool) }}" data-category="{{ $tool->category }}" @if($category !== '' && $tool->category !== $category) hidden @endif>
            <span class="card-tag">{{ $tool->category }}</span>
            <h3>{{ $tool->name }}</h3>
            <p class="card-desc">{{ $tool->description }}</p>
            <p class="tool-io small">{{ $tool->tagline }}</p>
            <div class="card-meta"><span>الرصيد: <b>0</b> بلا AI{{ $tool->credit_cost > 0 ? ' · '.$tool->credit_cost.' مع التحسين' : '' }}</span><span>→ {{ \App\Domain\Lab\Services\LabCatalog::label((string) $tool->output_type) }}</span></div>
        </a>
        @endforeach
    </div>
</div></section>

<section class="section-tight" id="calculators"><div class="shell">
    <div class="split"><div><span class="kicker">الحاسبات</span><h2>أربع حاسبات بنتيجة فورية</h2><p class="muted">مجانية بلا تسجيل: الرقم، ماذا يعني، ومعياره المرجعي.</p></div></div>
    <div class="grid grid-4 tool-grid" data-tool-grid>
        @foreach($calculators as $tool)
        <a class="card is-link tool-card" href="{{ route('tools.calculator', $tool->slug) }}" data-category="{{ $tool->category }}" @if($category !== '' && $tool->category !== $category) hidden @endif>
            <span class="card-tag">{{ $tool->category }} · حاسبة</span>
            <h3>{{ $tool->name }}</h3>
            <p class="card-desc">{{ $tool->description }}</p>
            <div class="card-meta"><span>مجانية</span><span>بلا إرسال</span></div>
        </a>
        @endforeach
    </div>
</div></section>

@if($simulator)
<section class="section-tight" id="simulator"><div class="shell">
    <a class="card is-link tool-card simulator-card" href="{{ route('tools.simulator') }}" data-category="{{ $simulator->category }}" @if($category !== '' && $simulator->category !== $category) hidden @endif>
        <span class="card-tag">{{ $simulator->category }} · محاكي</span>
        <h2 style="font-size:24px">{{ $simulator->name }}</h2>
        <p class="card-desc">{{ $simulator->description }}</p>
        <p class="tool-io small">{{ $simulator->tagline }}</p>
        <div class="card-meta"><span>قواعد ومعايير مُعلنة في الصفحة</span><span>مجاني</span></div>
    </a>
</div></section>
@endif

<section class="section" id="how"><div class="shell">
    <span class="kicker">كيف يعمل</span><h2>ثلاث نقاط</h2>
    <ol class="steps how-steps">
        <li><b>بلا ذكاء اصطناعي يعمل بالقوالب.</b> كل مولّد يُنشئ أصلاً كاملاً بقواعد مُعلنة ومعايير مرجعية مكتوبة؛ لا شيء يتوقف على مفتاح أو رصيد.</li>
        <li><b>مع الذكاء الاصطناعي يتحسّن.</b> «حسّن بالذكاء الاصطناعي» يصقل الصياغة ويُخصم رصيده مرة واحدة؛ إن تعطّل المزود بقي المخرج القاعدي وعاد الرصيد.</li>
        <li><b>كل مخرج يُحفظ في مشروعك.</b> بإصدارات لا تمحو السابق، وفحص قابلية تنفيذ من ستة بنود، ورابط قراءة قابل للإلغاء، وعند اكتمال السلسلة تتجمّع خطة مشروعك في صفحة.</li>
    </ol>
</div></section>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">
(function(){
  var nav=document.getElementById('tool-filters');if(!nav)return;
  nav.addEventListener('click',function(e){var a=e.target.closest('a[data-filter]');if(!a)return;e.preventDefault();var f=a.dataset.filter;
    nav.querySelectorAll('a').forEach(function(x){if(x===a)x.setAttribute('aria-current','true');else x.removeAttribute('aria-current')});
    document.querySelectorAll('[data-tool-grid] [data-category],.simulator-card').forEach(function(c){c.hidden=f!==''&&c.dataset.category!==f});
    try{history.replaceState(null,'',a.getAttribute('href'))}catch(err){}});
})();
</script>
@endpush
