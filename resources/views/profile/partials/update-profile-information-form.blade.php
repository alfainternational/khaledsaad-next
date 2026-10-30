<section>
    <header>
        <x-athar.kicker>بيانات الحساب</x-athar.kicker>
        <h2>معلومات الملف الشخصي</h2>
        <p class="muted small">حدّث اسمك وبريدك الإلكتروني المستخدم للدخول والتنبيهات.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="stack">
        @csrf
        @method('patch')

        <div class="field">
            <x-input-label for="name" value="الاسم" />
            <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div class="field">
            <x-input-label for="email" value="البريد الإلكتروني" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <p class="small">بريدك الإلكتروني غير مؤكد. <button form="send-verification" class="btn btn-text" type="submit">أعد إرسال رسالة التأكيد</button></p>
                @if (session('status') === 'verification-link-sent')
                    <p class="form-note" role="status">أرسلنا رابط تحقق جديدًا إلى بريدك الإلكتروني.</p>
                @endif
            @endif
        </div>

        <div class="btn-row">
            <x-primary-button>حفظ</x-primary-button>
            @if (session('status') === 'profile-updated')
                <p class="form-note" role="status">تم الحفظ.</p>
            @endif
        </div>
    </form>
</section>
