{{-- بوابة التسجيل (§1.4 رقم 9): صندوق ثابت الشكل يقول ماذا تكسب بالتسجيل مع زر واحد --}}
@props(['title' => 'احفظ هذا في مشروعك', 'text' => 'بحساب مجاني تحتفظ بأصولك وتقدمك وتحصل على شهادة المسار.', 'cta' => 'أنشئ حساباً مجانياً', 'href' => null, 'benefits' => ['حفظ الأصول', 'تتبع التقدم', 'شهادة المسار']])
<aside {{ $attributes->merge(['class' => 'gate']) }} data-gate>
    <div><h3>{{ $title }}</h3><p>{{ $text }}</p><ul>@foreach($benefits as $b)<li>{{ $b }}</li>@endforeach</ul></div>
    <a class="btn btn-primary" href="{{ $href ?? route('register') }}">{{ $cta }}</a>
</aside>
