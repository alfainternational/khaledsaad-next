<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">التشغيل والتجارة</h2></x-slot>
    <div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="bg-white rounded-xl p-5 shadow-sm"><strong>المدفوعات</strong><span class="block text-3xl">{{ $stats['payments'] }}</span></div>
            <div class="bg-white rounded-xl p-5 shadow-sm"><strong>الاشتراكات النشطة</strong><span class="block text-3xl">{{ $stats['subscriptions'] }}</span></div>
            <div class="bg-white rounded-xl p-5 shadow-sm"><strong>تشغيلات فاشلة</strong><span class="block text-3xl">{{ $stats['failed_runs'] }}</span></div>
        </div>
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-xl font-bold mb-4">إضافة تحويل</h3>
            <form method="POST" action="{{ route('admin.redirects.store') }}" class="grid gap-3 md:grid-cols-4">@csrf
                <input class="rounded-lg border-gray-300" name="from_path" placeholder="/old-path" required>
                <input class="rounded-lg border-gray-300" name="to_path" placeholder="/new-path" required>
                <select class="rounded-lg border-gray-300" name="status_code"><option>301</option><option>302</option><option>307</option><option>308</option><option>410</option></select>
                <button class="rounded-lg bg-slate-900 text-white" type="submit">إضافة</button>
            </form>
        </div>
        <div class="bg-white rounded-xl p-6 shadow-sm"><h3 class="text-xl font-bold mb-4">التحويلات الحالية</h3>
            @forelse($redirects as $redirect)<div class="flex items-center justify-between gap-4 border-b py-3"><code>{{ $redirect->from_path }} → {{ $redirect->to_path }}</code><form method="POST" action="{{ route('admin.redirects.destroy', $redirect->id) }}">@csrf @method('DELETE')<button class="text-red-700" type="submit">حذف</button></form></div>@empty<p>لا توجد تحويلات.</p>@endforelse
        </div>
        <div class="bg-white rounded-xl p-6 shadow-sm"><h3 class="text-xl font-bold mb-4">الخطط والأسعار</h3>
            @foreach($plans as $plan)<form method="POST" action="{{ route('admin.plans.update', $plan->slug) }}" class="grid gap-3 md:grid-cols-4 items-end border-b py-3">@csrf @method('PATCH')<strong>{{ $plan->name }}</strong><label>السعر<input class="block w-full rounded-lg border-gray-300" type="number" name="price" value="{{ $plan->price }}"></label><label>الأرصدة<input class="block w-full rounded-lg border-gray-300" type="number" name="credits" value="{{ $plan->credits }}"></label><button class="rounded-lg bg-slate-900 text-white py-2" type="submit">حفظ وتدقيق</button></form>@endforeach
        </div>
    </div></div>
</x-app-layout>
