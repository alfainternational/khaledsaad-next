@extends('layouts.platform')
@section('title', $tool->name.' — المختبر — أثر')
@section('description', $tool->description)
@section('content')
<section class="page-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['المختبر', route('tools.index')], [$tool->name, null]]" />
    <span class="kicker">{{ $tool->category }} · حاسبة مجانية</span>
    <h1>{{ $tool->name }}</h1>
    <p class="lead">{{ $tool->description }}</p>
    <nav class="filters" aria-label="الحاسبات">@foreach($calculators as $c)<a href="{{ route('tools.calculator', $c->slug) }}" @if($c->slug === $tool->slug) aria-current="true" @endif>{{ $c->name }}</a>@endforeach</nav>
</div></section>
<section class="section-tight"><div class="shell tool-layout calc-layout" id="calc" data-calc="{{ $tool->slug }}">
    <form class="lab" method="get" action="{{ route('tools.calculator', $tool->slug) }}" id="calc-form">
        <h2 style="font-size:22px">الأرقام</h2>
        <div class="lab-fields">
            @foreach($tool->fields() as $field)
            <div class="field"><label for="f-{{ $field['key'] }}">{{ $field['label'] }}</label><input id="f-{{ $field['key'] }}" name="{{ $field['key'] }}" type="number" step="any" min="0" value="{{ $input[$field['key']] }}" data-calc-input="{{ $field['key'] }}"></div>
            @endforeach
        </div>
        <div class="btn-row"><button class="btn btn-secondary" type="submit">احسب</button><span class="small muted">النتيجة تتحدث فوراً وأنت تكتب؛ زر «احسب» للمتصفحات بلا JavaScript.</span></div>
    </form>
    <div class="stack" style="gap:16px">
        <div class="card calc-result" id="calc-result" data-calc-result>
            <x-athar.kicker>{{ $result['label'] }}</x-athar.kicker>
            <div class="calc-headline" data-headline>{{ $result['headline'] }}</div>
            <table class="table calc-table"><tbody data-rows>@foreach($result['rows'] as [$k, $v])<tr><th scope="row">{{ $k }}</th><td>{{ $v }}</td></tr>@endforeach</tbody></table>
            <h3 style="font-size:17px;margin-top:12px">ماذا يعني الرقم</h3><p data-meaning>{{ $result['meaning'] }}</p>
            <h3 style="font-size:17px">المعيار المرجعي</h3><p class="muted small" data-benchmark>{{ $result['benchmark'] }}</p>
            <textarea class="sr-only" id="calc-copy" readonly aria-hidden="true" tabindex="-1">{{ $result['label'] }}: {{ $result['headline'] }}
@foreach($result['rows'] as [$k, $v]){{ $k }}: {{ $v }}
@endforeach{{ $result['meaning'] }}</textarea>
            <div class="btn-row"><button class="btn btn-secondary" type="button" data-copy-target="#calc-copy">نسخ النتيجة</button></div>
        </div>
        <div class="side-box"><h3>كيف تُستخدم مع بقية المختبر</h3><p class="small muted" style="margin:0">احسب الرقم هنا، ثم ضعه في <a href="{{ route('tools.show', 'campaign-brief-builder') }}">موجز الحملة</a> كقاعدة إيقاف، أو جرّبه في <a href="{{ route('tools.simulator') }}">محاكي الحملة</a> قبل الإنفاق.</p></div>
    </div>
</div></section>
@endsection
@push('scripts')
@include('partials.asset-actions-script')
<script nonce="{{ $cspNonce }}">
(function(){
  var root=document.getElementById('calc'),slug=root.dataset.calc,inputs={};
  document.querySelectorAll('[data-calc-input]').forEach(function(i){inputs[i.dataset.calcInput]=i});
  var fmt=function(n,d){return Number(n).toLocaleString('en-US',{maximumFractionDigits:d===undefined?0:d,minimumFractionDigits:d||0})};
  var val=function(k){var v=parseFloat(inputs[k]&&inputs[k].value);return isNaN(v)?0:v};
  // Same formulas as App\Domain\Lab\Services\Calculators (server) — keep both in sync.
  var calc={
    'cac':function(){var s=val('spend'),c=val('customers'),cac=c>0?s/c:null;return {h:cac===null?'—':fmt(cac,1)+' ريال',rows:[['الإنفاق',fmt(s)+' ريال'],['العملاء الجدد',fmt(c)],['CAC',cac===null?'—':fmt(cac,1)+' ريال']],m:cac===null?'أدخل عدد عملاء أكبر من صفر.':'كل عميل جديد كلّفك '+fmt(cac,1)+' ريالاً تسويقياً. الرقم بلا معنى وحده: قارنه بهامش الربح من العميل الواحد وبقيمته الدائمة (LTV).'}},
    'ltv':function(){var a=val('avg_order'),p=val('orders_per_year'),y=val('years'),m=val('margin')/100,cac=val('cac'),ltv=a*p*y*m,r=cac>0?ltv/cac:null;var v=r===null?'':(r>=3?'نسبة صحية (≥ 3).':(r>=1?'نسبة ضعيفة (1–3): تربح لكن النمو بطيء ومكلف.':'تخسر على كل عميل: خفّض CAC أو ارفع التكرار والهامش.'));return {h:fmt(ltv)+' ريال',rows:[['الإيراد من العميل',fmt(a*p*y)+' ريال'],['هامش الربح',Math.round(m*100)+'٪'],['LTV',fmt(ltv)+' ريال'],['LTV : CAC',r===null?'— (أدخل CAC)':fmt(r,1)+' : 1']],m:'العميل الواحد يترك لك '+fmt(ltv)+' ريالاً ربحاً إجمالياً طوال علاقته بك. '+v+' أكبر رافعة عادةً هي عدد الطلبات في السنة، لا السعر.'}},
    'roas':function(){var rev=val('revenue'),s=val('spend'),m=val('margin')/100,roas=s>0?rev/s:null,be=m>0?1/m:null;var v=roas===null||be===null?'':(roas>=be*1.3?'الإعلانات مربحة بعد التكلفة.':(roas>=be?'على نقطة التعادل تقريباً: أي ارتفاع في التكلفة يحوّلها إلى خسارة.':'الإعلانات تخسر: كل ريال إعلان يعيد أقل من تكلفته بعد هامش الربح.'));return {h:roas===null?'—':fmt(roas,2)+'×',rows:[['الإيراد',fmt(rev)+' ريال'],['الإنفاق',fmt(s)+' ريال'],['ROAS',roas===null?'—':fmt(roas,2)+'×'],['نقطة التعادل',be===null?'—':fmt(be,2)+'× (1 ÷ الهامش)']],m:roas===null?'أدخل إنفاقاً أكبر من صفر.':'كل ريال إعلان أعاد '+fmt(roas,2)+' ريالاً إيراداً. '+v}},
    'conversion-sample':function(){var vis=val('visitors'),con=val('conversions'),lift=val('lift')/100,rate=vis>0?con/vis:null,n=null;if(rate!==null&&rate>0&&rate<1&&lift>0){var d=rate*lift;n=Math.ceil(16*rate*(1-rate)/(d*d))}return {h:rate===null?'—':fmt(rate*100,2)+'٪',rows:[['الزيارات',fmt(vis)],['التحويلات',fmt(con)],['معدل التحويل',rate===null?'—':fmt(rate*100,2)+'٪'],['العينة لكل نسخة',n===null?'—':fmt(n)+' زيارة']],m:rate===null?'أدخل زيارات أكبر من صفر.':'من كل 100 زائر يتحوّل '+fmt(rate*100,1)+'. لكشف تحسين نسبي قدره '+Math.round(lift*100)+'٪ تحتاج '+(n===null?'—':fmt(n))+' زيارة لكل نسخة قبل أن تحكم؛ قبل ذلك الفرق ضجيج.'}}
  };
  function render(){var fn=calc[slug];if(!fn)return;var r=fn();var h=root.querySelector('[data-headline]');h.textContent=r.h;var tb=root.querySelector('[data-rows]');tb.innerHTML='';r.rows.forEach(function(row){var tr=document.createElement('tr'),th=document.createElement('th'),td=document.createElement('td');th.scope='row';th.textContent=row[0];td.textContent=row[1];tr.appendChild(th);tr.appendChild(td);tb.appendChild(tr)});root.querySelector('[data-meaning]').textContent=r.m;
    var copy=document.getElementById('calc-copy');copy.value=root.querySelector('.kicker').textContent+': '+r.h+'\n'+r.rows.map(function(x){return x[0]+': '+x[1]}).join('\n')+'\n'+r.m}
  Object.keys(inputs).forEach(function(k){inputs[k].addEventListener('input',render)});
  document.getElementById('calc-form').addEventListener('submit',function(e){e.preventDefault();render();try{var q=new URLSearchParams();Object.keys(inputs).forEach(function(k){q.set(k,inputs[k].value)});history.replaceState(null,'',location.pathname+'?'+q.toString())}catch(err){}});
})();
</script>
@endpush
