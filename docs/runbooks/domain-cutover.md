# نقل المنصة من next.khaledsaad.net إلى khaledsaad.net

> الاستضافة الحالية cPanel مشتركة (LiteSpeed، CloudLinux). جذر الدومين الأساسي مثبّت على `~/public_html` ولا يمكن تغييره من cPanel، لذلك الانتقال يتم بتبديل `public_html` نفسه لا بتغيير الإعدادات.

## المرحلة 1 — ما قبل الانتقال (بينما next تعمل كبيئة تجربة)

### 1.1 المحتوى القديم وخريطة التحويلات
الموقع القديم فيه 47 رابطاً مفهرساً: 20 مقالة تحت `/blog/{slug عربي}`، 11 أداة تحت `/tools/{key}`، 3 قطاعات، و11 صفحة ثابتة.

```bash
# على الخادم، داخل مجلد المنصة (يُنفَّذ مرة، ويمكن تكراره بأمان)
cd ~/repositories/khaledsaad-next
/opt/alt/php83/usr/bin/php artisan platform:import-legacy --dry-run      # معاينة
/opt/alt/php83/usr/bin/php artisan platform:import-legacy                # استيراد كمسودات + تحويلات /blog/{slug} → /articles/{slug}
/opt/alt/php83/usr/bin/php artisan platform:import-redirects docs/cutover/redirects.csv   # الصفحات والأدوات والقطاعات
```

- المقالات تُستورد **مسودات** بنفس الـ slug العربي، وتُنشر من لوحة الإدارة بعد المراجعة. `--publish` ينشرها فوراً بتاريخها الأصلي.
- خريطة الصفحات في [`docs/cutover/redirects.csv`](../cutover/redirects.csv) قابلة للتعديل؛ القرارات الحالية: الصفحات التعريفية → `/about`، القطاعات → `/academy`، التسعير → `/register`، تطبيق أندرويد → 410، الأدوات القديمة → أقرب أداة جديدة أو فهرس الأدوات.
- التحويلات تعمل فوراً على next أيضاً، فيمكن اختبارها: `curl -I https://next.khaledsaad.net/blog`.

### 1.2 المفاتيح والتهيئة
- PayPal: تطبيق Sandbox أولاً واختبار دفعة كاملة، ثم Live مع `PAYPAL_MODE=live` وwebhook على الدومين النهائي.
- OpenAI: مفتاح بحد إنفاق، ثم اختبار أداة من المختبر.
- البريد: التأكد من SPF/DKIM للنطاق، واختبار بريد التحقق من حساب جديد.
- `php artisan platform:doctor --env=production` بلا أي `[FAIL]`.

### 1.3 اختبار القبول على next
تسجيل حساب، تأكيد البريد، تشغيل أداة وحفظ المخرج، إكمال درس، صفحة الفوترة، الجوال، وخريطة الموقع.

## المرحلة 2 — الانتقال (نافذة ساعة، خارج أوقات الذروة)

1. **نسخة احتياطية** للموقع القديم وقاعدته: `tar czf ~/backup-old-site-$(date +%F).tgz public_html` + نسخة JetBackup. اختبر الاستعادة في مجلد منفصل.
2. **TTL**: إن كان DNS سيبقى على نفس الخادم (الحالة الآن) فلا تغيير DNS أصلاً.
3. **تبديل الجذر**:
   ```bash
   cd ~ && mv public_html public_html_old && ln -s repositories/khaledsaad-next/public public_html
   ```
   إن رفض الخادم الرابط الرمزي (خطأ 403/404): `mkdir public_html && cp -a repositories/khaledsaad-next/public/. public_html/` ثم تعديل `public_html/index.php` ليشير إلى `../repositories/khaledsaad-next/vendor/autoload.php` و`bootstrap/app.php`، وتكرار النسخ بعد كل نشر (السكربت سيتكفل بذلك عند اعتماد هذا المسار).
4. **ملف البيئة**: `APP_URL=https://khaledsaad.net`، `PLATFORM_PUBLIC_HOST=khaledsaad.net`، `SESSION_DOMAIN=khaledsaad.net`، ثم `php artisan optimize:clear && php artisan optimize`.
5. **PayPal**: تحديث عنوان الـ webhook إلى `https://khaledsaad.net/webhooks/paypal` وتحديث `PAYPAL_WEBHOOK_ID` إن تغيّر.
6. **cron**: المسار لا يتغير (`~/repositories/khaledsaad-next`).
7. **فحص فوري**: `/`، `/sitemap.xml`، `/blog` (يجب أن يحوّل)، تسجيل دخول، تشغيل أداة.
8. بعد يومين من الاستقرار: تحويل `next.khaledsaad.net` بالكامل إلى الدومين الأساسي (Domains → Redirects، 301).

## المرحلة 3 — بعد الانتقال (72 ساعة)
- مراقبة 404/5xx من سجلات cPanel (Metrics → Errors) ومن Search Console (Pages).
- إرسال خريطة الموقع الجديدة في Search Console وBing، ومراقبة الفهرسة.
- التأكد من وصول webhooks الدفع، وأن `platform:doctor` يمرّ.

## الرجوع
```bash
cd ~ && rm public_html && mv public_html_old public_html && /opt/alt/php83/usr/bin/php ~/public_html/artisan optimize:clear
```
دقيقة واحدة، ولا يمس قاعدة المنصة الجديدة.
