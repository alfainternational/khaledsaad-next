<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>{{ $title }} — أثر</title>
<link rel="stylesheet" href="{{ asset('css/platform.css') }}">
<style nonce="{{ $cspNonce }}">body{background:#fff;color:#000;padding:24px}.sheet{max-width:820px;margin:0 auto}.sheet header{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #F26B2B;padding-bottom:10px;margin-bottom:20px}.sheet footer{margin-top:30px;border-top:1px solid #ddd;padding-top:10px;font-size:13px;color:#555}.toolbar{margin-bottom:20px}</style>
</head>
<body>
<div class="sheet">
<div class="toolbar no-print"><button class="btn btn-primary" type="button" id="print-btn">احفظ كـ PDF (طباعة)</button> <span class="muted small">اختر «حفظ كـ PDF» من نافذة الطباعة.</span></div>
<header><strong>أثر · تعلّم. طبّق. قِس الأثر.</strong><span>{{ now()->format('Y/m/d') }}</span></header>
<h1>{{ $title }}</h1>
<div class="asset-body">{!! \App\Support\Markdown::toHtml($markdown) !!}</div>
<footer>أُنشئ هذا الأصل على منصة أثر — {{ config('platform.public_host') }}</footer>
</div>
<script nonce="{{ $cspNonce }}">document.getElementById('print-btn').addEventListener('click',function(){window.print()});if(!/[?&]noprint/.test(location.search)){setTimeout(function(){try{window.print()}catch(e){}},400)}</script>
</body></html>
