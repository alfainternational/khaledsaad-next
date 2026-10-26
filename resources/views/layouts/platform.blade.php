<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#071b2d">
    <title>@yield('title', config('platform.name').' — '.config('platform.tagline'))</title>
    <meta name="description" content="@yield('description', 'منصة عربية للتسويق الرقمي والذكاء الاصطناعي: محتوى موثوق، تعليم تطبيقي وأدوات تحفظ نتائجك.')">
    <meta property="og:locale" content="ar_AR">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="أثر">
    <meta property="og:title" content="@yield('title', config('platform.name').' — '.config('platform.tagline'))">
    <meta property="og:description" content="@yield('description', 'منصة عربية للتسويق الرقمي والذكاء الاصطناعي: محتوى موثوق، تعليم تطبيقي وأدوات تحفظ نتائجك.')">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta name="twitter:card" content="summary_large_image">
    @if(View::hasSection('noindex'))<meta name="robots" content="noindex,follow">@endif
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <link rel="alternate" hreflang="ar" href="@yield('canonical', url()->current())">
    <link rel="alternate" type="application/rss+xml" title="أحدث محتوى أثر" href="{{ route('feed') }}">
    <link rel="stylesheet" href="{{ asset('css/platform.css') }}">
    @stack('structured-data')
</head>
<body>
<a class="skip-link" href="#main">انتقل إلى المحتوى</a>
<header class="site-header"><div class="shell nav-wrap">
    <a class="brand" href="{{ route('home') }}" aria-label="أثر — الرئيسية"><span class="brand-mark">أ</span><span>أثر</span></a>
    <button class="nav-toggle" aria-expanded="false" aria-controls="main-nav">القائمة</button>
    <nav id="main-nav" class="main-nav" aria-label="التنقل الرئيسي"><a href="{{ route('academy.index') }}">تعلّم</a><a href="{{ route('articles.index') }}">المقالات</a><a href="{{ route('insights.index') }}">الرؤى العالمية</a><a href="{{ route('tools.index') }}">الأدوات</a><a href="{{ route('resources') }}">الموارد</a><a href="{{ route('about') }}">عن خالد</a></nav>
    <div class="nav-actions">@auth<a href="{{ route('dashboard') }}">مساحة العمل</a>@else<a href="{{ route('login') }}">دخول</a><a class="button button-small" href="{{ route('register') }}">ابدأ مجاناً</a>@endauth</div>
</div></header>
<main id="main">@yield('content')</main>
<footer class="site-footer"><div class="shell footer-grid">
    <div><a class="brand brand-light" href="{{ route('home') }}"><span class="brand-mark">أ</span><span>أثر</span></a><p>معرفة تسويقية تتحول إلى قرارات ومخرجات قابلة للقياس.</p></div>
    <div><h2>استكشف</h2><a href="{{ route('articles.index') }}">المجلة</a><a href="{{ route('academy.index') }}">الأكاديمية</a><a href="{{ route('tools.index') }}">مختبر التسويق</a></div>
    <div><h2>المنهجية</h2><a href="{{ route('about') }}">عن المنصة</a><a href="{{ route('feed') }}">RSS</a><a href="/llms.txt">llms.txt</a></div>
    <div><h2>النشرة العملية</h2><p>خلاصة أسبوعية بلا ضجيج: فكرة، تطبيق، وأداة.</p><form class="newsletter"><label class="sr-only" for="footer-email">البريد الإلكتروني</label><input id="footer-email" type="email" placeholder="name@example.com"><button type="button">انضم</button></form></div>
</div><div class="shell footer-bottom"><span>© {{ date('Y') }} أثر</span><span>محتوى عربي أصيل، ومصادر عالمية مُراجعة.</span></div></footer>
<script nonce="{{ $cspNonce }}">const t=document.querySelector('.nav-toggle'),n=document.querySelector('#main-nav');t?.addEventListener('click',()=>{const o=t.getAttribute('aria-expanded')==='true';t.setAttribute('aria-expanded',String(!o));n.classList.toggle('is-open',!o)});</script>
</body></html>
