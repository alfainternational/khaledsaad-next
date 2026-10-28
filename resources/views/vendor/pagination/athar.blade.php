@if ($paginator->hasPages())
<nav class="pagination" aria-label="الترقيم">
    @if ($paginator->onFirstPage())<span aria-disabled="true">السابق</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">السابق</a>@endif
    @foreach ($elements as $element)
        @if (is_string($element))<span aria-disabled="true">{{ $element }}</span>@endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())<span aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif
            @endforeach
        @endif
    @endforeach
    @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">التالي</a>@else<span aria-disabled="true">التالي</span>@endif
</nav>
@endif
