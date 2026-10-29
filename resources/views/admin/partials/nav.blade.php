<nav class="admin-nav" aria-label="الإدارة">
    <a href="{{ route('admin.index') }}" @if(request()->routeIs('admin.index')) aria-current="page" @endif>غرفة التحكم</a>
    <a href="{{ route('admin.academy.index') }}" @if(request()->routeIs('admin.academy.*')) aria-current="page" @endif>الأكاديمية والاستوديو</a>
    <a href="{{ route('admin.tools.index') }}" @if(request()->routeIs('admin.tools.*')) aria-current="page" @endif>استوديو الأدوات</a>
    <a href="{{ route('admin.assessment.index') }}" @if(request()->routeIs('admin.assessment.*')) aria-current="page" @endif>التشخيص</a>
    <a href="{{ route('admin.content.index') }}">المحتوى</a>
    <a href="{{ route('admin.operations') }}">التشغيل</a>
</nav>
