@extends('layouts.platform')
@section('title', $output->title.' — أصل مشترك — أثر')
@section('noindex', '1')
@section('content')
<section class="page-hero"><div class="shell">
    <span class="kicker">أصل مشترك للقراءة</span>
    <h1>{{ $output->title }}</h1>
    <p class="lead">{{ \App\Domain\Lab\Services\LabCatalog::label((string) $output->output_type) }} · الإصدار v{{ $output->version }} · شُورك {{ $output->shared_at?->format('Y/m/d') }}@if($output->tool) · أُنشئ بأداة «{{ $output->tool->name }}»@endif</p>
</div></section>
<section class="section-tight"><div class="shell narrow">
    <x-athar.asset-card id="asset" kicker="أصل من منصة أثر" :title="$output->title" :type="$output->output_type" :markdown="$output->body_markdown" :quality="$output->quality_report" :currentVersion="$output->version" :filename="$output->output_type" />
    <div class="cta-band" style="margin-top:24px"><div><h2 style="font-size:22px">أنشئ أصلك أنت في دقائق</h2><p style="margin:0">المختبر يعمل بلا ذكاء اصطناعي وبلا تسجيل: أدخل بياناتك واخرج بأصل مفحوص.</p></div><x-athar.button :href="$output->tool ? route('tools.show', $output->tool) : route('tools.index')">افتح الأداة</x-athar.button></div>
</div></section>
@endsection
@push('scripts')
@include('partials.asset-actions-script')
@endpush
