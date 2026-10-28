# متطلبات إطلاق next.khaledsaad.net — ما يُطلب من المالك

هذه القائمة هي كل ما يحتاجه فريق النشر من المالك قبل تنفيذ [deploy.md](deploy.md).
القالب الجاهز للتعبئة: [`.env.production.example`](../../.env.production.example).
معيار الاكتمال: `php artisan platform:doctor --env=production` ينتهي بكل الأسطر `[PASS]`.

## 1. الوصول المطلوب

| # | الوصول | الغرض | الشكل المطلوب |
|---|--------|-------|----------------|
| 1 | خادم Linux (Ubuntu 22.04+ أو ما يماثله) | استضافة المنصة | SSH بمفتاح عام لمستخدم نشر غير root مع sudo محدود، أو تثبيت الخادم من المالك مباشرة |
| 2 | إدارة DNS لنطاق khaledsaad.net | توجيه النطاق الفرعي | سجل `A` (و`AAAA` إن وُجد IPv6) لـ `next` نحو عنوان الخادم. إن كان النطاق خلف Cloudflare: اذكر ذلك لأن ذلك يتطلب ضبط البروكسي الموثوق |
| 3 | شهادة TLS | HTTPS إجباري | تُصدر تلقائياً عبر Let's Encrypt (certbot) من الخادم بعد توجيه DNS، لا يُطلب شيء إضافي إن كان المنفذ 80/443 مفتوحاً |
| 4 | PostgreSQL 15+ (أو MySQL 8) | قاعدة بيانات الإنتاج | اسم القاعدة والمستخدم وكلمة المرور، على الخادم نفسه أو خدمة مُدارة |
| 5 | Redis 7+ | الجلسات والكاش والطوابير وفحوصات heartbeat | كلمة مرور Redis؛ على الخادم نفسه يكفي |
| 6 | مسار نسخ احتياطي | فحص `recent backup` | مجلد `/var/backups/khaledsaad-next` وسكربت يومي ينسخ القاعدة ويلمس ملف `last-success` |

## 2. المفاتيح والأسرار

| # | المفتاح في `.env` | من أين | ملاحظات |
|---|-------------------|--------|---------|
| 1 | `DB_USERNAME` / `DB_PASSWORD` | إنشاء على الخادم | مستخدم مخصص للمنصة فقط |
| 2 | `REDIS_PASSWORD` | إنشاء على الخادم | `requirepass` في إعداد Redis |
| 3 | `MAIL_HOST` / `MAIL_USERNAME` / `MAIL_PASSWORD` | مزوّد بريد معاملات: Postmark أو Resend أو SES أو SMTP الاستضافة | يجب تفعيل SPF وDKIM للنطاق خلال الإعداد، وإلا تذهب رسائل التحقق إلى spam |
| 4 | `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_BUCKET` / `AWS_ENDPOINT` | Cloudflare R2 (أرخص) أو AWS S3 أو DigitalOcean Spaces | bucket خاص باسم `khaledsaad-next`، صلاحيات قراءة وكتابة فقط عليه |
| 5 | `OPENAI_API_KEY` | platform.openai.com → API keys | مفتاح مخصص للمشروع مع حد إنفاق شهري |
| 6 | `PAYMENT_WEBHOOK_SECRET` / `PAYMENT_PRO_PRICE_ID` | لوحة مزوّد الدفع | يلزم أولاً **اختيار المزوّد** (قرار معلّق، انظر القسم 4) ثم إنشاء منتج «الخطة الاحترافية» بسعر 49 ر.س وربط webhook على `https://next.khaledsaad.net/webhooks/payments` |

لا يُطلب من المالك: `APP_KEY` (يُولَّد على الخادم)، ومفاتيح Slack/Postmark/Resend الافتراضية في Laravel ما لم يُختر أحدها للبريد.

## 3. ما يُنفَّذ على الخادم بعد استلام ما سبق

```bash
# طابور العمل (Supervisor)
[program:khaledsaad-next-worker]
command=php /var/www/khaledsaad-next/current/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
user=deploy
autostart=true
autorestart=true
numprocs=2
```

```bash
# المجدول (cron لمستخدم النشر)
* * * * * cd /var/www/khaledsaad-next/current && php artisan schedule:run >> /dev/null 2>&1
```

```bash
# النسخ الاحتياطي اليومي (مثال PostgreSQL)
0 3 * * * pg_dump khaledsaad_next | gzip > /var/backups/khaledsaad-next/db-$(date +\%F).sql.gz && touch /var/backups/khaledsaad-next/last-success
```

المجدول يسجّل نبضتين كل دقيقة (`scheduler_heartbeat` و`worker_heartbeat`) ويشغّل `platform:recover-runs` كل خمس دقائق؛ فحص الجاهزية يفشل إن توقف أحدهما أكثر من 10 دقائق.

## 4. قرارات معلّقة على المالك

1. **مزوّد الدفع**: الكود يتحقق من توقيع HMAC عام ولا يفترض مزوّداً بعينه. الخيارات العملية للسوق السعودي: Moyasar أو Tap أو Stripe. التوصية: Moyasar لدعم مدى وApple Pay محلياً.
2. **مزوّد البريد**: التوصية Postmark لمعدل الوصول، أو Resend إن أردت أرخص.
3. **التخزين**: التوصية Cloudflare R2 بلا رسوم خروج.
4. **Cloudflare أمام الخادم أم لا**: إن نعم، يُضاف ضبط البروكسي الموثوق قبل الإطلاق حتى تُقرأ عناوين الزوار الحقيقية ويعمل rate limiting بشكل صحيح.

## 5. ترتيب التسليم المقترح

1. الخادم + DNS (البنود 1 و2 في القسم 1) ← يُمكّن تثبيت البيئة والشهادة.
2. قاعدة البيانات + Redis + البريد ← يُمكّن النشر الأول وتسجيل الحسابات.
3. التخزين + OpenAI ← يُمكّن الأدوات والرفع.
4. الدفع ← يُمكّن الاشتراكات؛ يمكن تأجيله بعد الإطلاق التجريبي لأن الأدوات تعمل برصيد ترحيبي.
