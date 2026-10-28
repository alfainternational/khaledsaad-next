@props(['type' => 'info'])
<div {{ $attributes->merge(['class' => 'alert alert-'.$type, 'role' => $type === 'error' ? 'alert' : 'status']) }}>{{ $slot }}</div>
