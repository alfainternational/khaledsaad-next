@extends('layouts.platform')
@section('title', $tool->name.' — المختبر — أثر')
@section('description', $tool->description)
@section('content')
<x-athar.schema :data="['@type' => 'WebApplication', 'name' => $tool->name, 'description' => $tool->description, 'url' => route('tools.reader'), 'inLanguage' => 'ar', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'publisher' => ['@type' => 'Organization', 'name' => 'أثر', 'url' => route('home')]]" />
<section class="page-hero tool-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['المختبر', route('tools.index')], [$tool->name, null]]" />
    <span class="kicker">{{ $tool->category }} · {{ $tool->kindLabel() }}</span>
    <h1>{{ $tool->name }}</h1>
    <p class="lead">{{ $tool->description }}</p>
    <div class="row tool-meta">
        <span class="card-tag">{{ $aiEnabled ? $tool->credit_cost.' رصيد مع الذكاء الاصطناعي' : 'استخلاص قاعدي بلا رصيد' }}</span>
    </div>
</div></section>

<section class="section-tight"><div class="shell">
    <x-athar.flash />
    @error('reader')<x-athar.alert type="warning">{{ $message }}</x-athar.alert>@enderror
    @if($result && ! empty($result['notice']))<x-athar.alert type="info">{{ $result['notice'] }}</x-athar.alert>@endif

    <div class="tool-layout">
        <div class="stack" style="gap:24px">
            <section class="lab" id="reader-inputs" aria-labelledby="reader-inputs-h">
                <h2 id="reader-inputs-h" style="font-size:22px">الرابط</h2>
                <p class="muted small">صفحة فيها نصّ حقيقي عن نشاطك. الصفحة الرئيسية أحياناً شعارات وصور فقط، وصفحة «من نحن» أو صفحة منتج تعطي استخلاصاً أدقّ.</p>
                <form id="reader-form" method="post" action="{{ route('app.tools.read', $tool) }}">
                    @csrf
                    @if($member)
                    <div class="field" style="margin-bottom:14px"><label for="project_id">المشروع</label>
                        @if($projects->isNotEmpty())<select id="project_id" name="project_id">@foreach($projects as $p)<option value="{{ $p->id }}" @selected($project && $project->id === $p->id)>{{ $p->name }}</option>@endforeach</select><span class="hint">الاقتراح يُعرض عليك، ولا يُكتب في ملف المشروع إلا بموافقتك.</span>
                        @else<p class="muted small">لا مشروع في مساحتك بعد. <a href="{{ route('dashboard') }}#projects">أنشئ مشروعاً</a> ليستقرّ فيه الملف.</p>@endif
                    </div>
                    @endif
                    <div class="lab-fields">
                        <div class="field span-2"><label for="f-url">رابط موقعك</label>
                            <input id="f-url" name="url" type="url" inputmode="url" dir="ltr" value="{{ $url }}" placeholder="https://example.com/about" @required($member)>
                            <span class="hint">http أو https فقط. العناوين الداخلية مرفوضة، والصفحات التي يمنعها <code>robots.txt</code> لا تُجلب.</span>
                        </div>
                    </div>
                    <div class="btn-row">
                        @if($member && $projects->isNotEmpty())
                            <x-athar.button type="submit" id="read-btn">اقرأ الصفحة</x-athar.button>
                            <span class="small muted">{{ $aiEnabled ? 'يُخصم '.$tool->credit_cost.' رصيد إن شارك النموذج في الاستخلاص.' : 'بلا رصيد: الاستخلاص القاعدي من نصّ الصفحة.' }}</span>
                        @elseif($member)
                            <span class="btn btn-secondary" aria-disabled="true">تحتاج مشروعاً</span>
                        @else
                            <a class="btn btn-primary" href="{{ route('register') }}" data-action="reader-gate">اقرأ موقعك (بحساب)</a>
                            <span class="small muted">الاقتراح يُطبَّق على ملف مشروعك، فيحتاج حساباً.</span>
                        @endif
                    </div>
                </form>
            </section>

            @if($result && ! empty($result['proposal']))
                @php($p = $result['proposal'])
                <div class="card" id="reader-result">
                    <x-athar.kicker>الاقتراح</x-athar.kicker>
                    <div class="split"><h3 style="font-size:20px">{{ $p['brand'] ?: $result['facts']['host'] }}</h3><span class="card-tag">{{ $result['used_ai'] ? 'بمشاركة النموذج' : 'استخلاص قاعدي' }} · {{ $result['credits'] }} رصيد</span></div>
                    <p class="small muted" style="margin:6px 0 14px">الحقول الفارغة تُركت فارغة عمداً: الصفحة لم تقل ذلك — وزائرك لم يقرأه أيضاً. اختر ما تريد تطبيقه فقط.</p>

                    <form method="post" action="{{ route('app.tools.read.apply', [$tool, $project]) }}" id="apply-form">
                        @csrf
                        <table class="table" data-proposal>
                            <thead><tr><th>طبّق</th><th>الحقل</th><th>ما استُخلص</th><th>في مشروعك الآن</th></tr></thead>
                            <tbody>
                                @foreach([ 'offer' => 'ما تبيعه', 'audience' => 'الجمهور' ] as $key => $label)
                                <tr data-field="{{ $key }}">
                                    <td><input type="checkbox" name="fields[]" value="{{ $key }}" @checked(trim((string) ($p[$key] ?? '')) !== '') @disabled(trim((string) ($p[$key] ?? '')) === '') aria-label="طبّق {{ $label }}"></td>
                                    <td>{{ $label }}</td>
                                    <td><input class="input" name="{{ $key }}" value="{{ $p[$key] ?? '' }}" maxlength="300" placeholder="لم تقله الصفحة"></td>
                                    <td class="small muted">{{ $profile[$key] ?? '—' }}</td>
                                </tr>
                                @endforeach
                                <tr data-field="sector">
                                    <td><input type="checkbox" name="fields[]" value="sector" @checked(! empty($p['sector'])) @disabled(empty($p['sector'])) aria-label="طبّق القطاع"></td>
                                    <td>القطاع</td>
                                    <td><select name="sector"><option value="">— لم يتضح</option>@foreach($sectors as $k => $l)<option value="{{ $k }}" @selected(($p['sector'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></td>
                                    <td class="small muted">{{ $sectors[$profile['sector'] ?? ''] ?? '—' }}</td>
                                </tr>
                                <tr data-field="channel">
                                    <td><input type="checkbox" name="fields[]" value="channel" @checked(! empty($p['channel'])) @disabled(empty($p['channel'])) aria-label="طبّق القناة"></td>
                                    <td>القناة الظاهرة</td>
                                    <td><select name="channel"><option value="">— لم تظهر قناة</option>@foreach($channels as $k => $l)<option value="{{ $k }}" @selected(($p['channel'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></td>
                                    <td class="small muted">{{ $channels[$profile['channel'] ?? ''] ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="btn-row">
                            <x-athar.button type="submit" id="apply-btn">طبّق المختار على ملف المشروع</x-athar.button>
                            <span class="small muted">يُكتب ما اخترته فقط، ويبقى الباقي كما هو.</span>
                        </div>
                    </form>

                    @if(! empty($p['differentiator']) || ! empty($p['proof']))
                    <div class="side-box" style="margin-top:16px"><h4 style="margin-top:0">لا يحمله ملف المشروع، ويستحق النسخ إلى أدواتك</h4>
                        <ul class="small" style="margin:0;padding-inline-start:18px">
                            @if(! empty($p['differentiator']))<li><b>ما يميّزك:</b> {{ $p['differentiator'] }}</li>@endif
                            @if(! empty($p['proof']))<li><b>الدليل:</b> {{ $p['proof'] }}</li>@endif
                        </ul>
                    </div>
                    @endif
                </div>

                <x-athar.asset-card id="reader-asset" kicker="ملف العلامة" :title="'ملف العلامة — '.($p['brand'] ?: $result['facts']['host'])" :type="$tool->output_type" :markdown="$result['markdown']" :member="$member" :versions="$saved->isNotEmpty() ? $saved : null" :currentVersion="$saved->first()?->version" regenerateForm="reader-form" :filename="$tool->output_type" />
            @else
                <div class="card placeholder-card" id="reader-placeholder"><x-athar.kicker>الاقتراح</x-athar.kicker><p class="muted" style="margin:0">سيظهر هنا بعد القراءة: ما تبيعه، لمن، قطاعك، ما يميّزك، ودليلك — مع ما قالته الصفحة نفسها بجانبه، وزرّ يطبّق المختار على ملف مشروعك.</p></div>
            @endif
        </div>

        <aside class="side stack" style="gap:16px">
            <div class="side-box"><h3>لماذا يَقترح ولا يكتب</h3>
                <p class="small" style="margin:0">ملف المشروع كلامك أنت، ومنه تُعبَّأ المولّدات التسعة كلها. استخلاصٌ من صفحة واحدة يكتب فوقه بلا مراجعة ليس مساعدة — هو المنصة تتحدث بدلاً عنك. فالقراءة تقترح، وأنت تعتمد.</p>
            </div>
            <div class="side-box"><h3>ما لا يُجلب</h3>
                <ul class="small" style="margin:0;padding-inline-start:18px">
                    <li>العناوين الداخلية والشبكات الخاصة — يُفحص العنوان الذي يشير إليه النطاق فعلاً لا اسمه.</li>
                    <li>ما يمنعه <code>robots.txt</code> من مسارات الموقع.</li>
                    <li>أكثر من عدد محدود من الطلبات للموقع نفسه في الدقيقة.</li>
                </ul>
            </div>
            <x-athar.card heading="h3" title="بعد التطبيق" desc="ابدأ بشخصية العميل: أول حلقة في السلسلة، وستجدها مُعبّأة من ملفك." :href="route('tools.show', 'customer-persona')" tag="المختبر" />
        </aside>
    </div>
</div></section>
@endsection
