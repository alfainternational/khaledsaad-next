{{-- سؤال القرار (§1.4 رقم 7): سيناريو + 3–4 خيارات + تغذية راجعة فورية لكل خيار + «القرار الأفضل». لا درجات مخفية. --}}
@props(['question', 'index' => 1, 'opening' => false, 'chosen' => null, 'name' => null])
@php($options = is_array($question->options) ? $question->options : [])
@php($best = collect($options)->search(fn ($o) => ! empty($o['is_best'])))
<div class="dq" data-question="{{ $question->id }}" data-opening="{{ $opening ? 1 : 0 }}" data-best="{{ $best === false ? '' : $best }}" @if($chosen !== null) data-answered="1" @endif id="q-{{ $question->id }}">
    <span class="kicker">{{ $opening ? 'قرارك الأول' : 'سؤال القرار '.$index }}</span>
    <p class="dq-scenario">{{ $question->scenario ?: $question->prompt }}</p>
    @if($question->scenario && $question->prompt)<p class="muted small">{{ $question->prompt }}</p>@endif
    <ul class="dq-options">
        @foreach($options as $i => $option)
        <li><button class="dq-option" type="button" data-option="{{ $i }}" data-feedback="{{ $option['feedback'] ?? '' }}" data-best="{{ ! empty($option['is_best']) ? 1 : 0 }}" @if($chosen !== null && (int) $chosen === $i) data-state="{{ $opening ? 'chosen' : (! empty($option['is_best']) ? 'best' : 'wrong') }}" @endif><b>{{ ['أ', 'ب', 'ج', 'د', 'هـ'][$i] ?? $i + 1 }}</b><span>{{ $option['label'] ?? '' }}</span></button></li>
        @endforeach
    </ul>
    @if($name)<input type="hidden" name="{{ $name }}" value="{{ $chosen ?? '' }}" data-choice-input>@endif
    <div class="dq-feedback" data-feedback-box hidden></div>
    @if(! $opening)<p class="dq-best" data-best-line hidden>القرار الأفضل: <b>{{ $best === false ? '—' : ($options[$best]['label'] ?? '') }}</b></p>@endif
</div>
