@extends('layouts.platform')
@section('title', 'الخطط والأسعار — أثر')
@section('description', 'ابدأ تعلّم التسويق مجانًا مع الدروس والقوالب والحاسبات و20 رصيدًا أوليًا، أو اختر الخطة الاحترافية لمزيد من أرصدة الذكاء الاصطناعي.')
@section('content')
<section class="page-hero"><div class="shell"><x-athar.breadcrumbs :items="[['الخطط والأسعار', null]]" /><span class="kicker">اختر ما يخدم مشروعك</span><h1>ابدأ مجانًا. توسّع عندما تحتاج.</h1><p>الدروس والحاسبات والقوالب والمولدات القاعدية متاحة مجانًا.</p></div></section>
<section class="section-tight"><div class="narrow"><div class="grid grid-2 pricing-plans">
<article class="card"><span class="kicker">للبداية والتعلّم</span><h2>مجاني</h2><p class="price">0 <span>دولار</span></p><ul><li>كل المسارات والدروس</li><li>الحاسبات والمولدات والقوالب</li><li>20 رصيد AI عند إنشاء الحساب</li><li>حفظ الأصول والتقدم في مشروعك</li></ul><a class="btn btn-secondary" href="{{ route('register') }}">ابدأ مجانًا</a></article>
<article class="card featured-plan"><span class="kicker">للتطبيق المستمر</span><h2>{{ $plan?->name ?? 'الخطة الاحترافية' }}</h2>
@if($plan)<p class="price">{{ number_format($plan->price / 100, $plan->price % 100 ? 2 : 0) }} <span>{{ $plan->currency }} / شهر</span></p><ul><li>{{ $plan->credits }} رصيد AI لكل فترة مدفوعة</li><li>حفظ غير محدود للأصول والمشاريع</li><li>شهادات إكمال المسارات بتحقق عام</li><li>كل مزايا الخطة المجانية</li></ul><a class="btn btn-primary" href="{{ route('app.billing') }}">الاشتراك من صفحة الفوترة</a>
@else<p>الاشتراك الاحترافي غير متاح حاليًا. يمكنك استخدام الخطة المجانية.</p><a class="btn btn-secondary" href="{{ route('contact') }}">استفسر عن الاشتراك</a>@endif
</article></div>
<section class="card" style="margin-top:24px"><h2>مقارنة الخطط</h2><x-athar.table caption="مزايا الخطط"><thead><tr><th>الميزة</th><th>مجاني</th><th>احترافي</th></tr></thead><tbody><tr><th>الدروس والقوالب والحاسبات</th><td>متاحة</td><td>متاحة</td></tr><tr><th>التوليد بالقواعد</th><td>بلا خصم أرصدة</td><td>بلا خصم أرصدة</td></tr><tr><th>أرصدة AI</th><td>20 عند التسجيل</td><td>{{ $plan?->credits ?? '—' }} لكل فترة مدفوعة</td></tr><tr><th>حفظ الأصول</th><td>متاح</td><td>غير محدود</td></tr><tr><th>شهادة إكمال المسار (تحقق عام)</th><td>—</td><td>عند استيفاء شروط الإكمال</td></tr></tbody></x-athar.table></section>
<section class="card faq" style="margin-top:24px"><h2>أسئلة شائعة</h2>
<details><summary>متى يُخصم الرصيد؟</summary><p>عند تشغيل ميزة AI، وتظهر تكلفتها قبل التنفيذ. التوليد بالقواعد والحاسبات وتنزيل القوالب لا يستهلك رصيدًا.</p></details>
<details><summary>هل أحتاج إلى الاشتراك لكي أتعلّم؟</summary><p>لا. جميع الدروس متاحة. الحساب المجاني يحفظ تقدمك وأصولك، والترقية تضيف أرصدة لاستخدام AI.</p></details>
<details><summary>كيف أحصل على الشهادة؟</summary><p>أكمل دروس المسار بحفظ أصولها والإجابة عن أسئلة القرار، ثم سجّل نتيجة واقعية واحدة على الأقل في المسار. الشهادات من مزايا الخطة الاحترافية: ما أكملته في الخطة المجانية يبقى محسوبًا وتُصدر شهادته فور تفعيل الاشتراك، ويظهر رابط التحقق العام في صفحة تقدمي. الشهادة لا تمثل اعتمادًا مهنيًا خارجيًا.</p></details>
<details><summary>هل الدفع يتجدد تلقائيًا؟</summary><p>الدفع الحالي عبر PayPal لشراء فترة وصول مدتها شهر. لا يُنشئ خصمًا تلقائيًا متكررًا. راجع المبلغ والعملة في صفحة الفوترة قبل التأكيد.</p></details>
</section></div></section>
@endsection
