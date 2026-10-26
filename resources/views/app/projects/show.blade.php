<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $project->name }}</h2></x-slot>
    <div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if(session('status'))<div class="bg-green-50 text-green-800 p-4 rounded-xl">{{ session('status') }}</div>@endif
        <div class="bg-white shadow-sm sm:rounded-xl p-6">
            <h3 class="text-xl font-bold mb-4">إعدادات المشروع</h3>
            <form method="POST" action="{{ route('app.projects.update', $project) }}" class="flex gap-3">@csrf @method('PATCH')
                <input class="flex-1 rounded-lg border-gray-300" name="name" value="{{ $project->name }}" required maxlength="120">
                <button class="px-5 py-2 rounded-lg bg-slate-900 text-white" type="submit">حفظ</button>
            </form>
        </div>
        <div class="bg-white shadow-sm sm:rounded-xl p-6">
            <h3 class="text-xl font-bold mb-4">مخرجات الأدوات المحفوظة</h3>
            <div class="space-y-4">
                @forelse($runs as $run)
                    <article class="border rounded-xl p-4">
                        <div class="flex items-center justify-between gap-4"><strong>{{ $run->tool_name }}</strong><span class="text-sm text-gray-500">{{ $run->created_at }}</span></div>
                        <p class="mt-3 whitespace-pre-line">{{ $run->output['text'] ?? $run->output['message'] ?? json_encode($run->output, JSON_UNESCAPED_UNICODE) }}</p>
                        <form class="mt-4" method="POST" action="{{ route('app.tool-runs.destroy', $run->id) }}">@csrf @method('DELETE')<button class="text-red-700" type="submit">حذف المخرج</button></form>
                    </article>
                @empty
                    <p class="text-gray-500">لا توجد مخرجات محفوظة بعد. شغّل إحدى أدوات المختبر من صفحة الأدوات.</p>
                @endforelse
            </div>
        </div>
        @if($workspace->pivot->role === 'owner')
            <form method="POST" action="{{ route('app.projects.destroy', $project) }}">@csrf @method('DELETE')<button class="text-red-700" type="submit">حذف المشروع</button></form>
        @endif
    </div></div>
</x-app-layout>
