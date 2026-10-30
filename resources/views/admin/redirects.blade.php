@extends('layouts.platform')
@section('title', 'التحويلات — الإدارة — أثر')
@section('noindex', '1')
@section('content')
@php($sortLink = fn (string $col) => route('admin.redirects.index', ['q' => $q, 'sort' => $col, 'dir' => $sort === $col && $dir === 'asc' ? 'desc' : 'asc']))
@php($ariaSort = fn (string $col) => $sort === $col ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none')
<section class="app-hero"><div class="shell">
    <x-athar.kicker>الإدارة · الشاشات القياسية</x-athar.kicker><h1>التحويلات</h1>
    <p class="muted small">{{ $stats['total'] }} تحويلاً · {{ $stats['gone'] }} محذوف عمداً (410) · {{ $stats['inactive'] }} متوقف</p>
    @include('admin.partials.nav')
</div></section>
<section class="section-tight"><div class="shell editor-layout">
    <div class="stack" style="gap:16px">
        <x-athar.flash />
        <form class="filters-form" method="get" action="{{ route('admin.redirects.index') }}">
            <div class="field" style="flex:1 1 260px"><label for="r-q">بحث</label><input class="input" id="r-q" name="q" value="{{ $q }}" placeholder="المسار القديم أو الجديد" dir="ltr"></div>
            <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $dir }}">
            <button class="btn btn-secondary" type="submit">بحث</button>
            @if($q !== '')<a class="btn btn-text" href="{{ route('admin.redirects.index') }}">مسح</a>@endif
        </form>
        <div class="card" style="padding:0">
            <x-athar.table caption="التحويلات" class="admin-table"><thead><tr>
                <th><a href="{{ $sortLink('from_path') }}" aria-sort="{{ $ariaSort('from_path') }}">من</a></th>
                <th><a href="{{ $sortLink('to_path') }}" aria-sort="{{ $ariaSort('to_path') }}">إلى</a></th>
                <th><a href="{{ $sortLink('status_code') }}" aria-sort="{{ $ariaSort('status_code') }}">الحالة</a></th>
                <th><a href="{{ $sortLink('is_active') }}" aria-sort="{{ $ariaSort('is_active') }}">نشط</a></th>
                <th><a href="{{ $sortLink('created_at') }}" aria-sort="{{ $ariaSort('created_at') }}">أُنشئ</a></th>
                <th></th>
            </tr></thead><tbody>
            @forelse($redirects as $redirect)
                <tr data-redirect="{{ $redirect->id }}">
                    <td><code dir="ltr">{{ $redirect->from_path }}</code></td>
                    <td>@if((int) $redirect->status_code === 410)<span class="muted small">—</span>@else<code dir="ltr">{{ $redirect->to_path }}</code>@endif</td>
                    <td>{{ $redirect->status_code }}</td>
                    <td>{{ $redirect->is_active ? 'نعم' : 'لا' }}</td>
                    <td>{{ $redirect->created_at ? \Illuminate\Support\Carbon::parse($redirect->created_at)->format('Y/m/d') : '—' }}</td>
                    <td><div class="actions">
                        <form method="post" action="{{ route('admin.redirects.destroy', $redirect->id) }}" data-confirm="حذف التحويل {{ $redirect->from_path }} نهائياً؟">@csrf @method('DELETE')<button class="btn btn-text danger" type="submit">حذف</button></form>
                    </div></td>
                </tr>
            @empty<tr><td colspan="6" class="muted" style="padding:24px">لا تحويلات.</td></tr>@endforelse
            </tbody></x-athar.table>
        </div>
        <div class="pagination">{{ $redirects->links() }}</div>
    </div>
    <aside class="stack" style="gap:16px">
        <form method="post" action="{{ route('admin.redirects.store') }}" class="card stack" id="add">@csrf
            <h2 style="font-size:18px;margin:0">إضافة تحويل</h2>
            <div class="field"><label for="from_path">من المسار</label><input class="input" dir="ltr" id="from_path" name="from_path" value="{{ old('from_path') }}" placeholder="/old-path" required maxlength="500"></div>
            <div class="field"><label for="to_path">إلى المسار</label><input class="input" dir="ltr" id="to_path" name="to_path" value="{{ old('to_path', '/') }}" placeholder="/new-path" required maxlength="500"></div>
            <div class="field"><label for="status_code">الحالة</label><select class="input" id="status_code" name="status_code">@foreach([301, 302, 307, 308, 410] as $code)<option value="{{ $code }}" @selected((int) old('status_code', 301) === $code)>{{ $code }}{{ $code === 410 ? ' — محذوف عمداً' : '' }}</option>@endforeach</select></div>
            <p class="muted small">مسارات نسبية تبدأ بـ «/» فقط؛ لا «//» ولا مسافات. للحالة 410 يكفي «/» في الهدف.</p>
            <button class="btn btn-primary" type="submit">إضافة</button>
        </form>
        <form method="post" action="{{ route('admin.redirects.import') }}" enctype="multipart/form-data" class="card stack" id="import">@csrf
            <h2 style="font-size:18px;margin:0">استيراد CSV</h2>
            <p class="muted small">الأعمدة: <code dir="ltr">from_path,to_path,status_code</code> (سطر العناوين اختياري). يُحدَّث التحويل الموجود بنفس المسار القديم. الحد 512 كيلوبايت.</p>
            <div class="field"><label for="csv">الملف</label><input class="input" id="csv" name="csv" type="file" accept=".csv,text/csv,text/plain" required></div>
            <button class="btn btn-secondary" type="submit">استيراد</button>
        </form>
    </aside>
</div></section>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">document.querySelectorAll('form[data-confirm]').forEach(function(f){f.addEventListener('submit',function(e){if(!window.confirm(f.dataset.confirm))e.preventDefault()})});</script>
@endpush
