@extends('layouts.platform')
@section('title', 'الخطط والدفع — الإدارة — أثر')
@section('noindex', '1')
@section('content')
@php($money = fn (int $minor, string $currency) => number_format($minor / 100, 2).' '.$currency)
<section class="app-hero"><div class="shell">
    <x-athar.kicker>الإدارة · الشاشات القياسية</x-athar.kicker><h1>الخطط والدفع</h1>
    <p class="muted small">الأسعار تُخزَّن بأصغر وحدة للعملة (الهللة/السنت) وتُعرض هنا بالوحدة الكبرى.</p>
    @include('admin.partials.nav')
</div></section>
<section class="section-tight"><div class="shell stack" style="gap:24px">
    <x-athar.flash />
    <div class="stat-grid" aria-label="مؤشرات الدفع">
        <div class="stat"><b>{{ $stats['payments'] }}</b><span>المدفوعات الكلية</span></div>
        <div class="stat"><b>{{ $stats['paid'] }}</b><span>مدفوعات مكتملة</span></div>
        <div class="stat"><b>{{ $stats['active'] }}</b><span>اشتراكات نشطة</span></div>
        <div class="stat"><b>{{ number_format($stats['revenue'] / 100, 2) }}</b><span>إجمالي المحصَّل (بالوحدة الكبرى)</span></div>
    </div>

    <div class="card" id="plans"><x-athar.kicker>الخطط</x-athar.kicker>
        <x-athar.table caption="الخطط" class="admin-table"><thead><tr><th>الخطة</th><th>الرابط</th><th>السعر</th><th>العملة</th><th>الأرصدة</th><th>الحالة</th><th>الاستحقاقات</th><th>تعديل (السعر بأصغر وحدة · الأرصدة · نشطة)</th></tr></thead><tbody>
        @forelse($plans as $plan)
            <tr data-plan="{{ $plan->slug }}">
                <td><b>{{ $plan->name }}</b></td>
                <td><span dir="ltr" class="small">{{ $plan->slug }}</span></td>
                <td>{{ $money((int) $plan->price, $plan->currency) }}</td>
                <td>{{ $plan->currency }}</td>
                <td>{{ $plan->credits }}</td>
                <td>@if($plan->is_active)<span class="soon">نشطة</span>@else<span class="muted small">متوقفة</span>@endif</td>
                <td>@if($plan->entitlements_pretty)<details class="small"><summary>عرض</summary><pre dir="ltr" style="font-size:12px;white-space:pre-wrap;margin:6px 0 0">{{ $plan->entitlements_pretty }}</pre></details>@else<span class="muted small">—</span>@endif</td>
                <td>
                    <form method="post" action="{{ route('admin.plans.update', $plan->slug) }}" class="row" style="gap:8px;align-items:center;flex-wrap:wrap">@csrf @method('PATCH')
                        <input class="input" type="number" name="price" min="0" max="100000000" value="{{ old('price', $plan->price) }}" aria-label="سعر {{ $plan->name }} بأصغر وحدة" style="width:130px;min-height:40px">
                        <input class="input" type="number" name="credits" min="0" max="1000000" value="{{ old('credits', $plan->credits) }}" aria-label="أرصدة {{ $plan->name }}" style="width:100px;min-height:40px">
                        <label class="row small" style="gap:6px;min-height:40px"><input type="checkbox" name="is_active" value="1" @checked($plan->is_active)> نشطة</label>
                        <button class="btn btn-primary" type="submit" style="min-height:40px">حفظ وتدقيق</button>
                    </form>
                </td>
            </tr>
        @empty<tr><td colspan="8" class="muted" style="padding:24px">لا خطط.</td></tr>@endforelse
        </tbody></x-athar.table>
    </div>

    <div class="grid grid-2">
        <div class="card" id="payments"><x-athar.kicker>آخر 50 دفعة</x-athar.kicker>
            <x-athar.table caption="المدفوعات" class="admin-table"><thead><tr><th>المساحة</th><th>الخطة</th><th>المبلغ</th><th>الحالة</th><th>تاريخ الدفع</th></tr></thead><tbody>
            @forelse($payments as $p)
                <tr><td>{{ $p->workspace_name ?? '—' }}</td><td>{{ $p->plan }}</td><td>{{ $money((int) $p->amount, $p->currency) }}</td><td>{{ $p->status }}</td><td>{{ $p->paid_at ? \Illuminate\Support\Carbon::parse($p->paid_at)->format('Y/m/d H:i') : '—' }}</td></tr>
            @empty<tr><td colspan="5" class="muted" style="padding:24px">لا مدفوعات بعد.</td></tr>@endforelse
            </tbody></x-athar.table>
        </div>
        <div class="card" id="subscriptions"><x-athar.kicker>الاشتراكات النشطة ({{ $stats['active'] }})</x-athar.kicker>
            <x-athar.table caption="الاشتراكات النشطة" class="admin-table"><thead><tr><th>المساحة</th><th>الخطة</th><th>الحالة</th><th>تنتهي</th></tr></thead><tbody>
            @forelse($subscriptions as $s)
                <tr><td>{{ $s->workspace_name ?? '—' }}</td><td>{{ $s->plan }}</td><td>{{ $s->status }}</td><td>{{ $s->ends_at ? \Illuminate\Support\Carbon::parse($s->ends_at)->format('Y/m/d') : 'مفتوح' }}</td></tr>
            @empty<tr><td colspan="4" class="muted" style="padding:24px">لا اشتراكات نشطة.</td></tr>@endforelse
            </tbody></x-athar.table>
        </div>
    </div>
</div></section>
@endsection
