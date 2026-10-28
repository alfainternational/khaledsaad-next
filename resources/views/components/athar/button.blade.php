@props(['variant' => 'primary', 'href' => null, 'type' => 'button', 'block' => false])
@php($class = 'btn btn-'.$variant.($block ? ' btn-block' : ''))
@if($href)
<a {{ $attributes->merge(['class' => $class, 'href' => $href]) }}>{{ $slot }}</a>
@else
<button {{ $attributes->merge(['class' => $class, 'type' => $type]) }}>{{ $slot }}</button>
@endif
