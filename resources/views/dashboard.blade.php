<x-app-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            مساحة العمل
        </h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-2xl font-bold mb-2">مرحباً {{ auth()->user()->name }}</h3>
                    <p class="text-gray-600">من هنا تدير مشاريعك، تكمل المسارات وتحفظ مخرجات مختبر التسويق.</p>
                    <div class="grid gap-4 md:grid-cols-3 mt-8">
                        <a class="border rounded-xl p-5" href="{{ route('academy.index') }}"><strong>تابع التعلّم</strong><span class="block text-sm text-gray-500 mt-2">دروس وتمارين مرتبطة بمشروعك</span></a>
                        <a class="border rounded-xl p-5" href="{{ route('tools.index') }}"><strong>افتح المختبر</strong><span class="block text-sm text-gray-500 mt-2">أنشئ مخرجاً واحفظ نسخه</span></a>
                        <a class="border rounded-xl p-5" href="{{ route('articles.index') }}"><strong>أحدث الرؤى</strong><span class="block text-sm text-gray-500 mt-2">محتوى موثّق وقابل للتطبيق</span></a>
                    </div>
                    <div class="mt-10 grid gap-6 lg:grid-cols-2">
                        <div>
                            <h3 class="text-xl font-bold mb-4">مشاريعك</h3>
                            <div class="space-y-3">
                                @forelse($workspaces as $workspace)
                                    @foreach($workspace->projects as $project)
                                        <a class="block border rounded-xl p-4 hover:border-orange-400" href="{{ route('app.projects.show', $project) }}">
                                            <strong>{{ $project->name }}</strong><span class="block text-sm text-gray-500">{{ $workspace->name }}</span>
                                        </a>
                                    @endforeach
                                @empty
                                    <p class="text-gray-500">لا توجد مساحة عمل بعد.</p>
                                @endforelse
                            </div>
                        </div>
                        @if($workspaces->isNotEmpty())
                            <form method="POST" action="{{ route('app.projects.store') }}" class="border rounded-xl p-5">
                                @csrf
                                <h3 class="text-xl font-bold mb-4">مشروع جديد</h3>
                                <label class="block mb-2" for="workspace_id">مساحة العمل</label>
                                <select class="w-full rounded-lg border border-gray-300" id="workspace_id" name="workspace_id">@foreach($workspaces as $workspace)<option value="{{ $workspace->id }}">{{ $workspace->name }}</option>@endforeach</select>
                                <label class="block mt-4 mb-2" for="project_name">اسم المشروع</label>
                                <input class="w-full rounded-lg border border-gray-300" id="project_name" name="name" required maxlength="120">
                                <button class="mt-4 px-5 py-2 rounded-lg bg-orange-600 text-white" type="submit">إنشاء المشروع</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
