@extends('layouts.platform')
@section('title', 'خطة '.$project->name.' — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['لوحتي', route('dashboard')], [$project->name, route('app.projects.show', $project)], ['الخطة المجمّعة', null]]" />
    <h1>خطة التسويق — {{ $project->name }}</h1>
    <p class="muted">{{ $plan['complete'] ? 'السلسلة مكتملة: عشرة أصول في صفحة واحدة، بأحدث إصدار من كلٍّ منها.' : 'السلسلة غير مكتملة بعد ('.$plan['saved'].' من '.$plan['total'].'): تُعرض الأصول المحفوظة حتى الآن.' }}</p>
</div></section>
<section class="section-tight"><div class="shell narrow">
    <x-athar.flash />
    <x-athar.asset-card id="plan" kicker="{{ $plan['complete'] ? 'خطة مجمّعة' : 'خطة قيد التجمّع' }}" :title="'خطة التسويق — '.$project->name" type="marketing_plan" :markdown="$markdown" filename="marketing-plan" />
    @if($assembly)<p class="small muted" style="margin-top:10px">جُمّعت آلياً {{ $assembly->assembled_at->format('Y/m/d H:i') }}@if($assembly->course) · مرتبطة بمسار «{{ $assembly->course->title }}»@endif</p>@endif
</div></section>
@endsection
@push('scripts')
@include('partials.asset-actions-script')
@endpush
