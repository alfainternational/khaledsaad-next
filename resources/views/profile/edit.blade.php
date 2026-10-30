@extends('layouts.platform')
@section('title', 'حسابي — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['لوحتي', route('dashboard')], ['الحساب', null]]" />
    <h1>الحساب</h1>
    <p class="muted">بيانات الدخول وكلمة المرور وحذف الحساب. مشروعك وأصولك في <a class="text-link" href="{{ route('dashboard') }}">لوحتي</a>، واشتراكك في <a class="text-link" href="{{ route('app.billing') }}">الاشتراك والأرصدة</a>.</p>
</div></section>
<section class="section-tight"><div class="shell account-grid">
    <div class="card">@include('profile.partials.update-profile-information-form')</div>
    <div class="card">@include('profile.partials.update-password-form')</div>
    <div class="card danger-card">@include('profile.partials.delete-user-form')</div>
</div></section>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">document.querySelectorAll('[data-close-details]').forEach(function(b){b.addEventListener('click',function(){var d=b.closest('details');if(d){d.removeAttribute('open');var s=d.querySelector('summary');if(s)s.focus()}})});</script>
@endpush
