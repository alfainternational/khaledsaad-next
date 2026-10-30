@extends('layouts.platform')
@section('title', 'العمليات والصحة — الإدارة — أثر')
@section('noindex', '1')
@section('content')
@php($ageLabel = fn (?int $age, bool $ok) => $age === null ? 'لا نبض' : ($ok ? 'يعمل · آخر نبض قبل '.$age.' ث' : 'متأخر '.$age.' ث'))
<section class="app-hero"><div class="shell">
    <x-athar.kicker>الإدارة · الشاشات القياسية</x-athar.kicker><h1>العمليات والصحة</h1>
    <p class="muted small">نفس مؤشرات <code dir="ltr">/health.json</code>: قاعدة البيانات، نبض المجدول والعامل (أقل من 600 ثانية)، الطابور.</p>
    @include('admin.partials.nav')
</div></section>
<section class="section-tight"><div class="shell stack" style="gap:24px">
    <x-athar.flash />
    <div class="grid grid-3" aria-label="الصحة">
        <div class="card" data-health="database"><x-athar.kicker>قاعدة البيانات</x-athar.kicker><p style="font-size:22px;font-weight:700;margin:4px 0">{{ $health['database'] ? 'متصلة' : 'غير متاحة' }}</p><p class="muted small">{{ $health['database'] ? 'استعلام التحقق يعمل.' : 'فشل استعلام التحقق — راجع الاتصال.' }}</p></div>
        <div class="card" data-health="scheduler"><x-athar.kicker>المجدول</x-athar.kicker><p style="font-size:22px;font-weight:700;margin:4px 0">{{ $health['scheduler_ok'] ? 'سليم' : 'انتبه' }}</p><p class="muted small">{{ $ageLabel($health['scheduler_age'], $health['scheduler_ok']) }}</p></div>
        <div class="card" data-health="worker"><x-athar.kicker>عامل الطابور</x-athar.kicker><p style="font-size:22px;font-weight:700;margin:4px 0">{{ $health['worker_ok'] ? 'سليم' : 'انتبه' }}</p><p class="muted small">{{ $ageLabel($health['worker_age'], $health['worker_ok']) }}</p></div>
    </div>
    <div class="stat-grid" aria-label="الأرقام">
        <div class="stat"><b>{{ $health['queue_pending'] }}</b><span>مهام معلّقة في الطابور</span></div>
        <div class="stat"><b>{{ $health['queue_failed'] }}</b><span>مهام فاشلة</span></div>
        <div class="stat"><b>{{ $stats['payments'] }}</b><span>المدفوعات</span></div>
        <div class="stat"><b>{{ $stats['subscriptions'] }}</b><span>الاشتراكات النشطة</span></div>
        <div class="stat"><b>{{ $stats['failed_runs'] }}</b><span>تشغيلات أدوات فاشلة</span></div>
        <div class="stat"><b>{{ $stats['users'] }}</b><span>المستخدمون</span></div>
        <div class="stat"><b>{{ $stats['redirects'] }}</b><span>التحويلات</span></div>
        <div class="stat"><b>{{ $stats['audit'] }}</b><span>سجلات التدقيق</span></div>
    </div>
    <div class="grid grid-2">
        <div class="card" id="integrations"><x-athar.kicker>التكاملات</x-athar.kicker><ul class="list">
            <li><span>النسخة الاحتياطية</span><b>{{ $health['backup'] ?? 'لا علامة' }}</b></li>
            <li><span>PayPal</span><b>{{ $health['paypal'] ? 'مهيأ' : 'غير مهيأ' }}</b></li>
            <li><span>AI</span><b>{{ $health['ai'] ? 'مفتاح موجود' : 'بلا مفتاح' }}</b></li>
        </ul></div>
        <div class="card" id="screens"><x-athar.kicker>الجداول القياسية</x-athar.kicker>
            <p class="muted small">إدارة المستخدمين والخطط والتحويلات انتقلت إلى شاشاتها المستقلة؛ كل تغيير فيها يُسجَّل في التدقيق.</p>
            <div class="row" style="flex-wrap:wrap;gap:8px">
                <a class="btn btn-secondary" href="{{ route('admin.users.index') }}">المستخدمون والأدوار</a>
                <a class="btn btn-secondary" href="{{ route('admin.plans.index') }}">الخطط والدفع</a>
                <a class="btn btn-secondary" href="{{ route('admin.redirects.index') }}">التحويلات</a>
                <a class="btn btn-secondary" href="{{ route('admin.audit.index') }}">سجل التدقيق</a>
            </div>
        </div>
    </div>
    <div class="card" id="recent-audit"><x-athar.kicker>آخر 10 تغييرات مدقّقة</x-athar.kicker>
        <x-athar.table caption="آخر التغييرات" class="admin-table"><thead><tr><th>الوقت</th><th>المستخدم</th><th>الإجراء</th><th>الموضوع</th></tr></thead><tbody>
        @forelse($recentAudit as $row)
            <tr><td class="small">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('Y/m/d H:i') }}</td><td>{{ $row->user_name ?? 'النظام' }}</td><td><code dir="ltr">{{ $row->action }}</code></td><td class="small">{{ $row->subject_type }} {{ \Illuminate\Support\Str::limit((string) $row->subject_id, 40) }}</td></tr>
        @empty<tr><td colspan="4" class="muted" style="padding:24px">لا سجلات بعد.</td></tr>@endforelse
        </tbody></x-athar.table>
        <div class="row"><a class="btn btn-text" href="{{ route('admin.audit.index') }}">السجل الكامل ←</a></div>
    </div>
</div></section>
@endsection
