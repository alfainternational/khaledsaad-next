@props(['title', 'desc' => null, 'tag' => null, 'href' => null, 'soon' => false, 'number' => null, 'heading' => 'h3'])
@php($classes = 'card'.($soon ? ' is-soon' : '').($href ? ' is-link' : ''))
@if($href && ! $soon)
<a {{ $attributes->merge(['class' => $classes, 'href' => $href]) }}>
@else
<article {{ $attributes->merge(['class' => $classes]) }} @if($soon) aria-disabled="true" @endif>
@endif
    @if($number)<span class="card-num">{{ $number }}</span>@endif
    @if($tag)<span class="card-tag">{{ $tag }}</span>@endif
    <{{ $heading }}>{{ $title }}</{{ $heading }}>
    @if($desc)<p class="card-desc">{{ $desc }}</p>@endif
    {{ $slot }}
    @isset($meta)<div class="card-meta">{{ $meta }}</div>@endisset
@if($href && ! $soon)
</a>
@else
</article>
@endif
