@extends('layouts.platform')
@section('title','مختبر التسويق — أثر')
@section('content')
<section class="page-hero tools-hero"><div class="shell"><span class="kicker">مختبر التسويق</span><h1>من السؤال إلى مخرج قابل للاستخدام</h1><p>أدوات عملية للتخطيط والمحتوى والتحويل. النتائج تُحفظ بإصدارات داخل مشروعك.</p></div></section>
<section class="section"><div class="shell"><div class="cards three">@foreach($tools as $tool)<a class="content-card tool-card" href="{{ route('tools.show',$tool) }}"><span class="tag">{{ $tool->category }}</span><h2>{{ $tool->name }}</h2><p>{{ $tool->description }}</p><div class="card-meta"><span>{{ $tool->kind === 'ai' ? 'مدعوم بالذكاء الاصطناعي' : 'حتمي' }}</span><span>{{ $tool->credit_cost ? $tool->credit_cost.' رصيد' : 'مجاني' }}</span></div></a>@endforeach</div></div></section>
@endsection
