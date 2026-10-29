@extends('layouts.platform')
@section('title', $project->name.' — مشروعي — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell">
    <x-athar.breadcrumbs :items="[['لوحتي', route('dashboard')], ['مشروعي: '.$project->name, null]]" />
    <h1>{{ $project->name }}</h1>
    <p class="muted">ملف المشروع يعبّئ نماذج الأدوات تلقائياً؛ أصوله تتراكم هنا، وعند اكتمال السلسلة تتجمّع خطته في صفحة.</p>
</div></section>
<section class="section-tight"><div class="shell dash-grid">
    <div class="stack" style="gap:24px">
        <x-athar.flash />
        <form class="card" method="post" action="{{ route('app.projects.update', $project) }}" id="project-profile">@csrf @method('PATCH')
            <x-athar.kicker>ملف المشروع</x-athar.kicker><h2 style="font-size:22px">ما تعرفه الأدوات عنك</h2>
            <div class="lab-fields">
                <div class="field span-2"><label for="name">اسم المشروع</label><input id="name" name="name" required maxlength="120" value="{{ old('name', $project->name) }}"></div>
                <div class="field span-2"><label for="offer">ما الذي تبيعه؟ (العرض)</label><input id="offer" name="offer" maxlength="300" value="{{ old('offer', $profile['offer'] ?? '') }}" placeholder="مثال: منتجات عناية طبيعية للبشرة الحساسة"></div>
                <div class="field"><label for="sector">القطاع</label><select id="sector" name="sector"><option value="">—</option>@foreach($sectors as $k => $l)<option value="{{ $k }}" @selected(($profile['sector'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="city">المدينة</label><input id="city" name="city" maxlength="80" value="{{ old('city', $profile['city'] ?? '') }}"></div>
                <div class="field span-2"><label for="audience">الجمهور (شريحة واحدة)</label><input id="audience" name="audience" maxlength="300" value="{{ old('audience', $profile['audience'] ?? '') }}" placeholder="مثال: نساء 25–40 في الرياض ذوات بشرة حساسة"></div>
                <div class="field"><label for="goal">الهدف</label><select id="goal" name="goal"><option value="">—</option>@foreach($goals as $k => $l)<option value="{{ $k }}" @selected(($profile['goal'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="goal_number">الرقم المطلوب شهرياً</label><input id="goal_number" name="goal_number" type="number" min="0" value="{{ old('goal_number', $profile['goal_number'] ?? '') }}"></div>
                <div class="field"><label for="channel">القناة الأساسية</label><select id="channel" name="channel"><option value="">—</option>@foreach($channels as $k => $l)<option value="{{ $k }}" @selected(($profile['channel'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="budget">الميزانية الشهرية (ريال)</label><input id="budget" name="budget" type="number" min="0" value="{{ old('budget', $profile['budget'] ?? '') }}"></div>
                <div class="field span-2"><label for="notes">ملاحظات</label><textarea id="notes" name="notes" maxlength="4000" placeholder="قرارات، أرقام حقيقية بعد 14 يوماً، ما تعلمته…">{{ old('notes', $profile['notes'] ?? '') }}</textarea></div>
            </div>
            <div class="btn-row"><x-athar.button type="submit">حفظ الملف</x-athar.button><span class="small muted">يُستخدم في الدروس (موقفك) وفي كل أداة («من المشروع»).</span></div>
        </form>

        <div class="card" id="project-assets"><div class="split"><div><x-athar.kicker>أصول المشروع</x-athar.kicker><h2 style="font-size:22px">{{ $outputs->count() }} أصلاً</h2></div><a class="btn btn-text" href="{{ route('app.outputs.index', ['project' => $project->id]) }}">كل أصولي ←</a></div>
            @if($outputs->isEmpty())<p class="muted">لا أصول بعد. <a href="{{ route('tools.index') }}">افتح المختبر</a>؛ أول حلقة في السلسلة هي شخصية العميل.</p>
            @else<x-athar.table caption="أصول المشروع"><thead><tr><th>النوع</th><th>العنوان</th><th>المصدر</th><th>التاريخ</th><th></th></tr></thead><tbody>@foreach($outputs as $o)<tr><td>{{ \App\Domain\Lab\Services\LabCatalog::label((string) $o->output_type) }}</td><td><a href="{{ route('app.outputs.show', $o) }}">{{ $o->title }}</a> <span class="muted small">v{{ $o->version }}</span></td><td class="small">{{ $o->lesson ? 'درس' : ($o->tool?->name ?? '—') }}</td><td class="muted small">{{ $o->created_at->format('Y/m/d') }}</td><td><form method="post" action="{{ route('app.outputs.destroy', $o) }}" data-confirm="حذف «{{ $o->title }}» v{{ $o->version }}؟">@csrf @method('DELETE')<button class="btn btn-text danger" type="submit">حذف</button></form></td></tr>@endforeach</tbody></x-athar.table>@endif
        </div>
        @if($workspace->pivot->role === 'owner')
        <form method="post" action="{{ route('app.projects.destroy', $project) }}" data-confirm="حذف المشروع وكل أصوله نهائياً؟">@csrf @method('DELETE')<button class="btn btn-text danger" type="submit">حذف المشروع</button></form>
        @endif
    </div>
    <aside class="stack" style="gap:16px">
        <div class="card" id="plan-box"><x-athar.kicker>خطة مشروعك تتجمّع</x-athar.kicker>
            <x-athar.progress :value="$plan['percent']" :done="$plan['saved']" :total="$plan['total']" unit="أصول" />
            <ul class="plan-chain">@foreach($plan['items'] as $item)<li class="is-{{ $item['state'] }}" data-plan-item="{{ $item['type'] }}" data-state="{{ $item['state'] }}"><span aria-hidden="true">{{ $item['state'] === 'saved' ? '✓' : '○' }}</span>@if($item['url'])<a href="{{ $item['url'] }}">{{ $item['label'] }}</a>@else{{ $item['label'] }}@endif @if($item['version'])<small class="muted">v{{ $item['version'] }}</small>@endif</li>@endforeach</ul>
            @if($plan['complete'])<x-athar.button :href="route('app.projects.plan', $project)" block>افتح الخطة المجمّعة</x-athar.button>@if($assembly)<p class="small muted" style="margin:6px 0 0">جُمّعت {{ $assembly->assembled_at->format('Y/m/d H:i') }}</p>@endif
            @else<p class="small muted" style="margin:8px 0 0">بقي {{ $plan['total'] - $plan['saved'] }} أصلاً. ابدأ بأول حلقة ناقصة من القائمة.</p>@endif
        </div>
        <x-athar.card heading="h3" title="المختبر" desc="عشرة مولدات وأربع حاسبات ومحاكٍ، كلها تُعبَّأ من هذا الملف." :href="route('tools.index')" tag="افتح" />
    </aside>
</div></section>
@endsection
@push('scripts')
@include('partials.asset-actions-script')
@endpush
