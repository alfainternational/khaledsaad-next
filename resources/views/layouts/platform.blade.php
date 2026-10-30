<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FFFDF8" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0B1623" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#FFFDF8" data-theme-color>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('platform.name').' — '.config('platform.tagline'))</title>
    <meta name="description" content="@yield('description', 'أثر: تعلّم التسويق والذكاء الاصطناعي بالممارسة. كل درس ينتهي بأصل لمشروعك أنت، وكل أصل يُقاس في الواقع.')">
    <meta property="og:locale" content="ar_AR">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="أثر">
    <meta property="og:title" content="@yield('title', config('platform.name').' — '.config('platform.tagline'))">
    <meta property="og:description" content="@yield('description', 'أثر: تعلّم التسويق والذكاء الاصطناعي بالممارسة. كل درس ينتهي بأصل لمشروعك أنت، وكل أصل يُقاس في الواقع.')">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta name="twitter:card" content="summary_large_image">
    @if(View::hasSection('noindex'))<meta name="robots" content="noindex,follow">@endif
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <link rel="alternate" hreflang="ar" href="@yield('canonical', url()->current())">
    <link rel="alternate" type="application/rss+xml" title="أحدث محتوى أثر" href="{{ route('feed') }}">
    @stack('before-styles')
    <script nonce="{{ $cspNonce }}">(function(){var t;try{t=localStorage.getItem('athar-theme');if(localStorage.getItem('athar-depth')==='fast'){document.documentElement.classList.add('fast')}}catch(e){}if(t!=='dark'&&t!=='light'){t=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}document.documentElement.setAttribute('data-theme',t);document.documentElement.classList.add('js')})();</script>
    <link rel="stylesheet" href="{{ asset('css/platform.css') }}?v={{ filemtime(public_path('css/platform.css')) }}">
    @stack('structured-data')
    @stack('head')
</head>
<body class="@yield('body_class') {{ !empty($compactLayout) ? 'compact-layout' : '' }}">
<a class="skip-link" href="#main">انتقل إلى المحتوى</a>
@if($viewer->previewAs())
<div class="preview-banner">تعرض المنصة الآن كـ «{{ $viewer->previewLabel() }}» (معاينة إدارية). <form method="post" action="{{ route('admin.preview', ['as' => 'off']) }}">@csrf<button type="submit">إنهاء المعاينة</button></form></div>
@endif
@if($viewer->impersonating())
<div class="preview-banner" data-impersonation>تعرض المنصة الآن كالمستخدم «{{ $viewer->user()->name }}» — <form method="post" action="{{ route('admin.impersonation.stop') }}">@csrf<button type="submit">إنهاء</button></form></div>
@endif
<header class="site-header"><div class="shell nav-wrap">
    <a class="brand" href="{{ route('home') }}" aria-label="أثر — الرئيسية"><span class="brand-mark">أ</span><span>أثر</span></a>
    <nav id="main-nav" class="main-nav" aria-label="التنقل الرئيسي" @if(!empty($compactLayout)) hidden @endif>
        <a href="{{ route('academy.index') }}" @if(request()->routeIs('academy.*')) aria-current="page" @endif>تعلّم</a>
        <a href="{{ route('tools.index') }}" @if(request()->routeIs('tools.*')) aria-current="page" @endif>المختبر</a>
        <a href="{{ route('articles.index') }}" @if(request()->routeIs('articles.*')) aria-current="page" @endif>المجلة</a>
        <a href="{{ route('resources') }}" @if(request()->routeIs('resources*')) aria-current="page" @endif>الموارد</a>
        <a href="{{ route('about') }}" @if(request()->routeIs('about')) aria-current="page" @endif>عن أثر</a>
        @if($viewer->isMember())<a class="btn btn-primary mobile-only" href="{{ route('dashboard') }}">لوحتي</a>@else<a class="btn btn-primary mobile-only" href="{{ route('register') }}">ابدأ مجاناً</a>@endif
    </nav>
    <div class="nav-actions">
        <button class="icon-btn search-open" type="button" aria-label="بحث" aria-controls="search-layer" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></button>
        <button class="icon-btn theme-toggle" type="button" aria-label="تبديل الوضع الداكن" aria-pressed="false"><svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg></button>
        @if($viewer->isMember())
            <a class="btn btn-secondary nav-cta" href="{{ route('dashboard') }}">لوحتي</a>
            <details class="account-menu"><summary aria-label="قائمة الحساب"><span class="initial" aria-hidden="true">{{ mb_substr($viewer->user()->name, 0, 1) }}</span><span class="name">{{ Str::limit($viewer->user()->name, 18) }} ▾</span></summary><div class="menu">
                @php($menuProject = \App\Domain\Workspace\Models\Project::query()->whereIn('workspace_id', $viewer->user()->workspaces()->pluck('workspaces.id'))->orderBy('id')->first())
                <a href="{{ route('dashboard') }}">لوحتي</a><a href="{{ route('app.progress') }}">تقدمي</a><a href="{{ route('app.outputs.index') }}">أصولي</a><a href="{{ $menuProject ? route('app.projects.show', $menuProject) : route('dashboard').'#projects' }}">مشروعي</a><a href="{{ route('profile.edit') }}">الحساب</a><a href="{{ route('app.billing') }}">الاشتراك والأرصدة</a>
                @if($viewer->isStaff())<a href="{{ route('admin.index') }}">غرفة التحكم</a>@endif
                <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">تسجيل الخروج</button></form>
            </div></details>
        @else
            <a class="btn btn-primary nav-cta" href="{{ route('register') }}">ابدأ مجاناً</a>
        @endif
        <button class="icon-btn nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav" aria-label="القائمة"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
    </div>
</div></header>
<div id="search-layer" class="search-layer" hidden><form action="{{ route('search') }}" role="search"><label class="sr-only" for="layer-q">ابحث في المنصة</label><input id="layer-q" class="input" name="q" placeholder="ابحث عن درس أو أداة أو مادة…"><button class="btn btn-primary" type="submit">بحث</button><button class="btn btn-secondary search-close" type="button">إغلاق</button></form></div>
<main id="main">@yield('content')</main>
@if(!empty($compactLayout))
<footer class="auth-footer"><a href="{{ route('home') }}">أثر · خالد سعد</a><a href="{{ route('contact') }}">تواصل معنا</a><span>© {{ date('Y') }} أثر</span></footer>
@else
<footer class="site-footer"><div class="shell"><div class="footer-grid">
    <div><a class="brand brand-light" href="{{ route('home') }}"><span class="brand-mark">أ</span><span>أثر</span></a><p>منصة تعلّم بالممارسة للتسويق والذكاء الاصطناعي: كل درس ينتهي بأصل لمشروعك، وكل أصل يُقاس في الواقع.</p><p><strong>تعلّم. طبّق. قِس الأثر.</strong></p></div>
    <div><h2>تعلّم</h2><a href="{{ route('academy.index') }}#program-owner">برنامج أصحاب المشاريع</a><a href="{{ route('academy.index') }}#program-marketer">برنامج المسوّقين</a><a href="{{ route('academy.index') }}">الأكاديمية</a><a href="{{ route('assess.start') }}">شخّص مشروعك</a></div>
    <div><h2>طبّق</h2><a href="{{ route('tools.index') }}">المختبر</a><a href="{{ route('resources') }}">الموارد والقوالب</a><a href="{{ route('glossary.index') }}">القاموس</a><a href="{{ route('articles.index') }}">المجلة</a><a href="{{ route('insights.index') }}">الرؤى العالمية</a><a href="{{ route('news.index') }}">الأخبار</a><a href="{{ route('case-studies.index') }}">دراسات الحالة</a><a href="{{ route('research.index') }}">الأبحاث</a></div>
    <div><h2>المنصة</h2><a href="{{ route('about') }}">عن أثر</a><a href="{{ route('pricing') }}">التسعير</a><a href="{{ route('contact') }}">الاتصال</a><a href="{{ route('privacy') }}">الخصوصية</a><a href="{{ route('terms') }}">الشروط</a><a href="{{ route('feed') }}">RSS</a><a href="{{ route('llms') }}">llms.txt</a></div>
</div>
<div class="newsletter-wrap" id="newsletter">
<form class="newsletter" action="{{ route('newsletter.store') }}" method="post" data-newsletter>@csrf<label class="sr-only" for="footer-email">البريد الإلكتروني للنشرة</label><input id="footer-email" name="email" type="email" placeholder="بريدك لنشرة أسبوعية: فكرة، تطبيق، أداة" autocomplete="email" required><input class="sr-only" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"><button class="btn btn-primary" type="submit">انضم</button></form>
<p class="newsletter-status" data-newsletter-status role="status" @if(! session('newsletter') && ! $errors->has('email')) hidden @endif>{{ session('newsletter') ?: $errors->first('email') }}</p>
</div>
<div class="footer-bottom"><span>© {{ date('Y') }} أثر — خالد سعد</span><span>محتوى عربي أصيل ومصادر عالمية مُراجعة.</span></div></div></footer>
@endif
<script nonce="{{ $cspNonce }}">
(function(){
  var d=document,root=d.documentElement;
  var t=d.querySelector('.nav-toggle'),n=d.querySelector('#main-nav');
  t&&t.addEventListener('click',function(){var o=t.getAttribute('aria-expanded')==='true';t.setAttribute('aria-expanded',String(!o));n.classList.toggle('is-open',!o)});
  var th=d.querySelector('.theme-toggle');
  function cur(){var a=root.getAttribute('data-theme');if(a)return a;return window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}
  var systemTheme=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)');
  var tc=d.querySelector('meta[data-theme-color]');
  function applyTheme(value){root.setAttribute('data-theme',value);if(th)th.setAttribute('aria-pressed',String(value==='dark'));if(tc)tc.setAttribute('content',value==='dark'?'#0B1623':'#FFFDF8')}
  function savedTheme(){try{return localStorage.getItem('athar-theme')}catch(e){return null}}
  if(th){applyTheme(cur());th.addEventListener('click',function(){var nx=cur()==='dark'?'light':'dark';applyTheme(nx);try{localStorage.setItem('athar-theme',nx)}catch(e){}})}
  if(systemTheme&&systemTheme.addEventListener)systemTheme.addEventListener('change',function(e){var saved=savedTheme();if(saved!=='dark'&&saved!=='light')applyTheme(e.matches?'dark':'light')});
  window.addEventListener('storage',function(e){if(e.key==='athar-theme')applyTheme(e.newValue==='dark'||e.newValue==='light'?e.newValue:systemTheme&&systemTheme.matches?'dark':'light')});
  var so=d.querySelector('.search-open'),sl=d.querySelector('#search-layer'),sc=d.querySelector('.search-close');
  function closeS(){sl.hidden=true;sl.classList.remove('is-open');so.setAttribute('aria-expanded','false')}
  so&&so.addEventListener('click',function(){sl.hidden=false;sl.classList.add('is-open');so.setAttribute('aria-expanded','true');sl.querySelector('input').focus()});
  sc&&sc.addEventListener('click',closeS);sl&&sl.addEventListener('click',function(e){if(e.target===sl)closeS()});
  d.addEventListener('keydown',function(e){if(e.key==='Escape'&&sl&&!sl.hidden)closeS()});
  var nl=d.querySelector('[data-newsletter]'),ns=d.querySelector('[data-newsletter-status]');
  var nlFallback=false;
  nl&&nl.addEventListener('submit',function(e){if(!window.fetch||nlFallback)return;e.preventDefault();var btn=nl.querySelector('button');btn.disabled=true;
    fetch(nl.action,{method:'POST',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},body:new FormData(nl)}).then(function(r){return r.json().then(function(j){return {ok:r.ok,j:j}})}).then(function(x){ns.hidden=false;ns.textContent=x.ok?x.j.message:(x.j.message||(x.j.errors&&x.j.errors.email&&x.j.errors.email[0])||'تعذر الاشتراك الآن.');ns.classList.toggle('is-error',!x.ok);if(x.ok)nl.reset()}).catch(function(){nlFallback=true;nl.submit()}).finally(function(){btn.disabled=false})});
  d.addEventListener('click',function(e){d.querySelectorAll('details.account-menu[open]').forEach(function(m){if(!m.contains(e.target))m.removeAttribute('open')})});
  window.atharEvent=function(name,meta){try{var b=new Blob([JSON.stringify({event:name,meta:meta||{},_token:d.querySelector('meta[name=csrf-token]').content})],{type:'application/json'});if(navigator.sendBeacon){navigator.sendBeacon('{{ route('events.store') }}',b)}else{fetch('{{ route('events.store') }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':d.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({event:name,meta:meta||{}}),keepalive:true})}}catch(e){}};
})();
</script>
@stack('scripts')
</body></html>
