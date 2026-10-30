<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="field">
            <x-input-label for="name" value="الاسم" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div class="field">
            <x-input-label for="email" value="البريد الإلكتروني" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="field">
            <x-input-label for="password" value="كلمة المرور" />
            <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="field">
            <x-input-label for="password_confirmation" value="تأكيد كلمة المرور" />
            <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        {{-- الجمهور واسترداد نتيجة التشخيص (§4.14 / §4.2) --}}
        <div class="field">
            <x-input-label for="audience" value="أنت" />
            <select id="audience" name="audience" class="input">
                <option value="owner" @selected(old('audience', 'owner') === 'owner')>صاحب مشروع</option>
                <option value="marketer" @selected(old('audience') === 'marketer')>مسوّق أو وكالة</option>
            </select>
        </div>
        <input type="hidden" id="assessment_token" name="assessment_token" value="{{ old('assessment_token', request()->query('assessment')) }}">
        @if(request()->query('assessment'))<p class="small muted">ستُحفظ خريطة أولوياتك ويُنشأ ملف مشروعك من إجاباتك.</p>@endif
        <script nonce="{{ $cspNonce }}">(function(){try{var i=document.getElementById('assessment_token');if(!i.value){var s=JSON.parse(localStorage.getItem('athar-assessment')||'null');if(s&&s.token&&s.expires>Date.now())i.value=s.token}}catch(e){}})();</script>

        <div class="auth-actions">
            <a href="{{ route('login') }}">لديك حساب بالفعل؟</a>
            <x-primary-button>إنشاء الحساب</x-primary-button>
        </div>
    </form>
</x-guest-layout>
