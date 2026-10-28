{{-- فتات الخبز (§1.4 رقم 11) مع BreadcrumbList Schema --}}
@props(['items'])
@php($list = array_values($items))
<nav aria-label="مسار الصفحة"><ol class="breadcrumbs">
    <li><a href="{{ route('home') }}">الرئيسية</a></li>
    @foreach($list as $i => $item)
        <li>@if(! empty($item[1]) && $i < count($list) - 1)<a href="{{ $item[1] }}">{{ $item[0] }}</a>@else<span aria-current="page">{{ $item[0] }}</span>@endif</li>
    @endforeach
</ol></nav>
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! json_encode(['@'.'context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => collect(array_merge([['الرئيسية', route('home')]], $list))->values()->map(fn ($item, $i) => array_filter(['@type' => 'ListItem', 'position' => $i + 1, 'name' => $item[0], 'item' => $item[1] ?? null]))->all()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush
