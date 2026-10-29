@extends('layouts.platform')
@section('title', $tool->name.' — المختبر — أثر')
@section('description', $tool->description)
@section('content')
<section class="page-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['المختبر', route('tools.index')], [$tool->name, null]]" />
    <span class="kicker">{{ $tool->category }} · محاكي مجاني</span>
    <h1>{{ $tool->name }}</h1>
    <p class="lead">{{ $tool->description }}</p>
</div></section>
<section class="section-tight"><div class="shell tool-layout calc-layout" id="sim" data-benchmarks="{{ json_encode($benchmarks) }}" data-cpl="{{ json_encode(\App\Domain\Assessment\Services\Catalog::COST_PER_LEAD) }}">
    <form class="lab" method="get" action="{{ route('tools.simulator') }}" id="sim-form">
        <h2 style="font-size:22px">ميزانية + جمهور + قناة</h2>
        <div class="lab-fields">
            @foreach($tool->fields() as $field)
            <div class="field"><label for="f-{{ $field['key'] }}">{{ $field['label'] }}</label>
                @if(($field['type'] ?? '') === 'select')<select id="f-{{ $field['key'] }}" name="{{ $field['key'] }}" data-sim-input="{{ $field['key'] }}">@foreach(\App\Domain\Lab\Services\LabCatalog::options($field) as $k => $l)<option value="{{ $k }}" @selected((string) $input[$field['key']] === $k)>{{ $l }}@if(! isset($benchmarks[$k])) (تقريبي)@endif</option>@endforeach</select>
                @else<input id="f-{{ $field['key'] }}" name="{{ $field['key'] }}" type="number" step="any" min="0" value="{{ $input[$field['key']] }}" data-sim-input="{{ $field['key'] }}">@endif
            </div>
            @endforeach
        </div>
        <div class="btn-row"><button class="btn btn-secondary" type="submit">حاكِ الحملة</button><span class="small muted">تتحدث النتيجة فوراً وأنت تغيّر الأرقام.</span></div>
    </form>
    <div class="stack" style="gap:16px">
        <div class="card" id="sim-result">
            <x-athar.kicker>النتائج المتوقعة على {{ $result['channel_label'] }}</x-athar.kicker>
            <x-athar.table caption="نطاق النتائج"><thead><tr><th>المقياس</th><th>التقدير</th><th>النطاق (±30٪)</th></tr></thead><tbody data-sim-rows>@foreach($result['rows'] as [$k, $v, $r])<tr><th scope="row">{{ $k }}</th><td>{{ $v }}</td><td class="muted">{{ $r }}</td></tr>@endforeach</tbody></x-athar.table>
            <h3 style="font-size:17px;margin-top:14px">المخاطر</h3>
            <ul class="risk-list" data-sim-risks>@foreach($result['risks'] as [$level, $risk, $fix])<li data-level="{{ $level }}"><b>{{ $level }}</b><span>{{ $risk }} <em>{{ $fix }}</em></span></li>@endforeach</ul>
        </div>
        <div class="side-box" id="sim-rules"><h3>القواعد والمعايير المُعلنة</h3>
            <ul class="small" style="margin:0 0 8px;padding-inline-start:18px">@foreach($result['rules'] as $rule)<li>{{ $rule }}</li>@endforeach</ul>
            <x-athar.table caption="المعايير المرجعية"><thead><tr><th>القناة</th><th>CPM (ريال)</th><th>CTR</th><th>تكلفة الطلب المرجعية</th></tr></thead><tbody>@foreach($benchmarks as $ch => [$cpm, $ctr])<tr><th scope="row">{{ \App\Domain\Assessment\Services\Catalog::CHANNELS[$ch] ?? $ch }}</th><td>{{ $cpm }}</td><td>{{ number_format($ctr * 100, 1) }}٪</td><td>{{ \App\Domain\Assessment\Services\Catalog::COST_PER_LEAD[$ch] ?? '—' }} ريال</td></tr>@endforeach</tbody></x-athar.table>
            <p class="muted small" style="margin:8px 0 0">افتراضات للمشاريع الصغيرة في السوق السعودي مستندة إلى معايير منشورة (WordStream 2024، أدلة المعلنين لتيك توك وسناب) معدّلة محلياً؛ مصادرها مكتوبة في الكود. عدّلها بأرقامك بعد 14 يوماً.</p>
        </div>
    </div>
</div></section>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">
(function(){
  var root=document.getElementById('sim'),B=JSON.parse(root.dataset.benchmarks),CPL=JSON.parse(root.dataset.cpl),inputs={};
  document.querySelectorAll('[data-sim-input]').forEach(function(i){inputs[i.dataset.simInput]=i});
  var fmt=function(n,d){return Number(n).toLocaleString('en-US',{maximumFractionDigits:d||0,minimumFractionDigits:d||0})};
  var range=function(v,d){return fmt(v*0.7,d)+' – '+fmt(v*1.3,d)};
  // Same rules as App\Domain\Lab\Services\CampaignSimulator (server).
  function simulate(){
    var budget=Math.max(0,parseFloat(inputs.budget.value)||0),aud=Math.max(1,parseFloat(inputs.audience_size.value)||1),ch=inputs.channel.value,cvr=Math.min(100,Math.max(0,parseFloat(inputs.cvr.value)||0))/100;
    var b=B[ch]||B.instagram,cpm=b[0],ctr=b[1],paid=!!B[ch];
    var imp=cpm>0?budget/cpm*1000:0,reach=Math.min(aud,imp/1.8),clicks=imp*ctr,leads=clicks*cvr,cpl=leads>0?budget/leads:null,freq=reach>0?imp/reach:0,daily=budget/30,cov=Math.min(100,reach/aud*100);
    var rows=[['الظهورات (impressions)',fmt(imp),range(imp)],['الوصول (أشخاص)',fmt(reach),range(reach)],['النقرات',fmt(clicks),range(clicks)],['الطلبات المتوقعة',fmt(leads,1),range(leads,1)],['تكلفة الطلب (CPL)',cpl===null?'—':fmt(cpl,1)+' ريال',cpl===null?'—':fmt(cpl/1.3,1)+' – '+fmt(cpl/0.7,1)+' ريال'],['التكرار (ظهور/شخص)',fmt(freq,1),'—'],['الميزانية اليومية',fmt(daily,1)+' ريال','—']];
    var risks=[];
    if(daily<30)risks.push(['عالٍ','الميزانية اليومية أقل من 30 ريالاً: الحملة تبقى في مرحلة التعلم بلا نتائج مستقرة.','ركّز الميزانية في أسبوعين بدل شهر، أو ابدأ بقناة عضوية/مباشرة.']);
    if(freq>3)risks.push(['عالٍ','التكرار '+fmt(freq,1)+' ظهور لكل شخص: إجهاد إعلاني وارتفاع التكلفة بعد الأسبوع الثاني.','وسّع الجمهور إلى '+fmt(imp/1.8/2.5)+' شخصاً على الأقل أو خفّض الميزانية.']);
    if(leads<10)risks.push(['متوسط','أقل من 10 طلبات متوقعة: العينة لا تكفي للحكم على الرسالة أو القناة.','مدّد الفترة أو ارفع الميزانية حتى تبلغ 10 طلبات على الأقل قبل أي قرار.']);
    if(cov>80)risks.push(['متوسط','الوصول يغطي أكثر من 80٪ من الجمهور: ستكرّر على نفس الأشخاص سريعاً.','جهّز جمهوراً ثانياً مشابهاً (lookalike) للأسبوع الثالث.']);
    if(!paid)risks.push(['ملاحظة','القناة المختارة ليست قناة إعلان مدفوع في هذا المحاكي؛ استُخدمت معايير إنستغرام تقريبياً.','اختر إنستغرام أو تيك توك أو سناب أو بحث جوجل أو واتساب لنتيجة أدق.']);
    var bench=CPL[ch]||60;if(cpl!==null)risks.push([cpl<=bench?'منخفض':'متوسط','تكلفة الطلب المتوقعة '+fmt(cpl,1)+' ريال مقابل مرجعية القناة '+bench+' ريالاً.',cpl<=bench?'ضمن المرجعية: التزم بقاعدة القرار بعد 14 يوماً.':'ارفع معدل التحويل (صفحة هبوط أوضح) قبل رفع الميزانية.']);
    var tb=root.querySelector('[data-sim-rows]');tb.innerHTML='';rows.forEach(function(r){var tr=document.createElement('tr');var th=document.createElement('th');th.scope='row';th.textContent=r[0];var td1=document.createElement('td');td1.textContent=r[1];var td2=document.createElement('td');td2.className='muted';td2.textContent=r[2];tr.appendChild(th);tr.appendChild(td1);tr.appendChild(td2);tb.appendChild(tr)});
    var ul=root.querySelector('[data-sim-risks]');ul.innerHTML='';risks.forEach(function(r){var li=document.createElement('li');li.dataset.level=r[0];var b=document.createElement('b');b.textContent=r[0];var s=document.createElement('span');s.textContent=r[1]+' ';var em=document.createElement('em');em.textContent=r[2];s.appendChild(em);li.appendChild(b);li.appendChild(s);ul.appendChild(li)});
    var k=root.querySelector('#sim-result .kicker');if(k)k.textContent='النتائج المتوقعة على '+inputs.channel.options[inputs.channel.selectedIndex].text.replace(' (تقريبي)','');
  }
  Object.keys(inputs).forEach(function(k){inputs[k].addEventListener('input',simulate);inputs[k].addEventListener('change',simulate)});
  document.getElementById('sim-form').addEventListener('submit',function(e){e.preventDefault();simulate();try{var q=new URLSearchParams();Object.keys(inputs).forEach(function(k){q.set(k,inputs[k].value)});history.replaceState(null,'',location.pathname+'?'+q.toString())}catch(err){}});
})();
</script>
@endpush
