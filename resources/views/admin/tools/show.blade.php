@extends('layouts.platform')
@section('title', 'استوديو الأداة: '.$tool->name.' — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['غرفة التحكم', route('admin.index')], ['استوديو الأدوات', route('admin.tools.index')], [$tool->name, null]]" />
    <div class="split"><div><x-athar.kicker>استوديو الأداة · {{ $tool->kindLabel() }}</x-athar.kicker><h1>{{ $tool->name }}</h1></div>
        <div class="stat" id="save-rate"><b data-save-rate>{{ $rate['rate'] === null ? '—' : $rate['rate'].'٪' }}</b><span>معدل الحفظ · {{ $rate['saved'] }} محفوظ من {{ $rate['generated'] }} مولَّد</span></div>
    </div>
    @include('admin.partials.nav')
</div></section>
<section class="section-tight"><div class="shell stack" style="gap:24px">
    <x-athar.flash />
    <div class="grid grid-2" style="align-items:start">
        <form class="card stack" method="post" action="{{ route('admin.tools.update', $tool) }}" id="tool-definition">@csrf @method('PUT')
            <x-athar.kicker>التعريف ومخطط الحقول</x-athar.kicker>
            <div class="field"><label for="name">الاسم</label><input id="name" name="name" required maxlength="120" value="{{ old('name', $tool->name) }}"></div>
            <div class="field"><label for="description">الوصف</label><textarea id="description" name="description" required maxlength="600">{{ old('description', $tool->description) }}</textarea></div>
            <div class="field"><label for="tagline">ماذا تُدخل وماذا تخرج</label><input id="tagline" name="tagline" maxlength="300" value="{{ old('tagline', $tool->tagline) }}"></div>
            <div class="grid grid-2">
                <div class="field"><label for="category">الفئة</label><select id="category" name="category">@foreach($categories as $c)<option value="{{ $c }}" @selected($tool->category === $c)>{{ $c }}</option>@endforeach</select></div>
                <div class="field"><label for="credit_cost">رصيد «حسّن بالذكاء الاصطناعي»</label><input id="credit_cost" name="credit_cost" type="number" min="0" max="50" value="{{ old('credit_cost', $tool->credit_cost) }}"></div>
            </div>
            <label class="row small"><input type="checkbox" name="is_published" value="1" @checked($tool->is_published)> منشورة في المختبر</label>
            @if($tool->kind === 'generator')
            <div class="grid grid-2">
                <fieldset class="field"><legend class="small" style="font-weight:700">يُغذّى من</legend>@foreach($chainTypes as $k => $l)<label class="small row"><input type="checkbox" name="feeds_from[]" value="{{ $k }}" @checked(in_array($k, $tool->feeds_from ?? [], true))> {{ $l }}</label>@endforeach</fieldset>
                <fieldset class="field"><legend class="small" style="font-weight:700">يغذّي</legend>@foreach($chainTypes as $k => $l)@if($k !== 'profile')<label class="small row"><input type="checkbox" name="feeds_into[]" value="{{ $k }}" @checked(in_array($k, $tool->feeds_into ?? [], true))> {{ $l }}</label>@endif @endforeach</fieldset>
            </div>
            <fieldset class="field"><legend class="small" style="font-weight:700">قواعد فحص قابلية التنفيذ (الستة تظهر دائماً؛ المطلوبة تحكم النتيجة)</legend>
                <x-athar.table caption="قواعد الجودة"><thead><tr><th>البند</th><th>مطلوب</th><th>استرشادي</th></tr></thead><tbody>@foreach($checks as $k => $l)<tr><td>{{ $l }}</td><td><input type="checkbox" name="required_rules[]" value="{{ $k }}" @checked(in_array($k, $tool->quality_rules['required'] ?? [], true)) aria-label="مطلوب: {{ $l }}"></td><td><input type="checkbox" name="advisory_rules[]" value="{{ $k }}" @checked(in_array($k, $tool->quality_rules['advisory'] ?? [], true)) aria-label="استرشادي: {{ $l }}"></td></tr>@endforeach</tbody></x-athar.table>
            </fieldset>
            <div class="field"><label for="input_schema">مخطط الحقول (JSON: key, label, type, options, source, from_asset, required…)</label><textarea id="input_schema" name="input_schema" class="mono" rows="10">{{ old('input_schema', json_encode($tool->input_schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea></div>
            <div class="field"><label for="output_template">قالب المخرج (Markdown؛ المفاتيح بين قوسَين مزدوجَين) — القالب الحي للإصدار الفعّال</label><textarea id="output_template" name="output_template" class="mono" rows="14">{{ old('output_template', $tool->output_template) }}</textarea></div>
            @else
            <div class="field"><label for="input_schema">مخطط الحقول (JSON)</label><textarea id="input_schema" name="input_schema" class="mono" rows="8" readonly>{{ json_encode($tool->input_schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea><span class="hint">قواعد الحاسبات والمحاكي مكتوبة في الكود مع مصادرها (Calculators / CampaignSimulator).</span></div>
            @endif
            <div class="btn-row"><x-athar.button type="submit">حفظ التعريف</x-athar.button></div>
        </form>

        <div class="stack" style="gap:24px">
            @if($tool->kind === 'generator')
            <div class="card" id="versions"><x-athar.kicker>إصدارات قالب التوليد</x-athar.kicker>
                <x-athar.table caption="الإصدارات"><thead><tr><th>الإصدار</th><th>الوصف</th><th>مولَّد</th><th>محفوظ</th><th>معدل الحفظ</th><th>الحالة</th></tr></thead><tbody>
                    @foreach($versions as $v)@php($vr = $versionRates[$v->id] ?? ['generated' => 0, 'saved' => 0, 'rate' => null])
                    <tr data-version="{{ $v->version }}"><td><b>v{{ $v->version }}</b></td><td>{{ $v->label }}<br><span class="muted small">{{ $v->created_at->format('Y/m/d') }}</span></td><td>{{ $vr['generated'] }}</td><td>{{ $vr['saved'] }}</td><td>{{ $vr['rate'] === null ? '—' : $vr['rate'].'٪' }}</td>
                        <td>@if($v->is_active)<span class="card-tag">فعّال</span>@else<form method="post" action="{{ route('admin.tools.versions.activate', [$tool, $v]) }}">@csrf<button class="btn btn-text" type="submit">فعّل</button></form>@endif</td></tr>
                    @endforeach
                </tbody></x-athar.table>
                <details style="margin-top:12px"><summary class="btn btn-secondary" style="display:inline-flex">إصدار جديد من القالب</summary>
                    <form method="post" action="{{ route('admin.tools.versions.store', $tool) }}" class="stack" style="margin-top:12px" id="new-version">@csrf
                        <div class="field"><label for="v-label">وصف الإصدار</label><input id="v-label" name="label" required maxlength="120" placeholder="مثال: صياغة أقصر للجداول"></div>
                        <div class="field"><label for="v-template">قالب المخرج</label><textarea id="v-template" name="output_template" class="mono" rows="10" required>{{ $tool->output_template }}</textarea></div>
                        <div class="field"><label for="v-ai">تعليمات التحسين بالذكاء الاصطناعي</label><textarea id="v-ai" name="ai_instructions" maxlength="2000" rows="3">{{ $tool->activeVersion()?->ai_instructions }}</textarea></div>
                        <label class="row small"><input type="checkbox" name="activate" value="1"> فعّله فوراً</label>
                        <div class="btn-row"><x-athar.button type="submit" variant="secondary">أنشئ الإصدار</x-athar.button></div>
                    </form>
                </details>
            </div>

            <div class="card" id="experiment"><x-athar.kicker>تجربة A/B بين إصدارين</x-athar.kicker>
                @if($experiment)
                    <h3 style="font-size:18px">{{ $experiment->name }} <span class="card-tag">قيد التشغيل منذ {{ $experiment->started_at?->diffForHumans() }}</span></h3>
                    <x-athar.table caption="نتائج التجربة"><thead><tr><th>النسخة</th><th>الإصدار</th><th>نسبة التعرض</th><th>تعرّض</th><th>حُفظ</th><th>معدل الحفظ</th></tr></thead><tbody>
                        @foreach($experiment->variants as $variant)@php($er = $experimentRates[$variant->id] ?? ['exposed' => 0, 'converted' => 0, 'rate' => null])
                        <tr data-variant="{{ $variant->label }}"><td><b>{{ $variant->label }}</b></td><td>v{{ $versions->firstWhere('id', $variant->tool_version_id)?->version ?? '—' }}</td><td>{{ $variant->weight }}٪</td><td>{{ $er['exposed'] }}</td><td>{{ $er['converted'] }}</td><td>{{ $er['rate'] === null ? '—' : $er['rate'].'٪' }}</td></tr>
                        @endforeach
                    </tbody></x-athar.table>
                    <form method="post" action="{{ route('admin.tools.experiment.end', [$tool, $experiment]) }}" class="row" style="margin-top:12px">@csrf
                        <label class="small" for="winner">اعتمد الفائز</label><select id="winner" name="winner" class="input" style="width:auto;min-height:44px"><option value="">بلا اعتماد</option>@foreach($experiment->variants as $variant)<option value="{{ $variant->id }}">{{ $variant->label }} (v{{ $versions->firstWhere('id', $variant->tool_version_id)?->version }})</option>@endforeach</select>
                        <x-athar.button type="submit" variant="secondary">أنهِ التجربة</x-athar.button>
                    </form>
                @elseif($versions->count() >= 2)
                    <form method="post" action="{{ route('admin.tools.experiment.start', $tool) }}" class="stack" id="start-experiment">@csrf
                        <div class="grid grid-2">
                            <div class="field"><label for="variant_a">النسخة A</label><select id="variant_a" name="variant_a">@foreach($versions as $v)<option value="{{ $v->id }}" @selected($v->is_active)>v{{ $v->version }} — {{ $v->label }}</option>@endforeach</select></div>
                            <div class="field"><label for="variant_b">النسخة B</label><select id="variant_b" name="variant_b">@foreach($versions as $v)<option value="{{ $v->id }}" @selected(! $v->is_active && $loop->first)>v{{ $v->version }} — {{ $v->label }}</option>@endforeach</select></div>
                        </div>
                        <div class="field"><label for="weight_a">نسبة التعرض لـ A (٪)</label><input id="weight_a" name="weight_a" type="number" min="10" max="90" value="50"></div>
                        <div class="field"><label for="exp-name">اسم التجربة</label><input id="exp-name" name="name" maxlength="120" placeholder="مثال: جدول مقابل قائمة"></div>
                        @error('experiment')<p class="error">{{ $message }}</p>@enderror
                        <div class="btn-row"><x-athar.button type="submit" variant="secondary">ابدأ A/B</x-athar.button><span class="small muted">تجربة واحدة في كل مرة؛ المقياس معدل الحفظ لكل نسخة.</span></div>
                    </form>
                @else<p class="muted small">أنشئ إصداراً ثانياً من القالب لتفعيل A/B.</p>@endif
            </div>
            @endif

            @if($tool->kind === 'generator')
            <div class="card" id="test"><x-athar.kicker>اختبار بمدخلات نموذجية</x-athar.kicker>
                <form method="post" action="{{ route('admin.tools.test', $tool) }}" id="test-form" class="stack">@csrf
                    <div class="field"><label for="test-version">الإصدار</label><select id="test-version" name="version_id"><option value="0">الفعّال</option>@foreach($versions as $v)<option value="{{ $v->id }}">v{{ $v->version }} — {{ $v->label }}</option>@endforeach</select></div>
                    <div class="lab-fields">@foreach($tool->fields() as $field)<div class="field"><label for="t-{{ $field['key'] }}">{{ $field['label'] }}</label>
                        @if(($field['type'] ?? '') === 'select')<select id="t-{{ $field['key'] }}" name="{{ $field['key'] }}">@foreach(\App\Domain\Lab\Services\LabCatalog::options($field) as $k => $l)<option value="{{ $k }}" @selected((string) ($sample[$field['key']] ?? '') === (string) $k)>{{ $l }}</option>@endforeach</select>
                        @elseif(($field['type'] ?? '') === 'textarea')<textarea id="t-{{ $field['key'] }}" name="{{ $field['key'] }}">{{ $sample[$field['key']] ?? '' }}</textarea>
                        @else<input id="t-{{ $field['key'] }}" name="{{ $field['key'] }}" type="{{ ($field['type'] ?? 'text') === 'number' ? 'number' : 'text' }}" step="any" value="{{ $sample[$field['key']] ?? '' }}">@endif</div>@endforeach</div>
                    <div class="btn-row"><x-athar.button type="submit" variant="secondary" id="run-test">اختبر الآن</x-athar.button><span class="small muted">لا يُسجَّل في بيانات المتعلمين.</span></div>
                </form>
                @if($test)
                <div style="margin-top:16px" data-test-result>
                    <p class="small muted">نتيجة الاختبار على v{{ $test['version'] ?? '—' }} · {{ $test['at'] }}@if(! empty($test['missing'])) · حقول ناقصة: {{ implode('، ', $test['missing']) }}@endif</p>
                    <x-athar.asset-card id="test-asset" kicker="مخرج الاختبار" :title="$tool->name" :type="$tool->output_type" :markdown="$test['markdown']" :quality="$test['quality']" fixPrefix="#t-" />
                </div>
                @endif
            </div>
            @endif

            <div class="card" id="audit"><x-athar.kicker>سجل التدقيق</x-athar.kicker>
                <ul class="list small">@forelse($audit as $row)<li><span>{{ $row->action }} <span class="muted">{{ Str::limit((string) $row->context, 80) }}</span></span><span class="when">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('Y/m/d H:i') }}</span></li>@empty<li class="muted">لا تغييرات بعد.</li>@endforelse</ul>
            </div>
        </div>
    </div>
</div></section>
@endsection
@push('scripts')
@include('partials.asset-actions-script')
@endpush
