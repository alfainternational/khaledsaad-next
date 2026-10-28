@extends('layouts.platform')
@section('title', 'ماذا تفعل الآن؟ — تشخيص مشروعك — أثر')
@section('noindex', '1')
@section('content')
<section class="page-hero"><div class="narrow">
    <x-athar.breadcrumbs :items="[['شخّص مشروعك', route('assess.start')], ['ماذا تفعل الآن؟', null]]" />
    <x-athar.kicker>المرحلة 2</x-athar.kicker>
    <h1>ماذا تفعل الآن؟</h1>
    <p>ثمانية مواقف مصوغة من بيانات مشروعك. اختر الأقرب إلى ما تفعله فعلاً، لا ما تتمناه؛ لا توجد إجابة «ترسّب».</p>
</div></section>
<section class="section"><div class="narrow">
    @include('site.assess.stages')
    <x-athar.flash />
    @if($similar > 0)<span class="similar-bar">{{ number_format($similar) }} {{ $audience === 'owner' ? 'صاحب مشروع' : 'مسوّق' }} مشابه أجروا هذا التشخيص</span>@endif
    <form method="post" action="{{ route('assess.submit') }}" id="assess-questions" data-total="{{ $questions->count() }}">
        @csrf
        <x-athar.progress id="aq-progress" :value="0" :done="0" :total="$questions->count()" unit="أسئلة" />
        <div style="height:16px"></div>
        @foreach($questions as $i => $q)
        <fieldset class="aq" data-index="{{ $i }}" data-active="{{ $i === 0 ? 'true' : 'false' }}">
            <legend class="sr-only">السؤال {{ $i + 1 }}</legend>
            <span class="dim">البُعد: {{ $dimensions[$q->dimension] ?? $q->dimension }} · {{ $i + 1 }} من {{ $questions->count() }}</span>
            <h2>{{ $q->rendered }}</h2>
            <ul class="aq-options">
                @foreach($q->options as $oi => $opt)
                <li><label class="aq-option"><input type="radio" name="q[{{ $q->id }}]" value="{{ $oi }}" required @checked((string) old('q.'.$q->id) === (string) $oi)><span>{{ $opt['label'] }}</span></label></li>
                @endforeach
            </ul>
        </fieldset>
        @endforeach
        <div class="btn-row"><button class="btn btn-secondary" type="button" id="aq-prev" hidden>السابق</button><x-athar.button type="submit" id="aq-submit">اعرض خريطة أولوياتي</x-athar.button></div>
    </form>
</div></section>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">
(function(){
  var form=document.getElementById('assess-questions'),sets=[].slice.call(form.querySelectorAll('.aq')),total=sets.length,cur=0;
  var prev=document.getElementById('aq-prev'),submit=document.getElementById('aq-submit'),prog=document.getElementById('aq-progress');
  function answered(){return form.querySelectorAll('.aq input:checked').length}
  function render(){
    sets.forEach(function(s,i){s.dataset.active=String(i===cur)});
    prev.hidden=cur===0;
    var n=answered();prog.querySelector('.progress-bar').style.width=Math.round(n/total*100)+'%';prog.setAttribute('aria-valuenow',Math.round(n/total*100));prog.querySelector('span').textContent=Math.round(n/total*100)+'٪ · '+n+' من '+total+' أسئلة';
    submit.hidden=!(cur===total-1||n===total);
  }
  sets.forEach(function(s,i){s.addEventListener('change',function(){if(i<total-1){setTimeout(function(){cur=i+1;render();sets[cur].scrollIntoView({behavior:'smooth',block:'start'})},180)}else{render()}})});
  prev.addEventListener('click',function(){cur=Math.max(0,cur-1);render()});
  render();
})();
</script>
@endpush
