@extends('layouts.platform')
@section('title', 'غرفة التحكم — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell">
    <div class="split"><div><x-athar.kicker>غرفة التحكم</x-athar.kicker><h1>جودة التعلّم، لا السجلات</h1></div>
        <div class="row">
            <div class="toggle" role="group" aria-label="الفترة"><a href="{{ route('admin.index', ['days' => 7]) }}" @if($days === 7) aria-current="true" @endif>7 أيام</a><a href="{{ route('admin.index', ['days' => 30]) }}" @if($days === 30) aria-current="true" @endif>30 يوماً</a></div>
            <x-athar.button href="{{ route('admin.academy.index') }}" variant="secondary">استوديو الدروس</x-athar.button>
            <x-athar.button href="{{ route('admin.tools.index') }}" variant="secondary">استوديو الأدوات</x-athar.button>
            <x-athar.button href="{{ route('admin.sources.index') }}" variant="secondary">صندوق المصادر</x-athar.button>
            <button class="btn btn-secondary" type="button" id="cmdk-open" aria-controls="cmdk">⌘K بحث</button>
            <form method="post" action="{{ route('admin.preview', ['as' => 'visitor']) }}" id="preview-form" class="row">@csrf<label class="small" for="preview-as">اعرض المنصة كـ</label><select id="preview-as" name="as" class="input" style="min-height:44px;width:auto">@foreach($previews as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><button class="btn btn-secondary" type="submit">اعرض</button></form>
        </div>
    </div>
    @include('admin.partials.nav')
</div></section>
<section class="section-tight"><div class="shell stack" style="gap:28px">
    <x-athar.flash />
    <div id="journey"><div class="split"><h2 style="font-size:22px">أعمدة الرحلة الخمسة</h2><span class="muted small">عدد من وصل · نسبة الانتقال من العمود السابق · الإجراء الأعلى أثراً</span></div>
        <div class="journey">@foreach($journey as $col)<div class="col" data-status="{{ $col['status'] }}"><h3>{{ $col['label'] }}</h3><div class="n">{{ number_format($col['count']) }}</div><div class="rate">{{ $col['rate'] === null ? 'نقطة الدخول' : 'انتقال '.$col['rate'].'٪' }}</div><span class="status">{{ $col['status_label'] }}</span><div class="action"><a href="{{ $col['action'][1] }}">{{ $col['action'][0] }} ←</a></div></div>@endforeach</div>
    </div>

    <div class="card" id="signals"><div class="split"><div><x-athar.kicker>إشارات الجودة</x-athar.kicker><h2 style="font-size:22px">مرتبة بالأثر، تُحسب كل ساعة من السلوك</h2><p class="muted small" style="margin:0">آخر حساب: {{ $computedAt ? \Illuminate\Support\Carbon::createFromTimestamp($computedAt)->diffForHumans() : 'الآن' }} · الحد الأدنى للعينة: {{ config('platform.quality_min_sample') }} متعلمين</p></div><form method="post" action="{{ route('admin.quality.recompute') }}">@csrf<button class="btn btn-secondary" type="submit" id="recompute-signals">أعد الحساب الآن</button></form></div>
        @forelse($signals as $signal)<div class="signal" id="signal-{{ $signal->id }}"><span class="impact" title="الأثر">{{ $signal->impact }}</span><div><h4>{{ $signal->summary }}</h4><p>{{ ['lesson_dropoff' => 'تسرّب داخل درس عند جزء', 'question_wrong_majority' => 'سؤال قرار يُخطئ فيه الأغلبية', 'lab_not_saved' => 'مختبر يولّد ولا يُحفظ مخرجه', 'tool_not_saved' => 'أداة تولّد ولا يُحفظ مخرجها', 'stale_sources' => 'أرقام متقادمة (مصدر أقدم من 12 شهراً)', 'article_without_tool' => 'مادة تجلب زيارات بلا أداة مرتبطة'][$signal->type] ?? $signal->type }} · القيمة {{ $signal->value }}{{ $signal->type === 'stale_sources' ? ' شهراً' : ($signal->type === 'article_without_tool' ? ' زائراً' : '٪') }} · {{ $signal->computed_at->diffForHumans() }}</p></div><div class="row"><a class="btn btn-primary" href="{{ $signal->action_url }}">افتح في مكان المشكلة</a><form method="post" action="{{ route('admin.quality.status', $signal) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="handled"><button class="btn btn-text" type="submit">عُولج</button></form></div></div>
        @empty<p class="muted">لا إشارات مفتوحة. تظهر الإشارات عندما يتجاوز السلوك الحد الأدنى للعينة.</p>@endforelse
    </div>

    <div class="card" id="pipeline"><x-athar.kicker>خط إنتاج الدروس</x-athar.kicker><h2 style="font-size:22px">12 مساراً × دروسها</h2>
        <div class="heatmap">@foreach($pipeline as $row)<div class="heat-row"><span>{{ $row['course']->position }}. {{ $row['course']->title }} @if(! $row['course']->is_published)<span class="muted small">(قريباً)</span>@endif</span><div class="heat-cells">@forelse($row['cells'] as $cell)<a class="heat-cell" data-state="{{ $cell['state'] }}" href="{{ route('admin.academy.lessons.edit', $cell['lesson']) }}" title="{{ $cell['lesson']->title }} — {{ $cell['complete'] }}/{{ $cell['total'] }} أجزاء">{{ $cell['lesson']->position }}</a>@empty<span class="heat-cell" data-state="empty" title="لم يُكتب">—</span><span class="small muted" style="align-self:center">لم يُكتب · <span class="muted">«ولّد الهياكل بالذكاء الاصطناعي للمراجعة» يتطلب مفتاح AI</span></span>@endforelse</div></div>@endforeach</div>
        <div class="legend"><span><i style="background:var(--success)"></i>مكتمل</span><span><i style="background:var(--warning)"></i>بإشارة جودة</span><span><i style="background:var(--muted)"></i>مسودة ناقصة</span><span><i style="border:1px dashed var(--line-strong)"></i>لم يُكتب</span></div>
    </div>

    <div class="grid grid-2">
        <div class="card" id="experiment"><x-athar.kicker>تجربة قيد التشغيل</x-athar.kicker>
            @forelse($experiments as $exp)
                <h2 style="font-size:20px">{{ $exp['name'] }}</h2><p class="muted small">أداة «{{ $exp['tool']->name }}» · المقياس: معدل الحفظ · منذ {{ $exp['started'] }}</p>
                <ul class="list">@foreach($exp['variants'] as $v)<li><span>{{ $v['label'] }} · v{{ $v['version'] }} ({{ $v['weight'] }}٪)</span><span>{{ $v['rate'] === null ? '—' : $v['rate'].'٪' }} <span class="muted small">{{ $v['converted'] }}/{{ $v['exposed'] }}</span></span></li>@endforeach</ul>
                <a class="btn btn-text" href="{{ route('admin.tools.show', $exp['tool']) }}#experiment">افتح في الاستوديو ←</a>
            @empty
                <h2 style="font-size:20px">لا تجربة A/B نشطة</h2><p class="muted small">تجربة واحدة في كل مرة بين إصدارَي قالب أداة، تُقاس بمعدل الحفظ. <a href="{{ route('admin.tools.index') }}">ابدأ واحدة من استوديو الأدوات</a>.</p>
            @endforelse
        </div>
        <div class="card" id="success"><x-athar.kicker>صندوق نجاح المتعلمين</x-athar.kicker><h2 style="font-size:20px">متوقفون 7 أيام وبلاغات</h2>
            <ul class="list">@forelse($success['stalled'] as $u)<li><span>{{ $u->name }} <span class="muted small">{{ $u->email }}</span></span><span class="row"><span class="when">{{ \Illuminate\Support\Carbon::parse($u->last_seen)->diffForHumans() }}</span><a class="btn btn-text" href="mailto:{{ $u->email }}?subject={{ rawurlencode('أصلك ينتظرك في أثر') }}">أصلك ينتظرك</a></span></li>@empty<li class="muted">لا متوقفين الآن.</li>@endforelse</ul>
            <h3 style="font-size:16px;margin-top:12px">بلاغات من داخل الدروس</h3>
            <ul class="list">@forelse($success['reports'] as $r)<li><span>{{ $r->lesson?->title ?? '—' }} · جزء «{{ \App\Domain\Academy\Models\Lesson::PARTS[$r->part] ?? $r->part }}» · {{ ['not_my_case' => 'لا يناسب حالتي', 'wrong_numbers' => 'أرقام غير واقعية', 'unclear' => 'غير واضح', 'other' => 'أخرى'][$r->reason] ?? $r->reason }}@if($r->note) — <span class="muted">{{ Str::limit($r->note, 80) }}</span>@endif</span>@if($r->lesson)<a class="btn btn-text" href="{{ route('admin.academy.lessons.edit', ['lesson' => $r->lesson, 'part' => $r->part]) }}">افتح</a>@endif</li>@empty<li class="muted">لا بلاغات مفتوحة.</li>@endforelse</ul>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card" id="economy"><x-athar.kicker>اقتصاد الرصيد ({{ $days }} يوماً)</x-athar.kicker><ul class="list"><li><span>تشغيلات AI</span><b>{{ $economy['ai_runs'] }}</b></li><li><span>أرصدة مستهلكة</span><b>{{ $economy['credits'] }}</b></li><li><span>الإيراد</span><b>{{ number_format($economy['revenue_cents'] / 100, 2) }} $</b></li><li><span>أصول محفوظة</span><b>{{ $economy['saved'] }}</b></li><li><span>تكلفة كل أصل محفوظ (رصيد)</span><b>{{ $economy['cost_per_saved'] ?? '—' }}</b></li><li><span>الأداة الأغلى</span><b>{{ $economy['top_tool']?->name ?? '—' }} @if($economy['top_tool'])<span class="muted small">({{ $economy['top_tool']->credits }} رصيد / {{ $economy['top_tool']->runs }} تشغيل)</span>@endif</b></li></ul></div>
        <div class="card" id="operations"><x-athar.kicker>التشغيل</x-athar.kicker><ul class="list">
            <li><span>المجدول</span><b>{{ $operations['scheduler_age'] === null ? 'لا نبض' : ($operations['scheduler_age'] < 600 ? 'يعمل' : 'متأخر '.$operations['scheduler_age'].' ث') }}</b></li>
            <li><span>الطابور</span><b>{{ $operations['queue_pending'] }} معلّق · {{ $operations['queue_failed'] }} فاشل</b></li>
            <li><span>النسخة الاحتياطية</span><b>{{ $operations['backup'] ?? 'لا علامة' }}</b></li>
            <li><span>PayPal</span><b>{{ $operations['paypal'] ? 'مهيأ' : 'غير مهيأ' }}</b></li><li><span>AI</span><b>{{ $operations['ai'] ? 'مفتاح موجود' : 'بلا مفتاح' }}</b></li>
            <li><span>أعضاء جدد (30 يوماً)</span><b>{{ $operations['new_users'] }} · تحقق البريد {{ $operations['verified_rate'] === null ? '—' : $operations['verified_rate'].'٪' }}</b></li>
        </ul><div class="row"><a class="btn btn-text" href="{{ route('admin.users.index') }}">الأعضاء والأدوار</a><a class="btn btn-text" href="{{ route('admin.plans.index') }}">الخطط والدفع</a><a class="btn btn-text" href="{{ route('admin.redirects.index') }}">التحويلات</a><a class="btn btn-text" href="{{ route('admin.audit.index') }}">سجل التدقيق</a><a class="btn btn-text" href="{{ route('admin.operations') }}">الصحة والتشغيل ←</a></div></div>
    </div>
</div></section>
<div id="cmdk" class="cmdk" hidden><div><label class="sr-only" for="cmdk-q">ابحث واقفز إلى أي كيان</label><input id="cmdk-q" placeholder="ابحث: درس، مسار، أداة، شاشة إدارة…" autocomplete="off"><ul id="cmdk-list">@foreach($palette as $item)<li><a href="{{ $item['url'] }}"><span>{{ $item['label'] }}</span><small>{{ $item['kind'] }}</small></a></li>@endforeach</ul></div></div>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">
(function(){
  var k=document.getElementById('cmdk'),q=document.getElementById('cmdk-q'),items=[].slice.call(document.querySelectorAll('#cmdk-list li')),open=document.getElementById('cmdk-open');
  function show(){k.hidden=false;k.classList.add('is-open');q.value='';filter();q.focus()}function hide(){k.hidden=true;k.classList.remove('is-open')}
  function filter(){var v=q.value.trim().toLowerCase();items.forEach(function(li){li.hidden=v!==''&&li.textContent.toLowerCase().indexOf(v)===-1})}
  open.addEventListener('click',show);q.addEventListener('input',filter);k.addEventListener('click',function(e){if(e.target===k)hide()});
  document.addEventListener('keydown',function(e){if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();show()}if(e.key==='Escape'&&!k.hidden)hide()});
  var pf=document.getElementById('preview-form'),ps=document.getElementById('preview-as');pf.addEventListener('submit',function(){pf.action=pf.action.replace(/\/[a-z]+$/,'/'+ps.value)});
})();
</script>
@endpush
