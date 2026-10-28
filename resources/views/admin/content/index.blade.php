<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="font-semibold text-xl text-gray-800">إدارة المحتوى</h1>
            <a href="{{ route('admin.index') }}" class="inline-block py-2 text-sm underline">لوحة الإدارة</a>
        </div>
    </x-slot>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('status'))<div class="mb-5 rounded-lg bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif
            @if($errors->has('publish'))<div class="mb-5 rounded-lg bg-red-50 p-4 text-red-800">{{ $errors->first('publish') }}</div>@endif
            <nav class="mb-4 flex flex-wrap gap-2 text-sm" aria-label="تصفية حسب الحالة">
                <a class="inline-block rounded-full px-3 py-2 {{ request('status') ? 'bg-gray-100' : 'bg-orange-100 text-orange-900' }}" href="{{ route('admin.content.index') }}">الكل</a>
                @foreach(['draft' => 'مسودة', 'review' => 'جاهزة للمراجعة', 'seo_review' => 'مراجعة SEO', 'published' => 'منشور'] as $key => $label)
                    <a class="inline-block rounded-full px-3 py-2 {{ request('status') === $key ? 'bg-orange-100 text-orange-900' : 'bg-gray-100' }}" href="{{ route('admin.content.index', ['status' => $key]) }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
                @endforeach
            </nav>
            <div class="bg-white shadow-sm rounded-xl overflow-x-auto">
                <table class="w-full min-w-[720px] text-right">
                    <thead class="bg-gray-50"><tr><th class="p-4">العنوان</th><th class="p-4">النوع</th><th class="p-4">الحالة</th><th class="p-4">الإجراء</th></tr></thead>
                    <tbody>
                    @forelse($items as $item)
                        <tr class="border-t">
                            <td class="p-4"><strong>{{ $item->title }}</strong><span class="block text-xs text-gray-500" dir="ltr">{{ $item->slug }}</span></td>
                            <td class="p-4">{{ $item->type }}</td>
                            <td class="p-4">{{ $item->status }}</td>
                            <td class="p-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a class="inline-block rounded-lg border border-gray-300 px-3 py-2" href="{{ route('admin.content.edit', $item) }}">تعديل</a>
                                    @if(in_array($item->status, ['review', 'seo_review'], true))
                                        <form method="post" action="{{ route('admin.content.publish', $item) }}">@csrf<button class="rounded-lg bg-orange-600 px-4 py-2 text-white">نشر</button></form>
                                    @elseif($item->status === 'published')
                                        <a class="inline-block px-3 py-2 underline" href="{{ route('articles.show', $item) }}">عرض</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="p-6 text-gray-500" colspan="4">لا توجد مواد في هذه الحالة.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-5">{{ $items->links() }}</div>
        </div>
    </div>
</x-app-layout>
