<x-guest-layout>
    <p class="muted">أدخل بريد حسابك لنرسل إليك رابط اختيار كلمة مرور جديدة.</p>

    <x-auth-session-status :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="field">
            <x-input-label for="email" value="البريد الإلكتروني" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="auth-actions">
            <a href="{{ route('login') }}">العودة إلى الدخول</a>
            <x-primary-button>إرسال رابط الاستعادة</x-primary-button>
        </div>
    </form>
</x-guest-layout>
