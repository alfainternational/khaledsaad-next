@extends('layouts.platform')
@section('title', ($term !== '' ? 'نتائج البحث عن «'.$term.'»' : 'البحث').' — أثر')
@section('noindex', '1')
@section('content')
<section class="page-hero compact"><div class="shell">
    <x-athar.kicker>البحث</x-athar.kicker>
    <h1>{{ $term !== '' ? 'نتائج البحث' : 'ابحث في المنصة' }}</h1>
    <form class="search-form" action="{{ route('search') }}" role="search"><label class="sr-only" for="q">ابحث</label><input class="input" id="q" name="q" value="{{ $term }}" placeholder="درس، أداة، مادة، أو مصطلح…" autofocus><button class="btn btn-primary" type="submit">بحث</button></form>
    @if($term !== '')<p class="muted small" style="margin:10px 0 0" data-search-total="{{ $total }}">{{ $total > 0 ? $total.' نتيجة في '.count($groups).' مجموعات' : 'لا نتائج مطابقة لـ«'.$term.'»' }}</p>@endif
</div></section>
<section class="section-tight"><div class="shell">
@if($total > 0)
    <nav class="filters" aria-label="المجموعات">@foreach($groups as $key => $group)<a href="#group-{{ $key }}">{{ $labels[$key] }} ({{ $group->count() }})</a>@endforeach</nav>
    <div class="search-groups">
    @foreach($groups as $key => $group)
        <section class="search-group" id="group-{{ $key }}" data-group="{{ $key }}"><h2>{{ $labels[$key] }}</h2>
            <ul class="result-list">
            @foreach($group as $r)
                @if($key === 'courses')<li><a href="{{ route('academy.show', $r) }}"><span class="small muted">مسار · {{ $r->level }}</span><strong>{{ $r->title }}</strong><span class="small muted">{{ Str::limit($r->summary, 140) }}</span></a></li>
                @elseif($key === 'lessons')<li><a href="{{ route('academy.lesson', [$r->course, $r]) }}"><span class="small muted">درس · {{ $r->course->title }}</span><strong>{{ $r->title }}</strong><span class="small muted">{{ Str::limit($r->goal, 140) }}</span></a></li>
                @elseif($key === 'tools')<li><a href="{{ \App\Support\Markdown::toolUrl($r) }}"><span class="small muted">{{ $r->kindLabel() }} · {{ $r->category }}</span><strong>{{ $r->name }}</strong><span class="small muted">{{ Str::limit($r->description, 140) }}</span></a></li>
                @elseif($key === 'items')<li><a href="{{ route('articles.show', $r) }}"><span class="small muted">{{ $r->typeLabel() }} · {{ $r->authorName() }}</span><strong>{{ $r->title }}</strong><span class="small muted">{{ Str::limit($r->tldr ?: $r->excerpt, 140) }}</span></a></li>
                @else<li><a href="{{ route('glossary.show', $r) }}"><span class="small muted">مصطلح</span><strong>{{ $r->term }}</strong><span class="small muted">{{ Str::limit($r->definition, 140) }}</span></a></li>@endif
            @endforeach
            </ul>
        </section>
    @endforeach
    </div>
@else
    <div class="search-suggestions" data-suggestions>
        @if($term !== '')<div class="card"><h2 style="font-size:20px">لا نتائج مطابقة. جرّب كلمة أوسع أو ابدأ من هنا:</h2><p class="muted" style="margin:0">البحث يغطي العناوين والملخصات والمتن في المسارات والدروس والأدوات والمواد والمصطلحات.</p></div>@endif
        <div class="grid grid-3">
            <div class="card"><h2 style="font-size:18px">أحدث المواد</h2><ul class="plain-list">@foreach($suggestions['items'] as $i)<li><a href="{{ route('articles.show', $i) }}">{{ $i->title }}</a></li>@endforeach</ul></div>
            <div class="card"><h2 style="font-size:18px">أشهر المصطلحات</h2><ul class="plain-list">@foreach($suggestions['terms'] as $t)<li><a href="{{ route('glossary.show', $t) }}">{{ $t->term }}</a></li>@endforeach</ul>@if($suggestions['terms']->isEmpty())<p class="muted small">القاموس قيد الإعداد.</p>@endif</div>
            <div class="card"><h2 style="font-size:18px">المسارات</h2><ul class="plain-list">@foreach($suggestions['courses'] as $c)<li><a href="{{ route('academy.show', $c) }}">{{ $c->title }}</a></li>@endforeach</ul><a class="btn btn-secondary" href="{{ route('academy.index') }}">الأكاديمية</a></div>
        </div>
    </div>
@endif
</div></section>
@endsection
