@extends('layouts.platform')
@section('title', $title.' — أثر')
@section('noindex', '1')
@section('content')
<section class="section"><div class="narrow">
    <x-athar.kicker>النشرة البريدية</x-athar.kicker>
    <h1>{{ $title }}</h1>
    <x-athar.alert :type="$ok ? 'success' : 'error'">{{ $message }}</x-athar.alert>
    <div class="btn-row"><x-athar.button href="{{ route('articles.index') }}">تصفّح المجلة</x-athar.button><x-athar.button href="{{ route('tools.index') }}" variant="secondary">افتح المختبر</x-athar.button></div>
</div></section>
@endsection
