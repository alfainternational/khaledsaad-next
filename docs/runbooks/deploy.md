# نشر منصة أثر على المضيف الفرعي

## اكتمال المرحلة الرابعة — 2026-10-30

ما أُضيف فوق إصدار 2026-10-29: (1) إصدار الشهادة عبر حدث `CourseCompleted` ومستمع `IssueCertificate`، وبوابة الخطة الاحترافية (اشتراك نشط لم ينتهِ على مساحة المتعلم، وهي البوابة نفسها التي تمنح الأرصدة): المسار المكتمل بنتيجة واقعية في الخطة المجانية يظهر في «تقدمي» كـ«شهادة مستحقة» مع رابط الفوترة، وتُصدر الشهادة فور تفعيل الاشتراك عبر حدث `SubscriptionActivated` بعد تسوية الدفعة. (2) صفحة الشهادة قابلة للطباعة (زر «طباعة / حفظ PDF») مع Schema `EducationalOccupationalCredential`. (3) كل رسالة `/contact` تُسجَّل في جدول `contact_messages` (هجرة جديدة) وتظهر في `/admin/contact-messages` بحالة جديدة/عولجت/سخام. (4) قواعد التوجيه تغطي المسارات الـ12 عبر `StageFourCompletionSeeder` بعلامة `.seeded-stage4-completion` (updateOrCreate، لا تمس الأسئلة). (5) شاشات الإدارة القياسية: `/admin/users` (الأدوار + «اعرض كهذا المستخدم» مع حارس يمنع الشراء وتغيير الاعتمادات أثناء المعاينة)، `/admin/plans`، `/admin/redirects` (+ استيراد CSV)، `/admin/audit`، و`/admin/operations` على قالب المنصة. (6) الوضع الداكن على صفحات الأخطاء والحساب والفوترة والإدارة، و410 تعرض الصفحة المصممة.

بعد `bash ~/bootstrap.sh`: الهجرة تنشئ `contact_messages`، والسكربت يبذر قواعد التوجيه مرة واحدة. افحص: `/academy` (12 مساراً)، درساً من مسار جديد مثل `/academy/seo-content-marketing/seo-query-intent-map` (الأجزاء الثمانية + القرار الافتتاحي + بطاقة «الأداة»)، `/resources` وتنزيل قالب، `/about` و`/pricing` (صف الشهادات للاحترافي) و`/contact` ثم `/admin/contact-messages`، `/certificates/<رمز غير صالح>` (404 بالتصميم)، صفحة غير موجودة (404 بالبحث وثلاثة روابط بالوضعين)، تحويل 410 من `/admin/redirects`، `/app/progress` لعضو أكمل مساراً (شهادة أو «شهادة مستحقة» حسب الخطة)، وشاشات الإدارة الأربع الجديدة بالوضع الداكن. لا تُعاد تسمية أو حذف أي علامة بذر قديمة.

## تحديث المرحلة الرابعة — 2026-10-29

فحص ما بعد النشر كشف تحويلين مستوردين من الموقع القديم: `/pricing` إلى `/register` و`/profile` إلى `/about`. صفحات الأسعار والحساب الحالية لها الأولوية على هذين التحويلين، مع بقاء تحويلات الروابط القديمة الأخرى فعالة. اختبارات الاستيراد تشمل الآن الوصول للأسعار، وحماية ملف الحساب للزائر وفتحه للعضو. تحديث الإصلاح يحتاج تشغيل bootstrap مرة أخرى، بلا إعادة بذر محتوى المرحلة الرابعة.

دليل الفحص الحي: الصحة والقاعدة والمجدول والعامل سليمة؛ 12 صفحة مسار و72 درساً تستجيب 200 وتضم الأجزاء الثمانية مع القرار الافتتاحي؛ المكتبة تضم 74 قالباً، وتصفية الجمهور تعيد ستة قوالب، وتم التحقق من Markdown وCSV وصفحة طباعة PDF. فُحصت عينات فعلية على المكتب والجوال في المظهرين بلا تجاوز أفقي أو أخطاء كونسول، ونجح توليد أصل زائر من درس جديد. التقدم والفوترة يعملان في الجلسة المسجلة؛ الحساب والأسعار ينتظران نشر الإصلاح. فحص 101 رابط عزل التعارضين فقط. اختبارات الإصلاح: 18 ناجحة / 198 تأكيداً مع Pint. إرسال البريد وإصدار شهادة حقيقية لم يُنفّذا على الإنتاج.

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
13. بعد المرحلة 4: افحص `/academy` (12 مساراً و72 درساً)، درساً من مسار جديد بالأجزاء الثمانية وبطاقة الأداة، `/resources` و`/resources/value-card-template` (معاينة وتنزيل Markdown/PDF/CSV)، `/about` و`/pricing` و`/contact` (ثم الرسالة في `/admin/contact-messages`)، `/privacy` و`/terms`، صفحة 404 و`/certificates/<رمز غير صالح>`، تبديل الوضع الداكن على صفحة عامة وصفحة عضو وصفحة إدارة، `/app/progress` لعضو أكمل مساراً (شهادة قابلة للطباعة بتحقق عام في الاحترافي)، و`/admin/users` و`/admin/plans` و`/admin/redirects` و`/admin/audit` و`/admin/operations`.
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
