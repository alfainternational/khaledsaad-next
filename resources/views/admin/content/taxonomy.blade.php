@extends('layouts.platform')
@section('title', $meta['label'].' — إدارة المحتوى — أثر')
@section('noindex', '1')
@section('content')
@php($sortLink = fn (string $col) => route('admin.content.taxonomy', [$kind, 'q' => $q, 'sort' => $col, 'dir' => $sort === $col && $dir === 'asc' ? 'desc' : 'asc']))
@php($ariaSort = fn (string $col) => $sort === $col ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none')
<section class="app-hero"><div class="shell">
    <div class="split"><div><x-athar.kicker>الإدارة · المحتوى</x-athar.kicker><h1>{{ $meta['label'] }}</h1></div></div>
    @include('admin.partials.nav')
    @include('admin.content.partials.subnav', ['active' => $kind])
</div></section>
<section class="section-tight"><div class="shell editor-layout">
    <div class="stack" style="gap:16px">
        <x-athar.flash />
        @if($errors->has('delete'))<x-athar.alert type="error">{{ $errors->first('delete') }}</x-athar.alert>@endif
        <form class="filters-form" method="get" action="{{ route('admin.content.taxonomy', $kind) }}">
            <div class="field" style="flex:1 1 220px"><label for="t-q">بحث</label><input class="input" id="t-q" name="q" value="{{ $q }}" placeholder="الاسم أو الرابط"></div>
            <button class="btn btn-secondary" type="submit">بحث</button>
        </form>
        <div class="card" style="padding:0">
            <x-athar.table :caption="$meta['label']" class="admin-table"><thead><tr>
                <th><a href="{{ $sortLink('name') }}" aria-sort="{{ $ariaSort('name') }}">الاسم</a></th><th>الرابط</th>
                @if($kind === 'authors')<th>الدور</th>@endif @if($kind === 'categories')<th>الترتيب</th>@endif
                <th><a href="{{ $sortLink('items_count') }}" aria-sort="{{ $ariaSort('items_count') }}">المواد</a></th><th><a href="{{ $sortLink('updated_at') }}" aria-sort="{{ $ariaSort('updated_at') }}">آخر تعديل</a></th><th></th>
            </tr></thead><tbody>
            @forelse($rows as $row)
                <tr data-row="{{ $row->slug }}">
                    <td><b>{{ $row->name }}</b></td><td><span dir="ltr" class="small">{{ $row->slug }}</span></td>
                    @if($kind === 'authors')<td>{{ $row->role }}</td>@endif @if($kind === 'categories')<td>{{ $row->position }}</td>@endif
                    <td>{{ $row->items_count }}</td><td>{{ $row->updated_at->format('Y/m/d') }}</td>
                    <td><div class="actions">
                        <a class="btn btn-secondary" href="{{ route('admin.content.taxonomy', [$kind, 'edit' => $row->id]) }}#form">تعديل</a>
                        @if($kind === 'authors')<a class="btn btn-text" href="{{ route('authors.show', $row) }}" target="_blank" rel="noopener">الصفحة</a>@elseif($kind === 'tags')<a class="btn btn-text" href="{{ route('tags.show', $row) }}" target="_blank" rel="noopener">الصفحة</a>@endif
                        <form method="post" action="{{ route('admin.content.taxonomy.destroy', [$kind, $row->id]) }}" class="row" style="gap:4px">@csrf @method('DELETE')
                            <label class="small row" style="gap:4px"><input type="checkbox" name="confirm" value="1" required aria-label="تأكيد الحذف"> تأكيد</label><button class="btn btn-text danger" type="submit">حذف</button></form>
                    </div></td>
                </tr>
            @empty<tr><td colspan="7" class="muted" style="padding:24px">لا عناصر.</td></tr>@endforelse
            </tbody></x-athar.table>
        </div>
        <div class="pagination">{{ $rows->links() }}</div>
    </div>
    <aside>
        <form method="post" action="{{ $editing ? route('admin.content.taxonomy.update', [$kind, $editing->id]) : route('admin.content.taxonomy.store', $kind) }}" class="card stack" id="form">
            @csrf @if($editing)@method('PUT')@endif
            <h2 style="font-size:18px;margin:0">{{ $editing ? 'تعديل '.$meta['singular'].': '.$editing->name : $meta['singular'].' جديد' }}</h2>
            <div class="field"><label for="name">الاسم</label><input class="input" id="name" name="name" value="{{ old('name', $editing?->name) }}" required maxlength="120"></div>
            <div class="field"><label for="slug">الرابط (slug)</label><input class="input" dir="ltr" id="slug" name="slug" value="{{ old('slug', $editing?->slug) }}" maxlength="120" placeholder="يُشتق من الاسم إن تُرك فارغاً"></div>
            @if($kind === 'categories')
                <div class="field"><label for="description">الوصف</label><textarea class="input" id="description" name="description" rows="2" maxlength="600">{{ old('description', $editing?->description) }}</textarea></div>
                <div class="field"><label for="position">الترتيب</label><input class="input" type="number" min="0" max="999" id="position" name="position" value="{{ old('position', $editing?->position ?? 0) }}"></div>
            @endif
            @if($kind === 'authors')
                <div class="field"><label for="role">الدور</label><input class="input" id="role" name="role" value="{{ old('role', $editing?->role) }}" maxlength="120"></div>
                <div class="field"><label for="bio">السيرة (E-E-A-T: الخبرة، التخصص، ما يراجعه)</label><textarea class="input" id="bio" name="bio" rows="5" maxlength="3000">{{ old('bio', $editing?->bio) }}</textarea></div>
                <div class="field"><label for="links">الروابط (سطر لكل رابط: التسمية | https://…)</label><textarea class="input" id="links" name="links" rows="3" dir="ltr">{{ old('links', $editing ? collect($editing->safeLinks())->map(fn ($l) => ($l['label'] ?? '').' | '.$l['url'])->implode("\n") : '') }}</textarea></div>
            @endif
            <div class="row"><button class="btn btn-primary" type="submit">{{ $editing ? 'حفظ' : 'إضافة' }}</button>@if($editing)<a class="btn btn-text" href="{{ route('admin.content.taxonomy', $kind) }}">إلغاء</a>@endif</div>
        </form>
    </aside>
</div></section>
@endsection
