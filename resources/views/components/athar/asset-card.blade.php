{{-- بطاقة الأصل (§1.4 رقم 6): عنوان المخرج، المحتوى، شريط أفعال: نسخ، تنزيل Markdown، تنزيل PDF، حفظ في مشروعي، إعادة التوليد --}}
@props(['title', 'type' => null, 'markdown', 'kicker' => 'أصلك', 'verdict' => null, 'saveUrl' => null, 'savePayload' => [], 'saved' => false, 'regenerateForm' => null, 'id' => 'asset', 'member' => false, 'lessonId' => null, 'filename' => null])
@php($verdictMap = ['within' => ['verdict-within', 'ضمن الهدف'], 'edge' => ['verdict-edge', 'على الحافة'], 'outside' => ['verdict-outside', 'خارج الهدف']])
<section {{ $attributes->merge(['class' => 'asset-card', 'id' => $id]) }} data-asset data-title="{{ $title }}" data-type="{{ $type }}">
    <div class="asset-head">
        <div><span class="kicker">{{ $kicker }}</span><h3>{{ $title }}</h3>@if($type)<small class="muted">النوع: {{ $type }}</small>@endif</div>
        @if($verdict && isset($verdictMap[$verdict]))<span class="asset-verdict {{ $verdictMap[$verdict][0] }}" data-verdict>{{ $verdictMap[$verdict][1] }}</span>@endif
    </div>
    <div class="asset-body" data-asset-html>{!! \App\Support\Markdown::toHtml($markdown) !!}</div>
    <textarea class="sr-only" data-asset-md aria-hidden="true" tabindex="-1" readonly>{{ $markdown }}</textarea>
    {{ $slot }}
    <div class="asset-actions">
        <button class="btn btn-secondary" type="button" data-action="copy">نسخ</button>
        <button class="btn btn-secondary" type="button" data-action="download-md" data-filename="{{ $filename ?? Str::slug($title, '-') ?: 'asset' }}">تنزيل Markdown</button>
        <form method="post" action="{{ route('assets.print') }}" target="_blank" class="inline-form"> @csrf<input type="hidden" name="title" value="{{ $title }}"><input type="hidden" name="markdown" value="{{ $markdown }}"><button class="btn btn-secondary" type="submit" data-action="download-pdf">تنزيل PDF</button></form>
        @if($saveUrl)
            @if($member)
                @if($saved)<span class="btn btn-secondary" aria-disabled="true">✓ محفوظ في مشروعي</span>
                @else<form method="post" action="{{ $saveUrl }}">@csrf<input type="hidden" name="title" value="{{ $title }}"><input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="markdown" value="{{ $markdown }}">@if($lessonId)<input type="hidden" name="lesson_id" value="{{ $lessonId }}">@endif @foreach($savePayload as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v }}">@endforeach<button class="btn btn-primary" type="submit" data-action="save">حفظ في مشروعي</button></form>@endif
            @else
                <a class="btn btn-primary" href="{{ route('register') }}" data-action="save-gate">حفظ في مشروعي (بحساب)</a>
            @endif
        @endif
        @if($regenerateForm)<button class="btn btn-text" type="submit" form="{{ $regenerateForm }}" data-action="regenerate">إعادة التوليد</button>@endif
    </div>
</section>
