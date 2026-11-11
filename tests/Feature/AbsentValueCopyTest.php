<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Services\LessonComposer;
use App\Domain\Assessment\Models\AssessmentQuestion;
use App\Domain\Assessment\Models\AssessmentRule;
use App\Domain\Assessment\Services\AssessmentEngine;
use App\Domain\Assessment\Services\ProjectPhrases;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Services\GeneratorEngine;
use App\Domain\Lab\Services\LabCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * قيمة معناها العدم لا تُعرض كأنها شيء (2026-11-11).
 *
 * «لا قناة ثابتة بعد» and «لا ميزانية إعلانية الآن» are chosen answers, not missing ones, and three separate value
 * bags each dropped them into sentences that assumed a thing: «عبر لا قناة ثابتة بعد»، «بميزانية بلا ميزانية
 * إعلانية». The diagnosis then scored a channel question the member could only answer by lying.
 *
 * These tests render every member-facing template with a profile whose every optional value means "nothing yet".
 */
class AbsentValueCopyTest extends TestCase
{
    use RefreshDatabase;

    /** A real project that has chosen "none" everywhere it could. */
    private const NOTHING_YET = ['offer' => 'سيروم عناية', 'audience' => 'نساء 25–40', 'channel' => 'none', 'budget' => 0, 'goal' => 'leads', 'goal_number' => 0, 'sector' => 'other', 'city' => ''];

    private const SOMETHING = ['offer' => 'سيروم عناية', 'audience' => 'نساء 25–40', 'channel' => 'instagram', 'budget' => 3000, 'goal' => 'leads', 'goal_number' => 60, 'sector' => 'beauty', 'city' => 'الرياض'];

    /**
     * Each of these is a preposition or copula followed by a word that negates it — the shape of the bug. A rendered
     * sentence containing one is broken Arabic whatever else it says.
     */
    private const BROKEN = [
        'عبر لا قناة', 'على لا قناة', 'هي لا قناة', 'في لا قناة',
        'بميزانية بلا ميزانية', 'بميزانية لا ميزانية', 'بـ بلا', 'ركّز بلا ميزانية',
        'قطاع أخرى', 'في —', 'عبر —', 'على —', 'يبيع —',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Http::fake();
    }

    private function assertReadsAsArabic(string $text, string $where): void
    {
        foreach (self::BROKEN as $broken) {
            $this->assertStringNotContainsString($broken, $text, "{$where}: «{$broken}»");
        }
    }

    /**
     * A stronger rule for surfaces that are ALWAYS prose. The list label «لا قناة ثابتة بعد» belongs in a table cell
     * or a dropdown; a sentence must use channel_phrase, which says «بلا قناة ثابتة بعد» and is correct wherever it
     * lands. The two differ by one letter, which makes "did this come from the label or the phrase?" checkable.
     */
    private function assertNoBareLabel(string $text, string $where): void
    {
        $this->assertReadsAsArabic($text, $where);
        $this->assertStringNotContainsString('لا قناة ثابتة بعد', str_replace('بلا قناة ثابتة بعد', '', $text), "{$where}: the list label leaked into a sentence");
    }

    public function test_no_diagnosis_question_assumes_a_channel_or_a_budget_the_member_denied(): void
    {
        $engine = app(AssessmentEngine::class);

        foreach (['owner', 'marketer'] as $audience) {
            foreach ($engine->questionsFor($audience, self::NOTHING_YET) as $question) {
                $this->assertNoBareLabel($question->rendered, "سؤال/{$audience}/{$question->dimension}");
            }
            // And the ordinary case still names the real values rather than hedging for everyone.
            $withChannel = $engine->questionsFor($audience, self::SOMETHING)->map(fn ($q) => $q->rendered)->implode("\n");
            $this->assertStringContainsString('بميزانية 3,000 ريال', $withChannel);
        }
    }

    /**
     * The question that started this: a member with no channel was asked why they chose it, and every option was a
     * lie they had to tell to continue — which then scored the channel dimension.
     */
    public function test_the_channel_question_can_be_answered_truthfully_with_no_channel(): void
    {
        $question = AssessmentQuestion::query()->where('audience', 'owner')->where('dimension', 'channel')->orderBy('position')->first();

        $this->assertNotNull($question);
        $this->assertStringNotContainsString('{{channel}}', $question->prompt, 'the stem must not presuppose a chosen channel');
        $this->assertSame('ما الذي يحدّد أين تنشر أو تعلن اليوم؟', $question->prompt);

        // The zero-weight option has to be true for someone who has not started, or the score is manufactured.
        $lowest = collect($question->options)->sortBy('weight')->first();
        $this->assertSame(0, (int) $lowest['weight']);
        $this->assertStringContainsString('لم أبدأ بعد', $lowest['label']);
    }

    public function test_no_lesson_scenario_or_priority_assumes_what_the_member_does_not_have(): void
    {
        $composer = app(LessonComposer::class);

        foreach (Lesson::query()->whereNotNull('scenario_template')->get() as $lesson) {
            $lesson->general_scenario = array_merge((array) $lesson->general_scenario, self::NOTHING_YET);
            $sentence = $composer->scenarioSentence($lesson, $composer->situation($lesson, null, 'general'));
            $this->assertNoBareLabel($sentence, "مشهد/{$lesson->slug}");
        }

        $values = app(AssessmentEngine::class)->placeholders(self::NOTHING_YET);
        foreach (AssessmentRule::query()->get() as $rule) {
            foreach ((array) $rule->priorities as $priority) {
                foreach (['title', 'reason'] as $field) {
                    $this->assertNoBareLabel(\App\Support\Markdown::fill((string) ($priority[$field] ?? ''), $values), "أولوية/{$rule->weakest_dimension}/{$field}");
                }
            }
        }
    }

    public function test_no_tool_template_puts_an_absent_channel_inside_a_sentence(): void
    {
        $engine = app(GeneratorEngine::class);

        foreach (Tool::query()->get() as $tool) {
            if (! $tool->usesTemplate()) {
                continue;
            }
            $values = $engine->values(self::NOTHING_YET, $tool->fields()) + self::NOTHING_YET;
            $this->assertReadsAsArabic($engine->renderTemplate((string) $tool->output_template, $values), "أداة/{$tool->slug}");
        }
    }

    public function test_a_phrase_carries_its_own_preposition_so_a_template_never_has_to_guess(): void
    {
        $nothing = ProjectPhrases::all(self::NOTHING_YET);
        $something = ProjectPhrases::all(self::SOMETHING);

        $this->assertSame('بلا قناة ثابتة بعد', $nothing['channel_phrase']);
        $this->assertSame('عبر إنستغرام', $something['channel_phrase']);
        $this->assertSame('بلا ميزانية إعلانية', $nothing['budget_phrase']);
        $this->assertSame('بميزانية 3,000 ريال', $something['budget_phrase']);

        // An unknown sector produces no clause at all rather than «في قطاع أخرى» — and never an empty string, which
        // Markdown::fill would render as «—» in the middle of a sentence.
        $this->assertSame('سيروم عناية', $nothing['offer_phrase']);
        $this->assertSame('سيروم عناية في قطاع تجميل وعناية', $something['offer_phrase']);
        foreach ($nothing as $key => $phrase) {
            $this->assertNotSame('', trim((string) $phrase), "{{{$key}}} is empty, which Markdown::fill renders as «—»");
        }

        // No invented target, and no apology for its absence either.
        $this->assertSame('طلبات أو محادثات مؤهلة', $nothing['goal_phrase']);
        $this->assertSame('60 طلبات أو محادثات مؤهلة', $something['goal_phrase']);

        $this->assertTrue(ProjectPhrases::isNone('channel', 'none'));
        $this->assertTrue(ProjectPhrases::isNone('budget', 0));
        $this->assertFalse(ProjectPhrases::isNone('channel', 'instagram'));
    }

    /** The two tools that shipped in code, were advertised by the catalogue, and answered 404 in production. */
    public function test_every_catalogue_tool_is_seeded_and_the_doctor_says_so_when_one_is_not(): void
    {
        $absent = array_diff(array_column(LabCatalog::tools(), 'slug'), Tool::query()->pluck('slug')->all());
        $this->assertSame([], array_values($absent));

        $this->artisan('platform:doctor --env=production')->expectsOutputToContain('[PASS] every catalogue tool exists');

        Tool::query()->where('slug', 'keyword-planner')->delete();
        $this->artisan('platform:doctor --env=production')->expectsOutputToContain('[WARN] every catalogue tool exists (missing: keyword-planner)');

        // And the additive seeder puts it back without touching anything else — which is why it needs no marker.
        $before = Tool::query()->where('slug', 'customer-persona')->firstOrFail()->output_template;
        $this->artisan('db:seed', ['--class' => 'AdditiveToolsSeeder'])->assertSuccessful();
        $this->assertNotNull(Tool::query()->where('slug', 'keyword-planner')->first());
        $this->assertSame($before, Tool::query()->where('slug', 'customer-persona')->firstOrFail()->output_template);
    }

    /** The fix has to reach a production database whose seeders are frozen — and must not overwrite an editor. */
    public function test_the_copy_fix_updates_shipped_rows_and_leaves_edited_ones_alone(): void
    {
        $question = AssessmentQuestion::query()->where('dimension', 'channel')->orderBy('position')->firstOrFail();
        $question->forceFill(['prompt' => 'قناتك الحالية هي {{channel}}. لماذا هذه القناة تحديداً؟', 'options' => [
            ['label' => 'لأنها الأسهل أو لأن الجميع يستخدمها', 'weight' => 0],
            ['label' => 'لأن منافسيّ عليها', 'weight' => 1],
            ['label' => 'لأن شريحتي موجودة فيها حسب ملاحظتي', 'weight' => 2],
            ['label' => 'اختبرت قناتين على الأقل بأرقام، وهذه أعطت أقل تكلفة للطلب', 'weight' => 3],
        ]])->save();

        $edited = Lesson::query()->whereNotNull('scenario_template')->firstOrFail();
        $edited->forceFill(['scenario_template' => 'صياغة المحرّر الخاصة عبر {{channel_label}}.'])->save();

        $this->artisan('db:seed', ['--class' => 'SentinelCopyFixSeeder'])->assertSuccessful();

        $this->assertSame('ما الذي يحدّد أين تنشر أو تعلن اليوم؟', $question->fresh()->prompt);
        $this->assertStringContainsString('لم أبدأ بعد', collect($question->fresh()->options)->pluck('label')->implode(' '));
        $this->assertSame('صياغة المحرّر الخاصة عبر {{channel_label}}.', $edited->fresh()->scenario_template, 'the editor wins, even over our fix');

        // Running it again finds nothing left to do.
        $this->artisan('db:seed', ['--class' => 'SentinelCopyFixSeeder'])->assertSuccessful();
        $this->assertSame('ما الذي يحدّد أين تنشر أو تعلن اليوم؟', $question->fresh()->prompt);
    }
}
