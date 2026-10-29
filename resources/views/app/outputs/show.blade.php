@extends('layouts.platform')
@section('title', $output->title.' — أصولي — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['لوحتي', route('dashboard')], ['أصولي', route('app.outputs.index')], [$output->title, null]]" />
    <h1>{{ $output->title }}</h1>
    <p class="muted">{{ \App\Domain\Lab\Services\LabCatalog::label((string) $output->output_type) }} · v{{ $output->version }} من {{ $versions->count() }} · {{ $output->project?->name ?? '—' }} · {{ $output->lesson ? 'من درس «'.$output->lesson->title.'»' : ($output->tool ? 'من أداة «'.$output->tool->name.'»' : '') }}</p>
</div></section>
<section class="section-tight"><div class="shell tool-layout">
    <div class="stack" style="gap:20px">
        <x-athar.flash />
        <x-athar.asset-card id="asset" kicker="الأصل" :title="$output->title" :type="$output->output_type" :markdown="$output->body_markdown" :quality="$output->quality_report" :versions="$versions" :currentVersion="$output->version" :diff="$diff" :filename="$output->output_type">
            <x-slot:actions>
                @if($output->isShared())
                    <form method="post" action="{{ route('app.outputs.unshare', $output) }}">@csrf @method('DELETE')<button class="btn btn-secondary" type="submit" data-action="unshare">إلغاء المشاركة</button></form>
                @else
                    <form method="post" action="{{ route('app.outputs.share', $output) }}">@csrf<button class="btn btn-secondary" type="submit" data-action="share">مشاركة برابط للقراءة</button></form>
                @endif
                @if($output->tool && $output->tool->is_published)<a class="btn btn-text" href="{{ route('tools.show', [$output->tool, 'project' => $output->project_id]) }}">ولّد إصداراً جديداً</a>@endif
                <form method="post" action="{{ route('app.outputs.destroy', $output) }}" data-confirm="حذف هذا الإصدار نهائياً؟">@csrf @method('DELETE')<button class="btn btn-text danger" type="submit" data-action="delete">حذف</button></form>
            </x-slot:actions>
            @if($output->isShared())<div class="share-box" data-share-box><label for="share-url" class="small">رابط القراءة العامة (noindex، قابل للإلغاء)</label><div class="row"><input id="share-url" class="input" readonly value="{{ route('assets.shared', $output->share_token) }}"><button class="btn btn-secondary" type="button" data-copy-target="#share-url">نسخ الرابط</button></div></div>@endif
        </x-athar.asset-card>
    </div>
    <aside class="side stack" style="gap:16px">
        <div class="side-box"><h3>الإصدارات</h3><ul class="list">@foreach($versions as $v)<li><a href="{{ route('app.outputs.show', $v) }}" @if($v->id === $output->id) aria-current="true" @endif><b>v{{ $v->version }}</b>@if($v->id === $output->id) (الحالي)@endif</a><span class="when">{{ $v->created_at->format('Y/m/d H:i') }}</span></li>@endforeach</ul><p class="small muted" style="margin:8px 0 0">الإصدار الجديد لا يمحو السابق؛ افتح أي إصدار لترى ما تغيّر عمّا قبله.</p></div>
        @if($output->payload['input'] ?? null)<div class="side-box"><h3>المدخلات</h3><ul class="list small">@foreach($output->payload['input'] as $k => $v)@if(is_scalar($v) && trim((string) $v) !== '')<li><span class="muted">{{ $k }}</span><span>{{ Str::limit((string) $v, 60) }}</span></li>@endif @endforeach</ul></div>@endif
        @if($output->project)<x-athar.card heading="h3" title="مشروعي" :desc="$output->project->name" :href="route('app.projects.show', $output->project)" tag="الملف والأصول" />@endif
    </aside>
</div></section>
@endsection
@push('scripts')
@include('partials.asset-actions-script')
@endpush
