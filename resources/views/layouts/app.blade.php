@extends('layouts.platform')
@section('title', request()->routeIs('app.billing') ? 'الاشتراك والأرصدة — أثر' : 'حسابي — أثر')
@section('noindex', '1')
@push('before-styles')
@vite(['resources/css/app.css', 'resources/js/app.js'])
@endpush
@section('content')
<div class="legacy-app-area">
@isset($header)<header class="app-hero"><div class="shell">{{ $header }}</div></header>@endisset
{{ $slot }}
</div>
@endsection
