{{-- المختبر المضمّن (§1.4 رقم 8): نموذج من 3–5 حقول → «أنشئ» → بطاقة أصل. يعمل بلا AI عبر قوالب وقواعد. --}}
@props(['action', 'id' => 'lab-form', 'submitLabel' => 'أنشئ الأصل', 'title' => null, 'ai' => false])
<div {{ $attributes->merge(['class' => 'lab']) }}>
    @if($title)<h3>{{ $title }}</h3>@endif
    <form id="{{ $id }}" method="post" action="{{ $action }}">
        @csrf
        <div class="lab-fields">{{ $fields }}</div>
        <div class="btn-row"><button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>@if($ai)<button class="btn btn-secondary" type="submit" name="enhance" value="1">حسّن بالذكاء الاصطناعي</button>@endif {{ $extra ?? '' }}</div>
    </form>
    {{ $slot }}
</div>
