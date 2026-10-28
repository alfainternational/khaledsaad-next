@extends('layouts.platform')
@section('title', 'الأكاديمية — الإدارة — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell"><x-athar.kicker>الإدارة</x-athar.kicker><h1>البرامج والمسارات والدروس</h1>@include('admin.partials.nav')</div></section>
<section class="section-tight"><div class="shell stack" style="gap:24px">
    <x-athar.flash />
    <div class="grid grid-2">@foreach($programs as $program)<div class="card"><x-athar.kicker>برنامج</x-athar.kicker><h2 style="font-size:20px">{{ $program->title }}</h2><p class="muted small">{{ $program->summary }}</p><ol class="program-list">@foreach($program->courses as $c)<li><b>{{ $c->pivot->position }}</b><span>{{ $c->title }}</span>@if(! $c->is_published)<span class="soon">قريباً</span>@endif</li>@endforeach</ol></div>@endforeach</div>
    @foreach($courses as $course)
    <form method="post" action="{{ route('admin.academy.courses.update', $course) }}" class="card" id="course-{{ $course->id }}">@csrf @method('PATCH')
        <div class="split"><div><span class="card-num">{{ str_pad($course->position, 2, '0', STR_PAD_LEFT) }}</span><h2 style="font-size:20px">{{ $course->title }}</h2><p class="muted small">{{ $course->lessons->count() }} دروس · <x-athar.level :level="$course->level" /></p></div><label class="row small"><input type="checkbox" name="is_published" value="1" @checked($course->is_published)> منشور</label></div>
        <div class="grid grid-2"><div class="field"><label for="outcome-{{ $course->id }}">ما ستخرج به</label><input id="outcome-{{ $course->id }}" name="outcome" value="{{ $course->outcome }}" maxlength="200"></div><div class="field"><label for="summary-{{ $course->id }}">الملخص</label><input id="summary-{{ $course->id }}" name="summary" value="{{ $course->summary }}" maxlength="600"></div></div>
        @if($course->lessons->isNotEmpty())
        <x-athar.table caption="دروس المسار"><thead><tr><th>الترتيب</th><th>الدرس</th><th>الأجزاء المكتملة</th><th>الحالة</th><th>إشارات</th><th></th></tr></thead><tbody>
            @foreach($course->lessons as $lesson)@php($parts = $lesson->partCompleteness())
            <tr><td><input class="input" style="width:72px;min-height:40px" type="number" name="positions[{{ $lesson->id }}]" value="{{ $lesson->position }}" min="1" max="99" aria-label="ترتيب {{ $lesson->title }}"></td><td>{{ $lesson->title }}</td><td>{{ count(array_filter($parts)) }}/{{ count($parts) }}</td><td>{{ $lesson->isPublished() ? 'منشور' : 'مسودة' }}</td><td>{{ isset($openSignals[$lesson->id]) ? $openSignals[$lesson->id]->count() : 0 }}</td><td><a class="btn btn-secondary" href="{{ route('admin.academy.lessons.edit', $lesson) }}">الاستوديو</a></td></tr>
            @endforeach
        </tbody></x-athar.table>
        @else<p class="muted small">لا دروس بعد. تُكتب من الاستوديو بعد إنشائها (المرحلة 4: بقية 11 مساراً).</p>@endif
        <div class="btn-row"><x-athar.button type="submit" variant="secondary">حفظ الترتيب والبيانات</x-athar.button></div>
    </form>
    @endforeach
</div></section>
@endsection
