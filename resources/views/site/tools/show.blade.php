@extends('layouts.platform')
@section('title', $tool->name.' — المختبر — أثر')
@section('description', $tool->description)
@section('content')
<section class="page-hero tool-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['المختبر', route('tools.index')], [$tool->name, null]]" />
    <span class="kicker">{{ $tool->category }} · {{ $tool->kindLabel() }}</span>
    <h1>{{ $tool->name }}</h1>
    <p class="lead">{{ $tool->description }}</p>
    <div class="row tool-meta">
        <span class="card-tag">الرصيد: 0 بلا ذكاء اصطناعي@if($tool->credit_cost > 0) · {{ $tool->credit_cost }} مع التحسين@endif</span>
        <a class="btn btn-secondary" href="{{ route('tools.show', [$tool, 'example' => 1]) }}#inputs" data-example>مثال جاهز</a>
        <a class="btn btn-text" href="{{ route('tools.show', [$tool, 'fresh' => 1]) }}#inputs" data-fresh>ابدأ من الصفر</a>
    </div>
</div></section>

<section class="section-tight"><div class="shell">
    <x-athar.flash />
    @if($result && ! empty($result['notice']))<x-athar.alert type="info">{{ $result['notice'] }}</x-athar.alert>@endif

    {{-- شريط السلسلة (§4.7 رقم 2) --}}
    <div class="chain-bar" id="chain" aria-label="شريط السلسلة">
        <div class="chain-cell"><span class="chain-k">يُغذّى من</span>
            <ul>@forelse($chain['feeds_from'] as $f)<li class="{{ $f['ok'] ? 'is-ok' : 'is-missing' }}" data-feed="{{ $f['type'] }}" data-ok="{{ $f['ok'] ? '1' : '0' }}"><span aria-hidden="true">{{ $f['ok'] ? '✓' : '○' }}</span>@if($f['url'])<a href="{{ $f['url'] }}">{{ $f['label'] }}</a>@else{{ $f['label'] }}@endif @if($f['ok'] && ! empty($f['title']))<small class="muted">{{ Str::limit($f['title'], 28) }}</small>@endif</li>@empty<li class="muted">تبدأ من الصفر</li>@endforelse</ul>
        </div>
        <div class="chain-cell is-produce"><span class="chain-k">هذه الأداة تُنتج</span><b>{{ $chain['produces']['label'] }}</b><small class="muted">أصل Markdown بفحص ستّي</small></div>
        <div class="chain-cell"><span class="chain-k">يغذّي بدوره</span>
            <ul>@forelse($chain['feeds_into'] as $f)<li class="{{ $f['ok'] ? 'is-ok' : '' }}">@if($f['url'])<a href="{{ $f['url'] }}">{{ $f['label'] }}</a>@else{{ $f['label'] }}@endif</li>@empty<li class="muted">نهاية السلسلة</li>@endforelse</ul>
        </div>
        <div class="chain-cell"><span class="chain-k">مكانه في خطة المسار</span>
            @if($chain['position'])<b>الحلقة {{ $chain['position'] }} من {{ $chain['total'] }}</b>@endif
            <x-athar.progress :value="$chain['plan']['percent']" :done="$chain['plan']['saved']" :total="$chain['plan']['total']" unit="أصول" />
            <small class="muted">{{ $chain['course'] ? 'مسار «'.$chain['course']->title.'»' : 'سلسلة خطة المشروع' }} · اكتمال خطتك {{ $chain['plan']['percent'] }}٪</small>
        </div>
    </div>

    <div class="tool-layout">
        <div class="stack" style="gap:24px">
            {{-- المدخلات (§4.7 رقم 3) --}}
            <section class="lab" id="inputs" aria-labelledby="inputs-h">
                <h2 id="inputs-h" style="font-size:22px">المدخلات</h2>
                <p class="muted small">الحقول الملوّنة مُعبّأة من مشروعك أو من أصل سابق في السلسلة، وكلها قابلة للتعديل.</p>
                <form id="tool-form" method="post" action="{{ route('tools.generate', $tool) }}">
                    @csrf
                    @if($member)
                    <div class="field" style="margin-bottom:14px"><label for="project_id">المشروع</label>
                        @if($projects->isNotEmpty())<select id="project_id" name="project_id">@foreach($projects as $p)<option value="{{ $p->id }}" @selected($project && $project->id === $p->id)>{{ $p->name }}</option>@endforeach</select><span class="hint">ملف المشروع يعبّئ الحقول؛ <a href="{{ $project ? route('app.projects.show', $project) : route('dashboard') }}">حرّره من صفحة مشروعي</a>.</span>
                        @else<p class="muted small">لا مشروع في مساحتك بعد. <a href="{{ route('dashboard') }}#projects">أنشئ مشروعاً</a> ليُحفظ الأصل فيه.</p>@endif
                    </div>
                    @endif
                    <div class="lab-fields">
                        @foreach($fields as $field)
                        <div class="field {{ $field['prefilled'] ? 'is-prefilled' : '' }} {{ $errors->has('lab') && ! empty($field['required']) && trim((string) $field['value']) === '' ? 'is-invalid' : '' }} {{ ($field['type'] ?? 'text') === 'textarea' ? 'span-2' : '' }}" data-field="{{ $field['key'] }}">
                            <label for="f-{{ $field['key'] }}">{{ $field['label'] }} @if($field['prefilled'])<span class="source" data-source>{{ $field['source_label'] }}</span>@endif</label>
                            @if(($field['type'] ?? 'text') === 'textarea')<textarea id="f-{{ $field['key'] }}" name="{{ $field['key'] }}" placeholder="{{ $field['placeholder'] ?? '' }}" @required(! empty($field['required']))>{{ $field['value'] }}</textarea>
                            @elseif(($field['type'] ?? '') === 'select')<select id="f-{{ $field['key'] }}" name="{{ $field['key'] }}">@foreach($field['options_map'] as $k => $l)<option value="{{ $k }}" @selected((string) $field['value'] === (string) $k)>{{ $l }}</option>@endforeach</select>
                            @else<input id="f-{{ $field['key'] }}" name="{{ $field['key'] }}" type="{{ ($field['type'] ?? 'text') === 'number' ? 'number' : 'text' }}" value="{{ $field['value'] }}" placeholder="{{ $field['placeholder'] ?? '' }}" @if(($field['type'] ?? '') === 'number') step="any" min="0" @endif @required(! empty($field['required']))>@endif
                            @if(! empty($field['hint']))<span class="hint">{{ $field['hint'] }}</span>@endif
                        </div>
                        @endforeach
                    </div>
                    <div class="btn-row">
                        <x-athar.button type="submit" id="generate-btn">أنشئ الأصل</x-athar.button>
                        @if($member && $projects->isNotEmpty())
                            <button class="btn btn-secondary" type="submit" formaction="{{ route('app.tools.run', $tool) }}" formmethod="post" id="enhance-btn" title="{{ $aiEnabled ? 'يُخصم '.$tool->credit_cost.' رصيد ويُحفظ كإصدار جديد' : 'الذكاء الاصطناعي غير مفعّل الآن: سيُحفظ المخرج القاعدي بلا خصم' }}">حسّن بالذكاء الاصطناعي{{ $tool->credit_cost > 0 ? ' ('.$tool->credit_cost.' رصيد)' : '' }}</button>
                        @elseif(! $member)
                            <a class="btn btn-text" href="{{ route('register') }}">حسّن بالذكاء الاصطناعي (بحساب)</a>
                        @endif
                        <span class="small muted">التوليد القاعدي لا يحتاج ذكاءً اصطناعياً ولا رصيداً.</span>
                    </div>
                </form>
            </section>

            {{-- بطاقة الأصل (§4.7 رقم 4) --}}
            @if($result && ! empty($result['markdown']))
                <x-athar.asset-card id="asset" kicker="{{ ($result['enhanced'] ?? false) ? 'أصلك (محسّن بالذكاء الاصطناعي)' : 'أصلك' }}" :title="$result['title']" :type="$tool->output_type" :markdown="$result['markdown']" :quality="$result['quality']" :saveUrl="$projects->isNotEmpty() || ! $member ? route('app.tools.save', $tool) : null" :savePayload="['project_id' => $project?->id]" :member="$member" :saved="$isSaved" :versions="$saved->isNotEmpty() ? $saved : null" :currentVersion="$isSaved ? $latest->version : null" :diff="$pendingDiff ?? $diff" regenerateForm="tool-form" :filename="$tool->output_type">
                    <x-slot:actions>
                        @if($member && $latest && $isSaved)
                            @if($latest->isShared())
                                <form method="post" action="{{ route('app.outputs.unshare', $latest) }}">@csrf @method('DELETE')<button class="btn btn-secondary" type="submit" data-action="unshare">إلغاء المشاركة</button></form>
                            @else
                                <form method="post" action="{{ route('app.outputs.share', $latest) }}">@csrf<button class="btn btn-secondary" type="submit" data-action="share">مشاركة برابط للقراءة</button></form>
                            @endif
                        @elseif($member)
                            <span class="btn btn-secondary" aria-disabled="true" title="احفظ الأصل أولاً ثم شاركه">مشاركة (بعد الحفظ)</span>
                        @else
                            <a class="btn btn-secondary" href="{{ route('register') }}" data-action="share-gate">مشاركة برابط (بحساب)</a>
                        @endif
                    </x-slot:actions>
                    @if(session('share_url'))<div class="share-box" data-share-box><label for="share-url" class="small">رابط القراءة العامة (noindex، قابل للإلغاء)</label><div class="row"><input id="share-url" class="input" readonly value="{{ session('share_url') }}"><button class="btn btn-secondary" type="button" data-copy-target="#share-url">نسخ الرابط</button></div></div>
                    @elseif($member && $latest && $isSaved && $latest->isShared())<div class="share-box" data-share-box><label for="share-url" class="small">رابط القراءة العامة</label><div class="row"><input id="share-url" class="input" readonly value="{{ route('assets.shared', $latest->share_token) }}"><button class="btn btn-secondary" type="button" data-copy-target="#share-url">نسخ الرابط</button></div></div>@endif
                </x-athar.asset-card>
                @if($chain['next'] && $chain['next']['url'])
                <div class="next-step card" id="next-step"><div class="split"><div><x-athar.kicker>الخطوة التالية في السلسلة</x-athar.kicker><h3 style="font-size:20px">{{ $chain['next']['label'] }}</h3><p class="muted small" style="margin:0">سيُعبَّأ من هذا الأصل تلقائياً.</p></div><x-athar.button :href="$chain['next']['url']" data-next-step>تابع ←</x-athar.button></div></div>
                @endif
                @if(! $member)<x-athar.gate title="احفظ أصلك وشاركه برابط" text="بحساب مجاني تُحفظ أصولك بإصدارات داخل مشروعك، وتُشارك برابط قراءة قابل للإلغاء، وتتجمّع خطتك عند اكتمال السلسلة." :href="route('register')" :benefits="['حفظ بإصدارات', 'مشاركة برابط', 'خطة مجمّعة']" />@endif
            @else
                <div class="card placeholder-card" id="asset-placeholder"><x-athar.kicker>أصلك</x-athar.kicker><p class="muted" style="margin:0">سيظهر هنا بعد «أنشئ الأصل»: المخرج منسقاً، فحص قابلية التنفيذ بستة بنود، والأفعال (نسخ، تنزيل، حفظ، مشاركة).</p></div>
            @endif
        </div>

        {{-- الجانب (§4.7 رقم 5) --}}
        <aside class="side stack" style="gap:16px">
            <div class="side-box" id="plan-box"><h3>خطة مشروعك تتجمّع</h3>
                <x-athar.progress :value="$chain['plan']['percent']" :done="$chain['plan']['saved']" :total="$chain['plan']['total']" unit="أصول" />
                <ul class="plan-chain">@foreach($chain['plan']['items'] as $item)<li class="is-{{ $item['state'] }}" data-plan-item="{{ $item['type'] }}" data-state="{{ $item['state'] }}"><span aria-hidden="true">{{ $item['state'] === 'saved' ? '✓' : ($item['state'] === 'generated' ? '◔' : '○') }}</span>@if($item['url'])<a href="{{ $item['url'] }}">{{ $item['label'] }}</a>@else{{ $item['label'] }}@endif @if($item['version'])<small class="muted">v{{ $item['version'] }}</small>@endif</li>@endforeach</ul>
                @if($member && $project)
                    @if($chain['plan']['complete'])<x-athar.button :href="route('app.projects.plan', $project)" block>افتح خطتك المجمّعة</x-athar.button>
                    @else<p class="small muted" style="margin:8px 0 0">عند اكتمال العشرة تُجمَّع في خطة من صفحة داخل <a href="{{ route('app.projects.show', $project) }}">مشروعك</a>.</p>@endif
                @else<p class="small muted" style="margin:8px 0 0">◔ مولَّد في هذه الجلسة · ✓ محفوظ في مشروعك (بحساب).</p>@endif
            </div>
            <div class="side-box" id="examples"><h3>أمثلة مجهولة من متعلمين آخرين</h3>
                @forelse($examples as $ex)<details class="example-asset"><summary>{{ $ex->title }} <small class="muted">— {{ $ex->payload['example_project'] ?? 'مثال' }}</small></summary><div class="asset-body small">{!! \App\Support\Markdown::toHtml(Str::limit((string) $ex->body_markdown, 1400)) !!}</div></details>@empty<p class="muted small">لا أمثلة بعد.</p>@endforelse
            </div>
            @if($relatedLesson)<x-athar.card heading="h3" title="الدرس المرتبط" :desc="$relatedLesson->title" :href="route('academy.lesson', [$relatedLesson->course, $relatedLesson])" tag="الأكاديمية" />@endif
            @if($relatedArticle)<x-athar.card heading="h3" title="المادة المرتبطة" :desc="$relatedArticle->title" :href="route('articles.show', $relatedArticle)" tag="المجلة" />@endif
        </aside>
    </div>
</div></section>
@endsection
@push('scripts')
@include('partials.asset-actions-script')
@endpush
