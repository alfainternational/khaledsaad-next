@extends('layouts.platform')
@section('title', $tool->name.' — المختبر — أثر')
@section('description', $tool->description)
@section('content')
<x-athar.schema :data="['@type' => 'WebApplication', 'name' => $tool->name, 'description' => $tool->description, 'url' => route('tools.studio'), 'inLanguage' => 'ar', 'applicationCategory' => 'DesignApplication', 'operatingSystem' => 'Web', 'isAccessibleForFree' => true, 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'], 'publisher' => ['@type' => 'Organization', 'name' => 'أثر', 'url' => route('home')]]" />
<section class="page-hero tool-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['المختبر', route('tools.index')], [$tool->name, null]]" />
    <span class="kicker">{{ $tool->category }} · {{ $tool->kindLabel() }}</span>
    <h1>{{ $tool->name }}</h1>
    <p class="lead">{{ $tool->description }}</p>
    <div class="row tool-meta">
        <span class="card-tag">بلا رصيد · بلا ذكاء اصطناعي · صورتك تبقى ملكك</span>
    </div>
</div></section>

<section class="section-tight"><div class="shell">
    <x-athar.flash />
    @error('studio')<x-athar.alert type="warning">{{ $message }}</x-athar.alert>@enderror
    @if(! $fontAvailable)<x-athar.alert type="info">كتابة العنوان على الصورة معطّلة الآن: ملف الخط العربي غير مثبّت على الخادم. المقاسات والشعار تعمل بدونه.</x-athar.alert>@endif

    <div class="chain-bar" id="chain" aria-label="شريط السلسلة">
        <div class="chain-cell"><span class="chain-k">يُغذّى من</span>
            <ul>@forelse($chain['feeds_from'] as $f)<li class="{{ $f['ok'] ? 'is-ok' : 'is-missing' }}" data-feed="{{ $f['type'] }}" data-ok="{{ $f['ok'] ? '1' : '0' }}"><span aria-hidden="true">{{ $f['ok'] ? '✓' : '○' }}</span>@if($f['url'])<a href="{{ $f['url'] }}">{{ $f['label'] }}</a>@else{{ $f['label'] }}@endif</li>@empty<li class="muted">تبدأ من الصفر</li>@endforelse</ul>
        </div>
        <div class="chain-cell is-produce"><span class="chain-k">هذا الاستوديو يُنتج</span><b>{{ $chain['produces']['label'] }}</b><small class="muted">ملفات صور جاهزة للنشر</small></div>
        <div class="chain-cell"><span class="chain-k">يغذّي بدوره</span>
            <ul>@forelse($chain['feeds_into'] as $f)<li class="{{ $f['ok'] ? 'is-ok' : '' }}">@if($f['url'])<a href="{{ $f['url'] }}">{{ $f['label'] }}</a>@else{{ $f['label'] }}@endif</li>@empty<li class="muted">نهاية السلسلة</li>@endforelse</ul>
        </div>
        <div class="chain-cell"><span class="chain-k">الحدود</span>
            <b>{{ (int) round($limits['max_bytes'] / 1048576) }} م.ب · {{ (float) $limits['max_megapixels'] }} ميغابكسل</b>
            <small class="muted">الحد على البكسلات لا على الحجم وحده: ملف صغير بأبعاد ضخمة يستهلك ذاكرة الخادم عند فكّه.</small>
        </div>
    </div>

    <div class="tool-layout">
        <div class="stack" style="gap:24px">
            <section class="lab" id="studio-inputs" aria-labelledby="studio-inputs-h">
                <h2 id="studio-inputs-h" style="font-size:22px">المدخلات</h2>
                <p class="muted small">صورتك تُقصّ من المنتصف لتملأ كل مقاس بلا تشويه، وتُعاد كتابتها من جديد — فتُحذف بيانات EXIF (ومنها موقع التصوير) تلقائياً.</p>
                <form id="studio-form" method="post" action="{{ route('app.tools.studio', $tool) }}" enctype="multipart/form-data">
                    @csrf
                    @if($member)
                    <div class="field" style="margin-bottom:14px"><label for="project_id">المشروع</label>
                        @if($projects->isNotEmpty())<select id="project_id" name="project_id">@foreach($projects as $p)<option value="{{ $p->id }}" @selected($project && $project->id === $p->id)>{{ $p->name }}</option>@endforeach</select>
                        @else<p class="muted small">لا مشروع في مساحتك بعد. <a href="{{ route('dashboard') }}#projects">أنشئ مشروعاً</a> لحفظ الأصول فيه.</p>@endif
                    </div>
                    @endif

                    <div class="lab-fields">
                        <div class="field span-2"><label for="f-source">صورة المنتج أو الخلفية <span class="muted small">(مطلوبة)</span></label>
                            <input id="f-source" name="source" type="file" accept="image/jpeg,image/png,image/webp" @required($member)>
                            <span class="hint">JPG أو PNG أو WebP، حتى {{ (int) round($limits['max_bytes'] / 1048576) }} ميغابايت.</span>
                        </div>
                        @foreach($fields as $field)
                        <div class="field span-2 {{ $field['prefilled'] ? 'is-prefilled' : '' }}" data-field="{{ $field['key'] }}">
                            <label for="f-{{ $field['key'] }}">{{ $field['label'] }} @if($field['prefilled'])<span class="source" data-source>{{ $field['source_label'] }}</span>@endif</label>
                            <textarea id="f-{{ $field['key'] }}" name="{{ $field['key'] }}" rows="2" maxlength="300" placeholder="{{ $field['placeholder'] ?? '' }}" @disabled(! $fontAvailable)>{{ $field['value'] }}</textarea>
                            @if(! empty($field['hint']))<span class="hint">{{ $field['hint'] }}</span>@endif
                        </div>
                        @endforeach
                        <div class="field span-2"><label for="f-logo">شعارك <span class="muted small">(اختياري)</span></label>
                            <input id="f-logo" name="logo" type="file" accept="image/png,image/webp,image/jpeg">
                            <span class="hint">PNG بخلفية شفافة يعطي أفضل نتيجة. يُوضع في الزاوية العلوية.</span>
                        </div>
                        <fieldset class="field span-2"><legend style="font-weight:700">المقاسات</legend>
                            <div class="row" style="flex-wrap:wrap;gap:10px">
                                @foreach($templates as $key => $template)
                                <label class="small row" style="gap:6px"><input type="checkbox" name="templates[]" value="{{ $key }}" @checked(in_array($key, $result['templates'] ?? ['instagram_post', 'instagram_story'], true))> {{ $template['label'] }} <span class="muted">{{ $template['width'] }}×{{ $template['height'] }}</span></label>
                                @endforeach
                            </div>
                            <span class="hint">حتى {{ (int) $limits['max_templates'] }} مقاسات في التشغيل الواحد.</span>
                        </fieldset>
                    </div>

                    <div class="btn-row">
                        @if($member && $projects->isNotEmpty())
                            <x-athar.button type="submit" id="studio-btn">جهّز الأصول</x-athar.button>
                            <span class="small muted">بلا رصيد: لا نموذج يُستدعى هنا.</span>
                        @elseif($member)
                            <span class="btn btn-secondary" aria-disabled="true">تحتاج مشروعاً</span>
                        @else
                            <a class="btn btn-primary" href="{{ route('register') }}" data-action="studio-gate">جهّز الأصول (بحساب)</a>
                            <span class="small muted">الأصول تُحفظ في مشروعك، فالاستوديو يحتاج حساباً — لا رصيداً.</span>
                        @endif
                    </div>
                </form>
            </section>

            @if($result && ! empty($result['media']))
                <div class="card" id="studio-result">
                    <x-athar.kicker>أصولك</x-athar.kicker>
                    <div class="split"><h3 style="font-size:20px">{{ $result['title'] }}</h3><span class="card-tag">{{ count($result['media']) }} ملفات · 0 رصيد</span></div>
                    <div class="row" style="flex-wrap:wrap;gap:14px;margin-top:12px">
                        @foreach($result['media'] as $asset)
                        <figure style="margin:0;max-width:220px">
                            <a href="{{ $asset['url'] }}" target="_blank" rel="noopener"><img src="{{ $asset['url'] }}" alt="{{ $asset['label'] }}" loading="lazy" style="max-width:220px;height:auto;border-radius:10px;display:block"></a>
                            <figcaption class="small muted" style="margin-top:6px">{{ $asset['label'] }} · {{ $asset['width'] }}×{{ $asset['height'] }} · {{ $asset['size'] }}</figcaption>
                        </figure>
                        @endforeach
                    </div>
                </div>
                <x-athar.asset-card id="studio-asset" kicker="أصل الاستوديو" :title="$result['title']" :type="$tool->output_type" :markdown="$result['markdown']" :member="$member" :versions="$saved->isNotEmpty() ? $saved : null" :currentVersion="$saved->first()?->version" regenerateForm="studio-form" :filename="$tool->output_type" />
            @else
                <div class="card placeholder-card" id="studio-placeholder"><x-athar.kicker>أصولك</x-athar.kicker><p class="muted" style="margin:0">سيظهر هنا بعد التجهيز: ملف لكل مقاس، بمعاينة ورابط تنزيل، محفوظاً في مشروعك.</p></div>
            @endif
        </div>

        <aside class="side stack" style="gap:16px">
            <div class="side-box"><h3>لماذا بلا توليد</h3>
                <p class="small" style="margin:0">معظم «استوديو المنتج» ليس توليداً: القصّ والكتابة والشعار عمليات حتمية على صورة تملكها أنت، فنتيجتها كاملة الجودة ومجانية. التوليد هو الشيء الوحيد الذي تكون نسخته المجانية أضعف فعلاً — فلم يُشحن ناقصاً، بل تُرك حتى يستحق ثمنه.</p>
            </div>
            <div class="side-box"><h3>ما يحدث لملفك</h3>
                <ul class="small" style="margin:0;padding-inline-start:18px">
                    <li>يُفحص رأس الملف قبل فكّه: الصيغة والأبعاد، لا الاسم ولا الامتداد.</li>
                    <li>يُعاد ترميزه، فتسقط بيانات EXIF ومنها إحداثيات التصوير.</li>
                    <li>يُخزَّن باسم عشوائي لا باسم ملفك، وخارج المجلد العام.</li>
                    <li>لا يُقرأ إلا بحسابك، أو بمن تشارك معه الأصل برابط.</li>
                </ul>
            </div>
            <x-athar.card heading="h3" title="اكتب العنوان أولاً" desc="العناوين التي تُولّدها أداة «عناوين وCTA» تُعبّأ هنا تلقائياً." :href="route('tools.show', 'headlines-cta')" tag="المختبر" />
        </aside>
    </div>
</div></section>
@endsection
