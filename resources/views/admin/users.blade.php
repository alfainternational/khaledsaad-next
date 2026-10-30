@extends('layouts.platform')
@section('title', 'المستخدمون والأدوار — الإدارة — أثر')
@section('noindex', '1')
@section('content')
@php($sortLink = fn (string $col) => route('admin.users.index', ['q' => $q, 'sort' => $col, 'dir' => $sort === $col && $dir === 'asc' ? 'desc' : 'asc']))
@php($ariaSort = fn (string $col) => $sort === $col ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none')
<section class="app-hero"><div class="shell">
    <x-athar.kicker>الإدارة · الشاشات القياسية</x-athar.kicker><h1>المستخدمون والأدوار</h1>
    <p class="muted small">{{ $stats['total'] }} مستخدماً · {{ $stats['staff'] }} من الفريق · {{ $stats['unverified'] }} بلا تحقق بريد</p>
    @include('admin.partials.nav')
</div></section>
<section class="section-tight"><div class="shell stack" style="gap:16px">
    <x-athar.flash />
    <form class="filters-form" method="get" action="{{ route('admin.users.index') }}">
        <div class="field" style="flex:1 1 260px"><label for="u-q">بحث</label><input class="input" id="u-q" name="q" value="{{ $q }}" placeholder="الاسم أو البريد"></div>
        <input type="hidden" name="sort" value="{{ $sort }}"><input type="hidden" name="dir" value="{{ $dir }}">
        <button class="btn btn-secondary" type="submit">بحث</button>
        @if($q !== '')<a class="btn btn-text" href="{{ route('admin.users.index') }}">مسح</a>@endif
    </form>
    <div class="card" style="padding:0">
        <x-athar.table caption="المستخدمون" class="admin-table"><thead><tr>
            <th><a href="{{ $sortLink('name') }}" aria-sort="{{ $ariaSort('name') }}">الاسم</a></th>
            <th><a href="{{ $sortLink('email') }}" aria-sort="{{ $ariaSort('email') }}">البريد</a></th>
            <th><a href="{{ $sortLink('platform_role') }}" aria-sort="{{ $ariaSort('platform_role') }}">الدور</a></th>
            <th><a href="{{ $sortLink('audience') }}" aria-sort="{{ $ariaSort('audience') }}">الجمهور</a></th>
            <th>التحقق</th>
            <th><a href="{{ $sortLink('workspaces_count') }}" aria-sort="{{ $ariaSort('workspaces_count') }}">المساحات</a></th>
            <th><a href="{{ $sortLink('created_at') }}" aria-sort="{{ $ariaSort('created_at') }}">انضم</a></th>
            <th></th>
        </tr></thead><tbody>
        @forelse($users as $user)
            @php($isSelf = $user->id === auth()->id())
            @php($locked = ! $canEdit || $isSelf || ($user->platform_role === 'super_admin' && ! $isSuper))
            <tr data-user="{{ $user->id }}">
                <td><b>{{ $user->name }}</b>@if($isSelf) <span class="muted small">(أنت)</span>@endif</td>
                <td><span dir="ltr" class="small">{{ $user->email }}</span></td>
                <td>
                    @if($locked)
                        <span class="soon">{{ $roles[$user->platform_role] ?? $user->platform_role }}</span>
                    @else
                        <form method="post" action="{{ route('admin.users.update', $user) }}" class="row" style="gap:6px" data-confirm="تغيير دور «{{ $user->name }}»؟ يُسجَّل التغيير في التدقيق.">@csrf @method('PATCH')
                            <select class="input" name="platform_role" aria-label="دور {{ $user->name }}" style="min-height:36px;padding-block:4px">
                                @foreach($roles as $value => $label)
                                    @if($value !== 'super_admin' || $isSuper)<option value="{{ $value }}" @selected($user->platform_role === $value)>{{ $label }}</option>@endif
                                @endforeach
                            </select>
                            <button class="btn btn-secondary" type="submit" style="min-height:36px;padding-inline:10px;font-size:13px">حفظ</button>
                        </form>
                    @endif
                </td>
                <td>{{ $audiences[$user->audience] ?? '—' }}</td>
                <td>{{ $user->email_verified_at ? 'مؤكد' : 'غير مؤكد' }}</td>
                <td>{{ $user->workspaces_count }}</td>
                <td><time datetime="{{ $user->created_at?->toDateString() }}">{{ $user->created_at?->format('Y/m/d') }}</time></td>
                <td><div class="actions">
                    @if($canEdit && ! $isSelf && $user->platform_role === 'member' && ! $impersonating)
                        <form method="post" action="{{ route('admin.users.impersonate', $user) }}" data-confirm="عرض المنصة كالمستخدم «{{ $user->name }}»؟ ستُمنع عمليات الشراء وتغيير بيانات الحساب أثناء العرض.">@csrf<button class="btn btn-text" type="submit">اعرض كهذا المستخدم</button></form>
                    @endif
                </div></td>
            </tr>
        @empty<tr><td colspan="8" class="muted" style="padding:24px">لا مستخدمين يطابقون البحث.</td></tr>@endforelse
        </tbody></x-athar.table>
    </div>
    <div class="pagination">{{ $users->links() }}</div>
    <p class="muted small">القواعد: المدير والمدير الأعلى فقط يغيّران الأدوار؛ منح دور «المدير الأعلى» أو سحبه للمدير الأعلى وحده؛ لا أحد يغيّر دوره هو. «اعرض كهذا المستخدم» متاح للأعضاء العاديين فقط، ويُسجَّل بدؤه وانتهاؤه في التدقيق.</p>
</div></section>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">document.querySelectorAll('form[data-confirm]').forEach(function(f){f.addEventListener('submit',function(e){if(!window.confirm(f.dataset.confirm))e.preventDefault()})});</script>
@endpush
