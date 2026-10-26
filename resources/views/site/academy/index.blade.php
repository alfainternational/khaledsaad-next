@extends('layouts.platform')
@section('title','الأكاديمية — أثر')
@section('content')
<section class="page-hero academy-hero"><div class="shell"><span class="kicker">الأكاديمية</span><h1>مسارات تعليمية تطبيقية</h1><p>كل درس ينتهي بقرار أو تمرين أو مخرج محفوظ، لا بمجرد علامة مشاهدة.</p></div></section>
<section class="section"><div class="shell"><div class="cards three">@foreach($courses as $course)<a class="content-card course-card" href="{{ route('academy.show',$course) }}"><span class="tag">{{ $course->level }}</span><h2>{{ $course->title }}</h2><p>{{ $course->summary }}</p><div class="card-meta"><span>{{ $course->lessons_count }} دروس</span><span>{{ $course->duration_minutes }} دقيقة</span></div></a>@endforeach</div></div></section>
@endsection
