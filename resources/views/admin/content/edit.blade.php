<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="font-semibold text-xl text-gray-800">تعديل: {{ $item->title }}</h1>
            <a href="{{ route('admin.content.index') }}" class="inline-block py-2 text-sm underline">إدارة المحتوى</a>
        </div>
    </x-slot>
    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if(session('status'))<div class="rounded-lg bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif
            @if($errors->has('publish'))<div class="rounded-lg bg-red-50 p-4 text-red-800">{{ $errors->first('publish') }}</div>@endif
            @if($errors->any() && ! $errors->has('publish'))<div class="rounded-lg bg-red-50 p-4 text-red-800"><ul class="list-disc pr-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

            <form method="post" action="{{ route('admin.content.update', $item) }}" class="bg-white shadow-sm rounded-xl p-6 space-y-5">
                @csrf @method('PUT')
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="block mb-1 font-medium" for="title">العنوان</label>
                        <input class="w-full rounded-lg border border-gray-300" id="title" name="title" value="{{ old('title', $item->title) }}" required maxlength="200">
                    </div>
                    <div>
                        <label class="block mb-1 font-medium" for="slug">الرابط (slug)</label>
                        <input class="w-full rounded-lg border border-gray-300" dir="ltr" id="slug" name="slug" value="{{ old('slug', $item->slug) }}" required maxlength="180">
                        <p class="mt-1 text-xs text-gray-500">حروف وأرقام وشرطات فقط. تغييره بعد النشر يكسر الروابط القديمة.</p>
                    </div>
                    <div>
                        <label class="block mb-1 font-medium" for="type">النوع</label>
                        <select class="w-full rounded-lg border border-gray-300" id="type" name="type">
                            @foreach($types as $t)<option value="{{ $t }}" @selected(old('type', $item->type) === $t)>{{ $t }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block mb-1 font-medium" for="status">الحالة</label>
                        @if($item->status === 'published')
                            <input type="hidden" name="status" value="published">
                            <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800">منشور منذ {{ $item->published_at?->format('Y-m-d') }}</p>
                        @else
                            <select class="w-full rounded-lg border border-gray-300" id="status" name="status">
                                @foreach($statuses as $s)<option value="{{ $s }}" @selected(old('status', $item->status) === $s)>{{ ['draft' => 'مسودة', 'review' => 'جاهزة للمراجعة', 'seo_review' => 'مراجعة SEO'][$s] }}</option>@endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">النشر يتطلب حالة "جاهزة للمراجعة" أو "مراجعة SEO".</p>
                        @endif
                    </div>
                    <div>
                        <label class="block mb-1 font-medium" for="eyebrow">الوسم العلوي</label>
                        <input class="w-full rounded-lg border border-gray-300" id="eyebrow" name="eyebrow" value="{{ old('eyebrow', $item->eyebrow) }}" maxlength="80">
                    </div>
                    <div>
                        <label class="block mb-1 font-medium" for="topic">الموضوع</label>
                        <input class="w-full rounded-lg border border-gray-300" id="topic" name="topic" value="{{ old('topic', $item->topic) }}" maxlength="80">
                    </div>
                    <div>
                        <label class="block mb-1 font-medium" for="author_name">الكاتب</label>
                        <input class="w-full rounded-lg border border-gray-300" id="author_name" name="author_name" value="{{ old('author_name', $item->author_name) }}" required maxlength="120">
                    </div>
                    <div>
                        <label class="block mb-1 font-medium" for="reviewer_name">المراجع</label>
                        <input class="w-full rounded-lg border border-gray-300" id="reviewer_name" name="reviewer_name" value="{{ old('reviewer_name', $item->reviewer_name) }}" maxlength="120">
                    </div>
                    <div>
                        <label class="block mb-1 font-medium" for="reading_minutes">دقائق القراءة</label>
                        <input class="w-full rounded-lg border border-gray-300" type="number" min="1" max="120" id="reading_minutes" name="reading_minutes" value="{{ old('reading_minutes', $item->reading_minutes) }}" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block mb-1 font-medium" for="excerpt">الملخص</label>
                        <textarea class="w-full rounded-lg border border-gray-300" id="excerpt" name="excerpt" rows="3" required maxlength="600">{{ old('excerpt', $item->excerpt) }}</textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block mb-1 font-medium" for="body">المحتوى</label>
                        <textarea class="w-full rounded-lg border border-gray-300 font-mono text-sm" id="body" name="body" rows="22" required>{{ old('body', $item->body) }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">فقرات مفصولة بسطر فارغ. يُعرض كنص عادي بلا HTML.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button class="rounded-lg bg-orange-600 px-5 py-2 text-white" type="submit">حفظ</button>
                    @if($item->status === 'published')
                        <a class="inline-block px-3 py-2 underline" href="{{ route('articles.show', $item) }}" target="_blank" rel="noopener">عرض على الموقع</a>
                    @endif
                </div>
            </form>

            @if(in_array($item->status, ['review', 'seo_review'], true))
                <form method="post" action="{{ route('admin.content.publish', $item) }}" class="bg-white shadow-sm rounded-xl p-6 flex items-center justify-between gap-4">
                    @csrf
                    <p class="text-gray-700">المادة جاهزة للمراجعة. النشر يحفظ نسخة ويجعلها ظاهرة للزوار فوراً.</p>
                    <button class="rounded-lg bg-emerald-600 px-5 py-2 text-white" type="submit">نشر الآن</button>
                </form>
            @endif

            @if(data_get($item->meta, 'legacy'))
                <details class="bg-white shadow-sm rounded-xl p-6">
                    <summary class="cursor-pointer font-medium">النسخة الأصلية من الموقع القديم (للمقارنة)</summary>
                    <p class="mt-3 text-sm text-gray-500">عنوان SEO القديم: {{ data_get($item->meta, 'legacy.seo_title') ?: '—' }}</p>
                    <div class="mt-3 prose max-w-none text-sm">{!! strip_tags((string) data_get($item->meta, 'legacy.body_html'), '<p><h2><h3><ul><ol><li><strong><em><blockquote><br>') !!}</div>
                </details>
            @endif
        </div>
    </div>
</x-app-layout>
