<nav class="filters" aria-label="شاشات المحتوى" style="margin-top:0">
    <a href="{{ route('admin.content.index') }}" @if(($active ?? '') === 'items') aria-current="page" @endif>المواد</a>
    <a href="{{ route('admin.content.taxonomy', 'categories') }}" @if(($active ?? '') === 'categories') aria-current="page" @endif>التصنيفات</a>
    <a href="{{ route('admin.content.taxonomy', 'tags') }}" @if(($active ?? '') === 'tags') aria-current="page" @endif>الوسوم</a>
    <a href="{{ route('admin.content.taxonomy', 'authors') }}" @if(($active ?? '') === 'authors') aria-current="page" @endif>المؤلفون</a>
    <a href="{{ route('admin.sources.index') }}" @if(($active ?? '') === 'sources') aria-current="page" @endif>صندوق المصادر</a>
</nav>
