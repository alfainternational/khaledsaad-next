@extends('layouts.platform')
@section('title', 'استوديو الدرس: '.$lesson->title.' — أثر')
@section('noindex', '1')
@section('content')
@php($json = fn ($v) => $v ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : '')
<section class="app-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['غرفة التحكم', route('admin.index')], ['الأكاديمية', route('admin.academy.index')], ['استوديو: '.$lesson->title, null]]" />
    <div class="split"><div><x-athar.kicker>استوديو الدرس · {{ $lesson->course->title }}</x-athar.kicker><h1 style="font-size:28px">{{ $lesson->title }}</h1><p class="muted small">{{ $lesson->isPublished() ? 'منشور' : 'مسودة (noindex)' }} · {{ count(array_filter($completeness)) }}/{{ count($completeness) }} أجزاء مكتملة · آخر إصدار v{{ $lesson->versions->first()?->version ?? 0 }}</p></div>
        <div class="row"><a class="btn btn-secondary" href="{{ route('academy.lesson', [$lesson->course, $lesson]) }}" target="_blank" rel="noopener">معاينة كمتعلم</a></div></div>
    @include('admin.partials.nav')
</div></section>
<section class="section-tight"><div class="shell">
    <x-athar.flash />
    <div class="studio">
        <div class="parts" id="studio-parts"><h2 style="font-size:16px">الأجزاء الثمانية · وصول المتعلمين</h2><p class="muted small">بدأ الدرس: {{ $reach['started'] }} متعلماً</p>
            @foreach($parts as $key => $label)
            <a href="{{ route('admin.academy.lessons.edit', ['lesson' => $lesson, 'part' => $key]) }}" @if($part === $key) aria-current="page" @endif data-signal="{{ $signals->firstWhere('part', $key) ? 1 : 0 }}"><span>{{ $completeness[$key] ? '✓' : '○' }} {{ $label }}</span><span class="reach">{{ $reach['parts'][$key]['percent'] }}٪</span></a>
            @endforeach
            <h3 style="font-size:15px;margin-top:16px">الإصدارات</h3>
            <ul class="list small">@forelse($lesson->versions->take(6) as $v)<li><span>v{{ $v->version }} · {{ $v->reason }}</span><form method="post" action="{{ route('admin.academy.lessons.restore', [$lesson, $v]) }}">@csrf<button class="btn btn-text" type="submit" style="min-height:32px">استرجع</button></form></li>@empty<li class="muted">لا إصدارات بعد.</li>@endforelse</ul>
        </div>

        <form method="post" action="{{ route('admin.academy.lessons.update', $lesson) }}" class="editor" id="studio-editor">@csrf @method('PUT')<input type="hidden" name="part" value="{{ $part }}">
            <div class="split" style="margin-bottom:16px"><h2 style="font-size:20px;margin:0">المحرر: {{ $parts[$part] }}</h2><label class="row small"><input type="checkbox" name="publish" value="1" @checked($lesson->isPublished())> منشور (لا نشر بلا اعتماد بشري)</label></div>
            <input type="hidden" name="publish" value="0" disabled>
            <div class="field"><label for="title">عنوان الدرس</label><input id="title" name="title" value="{{ $lesson->title }}" required maxlength="200"></div>

            <fieldset @if($part !== 'opening') hidden @endif><legend>قرارك الأول</legend>
                @php($opening = $lesson->questions->firstWhere('is_opening', true))
                @if($opening)@include('admin.academy.partials.question', ['q' => $opening, 'isOpening' => true])@else<p class="muted">لا سؤال افتتاحي.</p>@endif
            </fieldset>
            <fieldset @if($part !== 'goal') hidden @endif><legend>الهدف وما يدخل</legend>
                <div class="field"><label for="goal">جملة «ستخرج بـ…»</label><textarea id="goal" name="goal">{{ $lesson->goal }}</textarea></div>
                <div class="grid grid-2"><div class="field"><label for="outcome_title">اسم الأصل</label><input id="outcome_title" name="outcome_title" value="{{ $lesson->outcome_title }}"></div><div class="field"><label for="outcome_type">نوع الأصل (مفتاح)</label><input id="outcome_type" name="outcome_type" value="{{ $lesson->outcome_type }}" pattern="[a-z_]+"></div></div>
                <p class="small muted">الأصول الداخلة: @forelse($lesson->assetLinks as $l){{ $l->label }}@if(! $loop->last)، @endif @empty لا شيء @endforelse</p>
                <div class="field"><label for="objective">الوصف القصير (قائمة الدروس)</label><input id="objective" name="objective" value="{{ $lesson->objective }}"></div>
            </fieldset>
            <fieldset @if($part !== 'concept') hidden @endif><legend>المفهوم</legend>
                <div class="field"><label for="concept_body">المتن (Markdown) · {{ str_word_count_utf8($lesson->concept_body) }} كلمة</label><textarea id="concept_body" name="concept_body" style="min-height:360px">{{ $lesson->concept_body }}</textarea></div>
                <div class="field"><label for="market_example">مثال من السوق السعودي</label><textarea id="market_example" name="market_example">{{ $lesson->market_example }}</textarea></div>
                <div class="field"><label for="common_mistake">الخطأ الشائع (يقبل @{{offer}})</label><textarea id="common_mistake" name="common_mistake">{{ $lesson->common_mistake }}</textarea></div>
                <div class="field"><label for="quick_summary">ملخص الوضع السريع</label><textarea id="quick_summary" name="quick_summary">{{ $lesson->quick_summary }}</textarea></div>
                <div class="field"><label for="duration_minutes">المدة (دقائق)</label><input id="duration_minutes" name="duration_minutes" type="number" min="5" max="120" value="{{ $lesson->duration_minutes }}"></div>
            </fieldset>
            <fieldset @if($part !== 'situation') hidden @endif><legend>موقفك</legend>
                <div class="field"><label for="scenario_template">قالب الموقف (متغيّرات: @{{offer}} @{{budget_label}} @{{channel_label}} @{{goal_label}} @{{goal_number}} @{{project_name}} @{{city}})</label><textarea id="scenario_template" name="scenario_template">{{ $lesson->scenario_template }}</textarea></div>
                <div class="field"><label for="general_scenario">الموقف التدريبي العام (JSON)</label><textarea id="general_scenario" name="general_scenario" style="min-height:160px">{{ $json($lesson->general_scenario) }}</textarea></div>
                @if($variant)<div class="callout"><h4>اقتراح موقف بديل (من الذكاء الاصطناعي، للمراجعة)</h4><p>{{ $variant }}</p></div>@endif
            </fieldset>
            <fieldset @if($part !== 'guided') hidden @endif><legend>التدريب الموجّه</legend>
                <div class="field"><label for="guided_steps">الخطوات (JSON: key, label, hint, placeholder, source, type, required)</label><textarea id="guided_steps" name="guided_steps" style="min-height:260px">{{ $json($lesson->guided_steps) }}</textarea></div>
            </fieldset>
            <fieldset @if($part !== 'lab') hidden @endif><legend>المختبر + ماذا لو</legend>
                <div class="field"><label for="lab_config">إعداد المختبر (JSON: fields, computed, template)</label><textarea id="lab_config" name="lab_config" style="min-height:300px">{{ $json($lesson->lab_config) }}</textarea></div>
                <div class="field"><label for="whatif_rules">قواعد «ماذا لو» (JSON: variable, benchmark, outputs, target, explain)</label><textarea id="whatif_rules" name="whatif_rules" style="min-height:220px">{{ $json($lesson->whatif_rules) }}</textarea></div>
                <h3 style="font-size:16px">ربط حقول التدريب بحقول الأداة · نسبة التعبئة</h3>
                <p class="muted small">من {{ $mapping['total'] }} توليداً.</p>
                @foreach($mapping['rows'] as $row)<div class="editor-row"><code>{{ $row['key'] }}</code><span>{{ $row['label'] }} ← مصدر: <b>{{ $row['source'] ?? 'لا مصدر' }}</b> · تعبئة {{ $row['fill_rate'] === null ? '—' : $row['fill_rate'].'٪' }} · مسبقة من المشروع {{ $row['prefill_rate'] === null ? '—' : $row['prefill_rate'].'٪' }}</span></div>@endforeach
            </fieldset>
            <fieldset @if($part !== 'questions') hidden @endif><legend>أسئلة القرار</legend>
                @foreach($lesson->questions->where('is_opening', false) as $q)@include('admin.academy.partials.question', ['q' => $q, 'isOpening' => false])@endforeach
            </fieldset>
            <fieldset @if($part !== 'template') hidden @endif><legend>القالب</legend>
                <div class="field"><label for="template_id">القالب المرتبط</label><select id="template_id" name="template_id"><option value="">—</option>@foreach($templates as $t)<option value="{{ $t->id }}" @selected($lesson->template_id === $t->id)>{{ $t->name }}</option>@endforeach</select></div>
                @if($lesson->template)<p class="small muted">تنزيلات: {{ $lesson->template->downloads_count }} · <a href="{{ route('resources.download', $lesson->template) }}">تنزيل</a></p>@endif
            </fieldset>
            <fieldset @if($part !== 'apply') hidden @endif><legend>طبّق في الواقع</legend>
                <div class="field"><label for="real_action_template">الفعل الواقعي المقترح</label><textarea id="real_action_template" name="real_action_template">{{ $lesson->real_action_template }}</textarea></div>
                <div class="field"><label for="real_result_fields">حقول النتيجة (JSON: key, label, type)</label><textarea id="real_result_fields" name="real_result_fields" style="min-height:160px">{{ $json($lesson->real_result_fields) }}</textarea></div>
            </fieldset>
            <div class="field"><label for="reason">سبب التعديل (يُسجَّل مع الإصدار)</label><input id="reason" name="reason" maxlength="200" placeholder="مثال: إعادة صياغة السؤال 2 بعد إشارة الجودة"></div>
            <div class="btn-row"><x-athar.button type="submit">احفظ كإصدار جديد</x-athar.button></div>
        </form>

        <aside class="assistant" id="studio-assistant"><h2 style="font-size:16px">المساعد التحريري</h2><p class="muted small">يشخّص ويقترح بالقواعد الآن، وبالذكاء الاصطناعي عند وجود المفتاح. كل اقتراح يُطبَّق كإصدار جديد قابل للرجوع.</p>
            @foreach($suggestions as $s)
            <div class="suggestion" data-kind="{{ $s['kind'] }}"><h4>{{ $s['title'] }}</h4><p>{{ $s['text'] }}</p>
                @if($s['kind'] === 'ai')<form method="post" action="{{ route('admin.academy.lessons.variant', $lesson) }}" class="row" style="margin-top:8px">@csrf<input class="input" name="audience" value="المسوّقون والوكالات" style="min-height:40px"><button class="btn btn-secondary" type="submit" @if(empty($s['enabled'])) disabled @endif style="min-height:40px">ولّد</button></form>
                @else<a class="btn btn-text" href="{{ route('admin.academy.lessons.edit', ['lesson' => $lesson, 'part' => $s['part']]) }}{{ isset($s['question_id']) ? '#question-'.$s['question_id'] : '' }}">افتح الجزء ←</a>@endif
            </div>
            @endforeach
        </aside>
    </div>
</div></section>
@endsection
