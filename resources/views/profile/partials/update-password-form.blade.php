<section>
    <header>
        <x-athar.kicker>الأمان</x-athar.kicker>
        <h2>تغيير كلمة المرور</h2>
        <p class="muted small">استخدم كلمة مرور طويلة وعشوائية لا تكررها في خدمة أخرى.</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="stack">
        @csrf
        @method('put')

        <div class="field">
            <x-input-label for="update_password_current_password" value="كلمة المرور الحالية" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" />
        </div>

        <div class="field">
            <x-input-label for="update_password_password" value="كلمة المرور الجديدة" />
            <x-text-input id="update_password_password" name="password" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" />
        </div>

        <div class="field">
            <x-input-label for="update_password_password_confirmation" value="تأكيد كلمة المرور الجديدة" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" />
        </div>

        <div class="btn-row">
            <x-primary-button>حفظ</x-primary-button>
            @if (session('status') === 'password-updated')
                <p class="form-note" role="status">تم الحفظ.</p>
            @endif
        </div>
    </form>
</section>
