@extends('layouts.platform')
@section('title', 'تواصل مع أثر')
@section('description', 'أرسل استفسارًا أو اقتراحًا لفريق أثر، أو تواصل عبر بريد الدعم لتصحيح مادة أو طلب المساعدة.')
@section('content')
<section class="page-hero"><div class="narrow"><x-athar.breadcrumbs :items="[['تواصل معنا', null]]" /><span class="kicker">نسمع منك</span><h1>تواصل مع أثر</h1><p>سؤال، اقتراح أو تصحيح؟ اكتب لنا ما تحتاج إليه.</p></div></section>
<section class="section-tight"><div class="narrow"><div class="card"><x-athar.flash /><form method="post" action="{{ route('contact.store') }}" class="stack">@csrf
<div class="field"><label for="contact-name">الاسم</label><input id="contact-name" name="name" value="{{ old('name', auth()->user()?->name) }}" maxlength="100" autocomplete="name" required></div>
<div class="field"><label for="contact-email">البريد الإلكتروني</label><input id="contact-email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" maxlength="254" autocomplete="email" required></div>
<div class="field"><label for="contact-message">الرسالة</label><textarea id="contact-message" name="message" rows="6" minlength="10" maxlength="5000" required>{{ old('message') }}</textarea></div>
<div hidden aria-hidden="true"><label for="contact-website">الموقع</label><input id="contact-website" name="website" tabindex="-1" autocomplete="off"></div>
<p class="small muted">نستخدم بياناتك للرد على رسالتك وفق <a href="{{ route('privacy') }}">سياسة الخصوصية</a>.</p><button class="btn btn-primary" type="submit">إرسال الرسالة</button>
</form></div><p style="margin-top:24px">أو راسلنا مباشرة: <a class="text-link" dir="ltr" href="mailto:{{ config('platform.support_email') }}">{{ config('platform.support_email') }}</a></p></div></section>
@endsection
