@extends('layouts.platform')
@section('title', 'التشخيص — الإدارة — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell"><x-athar.kicker>الإدارة</x-athar.kicker><h1>أسئلة التشخيص وقواعد التوجيه</h1><p class="muted small">{{ $stats['results'] }} تشخيصاً إجمالاً · {{ $stats['last_30'] }} في آخر 30 يوماً</p>@include('admin.partials.nav')</div></section>
<section class="section-tight"><div class="shell stack" style="gap:24px">
    <x-athar.flash />
    <div class="card" id="coverage"><x-athar.kicker>خريطة التغطية</x-athar.kicker><h2 style="font-size:20px">جمهور × مستوى × بُعد</h2>
        @foreach($coverage as $audience => $byLevel)
        <h3 style="font-size:16px;margin-top:12px">{{ $audiences[$audience] }}</h3>
        <div class="coverage"><div class="head">المستوى</div>@foreach($dimensions as $d)<div class="head">{{ $d }}</div>@endforeach
            @foreach($byLevel as $level => $byDim)<div class="head">{{ $levels[$level] }}</div>@foreach($byDim as $dim => $rule)<div class="{{ $rule ? 'ok' : 'no' }}" title="{{ $rule?->lesson?->title }}">{{ $rule ? '✓ '.Str::limit($rule->lesson?->title ?? 'بلا درس', 18) : '✗' }}</div>@endforeach @endforeach
        </div>
        @endforeach
    </div>

    @foreach($questions as $audience => $list)
    <div class="card"><x-athar.kicker>أسئلة {{ $audiences[$audience] ?? $audience }}</x-athar.kicker>
        @foreach($list as $q)
        <form method="post" action="{{ route('admin.assessment.questions.update', $q) }}" class="stack" style="padding:14px 0;border-top:1px solid var(--line)" id="aq-{{ $q->id }}">@csrf @method('PUT')
            <input type="hidden" name="audience" value="{{ $q->audience }}">
            <div class="grid grid-2"><div class="field"><label for="aq-{{ $q->id }}-dim">البُعد</label><select id="aq-{{ $q->id }}-dim" name="dimension">@foreach($dimensions as $k => $l)<option value="{{ $k }}" @selected($q->dimension === $k)>{{ $l }}</option>@endforeach</select></div><div class="field"><label for="aq-{{ $q->id }}-pos">الترتيب</label><input id="aq-{{ $q->id }}-pos" name="position" type="number" min="1" value="{{ $q->position }}"></div></div>
            <div class="field"><label for="aq-{{ $q->id }}-prompt">نص السؤال (يقبل @{{offer}} @{{budget}} @{{channel}} @{{goal}} @{{goal_number}})</label><textarea id="aq-{{ $q->id }}-prompt" name="prompt">{{ $q->prompt }}</textarea></div>
            @foreach($q->options as $i => $opt)<div class="grid grid-2" style="gap:8px"><div class="field"><label for="aq-{{ $q->id }}-o{{ $i }}">الخيار {{ $i + 1 }}</label><input id="aq-{{ $q->id }}-o{{ $i }}" name="options[{{ $i }}][label]" value="{{ $opt['label'] }}"></div><div class="field"><label for="aq-{{ $q->id }}-w{{ $i }}">الوزن (0–3)</label><input id="aq-{{ $q->id }}-w{{ $i }}" name="options[{{ $i }}][weight]" type="number" min="0" max="3" value="{{ $opt['weight'] }}"></div></div>@endforeach
            <div class="btn-row"><label class="row small"><input type="checkbox" name="is_active" value="1" @checked($q->is_active)> فعّال</label><x-athar.button type="submit" variant="secondary">حفظ</x-athar.button><button class="btn btn-text" type="submit" form="aq-del-{{ $q->id }}">حذف</button></div>
        </form>
        <form id="aq-del-{{ $q->id }}" method="post" action="{{ route('admin.assessment.questions.destroy', $q) }}" data-confirm="حذف السؤال؟">@csrf @method('DELETE')</form>
        @endforeach
    </div>
    @endforeach

    <form method="post" action="{{ route('admin.assessment.questions.store') }}" class="card stack" id="aq-new">@csrf
        <x-athar.kicker>سؤال جديد</x-athar.kicker>
        <div class="grid grid-2"><div class="field"><label for="new-aud">الجمهور</label><select id="new-aud" name="audience">@foreach($audiences as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div><div class="field"><label for="new-dim">البُعد</label><select id="new-dim" name="dimension">@foreach($dimensions as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div></div>
        <div class="field"><label for="new-prompt">نص السؤال</label><textarea id="new-prompt" name="prompt" required></textarea></div>
        @for($i = 0; $i < 4; $i++)<div class="grid grid-2" style="gap:8px"><div class="field"><label for="new-o{{ $i }}">الخيار {{ $i + 1 }}</label><input id="new-o{{ $i }}" name="options[{{ $i }}][label]" @if($i < 3) required @endif></div><div class="field"><label for="new-w{{ $i }}">الوزن</label><input id="new-w{{ $i }}" name="options[{{ $i }}][weight]" type="number" min="0" max="3" value="{{ $i }}"></div></div>@endfor
        <div class="btn-row"><x-athar.button type="submit">أضف السؤال</x-athar.button></div>
    </form>

    <div class="card" id="rules"><x-athar.kicker>قواعد التوجيه</x-athar.kicker><h2 style="font-size:20px">جمهور × مستوى × أضعف بُعد ← درس البداية + 3 أولويات</h2>
        <x-athar.table caption="قواعد التوجيه"><thead><tr><th>الجمهور</th><th>المستوى</th><th>أضعف بُعد</th><th>درس البداية</th><th>الأولويات</th></tr></thead><tbody>@foreach($rules as $r)<tr><td>{{ $audiences[$r->audience] ?? $r->audience }}</td><td>{{ $levels[$r->level] }}</td><td>{{ $dimensions[$r->weakest_dimension] }}</td><td>{{ $r->lesson?->title ?? '—' }}</td><td class="small">@foreach($r->priorities as $p){{ $loop->iteration }}. {{ $p['title'] }}<br>@endforeach</td></tr>@endforeach</tbody></x-athar.table>
        <details style="margin-top:14px"><summary class="btn btn-secondary" style="display:inline-flex">تعديل/إضافة قاعدة</summary>
            <form method="post" action="{{ route('admin.assessment.rules.save') }}" class="stack" style="margin-top:12px" id="rule-form">@csrf
                <div class="grid grid-3"><div class="field"><label for="r-aud">الجمهور</label><select id="r-aud" name="audience">@foreach($audiences as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div><div class="field"><label for="r-lvl">المستوى</label><select id="r-lvl" name="level">@foreach($levels as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div><div class="field"><label for="r-dim">أضعف بُعد</label><select id="r-dim" name="weakest_dimension">@foreach($dimensions as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div></div>
                <div class="field"><label for="r-lesson">درس البداية</label><select id="r-lesson" name="lesson_id"><option value="">—</option>@foreach($lessons as $l)<option value="{{ $l->id }}">{{ $l->course->title }} · {{ $l->title }}</option>@endforeach</select></div>
                @for($i = 0; $i < 3; $i++)<div class="grid grid-3" style="gap:8px"><div class="field"><label for="r-t{{ $i }}">الأولوية {{ $i + 1 }}: العنوان</label><input id="r-t{{ $i }}" name="priorities[{{ $i }}][title]" required></div><div class="field"><label for="r-r{{ $i }}">السبب</label><input id="r-r{{ $i }}" name="priorities[{{ $i }}][reason]" required></div><div class="field"><label for="r-l{{ $i }}">درس (slug) أو مسار</label><input id="r-l{{ $i }}" name="priorities[{{ $i }}][lesson]" placeholder="first-segment"><input name="priorities[{{ $i }}][course]" placeholder="marketing-foundations" class="input" style="margin-top:4px"></div></div>@endfor
                <div class="btn-row"><x-athar.button type="submit">احفظ القاعدة</x-athar.button></div>
            </form>
        </details>
    </div>
</div></section>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">document.querySelectorAll('form[data-confirm]').forEach(function(f){f.addEventListener('submit',function(e){if(!confirm(f.dataset.confirm))e.preventDefault()})});</script>
@endpush
