<?php

namespace App\Domain\Insights\Services;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Services\LessonCoach;
use App\Domain\Insights\Models\LearnerEvent;
use App\Domain\Insights\Models\QualitySignal;
use App\Domain\Lab\Services\AiGateway;
use DomainException;

/**
 * المساعد التحريري في استوديو الدرس (blueprint §6.2): diagnoses and suggests from rules now, and from AI when a key exists.
 * Every suggestion is applied by the editor as a new lesson version — nothing is published automatically.
 */
class StudioAssistant
{
    public function __construct(private readonly AiGateway $ai) {}

    public function suggestions(Lesson $lesson): array
    {
        $out = [];
        $parts = $lesson->partCompleteness();
        foreach ($parts as $part => $ok) {
            if (! $ok) {
                $out[] = ['part' => $part, 'title' => 'اكتب جزء «'.Lesson::PARTS[$part].'»', 'text' => $this->missingHint($part), 'kind' => 'missing'];
            }
        }
        $words = str_word_count_utf8($lesson->concept_body);
        if ($words > 0 && $words < 600) {
            $out[] = ['part' => 'concept', 'title' => 'المفهوم قصير ('.$words.' كلمة)', 'text' => 'المستهدف 600–900 كلمة مع مثال من السوق السعودي ومربع «الخطأ الشائع».', 'kind' => 'length'];
        } elseif ($words > 900) {
            $out[] = ['part' => 'concept', 'title' => 'المفهوم طويل ('.$words.' كلمة)', 'text' => 'اختصره إلى 900 كلمة أو انقل التفاصيل إلى القالب؛ النسخة السريعة تعتمد على الملخص.', 'kind' => 'length'];
        }
        if (blank($lesson->quick_summary)) {
            $out[] = ['part' => 'concept', 'title' => 'لا ملخص للوضع السريع (8 دقائق)', 'text' => 'اكتب 3–5 جمل تُعرض بدل المفهوم الكامل عند اختيار «سريع».', 'kind' => 'missing'];
        }
        // Lab defaults from the situation: fields without a source cannot be pre-filled from the project profile.
        $unsourced = collect(array_merge($lesson->guided_steps ?? [], $lesson->lab_config['fields'] ?? []))->filter(fn ($f) => empty($f['source']))->pluck('label');
        if ($unsourced->isNotEmpty()) {
            $out[] = ['part' => 'lab', 'title' => 'عبّئ افتراضيات المختبر من الموقف', 'text' => 'حقول بلا مصدر من ملف المشروع: '.$unsourced->implode('، ').'. اربط ما يمكن ربطه بـ offer/budget/channel/goal_number ليُعبَّأ تلقائياً.', 'kind' => 'mapping'];
        }
        if (empty($lesson->whatif_rules['benchmark']) && empty($lesson->whatif_rules['outputs'])) {
            $out[] = ['part' => 'lab', 'title' => 'شريط «ماذا لو» بلا قاعدة مُعلنة', 'text' => 'حدّد متغيّراً واحداً ومعياراً مرجعياً وقاعدة حكم مقابل هدف المتعلم.', 'kind' => 'missing'];
        }
        // Questions the majority gets wrong.
        foreach (QualitySignal::query()->where('status', 'open')->where('type', 'question_wrong_majority')->get() as $signal) {
            if ((int) ($signal->meta['lesson_id'] ?? 0) !== $lesson->id) {
                continue;
            }
            $question = $lesson->questions->firstWhere('id', $signal->entity_id);
            if ($question) {
                $out[] = ['part' => 'questions', 'title' => 'أعد صياغة سؤال يُخطئ فيه الأغلبية ('.(int) $signal->value.'٪)', 'text' => 'السيناريو: «'.mb_substr((string) ($question->scenario ?: $question->prompt), 0, 90).'…». اجعل الفرق بين الخيار الأفضل وأقرب بديل أوضح في السيناريو، وراجع التغذية الراجعة للخيار الذي يختاره الأغلبية.', 'kind' => 'signal', 'question_id' => $question->id];
            }
        }
        foreach (QualitySignal::query()->where('status', 'open')->where('entity_type', 'lesson')->where('entity_id', $lesson->id)->get() as $signal) {
            $out[] = ['part' => $signal->part ?? 'lab', 'title' => $signal->type === 'lab_not_saved' ? 'المختبر يولّد ولا يُحفظ' : 'تسرّب عند هذا الجزء', 'text' => $signal->summary, 'kind' => 'signal'];
        }
        $out[] = [
            'part' => 'situation', 'title' => 'ولّد متغيّراً للدرس بموقف بديل لجمهور آخر',
            'text' => LessonCoach::enabled() ? 'يستخدم مفتاح الذكاء الاصطناعي لاقتراح موقف تدريبي بديل (مثلاً لوكالة بدل صاحب مشروع) تراجعه ثم تعتمده كإصدار جديد.' : 'يتطلب مفتاح ذكاء اصطناعي (OPENAI_API_KEY). بلا مفتاح: اكتب الموقف البديل يدوياً في حقل «الموقف العام».',
            'kind' => 'ai', 'enabled' => LessonCoach::enabled(),
        ];

        return $out;
    }

    private function missingHint(string $part): string
    {
        return match ($part) {
            'opening' => 'سؤال اختيار من 3 مرتبط بالموقف، يُسجَّل بلا تقييم ويُعاد في «هل غيّرت رأيك؟».',
            'goal' => 'جملة «ستخرج بـ…» تسمّي الأصل الذي ينتجه الدرس.',
            'concept' => '600–900 كلمة، مثال واحد من السوق السعودي، ومربع الخطأ الشائع.',
            'situation' => 'قالب جملة واحدة بمتغيّرات {{offer}} {{budget_label}} {{channel_label}} {{goal_label}}.',
            'guided' => '3–5 خطوات بحقول صغيرة، كل حقل بمفتاح ومصدر من ملف المشروع حيث أمكن.',
            'lab' => 'حقول المختبر وقالب الأصل (Markdown بمتغيّرات) وقواعد «ماذا لو».',
            'questions' => '3 أسئلة قرار بتغذية راجعة لكل خيار وخيار أفضل واحد.',
            'template' => 'اربط قالباً فارغاً قابلاً للتنزيل (Markdown/PDF).',
            'apply' => 'قالب الفعل الواقعي وحقول النتيجة التي يسجّلها المتعلم بعد 7 أيام.',
            default => '',
        };
    }

    /** Field mapping table: guided/lab fields ↔ project profile keys, with the observed fill rate from lab generations. */
    public function fieldMapping(Lesson $lesson): array
    {
        $events = LearnerEvent::query()->where('lesson_id', $lesson->id)->where('event', 'lesson.lab_generated')->latest('created_at')->limit(500)->get(['meta']);
        $total = $events->count();
        $rows = [];
        foreach (array_merge($lesson->guided_steps ?? [], $lesson->lab_config['fields'] ?? []) as $field) {
            $filled = $total > 0 ? $events->filter(fn ($e) => in_array($field['key'], $e->meta['filled'] ?? [], true))->count() : 0;
            $prefilled = $total > 0 ? $events->filter(fn ($e) => in_array($field['key'], $e->meta['prefilled'] ?? [], true))->count() : 0;
            $rows[] = ['key' => $field['key'], 'label' => $field['label'], 'source' => $field['source'] ?? null, 'fill_rate' => $total > 0 ? (int) round($filled / $total * 100) : null, 'prefill_rate' => $total > 0 ? (int) round($prefilled / $total * 100) : null];
        }

        return ['total' => $total, 'rows' => $rows];
    }

    /** AI: propose an alternative scenario for another audience (returned for review, never saved automatically). */
    public function generateVariant(Lesson $lesson, string $audience): string
    {
        if (! LessonCoach::enabled()) {
            throw new DomainException('يتطلب مفتاح ذكاء اصطناعي.');
        }
        $out = $this->ai->generate(
            "الدرس: {$lesson->title}\nالهدف: {$lesson->goal}\nالموقف الحالي: {$lesson->scenario_template}\nاكتب موقفاً تدريبياً بديلاً لجمهور «{$audience}» بجملتين، بنفس المتغيّرات {{offer}} {{budget_label}} {{channel_label}} {{goal_label}}، وبالعربية.",
            'أنت محرر تعليمي. أعد نصاً عربياً قصيراً فقط بلا مقدمات.'
        );

        return $out['text'];
    }
}
