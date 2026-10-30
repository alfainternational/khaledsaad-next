@extends('layouts.platform')
@section('title', 'التحقق من شهادة — أثر')
@section('description', 'تحقق من إصدار شهادة إكمال مسار تعليمي على منصة أثر وحالتها الحالية.')
@section('noindex', '1')
@section('body_class', 'certificate-page')
@if($certificate)
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! json_encode(array_filter([
    '@'.'context' => 'https://schema.org',
    '@'.'type' => 'EducationalOccupationalCredential',
    'name' => 'شهادة إكمال مسار: '.$certificate->course->title,
    'credentialCategory' => 'certificate',
    'recognizedBy' => ['@'.'type' => 'Organization', 'name' => config('platform.name'), 'url' => route('home')],
    'dateCreated' => $certificate->issued_at->toDateString(),
    'about' => array_filter(['@'.'type' => 'Course', 'name' => $certificate->course->title, 'url' => $certificate->course->is_published ? route('academy.show', $certificate->course) : null]),
    'url' => route('certificates.show', $certificate->verification_code),
    'validFor' => $certificate->revoked_at ? null : 'P100Y',
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush
@endif
@section('content')
<section class="page-hero no-print"><div class="narrow"><x-athar.breadcrumbs :items="[['الأكاديمية', route('academy.index')], ['التحقق من شهادة', null]]" /><span class="kicker">سجل الشهادات</span><h1>التحقق من شهادة</h1></div></section>
<section class="section-tight"><div class="narrow">
@if($certificate)
@php($verifyUrl = route('certificates.show', $certificate->verification_code))
<article class="card certificate {{ $certificate->revoked_at ? 'is-revoked' : '' }}" id="certificate">
    <header class="certificate-head">
        <span class="brand" aria-label="أثر"><span class="brand-mark">أ</span><span>أثر</span></span>
        <span class="certificate-status {{ $certificate->revoked_at ? 'invalid' : 'valid' }}">{{ $certificate->revoked_at ? 'شهادة ملغاة' : 'شهادة صالحة' }}</span>
    </header>
    <p class="kicker">شهادة إكمال مسار</p>
    <p class="certificate-intro">تشهد منصة أثر بأن</p>
    <h2 class="certificate-name">{{ $certificate->user->name }}</h2>
    <p class="certificate-intro">أكمل مسار</p>
    <h3 class="certificate-course">{{ $certificate->course->title }}</h3>
    <p class="certificate-date">تاريخ الإصدار: <time datetime="{{ $certificate->issued_at->toDateString() }}">{{ $certificate->issued_at->format('Y/m/d') }}</time></p>
    @if($certificate->revoked_at)<p class="certificate-revoked">أُلغيت صلاحية هذه الشهادة بتاريخ {{ $certificate->revoked_at->format('Y/m/d') }}.</p>@else<p class="muted small certificate-note">تُصدر شهادة الإكمال بعد إنهاء دروس المسار وتسجيل نتيجة تطبيق واقعية. وهي شهادة إكمال من المنصة، وليست اعتمادًا مهنيًا خارجيًا.</p>@endif
    <footer class="certificate-foot">
        <p>رمز التحقق: <code class="verification-code">{{ $certificate->verification_code }}</code></p>
        <p>رابط التحقق: <span class="verification-url">{{ $verifyUrl }}</span></p>
        <p>أثر · تعلّم. طبّق. قِس الأثر. · {{ config('platform.public_host') }}</p>
    </footer>
</article>
<div class="btn-row certificate-actions no-print">
    <button class="btn btn-primary" type="button" data-print-certificate>طباعة / حفظ PDF</button>
    @if($certificate->course->is_published)<a class="btn btn-secondary" href="{{ route('academy.show', $certificate->course) }}">تعرّف على المسار</a>@endif
</div>
@else<div class="card"><h2>لم نعثر على شهادة بهذا الرمز</h2><p>تحقق من نسخ الرابط كاملًا، أو تواصل معنا إذا كان لديك رابط إصدار.</p><a class="btn btn-primary" href="{{ route('contact') }}">تواصل معنا</a></div>@endif
</div></section>
@endsection
@if($certificate)
@push('scripts')
<script nonce="{{ $cspNonce }}">(function(){var b=document.querySelector('[data-print-certificate]');b&&b.addEventListener('click',function(){window.print()})})();</script>
@endpush
@endif
