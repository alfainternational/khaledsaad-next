@extends('layouts.platform')
@section('title','القوالب والموارد — أثر')
@section('description', 'قوالب تسويقية مجانية مرتبطة بدروس عملية: عاينها ونزّلها بصيغة Markdown أو PDF أو جدول، واحفظها في مشروعك.')
@section('canonical', route('resources'))
@section('content')
<section class="page-hero"><div class="shell">
<x-athar.breadcrumbs :items="[['الموارد', null]]" /><span class="kicker">من الفكرة إلى التطبيق</span><h1>قوالب تختصر البداية، لا التفكير</h1><p>اختر القالب الذي يناسب قرارك القادم. عاينه، نزّله مجانًا، ثم طبّقه على مشروعك.</p>
</div></section>
<section class="section-tight"><div class="shell">
<form class="resource-filters card" method="get" action="{{ route('resources') }}">
<div class="field"><label for="resource-topic">الموضوع</label><select id="resource-topic" name="topic"><option value="">كل المواضيع</option>@foreach($topics as $topic)<option value="{{ $topic->slug }}" @selected(($filters['topic'] ?? '') === $topic->slug)>{{ $topic->title }}</option>@endforeach</select></div>
<div class="field"><label for="resource-format">صيغة التنزيل</label><select id="resource-format" name="format"><option value="">كل الصيغ</option>@foreach(['markdown' => 'Markdown', 'pdf' => 'PDF', 'csv' => 'جدول CSV'] as $key => $label)<option value="{{ $key }}" @selected(($filters['format'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
<button class="btn btn-primary" type="submit">عرض القوالب</button><a class="btn btn-secondary" href="{{ route('resources') }}">كل القوالب</a>
</form><p class="muted small">{{ $resources->total() }} قالبًا · التنزيل متاح بلا حساب</p>
<div class="grid grid-3">@forelse($resources as $resource)<article class="card content-card">
<span class="tag">{{ $resource->lesson?->course?->topic ?? 'قالب عملي' }}</span><h2><a href="{{ route('resources.show', $resource) }}">{{ $resource->name }}</a></h2><p>{{ $resource->description }}</p><p class="small muted">استخدمه {{ $resource->lesson ? 'مع درس «'.$resource->lesson->title.'»' : 'عند تجهيز أصل تسويقي جديد' }}.</p>
<div class="actions"><a class="text-link" href="{{ route('resources.show', $resource) }}">معاينة القالب ←</a><a class="text-link" href="{{ route('resources.download', [$resource, 'format' => $filters['format'] ?? 'markdown']) }}">تنزيل</a></div>
</article>@empty<div class="card"><h2>لا توجد قوالب بهذه الفلاتر</h2><p>جرّب موضوعًا آخر أو اعرض المكتبة كاملة.</p><a class="btn btn-secondary" href="{{ route('resources') }}">عرض كل القوالب</a></div>@endforelse</div>
{{ $resources->links() }}</div></section>
@endsection
