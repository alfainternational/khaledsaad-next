<?php

namespace App\Domain\Academy\Services;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Lab\Ai\AiContext;
use App\Domain\Lab\Services\AiGateway;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;

/**
 * «حسّن بالذكاء الاصطناعي» inside the lesson lab (§1.4-8): available only when an AI key exists.
 * The rule-based asset stays the source of facts; the model may only tighten wording and structure.
 * Credits follow the same reserve → release → capture trail as the lesson coach.
 */
class LessonAssetEnhancer
{
    private const DEFAULT_COST = 2;

    public function __construct(private readonly AiGateway $ai, private readonly CreditLedger $ledger) {}

    public static function enabled(): bool
    {
        return app(AiGateway::class)->enabled();
    }

    /** Shown on the button before the learner spends anything. */
    public static function cost(Lesson $lesson): int
    {
        $cost = (int) ($lesson->labTool?->credit_cost ?? 0);

        return $cost > 0 ? $cost : self::DEFAULT_COST;
    }

    public function enhance(User $user, Workspace $workspace, Lesson $lesson, string $markdown): string
    {
        if (! self::enabled()) {
            throw new DomainException('التحسين بالذكاء الاصطناعي غير متاح حالياً. لم يُخصم أي رصيد.');
        }
        $cost = self::cost($lesson);
        if ($this->ledger->balance($workspace) < $cost) {
            throw new DomainException("رصيدك لا يكفي للتحسين ({$cost} رصيد). أصلك الحالي يبقى كما هو.");
        }

        $instructions = 'أنت محرر تسويق عربي. حسّن صياغة هذا الأصل وبنيته ليصبح أوضح وأقرب للتنفيذ، بصيغة Markdown وبالعناوين نفسها. '
            .'لا تغيّر أي رقم أو اسم أو تاريخ أو التزام ورد فيه، ولا تضف أرقاماً أو مصادر أو وعوداً جديدة، ولا تحذف قسماً. أعد الأصل كاملاً فقط بلا مقدمة ولا تعليق.';
        $key = 'lesson-enhance:'.$lesson->id.':'.$user->id.':'.Str::random(8);
        $label = 'تحسين أصل الدرس: '.$lesson->title;

        $this->ledger->reserve($workspace, $key.':reserve', $cost, $label);
        try {
            $result = $this->ai->generate(
                "عنوان الدرس: {$lesson->title}\nهدف الدرس: {$lesson->goal}\n\nالأصل:\n".Str::limit($markdown, 6000, ''),
                $instructions,
                new AiContext(
                    workspace: $workspace,
                    userId: $user->id,
                    purpose: 'lesson:enhance',
                    operationKey: $key,
                    sensitive: true,
                    cacheable: false,
                ),
            );
        } catch (DomainException $e) {
            $this->ledger->record($workspace, $key.':release', 'release', $cost, 'فك حجز تحسين فاشل');
            throw $e;
        }
        $text = trim((string) ($result['text'] ?? ''));
        if ($text === '') {
            $this->ledger->record($workspace, $key.':release', 'release', $cost, 'فك حجز تحسين فارغ');
            throw new DomainException('لم يُرجع المزود نصاً. لم يُخصم أي رصيد وأصلك كما هو.');
        }
        $this->ledger->record($workspace, $key.':release', 'release', $cost, 'تحويل الحجز إلى استخدام');
        $this->ledger->record($workspace, $key.':capture', 'capture', -$cost, $label);

        return $text;
    }
}
