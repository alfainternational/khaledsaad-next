<?php

namespace Tests\Feature;

use App\Domain\Assessment\Models\AssessmentFollowup;
use App\Domain\Assessment\Models\AssessmentResult;
use App\Domain\Assessment\Services\AssessmentEngine;
use App\Domain\Assessment\Services\RemeasureLoop;
use App\Models\User;
use App\Notifications\RemeasureDue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * إعادة القياس بعد 30/60/90 يوماً (spec 2026-11-09 §5، الموجة 2) — الحلقة المغلقة.
 *
 * The platform measured once and never asked again. These tests pin the loop that closes: it starts itself, a retake
 * closes exactly one point, the delta is arithmetic between two results the member produced, and the verdict never
 * congratulates a number that did not move.
 */
class RemeasureLoopTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Http::fake();
        Notification::fake();
        $this->user = User::factory()->create(['audience' => 'owner']);
    }

    private function diagnosis(int $overall = 50, array $scores = [], ?string $weakest = null, ?int $daysAgo = null): AssessmentResult
    {
        $result = AssessmentResult::query()->create([
            'user_id' => $this->user->id,
            'audience' => 'owner',
            'project_snapshot' => ['offer' => 'سيروم'],
            'answers' => [],
            'scores' => $scores ?: ['goal' => 40, 'audience' => 50, 'offer' => 60, 'channel' => 50, 'measure' => 50],
            'overall' => $overall,
            'level' => 'starting',
            'weakest_dimension' => $weakest ?? 'goal',
            'priorities' => [],
            'session_token' => \Illuminate\Support\Str::random(48),
        ]);

        if ($daysAgo !== null) {
            $result->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        }

        return $result->fresh();
    }

    public function test_a_diagnosis_opens_its_own_three_points(): void
    {
        $loop = app(RemeasureLoop::class);
        $result = $this->diagnosis();

        $loop->schedule($result);

        $points = AssessmentFollowup::query()->where('baseline_result_id', $result->id)->orderBy('days')->get();
        $this->assertSame([30, 60, 90], $points->pluck('days')->map(fn ($d) => (int) $d)->all());
        $this->assertEqualsWithDelta($result->created_at->copy()->addDays(30)->timestamp, $points[0]->due_at->timestamp, 5);
        $this->assertTrue($points->every(fn ($p) => ! $p->isDone()));

        // Scheduling twice must not double the loop.
        $loop->schedule($result);
        $this->assertSame(3, AssessmentFollowup::query()->where('baseline_result_id', $result->id)->count());
    }

    public function test_a_visitor_without_an_account_has_nowhere_to_be_reminded(): void
    {
        $orphan = AssessmentResult::query()->create([
            'user_id' => null, 'audience' => 'owner', 'project_snapshot' => [], 'answers' => [],
            'scores' => ['goal' => 10], 'overall' => 10, 'level' => 'starting', 'weakest_dimension' => 'goal',
            'priorities' => [], 'session_token' => \Illuminate\Support\Str::random(48),
        ]);

        $this->assertCount(0, app(RemeasureLoop::class)->schedule($orphan));
        $this->assertSame(0, AssessmentFollowup::query()->count());
    }

    public function test_a_retake_closes_exactly_one_due_point_and_records_the_delta(): void
    {
        $loop = app(RemeasureLoop::class);
        $baseline = $this->diagnosis(overall: 48, daysAgo: 95);
        $loop->schedule($baseline);

        $retake = $this->diagnosis(overall: 61);
        $closed = $loop->close($retake);

        $this->assertNotNull($closed);
        $this->assertSame(30, (int) $closed->days, 'the oldest due point closes first');
        $this->assertSame(13, (int) $closed->delta);
        $this->assertSame($retake->id, (int) $closed->result_id);

        // One retake, one point: closing all three at once would compare an afternoon to ninety days.
        $this->assertSame(2, AssessmentFollowup::query()->whereNull('completed_at')->count());
    }

    public function test_the_loop_starts_and_closes_itself_through_the_engine(): void
    {
        $engine = app(AssessmentEngine::class);
        $answers = $this->answers($engine);

        $first = $engine->create('owner', ['offer' => 'سيروم'], $answers, $this->user->id);
        $this->assertSame(3, AssessmentFollowup::query()->where('baseline_result_id', $first->id)->count());

        // Make the first point due, then take the assessment again.
        AssessmentFollowup::query()->where('baseline_result_id', $first->id)->where('days', 30)
            ->update(['due_at' => now()->subDay()]);

        $second = $engine->create('owner', ['offer' => 'سيروم'], $answers, $this->user->id);

        $closed = AssessmentFollowup::query()->whereNotNull('completed_at')->sole();
        $this->assertSame($second->id, (int) $closed->result_id);
        $this->assertSame(3, AssessmentFollowup::query()->where('baseline_result_id', $second->id)->count(), 'the retake opens its own loop');
    }

    public function test_the_delta_names_the_dimension_that_was_the_priority(): void
    {
        $loop = app(RemeasureLoop::class);
        $baseline = $this->diagnosis(overall: 48, scores: ['goal' => 30, 'audience' => 55, 'offer' => 60, 'channel' => 50, 'measure' => 45], weakest: 'goal');
        $latest = $this->diagnosis(overall: 60, scores: ['goal' => 48, 'audience' => 57, 'offer' => 60, 'channel' => 50, 'measure' => 45], weakest: 'measure');

        $rows = $loop->delta($baseline, $latest);

        $this->assertSame('goal', $rows[0]['key'], 'the dimension they were told to fix is reported first');
        $this->assertTrue($rows[0]['was_weakest']);
        $this->assertSame(18, $rows[0]['delta']);
        $this->assertStringContainsString('تحرّك ما كان يجب أن يتحرّك', $loop->verdict($rows, 12));
    }

    public function test_the_verdict_does_not_congratulate_a_number_that_stood_still(): void
    {
        $loop = app(RemeasureLoop::class);
        $baseline = $this->diagnosis(scores: ['goal' => 30, 'audience' => 55, 'offer' => 60, 'channel' => 50, 'measure' => 45], weakest: 'goal');
        $flat = $this->diagnosis(scores: ['goal' => 32, 'audience' => 55, 'offer' => 60, 'channel' => 50, 'measure' => 45], weakest: 'goal');

        $verdict = $loop->verdict($loop->delta($baseline, $flat), 1);

        $this->assertStringContainsString('لم يتحرّك', $verdict);
        $this->assertStringNotContainsString('تقدّم حقيقي', $verdict);

        $dropped = $this->diagnosis(scores: ['goal' => 18, 'audience' => 55, 'offer' => 60, 'channel' => 50, 'measure' => 45], weakest: 'goal');
        $this->assertStringContainsString('تراجع', $loop->verdict($loop->delta($baseline, $dropped), -6));
    }

    public function test_one_reminder_per_member_even_when_three_points_are_due(): void
    {
        $baseline = $this->diagnosis(daysAgo: 120);
        app(RemeasureLoop::class)->schedule($baseline);
        $this->assertSame(3, AssessmentFollowup::query()->where('due_at', '<=', now())->count());

        $this->artisan('platform:send-remeasure-reminders')->assertSuccessful();

        Notification::assertSentToTimes($this->user, RemeasureDue::class, 1);
        $this->assertSame(1, AssessmentFollowup::query()->whereNotNull('reminder_sent_at')->count());

        // A second run the next day does not nag again for the NEXT point either: three points fall within sixty
        // days of each other, so without a quiet period the loop would mail three times in three days.
        $this->artisan('platform:send-remeasure-reminders')->assertSuccessful();
        Notification::assertSentToTimes($this->user, RemeasureDue::class, 1);

        $this->travel(\App\Console\Commands\SendRemeasureReminders::QUIET_DAYS + 1)->days();
        $this->artisan('platform:send-remeasure-reminders')->assertSuccessful();
        Notification::assertSentToTimes($this->user, RemeasureDue::class, 2);
        $this->travelBack();

        $this->assertTrue(collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->contains(fn ($e) => str_contains($e->command ?? '', 'platform:send-remeasure-reminders')));
    }

    public function test_the_progress_page_shows_the_movement_and_the_next_point(): void
    {
        $loop = app(RemeasureLoop::class);
        $baseline = $this->diagnosis(overall: 48, scores: ['goal' => 30, 'audience' => 55, 'offer' => 60, 'channel' => 50, 'measure' => 45], weakest: 'goal', daysAgo: 95);
        $loop->schedule($baseline);
        $latest = $this->diagnosis(overall: 61, scores: ['goal' => 48, 'audience' => 57, 'offer' => 60, 'channel' => 50, 'measure' => 45], weakest: 'measure');
        $loop->close($latest);

        $this->actingAs($this->user)->get(route('app.progress'))->assertOk()
            ->assertSee('هل تحرّك الرقم؟')
            ->assertSee('+13 نقطة')
            ->assertSee('كانت الأولوية')
            ->assertSee('الهدف والرقم')
            ->assertSee('نقطة القياس التالية');
    }

    public function test_a_member_who_never_took_it_is_told_where_to_start(): void
    {
        $this->actingAs($this->user)->get(route('app.progress'))->assertOk()
            ->assertSee('لم تشخّص مشروعك بعد')
            ->assertSee(route('assess.start'), false);
    }

    /** The engine needs a full answer set; take whatever the seeded questions ask for. */
    private function answers(AssessmentEngine $engine): array
    {
        $answers = [];
        foreach (\App\Domain\Assessment\Models\AssessmentQuestion::query()->where('audience', 'owner')->get() as $question) {
            $options = (array) $question->options;
            $answers[$question->key] = (string) array_key_first($options);
        }

        return $answers;
    }
}
