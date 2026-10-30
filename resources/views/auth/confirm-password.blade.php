<x-guest-layout>
    <p class="muted">أدخل كلمة مرور حسابك لتأكيد هويتك قبل المتابعة.</p>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <div class="field">
            <x-input-label for="password" value="كلمة المرور" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="auth-actions">
            <x-primary-button>تأكيد كلمة المرور</x-primary-button>
        </div>
    </form>
</x-guest-layout>
