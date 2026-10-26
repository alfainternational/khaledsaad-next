<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            مساحة العمل
        </h2>
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
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
