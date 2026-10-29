<nav class="admin-nav" aria-label="الإدارة">
    <a href="{{ route('admin.index') }}" @if(request()->routeIs('admin.index')) aria-current="page" @endif>غرفة التحكم</a>
    <a href="{{ route('admin.academy.index') }}" @if(request()->routeIs('admin.academy.*')) aria-current="page" @endif>الأكاديمية والاستوديو</a>
    <a href="{{ route('admin.tools.index') }}" @if(request()->routeIs('admin.tools.*')) aria-current="page" @endif>استوديو الأدوات</a>
    <a href="{{ route('admin.assessment.index') }}" @if(request()->routeIs('admin.assessment.*')) aria-current="page" @endif>التشخيص</a>
    <a href="{{ route('admin.content.index') }}" @if(request()->routeIs('admin.content.*')) aria-current="page" @endif>المحتوى</a>
    <a href="{{ route('admin.sources.index') }}" @if(request()->routeIs('admin.sources.*')) aria-current="page" @endif>صندوق المصادر</a>
    <a href="{{ route('admin.operations') }}" @if(request()->routeIs('admin.operations')) aria-current="page" @endif>التشغيل</a>
</nav>
