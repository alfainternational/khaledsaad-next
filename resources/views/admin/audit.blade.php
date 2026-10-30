@extends('layouts.platform')
@section('title', 'سجل التدقيق — الإدارة — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell">
    <x-athar.kicker>الإدارة · الشاشات القياسية</x-athar.kicker><h1>سجل التدقيق</h1>
    <p class="muted small">{{ $total }} سجلاً · للقراءة فقط · الأحدث أولاً</p>
    @include('admin.partials.nav')
</div></section>
<section class="section-tight"><div class="shell stack" style="gap:16px">
    <x-athar.flash />
    <form class="filters-form" method="get" action="{{ route('admin.audit.index') }}">
        <div class="field" style="flex:1 1 200px"><label for="a-action">الإجراء (تام أو بادئة)</label><input class="input" id="a-action" name="action" value="{{ $action }}" list="audit-actions" placeholder="مثال: redirect." dir="ltr"><datalist id="audit-actions">@foreach($actions as $a)<option value="{{ $a }}"></option>@endforeach</datalist></div>
        <div class="field" style="flex:1 1 200px"><label for="a-user">المستخدم (المعرّف أو البريد)</label><input class="input" id="a-user" name="user" value="{{ $user }}" dir="ltr"></div>
        <div class="field"><label for="a-from">من</label><input class="input" id="a-from" name="from" type="date" value="{{ $from }}"></div>
        <div class="field"><label for="a-to">إلى</label><input class="input" id="a-to" name="to" type="date" value="{{ $to }}"></div>
        <button class="btn btn-secondary" type="submit">تصفية</button>
        @if($action !== '' || $user !== '' || $from || $to)<a class="btn btn-text" href="{{ route('admin.audit.index') }}">مسح</a>@endif
    </form>
    <div class="card" style="padding:0">
        <x-athar.table caption="سجل التدقيق" class="admin-table"><thead><tr><th>الوقت</th><th>المستخدم</th><th>الإجراء</th><th>الموضوع</th><th>السياق</th><th>الطلب</th></tr></thead><tbody>
        @forelse($rows as $row)
            <tr data-audit="{{ $row->id }}">
                <td><time datetime="{{ $row->created_at }}" class="small">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('Y/m/d H:i:s') }}</time></td>
                <td>@if($row->user_name)<b>{{ $row->user_name }}</b><br><span dir="ltr" class="small muted">{{ $row->user_email }}</span>@else<span class="muted small">النظام{{ $row->user_id ? ' · #'.$row->user_id : '' }}</span>@endif</td>
                <td><code dir="ltr">{{ $row->action }}</code></td>
                <td><span class="small">{{ $row->subject_type ?? '—' }}</span>@if($row->subject_id)<br><code dir="ltr" class="small">{{ \Illuminate\Support\Str::limit($row->subject_id, 60) }}</code>@endif</td>
                <td>@if($row->context_pretty !== '' && $row->context_pretty !== null)<details class="small"><summary>عرض</summary><pre dir="ltr" style="font-size:12px;white-space:pre-wrap;margin:6px 0 0;max-width:420px">{{ $row->context_pretty }}</pre></details>@else<span class="muted small">—</span>@endif</td>
                <td><code dir="ltr" class="small">{{ $row->request_id ? \Illuminate\Support\Str::limit($row->request_id, 8, '') : '—' }}</code></td>
            </tr>
        @empty<tr><td colspan="6" class="muted" style="padding:24px">لا سجلات تطابق التصفية.</td></tr>@endforelse
        </tbody></x-athar.table>
    </div>
    <div class="pagination">{{ $rows->links() }}</div>
</div></section>
@endsection
