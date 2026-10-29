# نشر منصة أثر على المضيف الفرعي

## تحديث المرحلة الرابعة — 2026-10-29

بعد رفع الإصدار للفرع المعتمد، شغّل `bash ~/bootstrap.sh` من Terminal في cPanel. يستورد السكربت `CompleteAcademySeeder` مرة واحدة بعلامة `.seeded-stage4`، ويضيف 66 درساً إلى المسارات الأحد عشر. لا يبدّل معرفات الدروس والأسئلة الموجودة أو تقدم المتعلمين وتعديلات المحرر. البذر الأول الجديد يضع علامات المراحل الأربع معاً لمنع إعادة بذر المرحلة الأولى فوق المحتوى المكتمل.

تحقق بعد النشر من 12 مساراً منشوراً و72 درساً، ومن `/resources` والتصفية والمعاينة والتنزيل والحفظ، و`/pricing` و`/contact` وصفحات الدخول في المظهرين. اختبر شهادة حقيقية من رابطها في `/app/progress`؛ الشهادات التجريبية توجد في قاعدة اختبار المتصفح فقط. تنزيل PDF يفتح نسخة الطباعة العربية، ثم يختار المستخدم الحفظ PDF في المتصفح.

رسائل التواصل تدخل الطابور إلى `PLATFORM_SUPPORT_EMAIL` (راجع مفتاح الإعداد في `config/platform.php`) باستخدام إعداد البريد الموجود، فلا يُعد نجاح النموذج دليلاً على تسليم البريد؛ تحقق من وصول رسالة بعد النشر ومن عمل المجدول. دفعات Pro الجديدة تمنح شهراً ويظل التجديد يدوياً دون خصم تلقائي؛ الدفعات القديمة تحتفظ بسجلها. الشهادة تعتمد إكمال الدروس ونتيجة واقعية من المسار نفسه، ولا يُضاف حاجز دفع جديد إلى أهلية المتعلمين الحالية.

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
11. بعد المرحلة 2: افحص `/tools` (15 أداة بلا «قريباً»)، توليد زائر على `/tools/customer-persona?example=1`، حاسبة `/tools/calculators/cac`، المحاكي `/tools/simulator`، و`/admin/tools` بمعدل الحفظ.
12. بعد المرحلة 3: افحص الأقسام الخمسة `/articles` و`/news` و`/insights` و`/case-studies` و`/research` (10 مواد لكل قسم على الأقل)، مادة بالشكل الكامل مثل `/articles/natural-skincare-store-true-cac` (في دقيقة، الفهرس، المصادر، طبّق ما قرأت)، القاموس `/glossary` و`/glossary/cac` (40 مصطلحاً)، موضوعاً `/topics/measurement-analytics-cro`، مؤلفاً `/authors/khaled-saad`، البحث المجمّع `/search?q=تكلفة اكتساب` (في الإنتاج يعمل بـFULLTEXT على MySQL)، اشتراك النشرة من التذييل ثم وصول بريد التأكيد (الطابور يُفرَّغ من المجدول كل دقيقة)، ثم `/admin/content` (المواد والتصنيفات والوسوم والمؤلفون) و`/admin/sources` (البوابات الخمس، 14 مصدراً، زر «اجلب المصادر الآن»)، و`sitemap.xml` يضم الأقسام والقاموس والمواضيع.

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
- بذر المحتوى بالمراحل (مرة واحدة لكل مرحلة، بعلامة في `storage/app/`): `.seeded` (البذر الأساسي)، `.seeded-stage1` (الأكاديمية والتشخيص)، `.seeded-stage2` (المختبر: 15 أداة وأمثلتها عبر `LabSeeder`)، `.seeded-stage3` (المجلة: التصنيفات والوسوم والمؤلفون و14 مصدراً مراقباً و40 مصطلحاً و50 مادة من `database/content/` عبر `MagazineSeeder`). `LabSeeder` idempotent (updateOrCreate بالـslug) فيمكن إعادته يدوياً لتحديث القوالب: `php artisan db:seed --class=LabSeeder --force`، مع ملاحظة أنه يعيد قالب الأداة إلى نسخة الكود (التعديلات اليدوية من استوديو الأداة تُحفظ في `tool_versions` ولا تُمس). `MagazineSeeder` idempotent كذلك (`php artisan db:seed --class=MagazineSeeder --force`): يحدّث نص المواد المبذورة من ملفاتها ولا يخفض حالة مادة نشرها محرر، ويسند قسماً وتصنيفاً ومؤلفاً للمواد المستوردة القديمة بلا إعادة كتابتها.
- جلب المصادر العالمية: `php artisan platform:fetch-sources` (مجدول يومياً 05:30؛ لا يفشل عند انقطاع الشبكة بل يسجّل الخطأ على المصدر في `/admin/sources`). الملخص بالذكاء الاصطناعي يعمل فقط عند وجود `OPENAI_API_KEY`، وإلا يعمل بالقواعد.

### ما تعلمناه من أول نشر فعلي (2026-10-28)
- طابور مهام cPanel (الاستنساخ والنشر من واجهة Git) قد يعلق؛ البديل المعتمد هو `deploy/cpanel-bootstrap.sh` من Terminal.
- المجلدات التي ينشئها السكربت يجب أن تكون قابلة للعبور (711) وإلا يرد LiteSpeed بـ 404 حتى للملفات الثابتة.
- `APP_KEY` يُولَّد على الخادم بـ `key:generate --force`؛ مفتاح منسوخ من جهاز آخر أعطى 500 من Encrypter.
- خادم الويب يستخدم إصدار PHP من "Select PHP Version" على مستوى الحساب (8.5 حالياً) بغض النظر عن MultiPHP للدومين؛ الـ CLI في السكربت هو alt-php83.
- مستخدم القاعدة المعتمد: `kcepljda_my` (كلمة مروره في `~/public_html/.env` للموقع القديم) مع صلاحيات كاملة على `kcepljda_next`.
