@extends('layouts.platform')
@section('title', $template->name.' — أثر')
@section('description', $template->description)
@section('content')
<section class="page-hero"><div class="shell"><x-athar.breadcrumbs :items="[['الموارد', route('resources')], [$template->name, null]]" /><span class="kicker">قالب مجاني</span><h1>{{ $template->name }}</h1><p>{{ $template->description }}</p></div></section>
<section class="section-tight"><div class="shell dash-grid">
<article class="card"><h2>معاينة القالب</h2><div class="asset-body">{!! \App\Support\Markdown::toHtml($markdown) !!}</div></article>
<aside class="stack"><div class="card"><h2>ابدأ بهذا القالب</h2><div class="stack">
<a class="btn btn-primary" href="{{ route('resources.download', $template) }}">تنزيل Markdown</a><a class="btn btn-secondary" href="{{ route('resources.download', [$template, 'format' => 'pdf']) }}">تنزيل PDF (طباعة)</a>
@if($template->template)<a class="btn btn-secondary" href="{{ route('resources.download', [$template, 'format' => 'csv']) }}">تنزيل جدول CSV</a>@endif
</div><p class="small muted">املأه بمعلومات مشروعك. لحفظ PDF اختر «حفظ كـ PDF» من نافذة الطباعة.</p></div>
@if($template->lesson && $template->lesson->isPublished() && $template->lesson->course?->is_published)<div class="card"><span class="kicker">متى وكيف تستخدمه؟</span><h3>{{ $template->lesson->title }}</h3><p>{{ $template->lesson->goal }}</p><a class="text-link" href="{{ route('academy.lesson', [$template->lesson->course, $template->lesson]) }}">طبّق مع الدرس ←</a></div>@endif
@if($template->tool?->is_published)<div class="card"><h3>الأداة المرتبطة</h3><a class="text-link" href="{{ \App\Support\Markdown::toolUrl($template->tool) }}">{{ $template->tool->name }} ←</a></div>@endif
<div class="card"><h3>احفظ في مشروعي</h3><x-athar.flash />
@if(auth()->check())
@if($projects->isNotEmpty())<form method="post" action="{{ route('resources.save', $template) }}">@csrf<div class="field"><label for="project_id">المشروع</label><select id="project_id" name="project_id" required>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select></div><button class="btn btn-primary" type="submit">احفظ في مشروعي</button></form>
@else<p>أنشئ مشروعًا أو اطلب صلاحية تحرير لحفظ القالب.</p><a class="btn btn-secondary" href="{{ route('dashboard') }}#projects">افتح لوحتي</a>@endif
@else<p>احتفظ بالقالب ضمن أصول مشروعك، وارجع إلى الدرس لتعبئته خطوة بخطوة.</p><a class="btn btn-primary" href="{{ route('register') }}">أنشئ حسابًا مجانيًا</a>@endif
</div></aside></div></section>
@endsection
