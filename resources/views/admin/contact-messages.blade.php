@extends('layouts.platform')
@section('title', 'رسائل الاتصال — الإدارة — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell"><x-athar.kicker>الإدارة</x-athar.kicker><h1>رسائل الاتصال</h1>@include('admin.partials.nav')</div></section>
<section class="section-tight"><div class="shell stack contact-messages" style="gap:24px">
    <x-athar.flash />
    <form class="card filters-form" method="get" action="{{ route('admin.contact-messages.index') }}">
        <div class="field"><label for="cm-status">الحالة</label><select id="cm-status" name="status"><option value="">الكل</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }} ({{ $counts[$key] ?? 0 }})</option>@endforeach</select></div>
        <div class="field"><label for="cm-q">بحث</label><input id="cm-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="اسم أو بريد أو كلمة من الرسالة" maxlength="120"></div>
        <button class="btn btn-secondary" type="submit">عرض</button>
    </form>
    <div class="card">
        @if($messages->isEmpty())<p class="muted">لا رسائل بهذه الفلاتر.</p>@else
        <x-athar.table caption="رسائل الاتصال" class="admin-table"><thead><tr><th>التاريخ</th><th>المرسل</th><th>الرسالة</th><th>الحالة</th><th></th></tr></thead><tbody>
        @foreach($messages as $message)<tr>
            <td><time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('Y/m/d H:i') }}</time></td>
            <td>{{ $message->name }}<br><a class="text-link" dir="ltr" href="mailto:{{ $message->email }}">{{ $message->email }}</a>@if($message->user)<br><span class="small muted">عضو #{{ $message->user->id }}</span>@endif</td>
            <td class="message-cell">{{ $message->message }}</td>
            <td><span class="tag">{{ $statuses[$message->status] ?? $message->status }}</span>@if($message->handled_at)<br><span class="small muted">{{ $message->handled_at->format('Y/m/d') }} · {{ $message->handler?->name }}</span>@endif</td>
            <td><form method="post" action="{{ route('admin.contact-messages.update', $message) }}" class="row">@csrf @method('PATCH')<label class="sr-only" for="st-{{ $message->id }}">الحالة</label><select id="st-{{ $message->id }}" name="status" class="input">@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected($message->status === $key)>{{ $label }}</option>@endforeach</select><button class="btn btn-secondary" type="submit">حفظ</button></form></td>
        </tr>@endforeach
        </tbody></x-athar.table>
        {{ $messages->links() }}
        @endif
    </div>
</div></section>
@endsection
