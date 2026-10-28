@extends('layouts.platform')
@section('title', 'خريطة الأولويات — أثر')
@section('noindex', '1')
@section('content')
<section class="section"><div class="narrow">
    <x-athar.breadcrumbs :items="[['شخّص مشروعك', route('assess.start')], ['خريطة أولوياتك', null]]" />
    <div class="card" id="result-missing"><h1 style="font-size:28px">لا توجد خريطة محفوظة لهذا المتصفح</h1><p class="muted">خرائط الزوار تبقى 7 أيام في المتصفح نفسه. ابدأ تشخيصاً جديداً في عشر دقائق.</p><x-athar.button href="{{ route('assess.start') }}">ابدأ التشخيص</x-athar.button></div>
</div></section>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">(function(){try{var s=JSON.parse(localStorage.getItem('athar-assessment')||'null');if(s&&s.token&&s.expires>Date.now()&&!new URLSearchParams(location.search).has('t')){location.replace('{{ route('assess.result') }}?t='+encodeURIComponent(s.token))}}catch(e){}})();</script>
@endpush
