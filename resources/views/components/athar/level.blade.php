@props(['level'])
@php($map = ['مبتدئ' => 'beginner', 'متوسط' => 'intermediate', 'متقدم' => 'advanced'])
<span {{ $attributes->merge(['class' => 'level level-'.($map[$level] ?? 'beginner')]) }}>{{ $level }}</span>
