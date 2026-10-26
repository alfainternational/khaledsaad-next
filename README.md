# أثر — منصة التسويق والذكاء الاصطناعي

تطبيق Laravel 13 مستقل يعمل أولاً على `next.khaledsaad.net` ثم ينتقل إلى `khaledsaad.net` بعد اجتياز بوابة الإطلاق.

## ما يتضمنه

- مجلة عربية بمقالات أصلية وتحليلات مترجمة موثقة بالمصادر.
- أكاديمية تطبيقية ودروس ومخرجات مرتبطة بالمشاريع.
- مختبر أدوات تسويقية مع تشغيل حتمي وبنية جاهزة لمزودي AI.
- حسابات ومساحات عمل مع عزل المشاريع والصلاحيات.
- دفتر رصيد append-only، اشتراكات وأحداث دفع idempotent في نموذج البيانات.
- لوحة إدارة للنشر وسجل نسخ المحتوى.
- Sitemap وRSS وcanonical وJSON-LD و`llms.txt`.
- فحص جاهزية نشر عبر `php artisan platform:doctor`.

## التشغيل المحلي

```powershell
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm install
npm run build
php artisan serve
```

افتح `http://127.0.0.1:8000`. الحسابات الجديدة تحصل تلقائياً على مساحة عمل ومشروع أول و20 رصيداً.

## التحقق

```powershell
composer audit
npm audit
npm run build
php artisan optimize:clear
php artisan migrate:fresh --seed --env=testing
php artisan test
php artisan platform:doctor --env=testing
```

## الإنتاج

استخدم PostgreSQL وRedis وS3-compatible storage، وشغّل queue worker وscheduler. تفاصيل النشر والعودة موجودة في `docs/runbooks/deploy.md` و`docs/runbooks/domain-cutover.md`.
