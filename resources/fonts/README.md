# خطوط الاستوديو

`arabic-regular.ttf` هو **Tajawal Regular** من مشروع Google Fonts، برخصة SIL Open Font License 1.1
(نصّها الكامل في `LICENSE-Tajawal-OFL.txt`، وحقوقه لـ Boutros International).

يُستعمل في `App\Domain\Lab\Services\VisualStudio` لكتابة العناوين على الأصول البصرية. وهو ملف ثابت
(static) لا متغيّر (variable) عن قصد: دعم FreeType للخطوط المتغيّرة ضعيف، فيرسم نسخة افتراضية غير متوقَّعة.

لتبديله: ضع ملفاً آخر هنا، أو وجّه `STUDIO_FONT_PATH` إلى مساره. وبلا أي خط عربي يبقى الاستوديو عاملاً
على المقاسات والشعار، وتُرفض كتابة العنوان برسالة تشرح السبب بدل أن تُرسم حروف مقطّعة.

**تنبيه رخصي**: الـOFL تسمح بالاستعمال التجاري وإعادة التوزيع، وتشترط إرفاق نصّ الرخصة — فلا تحذف
`LICENSE-Tajawal-OFL.txt`.
