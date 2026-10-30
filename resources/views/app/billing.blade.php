@extends('layouts.platform')
@section('title', 'الاشتراك والأرصدة — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['لوحتي', route('dashboard')], ['الاشتراك والأرصدة', null]]" />
    <h1>الاشتراك والأرصدة</h1>
    <p class="muted">رصيد مساحة عملك وخطتك الحالية، والخطط المتاحة للاشتراك بدفعة مستقلة.</p>
</div></section>
<section class="section-tight"><div class="shell">
    <x-athar.flash />
    <div class="grid grid-2 billing-grid">
        @foreach($workspaces as $workspace)
            @php($ended = $workspace->subscription?->ends_at && \Illuminate\Support\Carbon::parse($workspace->subscription->ends_at)->isPast())
            <article class="card">
                <x-athar.kicker>مساحة العمل</x-athar.kicker>
                <h2>{{ $workspace->name }}</h2>
                <p class="price">{{ $workspace->balance }} <span>رصيد متاح</span></p>
                <ul class="list">
                    <li><span>الخطة</span><strong>{{ $workspace->subscription->plan ?? 'free' }}</strong></li>
                    <li><span>الحالة</span><strong>{{ $ended ? 'انتهت الفترة المدفوعة' : ($workspace->subscription->status ?? 'active') }}</strong></li>
                    @if($workspace->subscription?->ends_at)<li><span>نهاية الفترة</span><span class="when">{{ \Illuminate\Support\Carbon::parse($workspace->subscription->ends_at)->format('Y/m/d') }}</span></li>@endif
                </ul>
            </article>
        @endforeach
        @foreach($plans as $plan)
            <article class="card featured-plan">
                <x-athar.kicker>خطة</x-athar.kicker>
                <h2>{{ $plan->name }}</h2>
                <p class="price">{{ number_format($plan->price / 100, 2) }} <span>{{ $plan->currency }} / شهر</span></p>
                <p class="card-desc">{{ $plan->credits }} رصيد. تجديد يدوي بدفعة مستقلة؛ لا يوجد خصم متكرر تلقائي.</p>
                @if($workspaces->isNotEmpty())
                    @if($paymentsEnabled)
                        <form method="POST" action="{{ route('app.billing.orders.store') }}">@csrf<input type="hidden" name="workspace_id" value="{{ $workspaces->first()->id }}"><input type="hidden" name="plan_id" value="{{ $plan->id }}"><button class="btn btn-primary" type="submit">اشترك عبر PayPal</button></form>
                        <p class="small muted">الدفع يتم على صفحة PayPal الآمنة ولا نحتفظ ببيانات بطاقتك.</p>
                    @else
                        <p class="small muted">الدفع الإلكتروني غير مفعّل حالياً.</p>
                    @endif
                @endif
            </article>
        @endforeach
    </div>
</div></section>
@endsection
