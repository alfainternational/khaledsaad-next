# نشر منصة أثر على المضيف الفرعي

## المتطلبات

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
