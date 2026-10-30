{{-- Legacy Breeze wrapper kept only for the unused dashboard.blade.php; every live member page extends layouts.platform directly. --}}
@extends('layouts.platform')
@section('title', 'حسابي — أثر')
@section('noindex', '1')
@section('content')
@isset($header)<header class="app-hero"><div class="shell">{{ $header }}</div></header>@endisset
<div class="shell">{{ $slot }}</div>
@endsection
