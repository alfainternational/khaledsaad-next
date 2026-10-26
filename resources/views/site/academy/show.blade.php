@extends('layouts.platform')
@section('title',$course->title.' — أكاديمية أثر')
@section('content')
<section class="page-hero"><div class="shell"><span class="kicker">{{ $course->level }} · {{ $course->duration_minutes }} دقيقة</span><h1>{{ $course->title }}</h1><p>{{ $course->summary }}</p><a class="button" href="{{ auth()->check() ? route('dashboard') : route('register') }}">ابدأ المسار</a></div></section>
<section class="section"><div class="shell narrow"><div class="section-heading"><span class="kicker">محتوى المسار</span><h2>من الفكرة إلى مشروع تطبيقي</h2></div><ol class="lesson-list">@foreach($course->lessons as $lesson)<li><span>{{ str_pad($lesson->position,2,'0',STR_PAD_LEFT) }}</span><div><h3>{{ $lesson->title }}</h3><p>{{ $lesson->objective }}</p>@if(auth()->user()?->hasVerifiedEmail())<form method="POST" action="{{ route('app.lessons.complete', $lesson) }}">@csrf<input name="deliverable" placeholder="اكتب مخرجك أو ملاحظتك"><button class="button button-small" type="submit">إكمال الدرس</button></form>@endif</div><small>{{ $lesson->duration_minutes }} دقيقة @if($lesson->is_preview)· معاينة مجانية@endif</small></li>@endforeach</ol></div></section>
@endsection
