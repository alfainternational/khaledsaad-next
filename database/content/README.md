# محتوى المجلة والقاموس (المرحلة 3)

تُقرأ هذه الملفات بواسطة `MagazineSeeder` (idempotent: `updateOrCreate` بالـ`slug`)، فتعديل ملف ثم إعادة البذر يحدّث المادة بلا تكرار.

- `magazine/<section>/<slug>.md` — مادة واحدة لكل ملف. الأقسام: `articles`، `news`، `insights`، `case-studies`، `research`.
- `glossary/<slug>.md` — مصطلح واحد لكل ملف.

## صيغة ملف المادة

واجهة أمامية بين سطرين `---` ثم المتن بصيغة Markdown المدعومة في `App\Support\Markdown`
(عناوين `##`/`###`، فقرات، `**غامق**`، قوائم `-` و`1.`، جداول بالأنابيب `|`، اقتباس `>`، روابط `[نص](https://…)`،
ومربع «طبّق الآن»):

```
:::apply customer-persona
جملة أو جملتان تقولان ماذا يفعل القارئ الآن بهذه الأداة على مشروعه.
:::
```

```
---
title: هل تحتاج شركتك الصغيرة إلى شخصية عميل قبل أول حملة؟
slug: do-you-need-a-persona-before-first-campaign
type: guide
section: articles
category: audience
tags: [personas, small-business, customer-journey]
author: khaled-saad
reviewer: ليان الحربي
topic: audience-and-personas
tool: customer-persona
lesson: first-segment
reading_minutes: 7
published_at: 2026-09-14
eyebrow: دليل تطبيقي
excerpt: جملتان تلخصان المادة وتظهران في البطاقات ونتائج البحث (حتى 220 حرفاً).
tldr: «في دقيقة»: إجابة مباشرة من جملتين إلى ثلاث عن سؤال العنوان.
sources:
  - publisher: Nielsen Norman Group
    title: Personas Make Users Memorable for Product Team Members
    url: https://www.nngroup.com/articles/persona/
    date: 2015-02-15
    language: en
faq:
  - q: سؤال فعلي يطرحه القارئ؟
    a: جواب من جملتين.
---
## السؤال الأول على هيئة سؤال حقيقي؟
...
```

### الحقول

| الحقل | القيم |
|---|---|
| `type` | `article`، `guide`، `news`، `translated_analysis`، `case_study`، `research` |
| `section` | `articles` (article/guide)، `news`، `insights` (translated_analysis)، `case-studies` (case_study)، `research` |
| `category` | `strategy`، `audience`، `content`، `ads`، `measurement`، `ai` |
| `tags` | من 2 إلى 4 وسوم من قائمة `MagazineSeeder::TAGS` (slugs لاتينية) |
| `author` | `khaled-saad`، `layan-alharbi`، `abdullah-alshehri` |
| `topic` | أحد مسارات الأكاديمية الاثني عشر (slug المسار) |
| `tool` | أداة من المختبر: `customer-persona`، `value-proposition`، `campaign-brief-builder`، `content-angle-generator`، `headlines-cta`، `content-calendar`، `email-sequence`، `utm-builder`، `landing-page-reviewer`، `prompt-builder`، `cac`، `ltv`، `roas`، `conversion-sample`، `ad-campaign-simulator` |
| `lesson` | اختياري؛ أحد دروس مسار «أساسيات التسويق»: `what-marketing-really-is`، `market-map`، `first-segment`، `core-message`، `first-channel-and-budget`، `first-marketing-plan` |
| `sources` | إلزامية للرؤى والأخبار والأبحاث؛ كل رقم في المتن يجب أن يعود إلى مصدر مذكور هنا |
| `faq` | اختيارية؛ أسئلة فعلية فقط (تولّد FAQPage) |

## صيغة ملف المصطلح

```
---
term: تكلفة اكتساب العميل (CAC)
slug: cac
definition: تعريف مباشر في جملة واحدة يظهر كأول سطر في الصفحة.
example: مثال محلي من جملتين إلى ثلاث.
tool: cac
lesson: first-channel-and-budget
tags: [measurement, paid-ads]
---
## متى يفيدك هذا المصطلح؟
متن قصير (150–250 كلمة) بالصيغة نفسها.
```
