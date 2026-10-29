{{-- بطاقة الأصل (§1.4 رقم 6 و§4.7 رقم 4): عنوان المخرج، المحتوى، فحص قابلية التنفيذ الستّي، الإصدارات مع «ما الذي تغيّر؟»، شريط أفعال: نسخ، تنزيل Markdown، تنزيل PDF، حفظ في مشروعي، مشاركة، إعادة التوليد --}}
@props(['title', 'type' => null, 'markdown', 'kicker' => 'أصلك', 'verdict' => null, 'saveUrl' => null, 'savePayload' => [], 'saved' => false, 'regenerateForm' => null, 'id' => 'asset', 'member' => false, 'lessonId' => null, 'filename' => null, 'quality' => null, 'versions' => null, 'diff' => null, 'currentVersion' => null, 'fixPrefix' => '#f-'])
@php($verdictMap = ['within' => ['verdict-within', 'ضمن الهدف'], 'edge' => ['verdict-edge', 'على الحافة'], 'outside' => ['verdict-outside', 'خارج الهدف']])
@php($explicitVerdict = $verdict)
@php($verdict = $explicitVerdict ?? ($quality['verdict'] ?? null))
@if($explicitVerdict === null && $quality)@php($verdictMap = ['within' => ['verdict-within', \App\Domain\Lab\Services\QualityChecker::VERDICTS['within']], 'edge' => ['verdict-edge', \App\Domain\Lab\Services\QualityChecker::VERDICTS['edge']], 'outside' => ['verdict-outside', \App\Domain\Lab\Services\QualityChecker::VERDICTS['outside']]])@endif
<section {{ $attributes->merge(['class' => 'asset-card', 'id' => $id]) }} data-asset data-title="{{ $title }}" data-type="{{ $type }}">
    <div class="asset-head">
        <div><span class="kicker">{{ $kicker }}</span><h3>{{ $title }}</h3>@if($type)<small class="muted">النوع: {{ \App\Domain\Lab\Services\LabCatalog::label($type) }}@if($currentVersion) · v{{ $currentVersion }}@endif</small>@endif</div>
        @if($verdict && isset($verdictMap[$verdict]))<span class="asset-verdict {{ $verdictMap[$verdict][0] }}" data-verdict>{{ $verdictMap[$verdict][1] }}</span>@endif
    </div>
    @if($versions && count($versions) > 0)
    <div class="asset-versions" data-versions><span class="small muted">الإصدارات:</span>
        @foreach($versions as $v)<a class="ver {{ $currentVersion === $v->version ? 'is-current' : '' }}" href="{{ route('app.outputs.show', $v) }}" title="{{ $v->created_at->format('Y/m/d H:i') }}">v{{ $v->version }}</a>@if(! $loop->last)<span class="muted">·</span>@endif @endforeach
    </div>
    @endif
    <div class="asset-body" data-asset-html>{!! \App\Support\Markdown::toHtml($markdown) !!}</div>
    <textarea class="sr-only" data-asset-md aria-hidden="true" tabindex="-1" readonly>{{ $markdown }}</textarea>
    @if($quality && ! empty($quality['checks']))
    <div class="quality" data-quality>
        <div class="split"><h4>فحص قابلية التنفيذ</h4><span class="small muted">{{ $quality['passed'] }}/{{ $quality['total'] }} بنود متحققة</span></div>
        <ol class="quality-list">
            @foreach($quality['checks'] as $check)
            <li class="q-{{ $check['ok'] ? 'ok' : 'no' }} {{ $check['applies'] ? '' : 'q-na' }}" data-check="{{ $check['key'] }}" data-ok="{{ $check['ok'] ? '1' : '0' }}">
                <span class="q-mark" aria-hidden="true">{{ $check['ok'] ? '✓' : '✗' }}</span>
                <span class="q-label">{{ $check['label'] }}@if(! $check['applies']) <span class="muted small">(لا ينطبق)</span>@elseif($check['level'] === 'advisory') <span class="muted small">(استرشادي)</span>@endif</span>
                @if(! $check['ok'])<span class="q-hint">{{ $check['hint'] }} @if($check['field'])<a href="{{ $fixPrefix }}{{ $check['field'] }}" data-fix="{{ $check['field'] }}">صحّحه ←</a>@endif</span>@endif
            </li>
            @endforeach
        </ol>
    </div>
    @endif
    @if($diff)
    <details class="asset-diff" data-diff><summary>ما الذي تغيّر؟ <span class="muted small">{{ $diff['summary'] }}</span></summary>
        <div class="diff-cols">
            <div><h5>أُضيف</h5>@forelse($diff['added'] as $line)<p class="diff-add">+ {{ $line }}</p>@empty<p class="muted small">لا إضافات.</p>@endforelse</div>
            <div><h5>حُذف</h5>@forelse($diff['removed'] as $line)<p class="diff-del">− {{ $line }}</p>@empty<p class="muted small">لا محذوفات.</p>@endforelse</div>
        </div>
    </details>
    @endif
    {{ $slot }}
    <div class="asset-actions">
        <button class="btn btn-secondary" type="button" data-action="copy">نسخ</button>
        <button class="btn btn-secondary" type="button" data-action="download-md" data-filename="{{ $filename ?? Str::slug($title, '-') ?: 'asset' }}">تنزيل Markdown</button>
        <form method="post" action="{{ route('assets.print') }}" target="_blank" class="inline-form"> @csrf<input type="hidden" name="title" value="{{ $title }}"><input type="hidden" name="markdown" value="{{ $markdown }}"><button class="btn btn-secondary" type="submit" data-action="download-pdf">تنزيل PDF</button></form>
        @if($saveUrl)
            @if($member)
                @if($saved)<span class="btn btn-secondary" aria-disabled="true" data-saved>✓ محفوظ في مشروعي</span>
                @else<form method="post" action="{{ $saveUrl }}">@csrf<input type="hidden" name="title" value="{{ $title }}"><input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="markdown" value="{{ $markdown }}">@if($lessonId)<input type="hidden" name="lesson_id" value="{{ $lessonId }}">@endif @foreach($savePayload as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v }}">@endforeach<button class="btn btn-primary" type="submit" data-action="save">حفظ في مشروعي</button></form>@endif
            @else
                <a class="btn btn-primary" href="{{ route('register') }}" data-action="save-gate">حفظ في مشروعي (بحساب)</a>
            @endif
        @endif
        {{ $actions ?? '' }}
        @if($regenerateForm)<button class="btn btn-text" type="submit" form="{{ $regenerateForm }}" data-action="regenerate">إعادة التوليد</button>@endif
    </div>
</section>
