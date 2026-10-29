<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        افتح رسالة التحقق التي أرسلناها إلى بريدك واضغط رابط التأكيد لتتمكن من حفظ تقدمك وأصولك. إن لم تجدها، افحص البريد غير المرغوب أو أعد الإرسال.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            أرسلنا رابط تحقق جديدًا إلى بريدك الإلكتروني.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    إعادة إرسال رسالة التحقق
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                تسجيل الخروج
            </button>
        </form>
    </div>
</x-guest-layout>
