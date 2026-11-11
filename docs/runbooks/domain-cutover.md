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
- **المسودة لا تُنتج 404.** التحويل القديم يُحسم وقت الطلب لا وقت الاستيراد: إن كانت المقالة منشورة فهو 301 إليها كما هو مسجّل، وإن لم تُراجَع بعد فهو **302 مؤقت** إلى قسمها (`/articles`، `/news`…). السبب أن 301 يُخزَّن للأبد، فلو وجّهناه للفهرس اليوم لظل يوجّه إليه بعد نشر المقالة. السجل في جدول `redirects` لا يتغيّر، فيعود 301 من تلقائه يوم النشر بلا هجرة بيانات. المنطق في `app/Domain/Content/Services/LegacyRedirects.php`.
- `platform:doctor --env=production` يطبع `[WARN] legacy redirects reach their article (N waiting on review)` طالما بقيت مقالات بانتظار المراجعة — تحذير لا يمنع الانتقال، لكنه يعني أن N قارئاً يصل الفهرس لا ما نقر عليه.
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

> **الخطوات 1 و3 و4 و6 و7 صارت سكربتاً واحداً**: [`deploy/cpanel-cutover.sh`](../../deploy/cpanel-cutover.sh). يُشغَّل من داخل النسخة لا عبر curl — وجوده في النسخة هو نفسه إثبات أن النشر سبق الانتقال.
>
> ```bash
> bash ~/repositories/khaledsaad-next/deploy/cpanel-cutover.sh
> ```
>
> يرفض الاستمرار إن كانت النسخة غير نظيفة أو غير متزامنة مع `origin`، أو إن كان الكود المنشور لا يحسم التحويلات وقت الطلب (يسأل `platform:doctor` نفسه، لا ملفاً)، أو إن كان هناك `[FAIL]`، أو إن وُجد `public_html_old` من محاولة سابقة — فلا يكتب فوق نسخة الموقع الحيّ. ثم ينسخ احتياطياً، يبدّل الجذر، **ويتحقق أن الدومين يجيب 200 — فإن لم يجب أعاد نفسه فوراً** بدل أن يترك الدومين مكسوراً. والتشغيل الثاني لا يفعل شيئاً.
>
> ما يبقى بيد الإنسان: webhook الدفع، وSearch Console، والخطوة 8.

1. **نسخة احتياطية** للموقع القديم وقاعدته: يأخذها السكربت (`~/cutover-backups/`: أرشيف الجذر، وdump لقاعدة الموقع القديم، ونسخة من `.env`) ويتوقف إن فشل أيّ منها — فـ«أخذتُ نسخة» لا يجب أن تكون جملة تُقال بلا فعل. إن لم يكن `mysqldump` متاحاً: نسخة JetBackup ثم `--skip-db-backup`. واختبر الاستعادة في مجلد منفصل.
2. **TTL**: إن كان DNS سيبقى على نفس الخادم (الحالة الآن) فلا تغيير DNS أصلاً.
3. **تبديل الجذر**:
   ```bash
   cd ~ && mv public_html public_html_old && ln -s repositories/khaledsaad-next/public public_html
   ```
   إن رفض الخادم الرابط الرمزي (خطأ 403/404): `mkdir public_html && cp -a repositories/khaledsaad-next/public/. public_html/` ثم تعديل `public_html/index.php` ليشير إلى `../repositories/khaledsaad-next/vendor/autoload.php` و`bootstrap/app.php`، وتكرار النسخ بعد كل نشر (السكربت سيتكفل بذلك عند اعتماد هذا المسار).
4. **ملف البيئة**: `APP_URL=https://khaledsaad.net`، `PLATFORM_PUBLIC_HOST=khaledsaad.net`، `SESSION_DOMAIN=khaledsaad.net`، ثم `php artisan optimize:clear && php artisan optimize`.
5. **PayPal**: تحديث عنوان الـ webhook إلى `https://khaledsaad.net/webhooks/paypal` وتحديث `PAYPAL_WEBHOOK_ID` إن تغيّر.
6. **cron**: المسار لا يتغير (`~/repositories/khaledsaad-next`).
7. **فحص فوري**: `/`، `/sitemap.xml`، `/blog` (يجب أن يحوّل)، تسجيل دخول، تشغيل أداة. وأحد روابط المدونة القديمة: `curl -I https://khaledsaad.net/blog/<slug>` — الجواب 301 إلى المقالة إن كانت منشورة، أو 302 إلى قسمها إن لم تُراجَع بعد. **أي 404 هنا خلل**، لا تأجيل مراجعة.
8. بعد يومين من الاستقرار: تحويل `next.khaledsaad.net` بالكامل إلى الدومين الأساسي (Domains → Redirects، 301). ولا تفعلها يوم الانتقال: `next` هو طريق الرجوع الوحيد الذي لا يمس الدومين الأساسي.
   - **وما يُكسر في هذه الخطوة تحديداً**: الروابط الموقّعة (رمز QR من بانِي UTM، روابط التحقق من البريد) التي صُنعت أثناء فترة التجربة تحمل توقيعاً محسوباً على اسم `next`. والتحويل 301 إلى اسم آخر يُبطل التوقيع، فتجيب 403 لا 404 — أي أنها تفشل بصمت بالنسبة لمن طبع الرمز. أعِد توليد أي رمز QR طُبع قبل الانتقال.

## المرحلة 3 — بعد الانتقال (72 ساعة)
- مراقبة 404/5xx من سجلات cPanel (Metrics → Errors) ومن Search Console (Pages).
- إرسال خريطة الموقع الجديدة في Search Console وBing، ومراقبة الفهرسة.
- التأكد من وصول webhooks الدفع، وأن `platform:doctor` يمرّ.

## الرجوع
```bash
bash ~/repositories/khaledsaad-next/deploy/cpanel-cutover.sh --rollback
```
دقيقة واحدة، ولا يمس قاعدة المنصة الجديدة — فالرجوع لا يفقد بياناً في أي من الاتجاهين.

**ويعيد النصفين معاً**: الجذر، و**ملف البيئة**. الصيغة الأولى في هذا الدليل كانت تعيد الجذر وحده (`mv public_html_old public_html`)، وهذا يترك المنصة تُعرّف نفسها بـ`khaledsaad.net` بينما ذلك الدومين عاد يخدم الموقع القديم: فكل رابط وكل بريد وكل خريطة موقع يولّدها مضيف التجربة تشير إلى الموقع الذي رجعنا عنه قبل قليل. نصف الرجوع حالة أسوأ من التي يُصلحها، لأنه يبدو منتهياً.
