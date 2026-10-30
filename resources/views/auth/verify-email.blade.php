<x-guest-layout>
    <p class="muted">افتح رسالة التحقق التي أرسلناها إلى بريدك واضغط رابط التأكيد لتتمكن من حفظ تقدمك وأصولك. إن لم تجدها، افحص البريد غير المرغوب أو أعد الإرسال.</p>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success" role="status">أرسلنا رابط تحقق جديدًا إلى بريدك الإلكتروني.</div>
    @endif

    <div class="auth-actions">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>إعادة إرسال رسالة التحقق</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-text">تسجيل الخروج</button>
        </form>
    </div>
</x-guest-layout>
