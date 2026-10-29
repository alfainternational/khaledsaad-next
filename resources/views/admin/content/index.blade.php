@extends('layouts.platform')
@section('title', 'إدارة المحتوى — الإدارة — أثر')
@section('noindex', '1')
@section('content')
@php($sortLink = fn (string $col) => route('admin.content.index', array_merge($filters, ['sort' => $col, 'dir' => $filters['sort'] === $col && $filters['dir'] === 'asc' ? 'desc' : 'asc', 'page' => null])))
@php($ariaSort = fn (string $col) => $filters['sort'] === $col ? ($filters['dir'] === 'asc' ? 'ascending' : 'descending') : 'none')
<section class="app-hero"><div class="shell">
    <div class="split"><div><x-athar.kicker>الإدارة</x-athar.kicker><h1>المحتوى</h1><p class="muted">المواد بالقسم والحالة ومرحلة الترجمة. كل تغيير يُسجَّل في التدقيق.</p></div>
        <div class="row"><x-athar.button href="{{ route('admin.content.create') }}">مادة جديدة</x-athar.button><x-athar.button href="{{ route('admin.sources.index') }}" variant="secondary">صندوق المصادر</x-athar.button></div></div>
    @include('admin.partials.nav')
    @include('admin.content.partials.subnav', ['active' => 'items'])
</div></section>
<section class="section-tight"><div class="shell stack" style="gap:20px">
    <x-athar.flash />
    @if($errors->has('publish'))<x-athar.alert type="error">{{ $errors->first('publish') }}</x-athar.alert>@endif

    @if($stale->isNotEmpty())
    <div class="card" id="stale-reminders" style="border-color:rgba(183,121,31,.4)"><h2 style="font-size:18px;margin:0 0 4px">تذكيرات تحديث ({{ $stale->count() }})</h2><p class="muted small" style="margin:0 0 8px">مواد منشورة تعتمد أرقاماً من مصادر أقدم من 12 شهراً.</p>
        <ul class="plain-list">@foreach($stale->take(6) as $s)<li><a href="{{ route('admin.content.edit', $s) }}">{{ $s->title }}</a> <span class="muted small">— أقدم مصدر {{ $s->oldestSourceDate()?->format('Y/m') }}</span></li>@endforeach</ul></div>
    @endif

    <form class="filters-form card" method="get" action="{{ route('admin.content.index') }}" id="content-filters" style="padding:16px 20px">
        <div class="field" style="flex:1 1 220px"><label for="f-q">بحث</label><input class="input" id="f-q" name="q" value="{{ $filters['q'] }}" placeholder="العنوان أو الرابط أو الملخص"></div>
        <div class="field"><label for="f-section">القسم</label><select class="input" id="f-section" name="section"><option value="">الكل</option>@foreach(\App\Domain\Content\Models\ContentItem::SECTIONS as $k => $s)<option value="{{ $k }}" @selected($filters['section'] === $k)>{{ $s['label'] }} ({{ $sectionCounts[$k] ?? 0 }})</option>@endforeach</select></div>
        <div class="field"><label for="f-status">الحالة</label><select class="input" id="f-status" name="status"><option value="">الكل</option>@foreach($statuses as $k => $label)<option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $label }} ({{ $counts[$k] ?? 0 }})</option>@endforeach</select></div>
        <div class="field"><label for="f-stage">مرحلة الترجمة</label><select class="input" id="f-stage" name="stage"><option value="">الكل</option>@foreach(\App\Domain\Content\Models\ContentItem::STAGES as $k => $label)<option value="{{ $k }}" @selected($filters['stage'] === $k)>{{ $label }}</option>@endforeach</select></div>
        <input type="hidden" name="sort" value="{{ $filters['sort'] }}"><input type="hidden" name="dir" value="{{ $filters['dir'] }}">
        <button class="btn btn-secondary" type="submit">تصفية</button>
        @if($filters['q'] !== '' || $filters['section'] || $filters['status'] || $filters['stage'])<a class="btn btn-text" href="{{ route('admin.content.index') }}">إلغاء</a>@endif
    </form>

    <div class="card" style="padding:0">
        <x-athar.table caption="المواد" class="admin-table"><thead><tr>
            <th><a href="{{ $sortLink('title') }}" aria-sort="{{ $ariaSort('title') }}">العنوان</a></th><th>القسم / النوع</th><th>المؤلف</th>
            <th><a href="{{ $sortLink('status') }}" aria-sort="{{ $ariaSort('status') }}">الحالة</a></th><th>المرحلة</th><th>المصادر</th>
            <th><a href="{{ $sortLink('published_at') }}" aria-sort="{{ $ariaSort('published_at') }}">النشر</a></th><th><a href="{{ $sortLink('updated_at') }}" aria-sort="{{ $ariaSort('updated_at') }}">آخر تعديل</a></th><th></th>
        </tr></thead><tbody>
        @forelse($items as $item)
            <tr data-item="{{ $item->slug }}">
                <td><a href="{{ route('admin.content.edit', $item) }}"><b>{{ $item->title }}</b></a><br><span class="muted small" dir="ltr">{{ $item->slug }}</span></td>
                <td>{{ $item->sectionLabel() }}<br><span class="muted small">{{ $item->typeLabel() }}@if($item->category) · {{ $item->category->name }}@endif</span></td>
                <td>{{ $item->authorName() }}</td>
                <td><span class="status-pill" data-status="{{ $item->status }}">{{ $statuses[$item->status] ?? $item->status }}</span></td>
                <td>{{ $item->translation_stage ? (\App\Domain\Content\Models\ContentItem::STAGES[$item->translation_stage] ?? $item->translation_stage) : '—' }}</td>
                <td>{{ $item->sources_count }}@if($item->requiresSources() && $item->sources_count === 0) <span class="muted small">(مطلوبة)</span>@endif</td>
                <td>{{ $item->published_at?->format('Y/m/d') ?? '—' }}</td>
                <td>{{ $item->updated_at->format('Y/m/d') }}</td>
                <td><div class="actions">
                    <a class="btn btn-secondary" href="{{ route('admin.content.edit', $item) }}">تعديل</a>
                    @if(in_array($item->status, ['review', 'seo_review'], true))<form method="post" action="{{ route('admin.content.publish', $item) }}">@csrf<button class="btn btn-primary">نشر</button></form>
                    @elseif($item->status === 'published')<a class="btn btn-text" href="{{ route('articles.show', $item) }}" target="_blank" rel="noopener">عرض</a>@endif
                </div></td>
            </tr>
        @empty
            <tr><td colspan="9" class="muted" style="padding:24px">لا مواد مطابقة.</td></tr>
        @endforelse
        </tbody></x-athar.table>
    </div>
    <div class="pagination">{{ $items->links() }}</div>
</div></section>
@endsection
