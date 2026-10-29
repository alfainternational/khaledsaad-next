@extends('layouts.platform', ['compactLayout' => true])
@php($authTitle = match(true) {
    request()->routeIs('register') => 'أنشئ حسابك في أثر',
    request()->routeIs('password.request') => 'استعادة كلمة المرور',
    request()->routeIs('password.reset') => 'اختر كلمة مرور جديدة',
    request()->routeIs('verification.notice') => 'تأكيد بريدك الإلكتروني',
    request()->routeIs('password.confirm') => 'أكد كلمة المرور',
    default => 'دخول إلى أثر',
})
@section('title', $authTitle.' — أثر')
@section('description', 'حسابك في أثر لحفظ أصول مشروعك ومتابعة دروسك ونتائج تطبيقك.')
@section('noindex', '1')
@section('content')
<section class="auth-section" data-auth-layout><div class="auth-card card">
<span class="kicker">تعلّم. طبّق. قِس الأثر.</span><h1>{{ $authTitle }}</h1>
<p class="muted">احفظ أصولك، تابع تقدمك، وارجع إلى مشروعك من حيث توقفت.</p>
{{ $slot }}
@if(request()->routeIs('login'))<p class="auth-switch">تبدأ رحلتك هنا؟ <a class="text-link" href="{{ route('register') }}">أنشئ حسابًا مجانيًا</a></p>@endif
<p class="small muted auth-legal">باستخدام أثر، اطّلع على <a href="{{ route('terms') }}">شروط الاستخدام</a> و<a href="{{ route('privacy') }}">سياسة الخصوصية</a>.</p>
</div></section>
@endsection
