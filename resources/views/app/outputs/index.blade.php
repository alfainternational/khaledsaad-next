@extends('layouts.platform')
@section('title', 'أصولي — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['لوحتي', route('dashboard')], ['أصولي', null]]" />
    <h1>أصولي</h1><p class="muted">كل ما أنشأته في الدروس والمختبر، بإصداراته. افتح، انسخ، نزّل، أو احذف.</p>
</div></section>
<section class="section-tight"><div class="shell stack" style="gap:20px">
    <x-athar.flash />
    <form class="filters-form row" method="get" action="{{ route('app.outputs.index') }}" id="outputs-filter">
        <div class="field"><label for="type">النوع</label><select id="type" name="type"><option value="">كل الأنواع</option>@foreach($types as $k => $l)<option value="{{ $k }}" @selected($type === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="project">المشروع</label><select id="project" name="project"><option value="0">كل المشاريع</option>@foreach($projects as $p)<option value="{{ $p->id }}" @selected($projectId === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
        <x-athar.button type="submit" variant="secondary">فلترة</x-athar.button>
        <span class="small muted">{{ $outputs->count() }} أصلاً</span>
    </form>
    @if($outputs->isEmpty())
        <div class="card"><p class="muted" style="margin:0">لا أصول بعد. <a href="{{ route('tools.index') }}">افتح المختبر</a> أو <a href="{{ route('academy.index') }}">درساً</a>؛ كل تفاعل ينتهي بأصل تحفظه هنا.</p></div>
    @else
    <div class="card">
        <x-athar.table caption="أصولي"><thead><tr><th>النوع</th><th>العنوان</th><th>المشروع</th><th>المصدر</th><th>التاريخ</th><th>أفعال</th></tr></thead><tbody>
            @foreach($outputs as $o)
            <tr data-output="{{ $o->id }}">
                <td>{{ \App\Domain\Lab\Services\LabCatalog::label((string) $o->output_type) }}</td>
                <td><a href="{{ route('app.outputs.show', $o) }}"><b>{{ $o->title }}</b></a> <span class="muted small">v{{ $o->version }}</span>@if($o->isShared()) <span class="card-tag" title="مشترك برابط">مشترك</span>@endif</td>
                <td>{{ $o->project?->name ?? '—' }}</td>
                <td>{{ $o->lesson ? 'درس: '.$o->lesson->title : ($o->tool?->name ?? '—') }}</td>
                <td class="muted small">{{ $o->created_at->format('Y/m/d') }}</td>
                <td><div class="row" style="gap:6px">
                    <a class="btn btn-text" href="{{ route('app.outputs.show', $o) }}">فتح</a>
                    <button class="btn btn-text" type="button" data-copy-target="#md-{{ $o->id }}">نسخ</button>
                    <form method="post" action="{{ route('assets.print') }}" target="_blank" class="inline-form">@csrf<input type="hidden" name="title" value="{{ $o->title }}"><input type="hidden" name="markdown" value="{{ $o->body_markdown }}"><button class="btn btn-text" type="submit">PDF</button></form>
                    <form method="post" action="{{ route('app.outputs.destroy', $o) }}" data-confirm="حذف «{{ $o->title }}» v{{ $o->version }} نهائياً؟">@csrf @method('DELETE')<button class="btn btn-text danger" type="submit">حذف</button></form>
                </div></td>
            </tr>
            @endforeach
        </tbody></x-athar.table>
        {{-- Hidden copy sources live outside the scrolling table: an absolutely positioned element inside it would escape the scroll container and widen the page on phones. --}}
        @foreach($outputs as $o)<textarea class="sr-only" id="md-{{ $o->id }}" readonly aria-hidden="true" tabindex="-1">{{ $o->body_markdown }}</textarea>@endforeach
    </div>
    @endif
</div></section>
@endsection
@push('scripts')
@include('partials.asset-actions-script')
@endpush
