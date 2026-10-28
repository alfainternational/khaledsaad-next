<x-app-layout>
    <x-slot name="header"><h1 class="font-semibold text-xl text-gray-800">الاشتراك والأرصدة</h1></x-slot>
    <div class="py-12"><div class="max-w-5xl mx-auto sm:px-6 lg:px-8 grid gap-5 md:grid-cols-2">
        @if(session('status'))<div class="md:col-span-2 bg-green-50 text-green-800 p-4 rounded-xl">{{ session('status') }}</div>@endif
        @foreach($workspaces as $workspace)
            <article class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-xl font-bold">{{ $workspace->name }}</h3>
                <p class="mt-3">الرصيد المتاح: <strong class="text-2xl">{{ $workspace->balance }}</strong></p>
                <p class="mt-2">الخطة: <strong>{{ $workspace->subscription->plan ?? 'free' }}</strong></p>
                <p>الحالة: {{ $workspace->subscription->status ?? 'active' }}</p>
            </article>
        @endforeach
        @foreach($plans as $plan)
            <article class="bg-slate-900 text-white rounded-xl shadow-sm p-6">
                <h3 class="text-xl font-bold">{{ $plan->name }}</h3><p class="text-3xl mt-3">{{ number_format($plan->price / 100, 2) }} {{ $plan->currency }}</p><p>{{ $plan->credits }} رصيد</p>
                @if($workspaces->isNotEmpty())<form method="POST" action="{{ route('app.billing.orders.store') }}" class="mt-4">@csrf<input type="hidden" name="workspace_id" value="{{ $workspaces->first()->id }}"><input type="hidden" name="plan_id" value="{{ $plan->id }}"><button class="px-5 py-2 bg-orange-500 rounded-lg" type="submit">إنشاء طلب اشتراك</button></form>@endif
            </article>
        @endforeach
    </div></div>
</x-app-layout>
