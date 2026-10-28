<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" value="الاسم" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" value="البريد الإلكتروني" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" value="كلمة المرور" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" value="تأكيد كلمة المرور" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <!-- Audience + diagnosis claim (blueprint §4.14 / §4.2) -->
        <div class="mt-4">
            <x-input-label for="audience" value="أنت" />
            <select id="audience" name="audience" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                <option value="owner" @selected(old('audience', 'owner') === 'owner')>صاحب مشروع</option>
                <option value="marketer" @selected(old('audience') === 'marketer')>مسوّق أو وكالة</option>
            </select>
        </div>
        <input type="hidden" id="assessment_token" name="assessment_token" value="{{ old('assessment_token', request()->query('assessment')) }}">
        @if(request()->query('assessment'))<p class="mt-3 text-sm text-gray-600">ستُحفظ خريطة أولوياتك ويُنشأ ملف مشروعك من إجاباتك.</p>@endif
        <script nonce="{{ $cspNonce }}">(function(){try{var i=document.getElementById('assessment_token');if(!i.value){var s=JSON.parse(localStorage.getItem('athar-assessment')||'null');if(s&&s.token&&s.expires>Date.now())i.value=s.token}}catch(e){}})();</script>

        <div class="flex items-center justify-end mt-4">
            <a class="inline-block py-2 underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                لديك حساب بالفعل؟
            </a>

            <x-primary-button class="ms-4">
                إنشاء الحساب
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
