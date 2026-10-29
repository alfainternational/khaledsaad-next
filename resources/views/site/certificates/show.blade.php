@extends('layouts.platform')
@section('title', 'التحقق من شهادة — أثر')
@section('description', 'تحقق من إصدار شهادة إكمال مسار تعليمي على منصة أثر وحالتها الحالية.')
@section('noindex', '1')
@section('content')
<section class="page-hero"><div class="narrow"><x-athar.breadcrumbs :items="[['الأكاديمية', route('academy.index')], ['التحقق من شهادة', null]]" /><span class="kicker">سجل الشهادات</span><h1>التحقق من شهادة</h1></div></section>
<section class="section-tight"><div class="narrow">
@if($certificate)<article class="card certificate"><span class="certificate-status {{ $certificate->revoked_at ? 'invalid' : 'valid' }}">{{ $certificate->revoked_at ? 'شهادة ملغاة' : 'شهادة صالحة' }}</span>
<p class="kicker">أثر · تعلّم. طبّق. قِس الأثر.</p><h2>{{ $certificate->user->name }}</h2><p>أكمل مسار</p><h3>{{ $certificate->course->title }}</h3>
<p>تاريخ الإصدار: <time datetime="{{ $certificate->issued_at->toDateString() }}">{{ $certificate->issued_at->format('Y/m/d') }}</time></p>
@if($certificate->revoked_at)<p>أُلغيت صلاحية هذه الشهادة بتاريخ {{ $certificate->revoked_at->format('Y/m/d') }}.</p>@else<p class="muted">تُصدر شهادة الإكمال بعد إنهاء دروس المسار وتسجيل نتيجة تطبيق واقعية. وهي شهادة إكمال من المنصة، وليست اعتمادًا مهنيًا خارجيًا.</p>@endif
<p class="small">رمز التحقق: <code class="verification-code">{{ $certificate->verification_code }}</code></p>
@if($certificate->course->is_published)<a class="btn btn-secondary" href="{{ route('academy.show', $certificate->course) }}">تعرّف على المسار</a>@endif</article>
@else<div class="card"><h2>لم نعثر على شهادة بهذا الرمز</h2><p>تحقق من نسخ الرابط كاملًا، أو تواصل معنا إذا كان لديك رابط إصدار.</p><a class="btn btn-primary" href="{{ route('contact') }}">تواصل معنا</a></div>@endif
</div></section>
@endsection
