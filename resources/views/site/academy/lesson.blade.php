@extends('layouts.platform')
@section('title', $lesson->title.' — '.$course->title.' — أثر')
@section('description', Str::limit($lesson->goal ?: $lesson->objective, 155))
@section('body_class', 'has-bottom-bar')
@section('canonical', route('academy.lesson', [$course, $lesson]))
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! $structuredJson !!}</script>
@endpush
@section('content')
@php($partKeys = array_keys($parts))
@php($lessonUrl = route('academy.lesson', [$course, $lesson, 'situation' => $mode]))
<div class="shell lesson-head" id="lesson" data-lesson="{{ $lesson->id }}" data-mode="{{ $mode }}" data-whatif="{{ route('academy.lesson.whatif', [$course, $lesson]) }}" data-decision="{{ route('academy.lesson.decision', [$course, $lesson]) }}" data-member="{{ $member ? 1 : 0 }}">
    <x-athar.breadcrumbs :items="[['الأكاديمية', route('academy.index')], [$course->title, route('academy.show', $course)], [$lesson->title, null]]" />
    <x-athar.kicker>الدرس {{ $index + 1 }} من {{ $lessons->count() }} · {{ $lesson->duration_minutes }} دقيقة</x-athar.kicker>
    <h1>{{ $lesson->title }}</h1>
    @if($courseProgress)<div style="max-width:420px"><x-athar.progress :value="$courseProgress['percent']" :done="$courseProgress['done']" :total="$courseProgress['total']" /></div>@endif
    <div class="meta">
        <div class="toggle" role="group" aria-label="مبدّل العمق"><button type="button" data-depth="deep" aria-pressed="true">عميق · {{ $lesson->duration_minutes }} د</button><button type="button" data-depth="fast" aria-pressed="false">سريع · 8 د</button></div>
        <div class="toggle" role="group" aria-label="مبدّل الموقف">@if($hasProfile)<a href="{{ route('academy.lesson', [$course, $lesson, 'situation' => 'project']) }}" @if($mode === 'project') aria-current="true" @endif>مشروعك</a><a href="{{ route('academy.lesson', [$course, $lesson, 'situation' => 'general']) }}" @if($mode === 'general') aria-current="true" @endif>الموقف التدريبي العام</a>@else<span aria-current="true" style="padding:0 14px;display:inline-flex;align-items:center;min-height:38px;font-size:14px;font-weight:700;border-radius:999px;background:var(--navy);color:#fff">الموقف التدريبي العام</span><a href="{{ $member ? route('assess.start') : route('register') }}">{{ $member ? 'أنشئ ملف مشروعك' : 'مشروعك (بحساب)' }}</a>@endif</div>
        @if($completed)<span class="level level-beginner">مكتمل ✓</span>@endif
    </div>
    <ol class="toc-mobile" aria-label="أجزاء الدرس">@foreach($parts as $key => $label)<li><a href="#part-{{ $key }}">{{ $label }}</a></li>@endforeach</ol>
</div>

<div class="shell lesson-layout">
<article class="lesson-main">
    <x-athar.flash />
    {{-- 0.5 قرارك الأول --}}
    <section class="part" id="part-opening" data-part="opening">
        <x-athar.kicker>قبل الشرح</x-athar.kicker>
        <h2>قرارك الأول</h2>
        @if($opening)<x-athar.decision-question :question="$opening" :opening="true" :chosen="$journal?->first_choice" />
        <p class="muted small" data-opening-saved @if($journal?->first_choice === null) hidden @endif>سُجّل قرارك الأول. سنعود إليه في «هل غيّرت رأيك؟».</p>@endif
    </section>

    {{-- 1 الهدف + ما يدخل --}}
    <section class="part" id="part-goal" data-part="goal">
        <x-athar.kicker>الجزء 1</x-athar.kicker><h2>الهدف وما يدخل من الدروس السابقة</h2>
        <p style="font-size:19px">{{ $lesson->goal }}</p>
        @if($incoming->isNotEmpty())
        <div class="assets-in"><b>ما يدخل من الدروس السابقة</b>
            @foreach($incoming as $item)<div class="item"><span>{{ $item['label'] }}</span>@if($item['asset'])<span class="ok">✓ محفوظ: {{ $item['asset']->title }}</span>@elseif($item['lesson'])<a class="missing" href="{{ route('academy.lesson', [$item['lesson']->course, $item['lesson']]) }}">غير محفوظ بعد — افتح الدرس</a>@else<span class="missing">غير محفوظ بعد</span>@endif</div>@endforeach
            @if(! $member)<p class="small muted" style="margin:6px 0 0">أصولك تتراكم هنا عندما تحفظها بحساب.</p>@endif
        </div>
        @else<p class="muted small">هذا أول درس في المسار: لا أصول سابقة يُبنى عليها.</p>@endif
    </section>

    {{-- 2 المفهوم --}}
    <section class="part" id="part-concept" data-part="concept">
        <x-athar.kicker>الجزء 2</x-athar.kicker><h2>المفهوم</h2>
        <div class="concept deep-only">{!! \App\Support\Markdown::toHtml((string) $lesson->concept_body) !!}</div>
        <div class="concept fast-only"><p class="muted small">الوضع السريع: الخلاصة بدل المفهوم الكامل.</p>{!! \App\Support\Markdown::toHtml((string) $lesson->quick_summary) !!}</div>
        @if($lesson->market_example)<div class="example deep-only"><x-athar.kicker>مثال من السوق السعودي</x-athar.kicker><p style="margin:0">{{ $lesson->market_example }}</p></div>@endif
        @if($commonMistake)<div class="callout"><h4>الخطأ الشائع (على مشروعك)</h4><p>{{ $commonMistake }}</p></div>@endif
    </section>

    {{-- 3 موقفك --}}
    <section class="part" id="part-situation" data-part="situation">
        <x-athar.kicker>الجزء 3</x-athar.kicker><h2>{{ $mode === 'project' ? 'موقفك' : 'الموقف التدريبي العام' }}</h2>
        <div class="situation"><p>{{ $scenario }}</p>@if($member)<a class="btn btn-secondary" href="{{ $hasProfile ? route('app.projects.show', $project) : route('assess.start') }}">{{ $hasProfile ? 'عدّل ملف المشروع' : 'أنشئ ملف مشروعك' }}</a>@else<a class="btn btn-secondary" href="{{ route('assess.start') }}">شخّص مشروعك ليصبح الموقف موقفك</a>@endif</div>
    </section>

    {{-- 4 التدريب الموجّه --}}
    <section class="part" id="part-guided" data-part="guided">
        <x-athar.kicker>الجزء 4</x-athar.kicker><h2>التدريب الموجّه</h2>
        <p class="muted">{{ count($guided) }} خطوات بحقول صغيرة تُجمَّع في المختبر. الحقول البرتقالية مُعبّأة من ملف مشروعك.</p>
        <ol class="guided">
            @foreach($guided as $step)
            <li><div class="field {{ $step['prefilled'] ? 'is-prefilled' : '' }} {{ $errors->has('lab') && trim((string) $step['value']) === '' && ! empty($step['required']) ? 'is-invalid' : '' }}">
                <label for="f-{{ $step['key'] }}">{{ $step['label'] }} @if($step['prefilled'])<span class="source">من المشروع</span>@endif</label>
                @if(($step['type'] ?? 'text') === 'textarea')<textarea id="f-{{ $step['key'] }}" name="{{ $step['key'] }}" form="lab-form" placeholder="{{ $step['placeholder'] ?? '' }}">{{ $step['value'] }}</textarea>
                @elseif(($step['type'] ?? '') === 'select')<select id="f-{{ $step['key'] }}" name="{{ $step['key'] }}" form="lab-form">@foreach($channels as $k => $l)<option value="{{ $k }}" @selected((string) $step['value'] === $k)>{{ $l }}</option>@endforeach</select>
                @else<input id="f-{{ $step['key'] }}" name="{{ $step['key'] }}" form="lab-form" type="{{ ($step['type'] ?? 'text') === 'number' ? 'number' : 'text' }}" value="{{ $step['value'] }}" placeholder="{{ $step['placeholder'] ?? '' }}" @if(($step['type'] ?? '') === 'number') step="any" @endif>@endif
                @if(! empty($step['hint']))<span class="hint">{{ $step['hint'] }}</span>@endif
            </div></li>
            @endforeach
        </ol>
    </section>

    {{-- 5 المختبر + ماذا لو --}}
    <section class="part" id="part-lab" data-part="lab">
        <x-athar.kicker>الجزء 5</x-athar.kicker><h2>المختبر: أنشئ «{{ $lesson->outcome_title }}»</h2>
        <x-athar.lab :action="route('academy.lesson.lab', [$course, $lesson])" id="lab-form" submitLabel="أنشئ الأصل">
            <x-slot:fields>
                <input type="hidden" name="situation" value="{{ $mode }}">
                @foreach($labFields as $field)
                <div class="field {{ $field['prefilled'] ? 'is-prefilled' : '' }}">
                    <label for="f-{{ $field['key'] }}">{{ $field['label'] }} @if($field['prefilled'])<span class="source">من المشروع</span>@endif</label>
                    <input id="f-{{ $field['key'] }}" name="{{ $field['key'] }}" type="{{ ($field['type'] ?? 'text') === 'number' ? 'number' : 'text' }}" value="{{ $field['value'] }}" @if(($field['type'] ?? '') === 'number') step="any" @endif>
                    @if(! empty($field['hint']))<span class="hint">{{ $field['hint'] }}</span>@endif
                </div>
                @endforeach
            </x-slot:fields>
            @if($generated)
            <div style="height:16px"></div>
            <x-athar.asset-card id="asset" kicker="أصلك" :title="$lesson->outcome_title" :type="$lesson->outcome_type" :markdown="$generated['markdown']" :verdict="$generated['verdict']" :saveUrl="route('academy.lesson.save', [$course, $lesson])" :member="$member" :saved="$savedAsset && $savedAsset->body_markdown === $generated['markdown']" regenerateForm="lab-form" :lessonId="$lesson->id" :filename="$lesson->outcome_type">
                @if($whatIf['variable'])
                <div class="whatif" id="whatif" data-key="{{ $whatIf['variable']['key'] }}">
                    <b>ماذا لو؟</b> <span class="muted small">غيّر متغيّراً واحداً وشاهد الحكم مقابل هدفك.</span>
                    <div class="whatif-row"><label for="whatif-range">{{ $whatIf['variable']['label'] }}</label><input id="whatif-range" type="range" min="{{ $whatIf['variable']['min'] }}" max="{{ $whatIf['variable']['max'] }}" step="{{ $whatIf['variable']['step'] }}" value="{{ $whatIf['variable']['value'] }}"><output for="whatif-range" data-wi-value>{{ \App\Domain\Academy\Services\RuleEvaluator::fmt($whatIf['variable']['value']) }} {{ $whatIf['variable']['unit'] }}</output></div>
                    <div class="row" data-wi-outputs>@foreach($whatIf['outputs'] as $o)<span class="card-tag" data-wi-out="{{ $o['key'] }}">{{ $o['label'] }}: {{ $o['display'] }}</span>@endforeach @if($whatIf['benchmark'])<span class="card-tag" data-wi-benchmark>{{ $whatIf['benchmark']['label'] }}: {{ $whatIf['benchmark']['display'] }}</span>@endif</div>
                    <div class="row" style="margin-top:8px">@if($whatIf['target'])<span>{{ $whatIf['target']['label'] }}: <b data-wi-target>{{ $whatIf['target']['display'] }}</b></span>@endif<span class="asset-verdict verdict-{{ $whatIf['verdict'] }}" data-wi-verdict>{{ $whatIf['verdict_label'] }}</span></div>
                    @if($whatIf['explain'])<p class="rule">{{ $whatIf['explain'] }}</p>@endif
                </div>
                @endif
                @if($savedAsset && $member)<div class="asset-body" style="padding-top:0"><details class="compare"><summary class="btn btn-text" style="display:inline-flex">قارن مع نسختك المحفوظة (v{{ $savedAsset->version }})</summary><blockquote>{{ Str::limit($savedAsset->body_markdown, 400) }}</blockquote></details></div>@endif
            </x-athar.asset-card>
            @else
            <p class="muted small" style="margin:12px 0 0">أكمل الخطوات ثم اضغط «أنشئ الأصل». يعمل بالقواعد والقوالب بلا ذكاء اصطناعي.</p>
            @endif
        </x-athar.lab>
        @if(! $member)<div style="height:16px"></div><x-athar.gate title="احفظ أصلك في مشروعك" text="بحساب مجاني تُحفظ أصولك وتتراكم من درس إلى درس." :href="route('register')" />@endif
    </section>

    {{-- 6 أسئلة القرار + هل غيّرت رأيك --}}
    <section class="part" id="part-questions" data-part="questions">
        <x-athar.kicker>الجزء 6</x-athar.kicker><h2>أسئلة القرار</h2>
        <p class="muted">تغذية راجعة فورية لكل خيار: لماذا صحيح ولماذا خاطئ. لا درجات مخفية.</p>
        @foreach($questions as $i => $q)<x-athar.decision-question :question="$q" :index="$i + 1" />@endforeach
        @if($opening)
        <div class="changed-mind" id="changed-mind">
            <h3>هل غيّرت رأيك؟</h3>
            <p class="muted">قرارك الأول: <span class="choice" data-first-choice>{{ $journal?->first_choice !== null ? ($opening->options[$journal->first_choice]['label'] ?? '—') : '—' }}</span></p>
            <p>بعد ما تعلمته، ما قرارك الآن؟</p>
            <form method="post" action="{{ route('academy.lesson.decision', [$course, $lesson]) }}" class="stack" data-final-form>
                @csrf<input type="hidden" name="stage" value="final">
                <div class="dq-options" style="display:grid;gap:8px">@foreach($opening->options as $oi => $opt)<label class="dq-option" style="cursor:pointer"><input type="radio" name="choice" value="{{ $oi }}" required @checked($journal?->final_choice === $oi) style="accent-color:var(--orange)"><span>{{ $opt['label'] }}</span></label>@endforeach</div>
                <div class="field"><label for="note">ما الذي غيّر رأيك؟ (اختياري)</label><input id="note" name="note" maxlength="500" value="{{ $journal?->note }}"></div>
                <div class="btn-row"><x-athar.button type="submit" variant="secondary">سجّل في مذكرة القرارات</x-athar.button>@if($journal?->final_choice !== null)<span class="small muted">مسجّل ✓ {{ $journal->first_choice !== $journal->final_choice ? '— غيّرت رأيك' : '— ثبتّ على قرارك' }}</span>@endif</div>
            </form>
        </div>
        @endif
    </section>

    {{-- 7 القالب --}}
    <section class="part" id="part-template" data-part="template">
        <x-athar.kicker>الجزء 7</x-athar.kicker><h2>القالب</h2>
        @if($lesson->template)
        <div class="card"><h3>{{ $lesson->template->name }}</h3><p class="card-desc">{{ $lesson->template->description }}</p>
            <div class="btn-row"><a class="btn btn-secondary" href="{{ route('resources.download', $lesson->template) }}" data-template-download>تنزيل Markdown</a><form method="post" action="{{ route('assets.print') }}" target="_blank">@csrf<input type="hidden" name="title" value="{{ $lesson->template->name }}"><input type="hidden" name="markdown" value="{{ $lesson->template->body_markdown }}"><button class="btn btn-secondary" type="submit">تنزيل PDF</button></form></div>
        </div>
        @else<p class="muted">لا قالب مرتبط بهذا الدرس بعد.</p>@endif
    </section>

    {{-- 8 طبّق في الواقع --}}
    <section class="part" id="part-apply" data-part="apply">
        <x-athar.kicker>الجزء 8</x-athar.kicker><h2>طبّق في الواقع</h2>
        <p class="muted">ليس «الخطوة التالية» فقط: التزام بفعل واقعي بموعد وتذكير بريدي، ثم تسجيل النتيجة الحقيقية بعد 7 أيام.</p>
        @if($member)
            @if($checkin && $checkin->mode === 'commit' && ! $checkin->recorded_at)
            <div class="card"><x-athar.kicker>التزمت يوم {{ $checkin->created_at->format('Y/m/d') }}</x-athar.kicker><p>{{ $checkin->action }}</p><p class="small muted">الموعد: {{ $checkin->due_at?->format('Y/m/d') }} — سيصلك تذكير بالبريد.</p>
                <form method="post" action="{{ route('academy.lesson.result', [$course, $lesson]) }}" class="stack" id="result-form">@csrf<h3 style="font-size:18px">سجّل النتيجة الحقيقية</h3>
                    @foreach($lesson->real_result_fields ?? [] as $f)<div class="field"><label for="r-{{ $f['key'] }}">{{ $f['label'] }}</label><input id="r-{{ $f['key'] }}" name="result[{{ $f['key'] }}]" type="{{ ($f['type'] ?? 'text') === 'number' ? 'number' : 'text' }}" @if(($f['type'] ?? '') === 'number') step="any" @endif></div>@endforeach
                    <div class="field"><label for="r-note">ملاحظة (اختياري)</label><input id="r-note" name="note" maxlength="1000"></div>
                    <div class="btn-row"><x-athar.button type="submit">سجّل النتيجة</x-athar.button><span class="small muted">{{ $checkin->due_at && $checkin->due_at->isFuture() ? 'الموعد بعد '.$checkin->due_at->diffInDays(now()).' أيام، ويمكنك التسجيل مبكراً إن أنجزت.' : 'حان موعد التسجيل.' }}</span></div>
                </form></div>
            @elseif($checkin && $checkin->recorded_at)
            <div class="card"><x-athar.kicker>{{ $checkin->mode === 'practice' ? 'أُكمل كتدريب' : 'نتيجة واقعية مسجّلة' }} · {{ $checkin->recorded_at->format('Y/m/d') }}</x-athar.kicker><p>{{ $checkin->action }}</p>@if($checkin->mode === 'commit')<ul class="list">@foreach($lesson->real_result_fields ?? [] as $f)<li><span>{{ $f['label'] }}</span><b>{{ $checkin->result[$f['key']] ?? '—' }}</b></li>@endforeach</ul>@endif<p class="small muted">تحدّثت لوحتك، والدرس التالي يفتح بأرقامك.</p>
                <form method="post" action="{{ route('academy.lesson.checkin', [$course, $lesson]) }}">@csrf<input type="hidden" name="mode" value="commit"><input type="hidden" name="action" value="{{ $lesson->real_action_template }}"><button class="btn btn-text" type="submit">التزم بجولة جديدة</button></form></div>
            @else
            <div class="checkin">
                <form method="post" action="{{ route('academy.lesson.checkin', [$course, $lesson]) }}" class="card" id="commit-form">@csrf<input type="hidden" name="mode" value="commit">
                    <h3 style="font-size:18px">الفعل</h3>
                    <div class="field"><label for="action">ما الذي ستفعله فعلاً؟</label><textarea id="action" name="action" maxlength="1000">{{ $lesson->real_action_template }}</textarea></div>
                    <div class="field"><label for="due_at">الموعد (تذكير بريدي)</label><input id="due_at" name="due_at" type="date" value="{{ now()->addDays(7)->format('Y-m-d') }}" min="{{ now()->addDay()->format('Y-m-d') }}"></div>
                    <p class="small muted">ما سيُسجَّل بعدها: @foreach($lesson->real_result_fields ?? [] as $f){{ $f['label'] }}@if(! $loop->last)، @endif @endforeach</p>
                    <x-athar.button type="submit" block>ألتزم بهذا الفعل</x-athar.button>
                </form>
                <form method="post" action="{{ route('academy.lesson.checkin', [$course, $lesson]) }}" class="card" id="practice-form">@csrf<input type="hidden" name="mode" value="practice">
                    <h3 style="font-size:18px">لا أملك حملة الآن</h3><p class="card-desc">أكمل الدرس كتدريب على الموقف العام؛ يمكنك العودة والالتزام لاحقاً.</p>
                    <x-athar.button type="submit" variant="secondary" block>أكمل كتدريب</x-athar.button>
                </form>
            </div>
            @endif
        @else
            <div class="card"><h3 style="font-size:18px">الفعل المقترح</h3><p>{{ $lesson->real_action_template }}</p><p class="small muted">ما سيُسجَّل بعد 7 أيام: @foreach($lesson->real_result_fields ?? [] as $f){{ $f['label'] }}@if(! $loop->last)، @endif @endforeach</p><p class="small muted" style="margin:0">خيار «لا أملك حملة الآن» يُكمل الدرس كتدريب على الموقف العام (بحساب).</p></div>
            <div style="height:12px"></div>
            <x-athar.gate title="التزم بفعل واقعي وقِس أثره" text="بحساب: موعد وتذكير بريدي، وتسجيل النتيجة بعد أسبوع يحدّث لوحتك ويفتح الدرس التالي بأرقامك." :href="route('register')" :benefits="['تذكير بريدي', 'نتيجة تُقاس', 'شهادة المسار']" />
        @endif
        <div class="next-grid">
            @if($next)<x-athar.card heading="h3" title="الدرس التالي" :desc="$next->title" :href="route('academy.lesson', [$course, $next])" tag="تابع" />@else<x-athar.card heading="h3" title="أكملت المسار" desc="أعد التشخيص لترى أثرك رقماً، أو ابدأ المسار التالي." :href="route('assess.start')" tag="قِس الأثر" />@endif
            @if($relatedTool)<x-athar.card heading="h3" title="الأداة" :desc="$relatedTool->name" :href="route('tools.show', $relatedTool)" tag="المختبر" />@endif
            @if($relatedArticle)<x-athar.card heading="h3" title="المادة" :desc="$relatedArticle->title" :href="route('articles.show', $relatedArticle)" tag="المجلة" />@endif
        </div>
        <div class="row" style="margin-top:16px">@if($prev)<a class="btn btn-text" href="{{ route('academy.lesson', [$course, $prev]) }}">→ السابق: {{ $prev->title }}</a>@endif @if($next)<a class="btn btn-text" href="{{ route('academy.lesson', [$course, $next]) }}">التالي: {{ $next->title }} ←</a>@endif</div>
    </section>
</article>

<aside class="lesson-side">
    <nav class="toc" aria-label="فهرس الأجزاء"><ol>@foreach($parts as $key => $label)<li><a href="#part-{{ $key }}" data-toc="{{ $key }}"><b>{{ $loop->index }}</b>{{ $label }}</a></li>@endforeach</ol></nav>
    @if($coachEnabled)
    <div class="side-box" id="coach"><h3>اسأل عن هذا الدرس</h3><p class="small muted">مدرّب محصور في محتوى الدرس وأصلك فقط (1 رصيد).</p>
        @if($errors->has('coach'))<p class="small" style="color:var(--danger)">{{ $errors->first('coach') }}</p>@endif
        @if($coachAnswer)<div class="coach-answer">{{ $coachAnswer['answer'] }}</div>@if(! empty($coachAnswer['examples']))<div class="compare" style="margin-top:8px"><b class="small">مثالان مجهولان للمقارنة</b>@foreach($coachAnswer['examples'] as $ex)<blockquote>{{ $ex }}</blockquote>@endforeach</div>@endif @endif
        <form method="post" action="{{ route('academy.lesson.coach', [$course, $lesson]) }}" class="stack" style="margin-top:8px">@csrf<div class="field"><label class="sr-only" for="coach-q">سؤالك</label><textarea id="coach-q" name="question" required maxlength="800" placeholder="مثال: هل شريحتي ضيقة جداً؟"></textarea></div><label class="small"><input type="checkbox" name="compare" value="1"> قارن بمثالين مجهولين</label><x-athar.button type="submit" variant="secondary" block>اسأل (1 رصيد)</x-athar.button></form>
    </div>
    @endif
    <div class="side-box"><h3>شرط الإكمال والشهادة</h3><ul class="list small"><li><span>حفظ الأصل</span><b>{{ $savedAsset ? '✓' : '—' }}</b></li><li><span>الإجابة عن أسئلة القرار</span><b>{{ $answered ? '✓' : '—' }}</b></li><li><span>شهادة المسار</span><span class="muted">نتيجة واقعية واحدة على الأقل</span></li></ul></div>
    <details class="side-box report-form"><summary>لا يناسب حالتي؟ أبلغ المحرر</summary>
        <form method="post" action="{{ route('academy.lesson.report', [$course, $lesson]) }}" class="stack" style="margin-top:8px">@csrf
            <div class="field"><label for="rp-part">الجزء</label><select id="rp-part" name="part">@foreach($parts as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="rp-reason">السبب</label><select id="rp-reason" name="reason"><option value="not_my_case">لا يناسب حالتي</option><option value="wrong_numbers">الأرقام غير واقعية</option><option value="unclear">غير واضح</option><option value="other">أخرى</option></select></div>
            <div class="field"><label for="rp-note">توضيح (اختياري)</label><textarea id="rp-note" name="note" maxlength="800"></textarea></div>
            <x-athar.button type="submit" variant="secondary" block>أرسل البلاغ</x-athar.button>
        </form>
    </details>
</aside>
</div>
<nav class="lesson-bottom-bar" aria-label="تنقل الدرس">@if($prev)<a class="btn btn-secondary" href="{{ route('academy.lesson', [$course, $prev]) }}">السابق</a>@endif<a class="btn btn-primary" href="#part-lab">المختبر</a>@if($next)<a class="btn btn-secondary" href="{{ route('academy.lesson', [$course, $next]) }}">التالي</a>@endif</nav>
@endsection
@push('scripts')
<script nonce="{{ $cspNonce }}">
(function(){
  var root=document.documentElement,L=document.getElementById('lesson'),lessonId=parseInt(L.dataset.lesson,10),member=L.dataset.member==='1';
  var csrf=document.querySelector('meta[name=csrf-token]').content;
  function ev(name,meta){meta=meta||{};meta.lesson_id=lessonId;window.atharEvent&&window.atharEvent(name,meta)}
  // depth toggle
  document.querySelectorAll('[data-depth]').forEach(function(b){b.addEventListener('click',function(){var fast=b.dataset.depth==='fast';root.classList.toggle('fast',fast);document.querySelectorAll('[data-depth]').forEach(function(x){x.setAttribute('aria-pressed',String(x===b))});try{localStorage.setItem('athar-depth',fast?'fast':'deep')}catch(e){}})});
  if(root.classList.contains('fast')){document.querySelectorAll('[data-depth]').forEach(function(x){x.setAttribute('aria-pressed',String(x.dataset.depth==='fast'))})}
  // part reached (once per part)
  var seen={};var io=new IntersectionObserver(function(entries){entries.forEach(function(en){if(en.isIntersecting){var p=en.target.dataset.part;if(!seen[p]){seen[p]=1;ev('lesson.part_reached',{part:p})}document.querySelectorAll('[data-toc]').forEach(function(a){a.classList.toggle('is-active',a.dataset.toc===p)})}})},{rootMargin:'-20% 0px -60% 0px',threshold:0});
  document.querySelectorAll('.part[data-part]').forEach(function(s){io.observe(s)});
  // decision questions
  var guestKey='athar-decisions-'+lessonId;var guest={};try{guest=JSON.parse(localStorage.getItem(guestKey)||'{}')}catch(e){}
  document.querySelectorAll('.dq').forEach(function(dq){
    var opening=dq.dataset.opening==='1',qid=dq.dataset.question,box=dq.querySelector('[data-feedback-box]'),best=dq.querySelector('[data-best-line]'),input=dq.querySelector('[data-choice-input]');
    function reveal(btn,record){
      var i=parseInt(btn.dataset.option,10),isBest=btn.dataset.best==='1';
      dq.querySelectorAll('.dq-option').forEach(function(o){o.removeAttribute('data-state');if(!opening&&o.dataset.best==='1')o.setAttribute('data-state','best')});
      btn.setAttribute('data-state',opening?'chosen':(isBest?'best':'wrong'));
      if(box&&btn.dataset.feedback){box.textContent=btn.dataset.feedback;box.hidden=false;box.className='dq-feedback '+(opening?'':(isBest?'is-best':'is-wrong'))}
      if(best)best.hidden=false;if(input)input.value=i;dq.setAttribute('data-answered','1');
      if(opening){var fc=document.querySelector('[data-first-choice]');if(fc)fc.textContent=btn.querySelector('span').textContent;var s=document.querySelector('[data-opening-saved]');if(s)s.hidden=false}
      if(!record)return;
      if(opening){ev('lesson.opening_decided',{part:'opening',choice:i});if(member){fetch(L.dataset.decision,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({stage:'first',choice:i})}).catch(function(){})}else{guest[qid]=i;try{localStorage.setItem(guestKey,JSON.stringify(guest))}catch(e){}}}
      else{ev('question.answered',{part:'questions',question_id:parseInt(qid,10),choice:i});guest[qid]=i;try{localStorage.setItem(guestKey,JSON.stringify(guest))}catch(e){}}
    }
    dq.querySelectorAll('.dq-option').forEach(function(btn){btn.addEventListener('click',function(){if(dq.hasAttribute('data-answered')&&!opening)return;reveal(btn,true)})});
    var pre=dq.querySelector('.dq-option[data-state]');
    if(pre){reveal(pre,false)}else if(guest[qid]!==undefined){var b=dq.querySelector('.dq-option[data-option="'+guest[qid]+'"]');if(b)reveal(b,false)}
  });
  // what-if
  var wi=document.getElementById('whatif');
  if(wi){var range=wi.querySelector('input[type=range]'),t;range.addEventListener('input',function(){clearTimeout(t);t=setTimeout(function(){
    fetch(L.dataset.whatif+'?value='+encodeURIComponent(range.value)+'&situation='+encodeURIComponent(L.dataset.mode),{headers:{'Accept':'application/json'}}).then(function(r){return r.json()}).then(function(d){
      wi.querySelector('[data-wi-value]').textContent=(d.variable?Number(d.variable.value).toLocaleString('en-US'):range.value)+' '+(d.variable?d.variable.unit:'');
      (d.outputs||[]).forEach(function(o){var el=wi.querySelector('[data-wi-out="'+o.key+'"]');if(el)el.textContent=o.label+': '+o.display});
      var v=wi.querySelector('[data-wi-verdict]');if(v&&d.verdict){v.textContent=d.verdict_label;v.className='asset-verdict verdict-'+d.verdict}
      var av=document.querySelector('[data-asset] [data-verdict]');if(av&&d.verdict){av.textContent=d.verdict_label;av.className='asset-verdict verdict-'+d.verdict}
      var tg=wi.querySelector('[data-wi-target]');if(tg&&d.target)tg.textContent=d.target.display;
    }).catch(function(){})},200)})}
  // asset actions
  document.querySelectorAll('[data-asset]').forEach(function(card){
    var md=card.querySelector('[data-asset-md]');
    var copy=card.querySelector('[data-action="copy"]');copy&&copy.addEventListener('click',function(){var text=md.value;(navigator.clipboard?navigator.clipboard.writeText(text):Promise.reject()).catch(function(){md.classList.remove('sr-only');md.select();document.execCommand('copy');md.classList.add('sr-only')}).finally(function(){copy.textContent='✓ نُسخ';setTimeout(function(){copy.textContent='نسخ'},1500);ev('asset.copied',{part:'lab'})})});
    var dl=card.querySelector('[data-action="download-md"]');dl&&dl.addEventListener('click',function(){var blob=new Blob([md.value],{type:'text/markdown;charset=utf-8'}),a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=(dl.dataset.filename||'asset')+'.md';document.body.appendChild(a);a.click();a.remove();ev('asset.downloaded',{part:'lab',format:'md'})});
    var pdf=card.querySelector('[data-action="download-pdf"]');pdf&&pdf.addEventListener('click',function(){ev('asset.downloaded',{part:'lab',format:'pdf'})});
  });
  var td=document.querySelector('[data-template-download]');td&&td.addEventListener('click',function(){ev('template.downloaded',{part:'template'})});
})();
</script>
@endpush
