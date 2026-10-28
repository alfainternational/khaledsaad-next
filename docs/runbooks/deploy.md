# نشر منصة أثر على المضيف الفرعي

## المتطلبات

> ما يُطلب من المالك قبل البدء (وصول، مفاتيح، قرارات) موثّق في [launch-requirements.md](launch-requirements.md)، وقالب البيئة في `.env.production.example`.

- PHP 8.3 مع extensions المطلوبة من Composer.
- PostgreSQL وRedis وNginx وSupervisor.
- مستخدم نشر محدود الصلاحيات ومجلد `/var/www/khaledsaad-next`.
- أسرار مستقلة عن الموقع القديم: قاعدة البيانات، Redis، البريد، التخزين، الدفع وAI.

## خطوات الإصدار

1. أنشئ release جديداً وانسخ المصدر إليه.
2. شغّل `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`.
3. شغّل `npm ci && npm run build` في بيئة البناء.
4. اربط `.env` و`storage` المشتركة الآمنة بالمجلد الجديد.
5. شغّل `php artisan platform:doctor` ويجب أن ينتهي بكود 0.
6. شغّل `php artisan migrate --force` بعد نسخة احتياطية ناجحة.
7. شغّل `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
8. بدّل symlink `current` إلى الإصدار الجديد.
9. شغّل `php artisan queue:restart` وأعد تحميل PHP-FPM.
10. افحص `/up` و`/` و`/sitemap.xml` وتسجيل الدخول وتشغيل الأداة المجانية.

## الرجوع

أعد symlink إلى الإصدار السابق، أعد تشغيل workers وPHP-FPM، ثم نفّذ rollback لقاعدة البيانات فقط إذا كانت migration غير متوافقة وكان rollback مجرباً على نسخة مستعادة. لا تستخدم `migrate:rollback` عشوائياً على بيانات الإنتاج.

## النشر الفعلي على الاستضافة الحالية (cPanel، 2026-10-28)

ما نُفّذ من cPanel:
- الدومين الفرعي `next.khaledsaad.net` بجذر `/home/kcepljda/repositories/khaledsaad-next/public`، وDNS يستجيب.
- قاعدة `kcepljda_next` مع منح المستخدم الحالي `kcepljda_new` كل الصلاحيات (بيانات الاتصال نفسها المستخدمة في الموقع القديم).
- PHP 8.3 (alt-php83) للدومين الفرعي.
- cron للمجدول كل دقيقة: `cd /home/kcepljda/repositories/khaledsaad-next && /opt/alt/php83/usr/bin/php artisan schedule:run`.
- المستودع على GitHub: `alfainternational/khaledsaad-next` (الفرع `codex/independent-platform`).

ما يُنفَّذ يدوياً من cPanel عند كل إصدار:
1. **Git™ Version Control → Create**: Clone URL `https://github.com/alfainternational/khaledsaad-next.git`، المسار `repositories/khaledsaad-next`. (مرة واحدة)
2. **File Manager**: رفع ملف `khaledsaad-next.env` (نسخة الإنتاج من `.env`، غير مُتتبّع في git) إلى `/home/kcepljda/`. (مرة واحدة؛ تُنسخ إلى `.env` تلقائياً عند أول نشر)
3. **Git™ Version Control → Manage → Pull or Deploy → Deploy HEAD Commit**: يشغّل مهام `.cpanel.yml` (composer install، migrate، seed أول مرة، optimize).
4. للتحديثات اللاحقة: **Update from Remote** ثم **Deploy HEAD Commit**.

ملاحظات:
- المستودع يجب أن يكون متاحاً للقراءة لحظة الاستنساخ والتحديث؛ البديل الدائم: مفتاح SSH من cPanel → SSH Access يُضاف كـ Deploy Key في GitHub.
- بلا Redis: الجلسات والكاش والطوابير على قاعدة البيانات، والطابور يُفرَّغ من المجدول كل دقيقة (`queue:work --stop-when-empty`).
- فحص الجاهزية بعد النشر: `php artisan platform:doctor --env=production` من Terminal.

### ما تعلمناه من أول نشر فعلي (2026-10-28)
- طابور مهام cPanel (الاستنساخ والنشر من واجهة Git) قد يعلق؛ البديل المعتمد هو `deploy/cpanel-bootstrap.sh` من Terminal.
- المجلدات التي ينشئها السكربت يجب أن تكون قابلة للعبور (711) وإلا يرد LiteSpeed بـ 404 حتى للملفات الثابتة.
- `APP_KEY` يُولَّد على الخادم بـ `key:generate --force`؛ مفتاح منسوخ من جهاز آخر أعطى 500 من Encrypter.
- خادم الويب يستخدم إصدار PHP من "Select PHP Version" على مستوى الحساب (8.5 حالياً) بغض النظر عن MultiPHP للدومين؛ الـ CLI في السكربت هو alt-php83.
- مستخدم القاعدة المعتمد: `kcepljda_my` (كلمة مروره في `~/public_html/.env` للموقع القديم) مع صلاحيات كاملة على `kcepljda_next`.
