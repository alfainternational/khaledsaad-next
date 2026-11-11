<?php

namespace Database\Seeders;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Assessment\Models\AssessmentQuestion;
use App\Domain\Assessment\Models\AssessmentRule;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolVersion;
use Illuminate\Database\Seeder;

/**
 * إصلاح النصوص التي كانت تفترض وجود ما نفاه العضو (2026-11-11).
 *
 * Questions, lesson scenarios and priorities are editable in the admin, so the seeders that write them are gated
 * behind one-time markers and must stay that way: re-running them would delete an editor's work. That also means a
 * correction to the shipped copy has no way to reach production — which is why this exists.
 *
 * It is a map of exact old string to new string, applied to the four places that store this text. A row whose text
 * no longer matches has been edited since, so it is counted as a conflict and left exactly as it is: the editor's
 * wording wins over ours, even when ours is the fix. Re-running finds nothing left to change.
 */
class SentinelCopyFixSeeder extends Seeder
{
    /** Lesson scenario templates: the sentence that read «عبر لا قناة ثابتة بعد» when the member had no channel. */
    private const SCENARIOS = [
        'مشروعك «{{project_name}}» يبيع {{offer}} في قطاع {{sector_label}}، بميزانية {{budget_label}} شهرياً عبر {{channel_label}}، وهدفك {{goal_label}} ({{goal_number}} شهرياً).' => 'مشروعك «{{project_name}}» يبيع {{offer_phrase}}، {{budget_phrase}} شهرياً، {{channel_phrase}}، وهدفك {{goal_phrase}} شهرياً.',
        '«{{project_name}}» يبيع {{offer}} في {{city}} بميزانية {{budget_label}} عبر {{channel_label}}، ويحتاج {{goal_number}} {{goal_label}} شهرياً. السؤال: كم شخصاً من شريحته يمكن أن يصل إليهم فعلاً، ومن يختارونه اليوم بدلاً منه؟' => '«{{project_name}}» يبيع {{offer}} في {{city}}، {{budget_phrase}}، {{channel_phrase}}، ويحتاج {{goal_phrase}} شهرياً. السؤال: كم شخصاً من شريحته يمكن أن يصل إليهم فعلاً، ومن يختارونه اليوم بدلاً منه؟',
        '«{{project_name}}» يصل إلى {{audience}} ويحتاج {{goal_number}} {{goal_label}} شهرياً عبر {{channel_label}}. كل من يتواصل ليس مناسباً؛ كم محادثة مؤهلة تحتاج فعلاً؟' => '«{{project_name}}» يصل إلى {{audience}} ويحتاج {{goal_phrase}} شهرياً، {{channel_phrase}}. كل من يتواصل ليس مناسباً؛ كم محادثة مؤهلة تحتاج فعلاً؟',
        '«{{project_name}}» يصل إلى نحو {{reach}} شخصاً من شريحته شهرياً عبر {{channel_label}} ويحتاج {{goal_number}} {{goal_label}}. الرسالة الحالية تجمع تفاعلاً بلا طلبات كافية.' => '«{{project_name}}» يصل إلى نحو {{reach}} شخصاً من شريحته شهرياً، {{channel_phrase}}، ويحتاج {{goal_phrase}}. الرسالة الحالية تجمع تفاعلاً بلا طلبات كافية.',
        '«{{project_name}}» يبيع {{offer}} بميزانية {{budget_label}} شهرياً وقناته الحالية {{channel_label}}، ويحتاج {{goal_number}} {{goal_label}}. السؤال: هل هذه الميزانية على هذه القناة تبلغ الرقم، ومتى يقرر الإيقاف أو المضاعفة؟' => '«{{project_name}}» يبيع {{offer_phrase}} {{budget_phrase}} شهرياً {{channel_clause}}، ويحتاج {{goal_phrase}}. السؤال: هل هذه الميزانية تبلغ الرقم على قناة واحدة، ومتى يقرر الإيقاف أو المضاعفة؟',
        '«{{project_name}}» جاهز للتنفيذ: {{offer}}، بميزانية {{budget_label}} على {{channel_label}}، وهدف {{goal_number}} {{goal_label}} شهرياً. السؤال: ما الإيقاع الأسبوعي، وما الرقمان، وما القرار كل أسبوع؟' => '«{{project_name}}» جاهز للتنفيذ: {{offer}}، {{budget_phrase}}، {{channel_phrase}}، وهدف {{goal_phrase}} شهرياً. السؤال: ما الإيقاع الأسبوعي، وما الرقمان، وما القرار كل أسبوع؟',    ];

    /** Question prompts, the four channel options, and the priority lines the member is told to act on. */
    private const TEXT = [
        // The question that asked someone with no channel why they chose it. The premise is gone, not special-cased.
        'قناتك الحالية هي {{channel}}. لماذا هذه القناة تحديداً؟' => 'ما الذي يحدّد أين تنشر أو تعلن اليوم؟',
        'لأنها الأسهل أو لأن الجميع يستخدمها' => 'لا شيء محدّد — أنشر حيث تيسّر، أو لم أبدأ بعد',
        'لأن منافسيّ عليها' => 'ما يفعله المنافسون، أو لأن الجميع هناك',
        'لأن شريحتي موجودة فيها حسب ملاحظتي' => 'ملاحظتي أن شريحتي موجودة في قناة بعينها',
        'اختبرت قناتين على الأقل بأرقام، وهذه أعطت أقل تكلفة للطلب' => 'اختبرت قناتين على الأقل بأرقام، وأعتمد التي أعطت أقل تكلفة للطلب',

        // «بميزانية بلا ميزانية إعلانية»: the preposition now travels with the value.
        'بميزانية {{budget}} شهرياً، كم طلباً أو عميلاً تتوقع أن تحصل عليه؟ وكيف وصلت إلى هذا الرقم؟' => '{{budget_phrase}} شهرياً، كم طلباً أو عميلاً تتوقع أن تحصل عليه؟ وكيف وصلت إلى هذا الرقم؟',
        'لو بدأت حملة الشهر القادم بـ {{budget}}: متى تقرر إيقافها أو مضاعفتها؟' => 'لو بدأت حملة الشهر القادم {{budget_phrase}}: متى تقرر إيقافها أو مضاعفتها؟',

        // Priorities: advice that named a channel the member might not have.
        'حوّل الرقم إلى خطة قناة واحدة بميزانية {{budget}}' => 'حوّل الرقم إلى خطة قناة واحدة {{budget_phrase}}',
        'الرسالة والقناة والسعر كلها تُبنى على شريحة واحدة واضحة؛ الاستهداف العام يُشتت الميزانية ({{budget}}) على من لن يشتري.' => 'الرسالة والقناة والسعر كلها تُبنى على شريحة واحدة واضحة؛ الاستهداف العام يُشتت ميزانيتك على من لن يشتري.',
        'اختبر الرسالة على {{channel}} بميزانية صغيرة' => 'اختبر الرسالة على قناة واحدة بميزانية صغيرة',
        'ركّز {{budget}} على قناة واحدة بقاعدة إيقاف' => 'ركّز ميزانيتك على قناة واحدة بقاعدة إيقاف',
        'حدّد تكلفة الطلب المقبولة لقناتك ({{channel}})' => 'حدّد تكلفة الطلب المقبولة لقناتك',

        // The same priorities are written a second time by StageFourCompletionSeeder, which runs later and wins —
        // so fixing them in one seeder alone fixed nothing that production would ever see.
        'اضبط الموافقة والتوقعات لكل شريحة في {{channel}} والبريد' => 'اضبط الموافقة والتوقعات لكل شريحة في قنواتك والبريد',
        'حدّد دور {{channel}} في رحلة العميل قبل التقويم' => 'حدّد دور كل قناة في رحلة العميل قبل التقويم',

        // The one tool template that put the channel inside a sentence rather than a table cell.
        'لـ**{{audience}}** على {{channel_label}} · النتيجة: {{benefit}}' => 'لـ**{{audience}}** {{channel_phrase}} · النتيجة: {{benefit}}',
    ];

    public function run(): void
    {
        $changed = $conflicts = 0;

        foreach (Lesson::query()->whereNotNull('scenario_template')->get() as $lesson) {
            $old = (string) $lesson->scenario_template;
            if (isset(self::SCENARIOS[$old])) {
                $lesson->forceFill(['scenario_template' => self::SCENARIOS[$old]])->save();
                $changed++;
            } elseif (! in_array($old, self::SCENARIOS, true) && $this->looksUnfixed($old)) {
                $this->command?->warn("  تعارض: مشهد الدرس «{$lesson->slug}» عُدّل بعد الشحن — تُرك كما هو.");
                $conflicts++;
            }
        }

        foreach (AssessmentQuestion::query()->get() as $question) {
            $prompt = self::TEXT[$question->prompt] ?? $question->prompt;
            $options = array_map(fn (array $o): array => ['label' => self::TEXT[$o['label'] ?? ''] ?? ($o['label'] ?? ''), 'weight' => $o['weight'] ?? 0], (array) $question->options);
            if ($prompt !== $question->prompt || $options !== (array) $question->options) {
                $question->forceFill(['prompt' => $prompt, 'options' => $options])->save();
                $changed++;
            } elseif ($this->looksUnfixed((string) $question->prompt)) {
                $this->command?->warn("  تعارض: سؤال «{$question->dimension}» عُدّل بعد الشحن — تُرك كما هو.");
                $conflicts++;
            }
        }

        foreach (AssessmentRule::query()->get() as $rule) {
            $priorities = (array) $rule->priorities;
            $fixed = array_map(fn (array $p): array => array_map(fn ($v) => is_string($v) ? (self::TEXT[$v] ?? $v) : $v, $p), $priorities);
            if ($fixed !== $priorities) {
                $rule->forceFill(['priorities' => $fixed])->save();
                $changed++;
            }
        }

        foreach (Tool::query()->whereNotNull('output_template')->get() as $tool) {
            $this->replaceInTemplate($tool, $changed);
        }
        foreach (ToolVersion::query()->whereNotNull('output_template')->get() as $version) {
            $this->replaceInTemplate($version, $changed);
        }

        $this->command?->info("إصلاح النصوص: {$changed} صفاً، {$conflicts} تعارضاً.");
    }

    /** A tool template is a whole document; only the offending LINE is swapped, so studio edits elsewhere survive. */
    private function replaceInTemplate(Tool|ToolVersion $model, int &$changed): void
    {
        $before = (string) $model->output_template;
        $after = $before;
        foreach (self::TEXT as $old => $new) {
            if (str_contains($after, $old)) {
                $after = str_replace($old, $new, $after);
            }
        }
        if ($after !== $before) {
            $model->forceFill(['output_template' => $after])->save();
            $changed++;
        }
    }

    /** Still carries a placeholder that assumes the thing exists — i.e. the editor's version has the bug too. */
    private function looksUnfixed(string $text): bool
    {
        foreach (['عبر {{channel_label}}', 'على {{channel_label}}', 'بميزانية {{budget_label}}', 'بميزانية {{budget}}', 'هي {{channel}}', 'بـ {{budget}}'] as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
