@extends('layouts.platform')
@section('title', $tool->name.' — المختبر — أثر')
@section('description', $tool->description)
@section('content')
<x-athar.schema :data="['@type' => 'WebApplication', 'name' => $tool->name, 'description' => $tool->description, 'url' => route('tools.panel'), 'inLanguage' => 'ar', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'publisher' => ['@type' => 'Organization', 'name' => 'أثر', 'url' => route('home')]]" />
<section class="page-hero tool-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['المختبر', route('tools.index')], [$tool->name, null]]" />
    <span class="kicker">{{ $tool->category }} · {{ $tool->kindLabel() }}</span>
    <h1>{{ $tool->name }}</h1>
    <p class="lead">{{ $tool->description }}</p>
    <div class="row tool-meta">
        <span class="card-tag">{{ $tool->credit_cost }} رصيد لكل صوت في كل جولة · الحد الأقصى {{ $caps['voices'] * $caps['rounds'] * $tool->credit_cost }} رصيد للتشغيل</span>
        <a class="btn btn-secondary" href="{{ route('tools.panel', ['example' => 1]) }}#panel-inputs" data-example>مثال جاهز</a>
        <a class="btn btn-text" href="{{ route('tools.panel', ['fresh' => 1]) }}#panel-inputs" data-fresh>ابدأ من الصفر</a>
    </div>
</div></section>

<section class="section-tight"><div class="shell">
    <x-athar.flash />
    @error('panel')<x-athar.alert type="warning">{{ $message }}</x-athar.alert>@enderror
    @if($result && ! empty($result['clamped']))<x-athar.alert type="info">{{ implode(' ', $result['clamped']) }}</x-athar.alert>@endif
    @if($result && ! empty($result['partial']))<x-athar.alert type="warning">توقّفت اللوحة قبل أن تتحدث كل الأصوات. ما تحدّث منها محفوظ، ولم يُخصم إلا ثمن الأصوات التي أجابت.</x-athar.alert>@endif

    <x-athar.alert type="warning">
        <b>محاكاة لا بحث سوق.</b> الأصوات هنا يلعبها نموذج لغوي بناءً على الشخصيات التي تكتبها أنت. ليست عيّنة ولا بيانات ميدانية ولا رأي عملاء حقيقيين، ولا تُقاس عليها نسبة طلب. قيمتها الحقيقية: كشف الاعتراض الذي ستسمعه بعد الإنفاق — قبله.
    </x-athar.alert>

    <div class="chain-bar" id="chain" aria-label="شريط السلسلة">
        <div class="chain-cell"><span class="chain-k">يُغذّى من</span>
            <ul>@forelse($chain['feeds_from'] as $f)<li class="{{ $f['ok'] ? 'is-ok' : 'is-missing' }}" data-feed="{{ $f['type'] }}" data-ok="{{ $f['ok'] ? '1' : '0' }}"><span aria-hidden="true">{{ $f['ok'] ? '✓' : '○' }}</span>@if($f['url'])<a href="{{ $f['url'] }}">{{ $f['label'] }}</a>@else{{ $f['label'] }}@endif</li>@empty<li class="muted">تبدأ من الصفر</li>@endforelse</ul>
        </div>
        <div class="chain-cell is-produce"><span class="chain-k">هذه اللوحة تُنتج</span><b>{{ $chain['produces']['label'] }}</b><small class="muted">أصل Markdown بالأصوات وقاعدة القرار</small></div>
        <div class="chain-cell"><span class="chain-k">يغذّي بدوره</span>
            <ul>@forelse($chain['feeds_into'] as $f)<li class="{{ $f['ok'] ? 'is-ok' : '' }}">@if($f['url'])<a href="{{ $f['url'] }}">{{ $f['label'] }}</a>@else{{ $f['label'] }}@endif</li>@empty<li class="muted">نهاية السلسلة</li>@endforelse</ul>
        </div>
        <div class="chain-cell"><span class="chain-k">حدود التشغيل</span>
            <b>{{ $caps['voices'] }} أصوات · {{ $caps['rounds'] }} جولات</b>
            <small class="muted">سقف مكتوب في إعدادات المنصة لا في التعليمات، فلا يمكن لنموذج أن يرفعه.</small>
        </div>
    </div>

    <div class="tool-layout">
        <div class="stack" style="gap:24px">
            <section class="lab" id="panel-inputs" aria-labelledby="panel-inputs-h">
                <h2 id="panel-inputs-h" style="font-size:22px">المدخلات</h2>
                <p class="muted small">الحقول الملوّنة مُعبّأة من مشروعك أو من أصل «شخصية العميل» في السلسلة، وكلها قابلة للتعديل.</p>
                <form id="panel-form" method="post" action="{{ route('app.tools.panel', $tool) }}">
                    @csrf
                    @if($member)
                    <div class="field" style="margin-bottom:14px"><label for="project_id">المشروع</label>
                        @if($projects->isNotEmpty())<select id="project_id" name="project_id">@foreach($projects as $p)<option value="{{ $p->id }}" @selected($project && $project->id === $p->id)>{{ $p->name }}</option>@endforeach</select><span class="hint">اللوحة تُحفظ كأصل في هذا المشروع.</span>
                        @else<p class="muted small">لا مشروع في مساحتك بعد. <a href="{{ route('dashboard') }}#projects">أنشئ مشروعاً</a> لتشغيل اللوحة.</p>@endif
                    </div>
                    @endif
                    <div class="lab-fields">
                        @foreach($fields as $field)
                        <div class="field {{ $field['prefilled'] ? 'is-prefilled' : '' }} {{ ($field['type'] ?? 'text') === 'textarea' ? 'span-2' : '' }}" data-field="{{ $field['key'] }}">
                            <label for="f-{{ $field['key'] }}">{{ $field['label'] }} @if($field['prefilled'])<span class="source" data-source>{{ $field['source_label'] }}</span>@endif</label>
                            @if(($field['type'] ?? 'text') === 'textarea')<textarea id="f-{{ $field['key'] }}" name="{{ $field['key'] }}" rows="{{ $field['key'] === 'personas' ? 5 : 4 }}" placeholder="{{ $field['placeholder'] ?? '' }}" @required(! empty($field['required']))>{{ $field['value'] }}</textarea>
                            @elseif(($field['type'] ?? '') === 'select')<select id="f-{{ $field['key'] }}" name="{{ $field['key'] }}">@foreach($field['options_map'] as $k => $l)<option value="{{ $k }}" @selected((string) $field['value'] === (string) $k)>{{ $l }}</option>@endforeach</select>
                            @else<input id="f-{{ $field['key'] }}" name="{{ $field['key'] }}" type="text" value="{{ $field['value'] }}" placeholder="{{ $field['placeholder'] ?? '' }}" @required(! empty($field['required']))>@endif
                            @if(! empty($field['hint']))<span class="hint">{{ $field['hint'] }}</span>@endif
                        </div>
                        @endforeach
                    </div>
                    <div class="btn-row">
                        @if($member && $projects->isNotEmpty() && $aiEnabled)
                            <x-athar.button type="submit" id="panel-btn">شغّل اللوحة</x-athar.button>
                            <span class="small muted">يُخصم رصيد عن كل صوت أجاب فعلاً — لا عن اللوحة كاملة إن توقفت.</span>
                        @elseif($member && $projects->isNotEmpty())
                            <span class="btn btn-secondary" aria-disabled="true">اللوحة غير متاحة الآن</span>
                            <span class="small muted">مزوّد الذكاء الاصطناعي غير مفعّل على المنصة. لا يوجد مخرج قاعدي لهذه اللوحة بلا نموذج، ولن يُخصم أي رصيد.</span>
                        @elseif($member)
                            <span class="btn btn-secondary" aria-disabled="true">تحتاج مشروعاً</span>
                        @else
                            <a class="btn btn-primary" href="{{ route('register') }}" data-action="panel-gate">شغّل اللوحة (بحساب)</a>
                            <span class="small muted">اللوحة تعمل على مشروعك المحفوظ، فهي تحتاج حساباً ورصيداً.</span>
                        @endif
                    </div>
                </form>
            </section>

            @if($result && ! empty($result['markdown']))
                <div class="card" id="panel-result">
                    <x-athar.kicker>نتيجة اللوحة</x-athar.kicker>
                    <div class="split"><h3 style="font-size:20px">{{ $result['decision'] }}</h3><span class="card-tag">{{ $result['answered'] }} إجابة · {{ $result['credits'] }} رصيد@if($result['cached']) · من الكاش@endif</span></div>
                    <table class="table" data-panel-counts>
                        <thead><tr><th>القرار</th><th>الأصوات</th></tr></thead>
                        <tbody>@foreach($verdicts as $key => $label)<tr data-verdict="{{ $key }}"><td>{{ $label }}</td><td>{{ $result['counts'][$key] ?? 0 }}</td></tr>@endforeach</tbody>
                    </table>
                    <p class="small muted" style="margin:10px 0 0">بذرة التشغيل <b>{{ $result['seed'] }}</b> · {{ $result['rounds'] }} جولة@if($caps['cache_minutes'] >= 60) · نفس المدخلات تعيد نفس السجل خلال {{ (int) round($caps['cache_minutes'] / 60) }} ساعة@endif.</p>
                </div>
                <x-athar.asset-card id="panel-asset" kicker="أصل اللوحة" :title="$result['title']" :type="$tool->output_type" :markdown="$result['markdown']" :member="$member" :versions="$saved->isNotEmpty() ? $saved : null" :currentVersion="$saved->first()?->version" regenerateForm="panel-form" :filename="$tool->output_type" />
            @else
                <div class="card placeholder-card" id="panel-placeholder"><x-athar.kicker>نتيجة اللوحة</x-athar.kicker><p class="muted" style="margin:0">سيظهر هنا بعد التشغيل: قرار كل صوت وسببه واعتراضه وما يقنعه، ثم النتيجة بالعدّ وقاعدة قرار واحدة.</p></div>
            @endif
        </div>

        <aside class="side stack" style="gap:16px">
            <div class="side-box"><h3>كيف تعمل اللوحة</h3>
                <ol class="small" style="margin:0;padding-inline-start:18px">
                    <li>كل شخصية تُسأل <b>وحدها</b>، فلا يقلّد صوتٌ صوتاً.</li>
                    <li>الجولة الثانية تعرض على كل شخصية اعتراضات البقية، فيتغير قرارها أو يثبت.</li>
                    <li>النتيجة تُحسب <b>بالعدّ في الكود</b>، لا بتلخيص من النموذج — فلا رأي ثانياً يحتاج تدقيقاً.</li>
                    <li>قاعدة القرار مكتوبة مسبقاً كقواعد المخاطر في محاكي الحملة، لا يختارها النموذج.</li>
                </ol>
            </div>
            <div class="side-box"><h3>حدود صريحة</h3>
                <ul class="small" style="margin:0;padding-inline-start:18px">
                    <li>أقصى {{ $caps['voices'] }} أصوات و{{ $caps['rounds'] }} جولات لكل تشغيل.</li>
                    <li>مدخلاتك لا تُرسل إلى مزوّد يتدرّب عليها؛ إن لم يتوفّر مزوّد كذلك تُرفض اللوحة بلا خصم.</li>
                    <li>لا توليد أرقام ولا إحصاءات: الصوت يعطي قراراً وسبباً واعتراضاً فقط.</li>
                    <li>تقدير الوصول والطلبات مكانه <a href="{{ route('tools.simulator') }}">محاكي الحملة</a>، لا هنا.</li>
                </ul>
            </div>
            <x-athar.card heading="h3" title="اصنع شخصياتك أولاً" desc="لوحة بشخصيات محددة تكشف اعتراضات حقيقية؛ لوحة بشخصيات عامة تعطي كلاماً عاماً." :href="route('tools.show', 'customer-persona')" tag="المختبر" />
        </aside>
    </div>
</div></section>
@endsection
