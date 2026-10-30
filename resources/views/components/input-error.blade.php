@props(['messages'])

@if ($messages)
    @foreach ((array) $messages as $message)
        <p {{ $attributes->merge(['class' => 'error', 'role' => 'alert']) }}>{{ $message }}</p>
    @endforeach
@endif
