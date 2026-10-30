<section>
    <header>
        <x-athar.kicker>منطقة الخطر</x-athar.kicker>
        <h2>حذف الحساب</h2>
        <p class="muted small">عند حذف حسابك تُحذف كل بياناته وأصوله نهائيًا. نزّل ما تريد الاحتفاظ به من أصولك قبل الحذف.</p>
    </header>

    {{-- تأكيد بلا Alpine: <details> أصلي يُفتح تلقائيًا عند خطأ التحقق --}}
    <details class="danger-zone" @if ($errors->userDeletion->isNotEmpty()) open @endif>
        <summary class="btn btn-danger">حذف الحساب</summary>
        <form method="post" action="{{ route('profile.destroy') }}" class="stack">
            @csrf
            @method('delete')

            <h3>هل أنت متأكد من حذف حسابك؟</h3>
            <p class="muted small">سيُحذف الحساب وكل بياناته نهائيًا. أدخل كلمة المرور لتأكيد الحذف.</p>

            <div class="field">
                <x-input-label for="password" value="كلمة المرور" />
                <x-text-input id="password" name="password" type="password" placeholder="كلمة المرور" autocomplete="current-password" />
                <x-input-error :messages="$errors->userDeletion->get('password')" />
            </div>

            <div class="btn-row">
                <x-danger-button>حذف الحساب نهائيًا</x-danger-button>
                <x-secondary-button data-close-details>إلغاء</x-secondary-button>
            </div>
        </form>
    </details>
</section>
