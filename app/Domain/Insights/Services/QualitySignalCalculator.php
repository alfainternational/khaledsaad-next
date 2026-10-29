<?php

namespace App\Domain\Insights\Services;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Models\Question;
use App\Domain\Insights\Models\LearnerEvent;
use App\Domain\Insights\Models\QualitySignal;
use App\Domain\Lab\Models\Tool;
use Illuminate\Support\Facades\DB;

/**
 * إشارات الجودة (blueprint §6.1 row 3), computed hourly from behaviour:
 *  - drop-off inside a lesson at a specific part,
 *  - a decision question most learners get wrong,
 *  - a lab/tool that generates but whose output is not saved.
 * Impact = how many learners the signal touches; signals are upserted by (type, entity, part).
 */
class QualitySignalCalculator
{
    public function minSample(): int
    {
        return max(1, (int) config('platform.quality_min_sample', 5));
    }

    public function compute(int $days = 30): int
    {
        $since = now()->subDays($days);
        $found = [];
        $found = array_merge($found, $this->lessonDropoffs($since));
        $found = array_merge($found, $this->wrongMajorityQuestions($since));
        $found = array_merge($found, $this->unsavedLabs($since));

        $keys = [];
        foreach ($found as $signal) {
            $keys[] = $signal['type'].'|'.$signal['entity_type'].'|'.$signal['entity_id'].'|'.($signal['part'] ?? '');
            $existing = QualitySignal::query()->where('type', $signal['type'])->where('entity_type', $signal['entity_type'])
                ->where('entity_id', $signal['entity_id'])->where('part', $signal['part'] ?? null)->first();
            if ($existing) {
                if ($existing->status !== 'ignored') {
                    $existing->update(['value' => $signal['value'], 'impact' => $signal['impact'], 'summary' => $signal['summary'], 'meta' => $signal['meta'], 'computed_at' => now(), 'status' => 'open']);
                }
            } else {
                QualitySignal::query()->create($signal + ['status' => 'open', 'computed_at' => now()]);
            }
        }
        // Signals that no longer reproduce are resolved automatically.
        QualitySignal::query()->where('status', 'open')->get()->each(function (QualitySignal $s) use ($keys): void {
            if (! in_array($s->type.'|'.$s->entity_type.'|'.$s->entity_id.'|'.($s->part ?? ''), $keys, true)) {
                $s->update(['status' => 'resolved']);
            }
        });
        cache()->put('platform:quality_signals_computed_at', now()->timestamp, now()->addDay());

        return count($found);
    }

    /** Distinct learners reaching each part of each lesson; a part reached by < 50% of those who started is a drop-off. */
    public function partReach(Lesson $lesson, $since = null): array
    {
        $rows = LearnerEvent::query()->where('lesson_id', $lesson->id)->where('event', 'lesson.part_reached')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->selectRaw("part, count(distinct coalesce(cast(user_id as char), session_token)) as n")->groupBy('part')->pluck('n', 'part')->all();
        $started = LearnerEvent::query()->where('lesson_id', $lesson->id)->where('event', 'visit.lesson')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->selectRaw("count(distinct coalesce(cast(user_id as char), session_token)) as n")->value('n');
        $reach = [];
        foreach (array_keys(Lesson::PARTS) as $part) {
            $n = (int) ($rows[$part] ?? 0);
            $reach[$part] = ['count' => $n, 'percent' => $started > 0 ? (int) round($n / $started * 100) : 0];
        }

        return ['started' => (int) $started, 'parts' => $reach];
    }

    private function lessonDropoffs($since): array
    {
        $signals = [];
        $min = $this->minSample();
        foreach (Lesson::query()->whereNotNull('published_at')->get() as $lesson) {
            $reach = $this->partReach($lesson, $since);
            if ($reach['started'] < $min) {
                continue;
            }
            $previous = $reach['started'];
            foreach (Lesson::PARTS as $part => $label) {
                $n = $reach['parts'][$part]['count'];
                if ($previous >= $min && $n < $previous * 0.5) {
                    $signals[] = [
                        'type' => 'lesson_dropoff', 'entity_type' => 'lesson', 'entity_id' => $lesson->id, 'part' => $part,
                        'value' => round($n / $previous * 100, 2), 'impact' => $previous - $n,
                        'summary' => "تسرّب في درس «{$lesson->title}» عند جزء «{$label}»: وصل {$n} من {$previous} فقط.",
                        'meta' => ['reached' => $n, 'previous' => $previous, 'started' => $reach['started']],
                    ];
                    break;
                }
                $previous = $n;
            }
        }

        return $signals;
    }

    private function wrongMajorityQuestions($since): array
    {
        $min = $this->minSample();
        $rows = LearnerEvent::query()->where('event', 'question.answered')->where('created_at', '>=', $since)->whereNotNull('question_id')
            ->get(['question_id', 'user_id', 'session_token', 'meta'])
            ->groupBy('question_id');
        $signals = [];
        foreach ($rows as $questionId => $events) {
            // one vote per learner (first answer)
            $byLearner = $events->groupBy(fn ($e) => $e->user_id ? 'u'.$e->user_id : 's'.$e->session_token)->map->first();
            if ($byLearner->count() < $min) {
                continue;
            }
            $wrong = $byLearner->filter(fn ($e) => empty($e->meta['correct']))->count();
            if ($wrong / $byLearner->count() > 0.5) {
                $question = Question::query()->with('lesson')->find($questionId);
                if (! $question || $question->is_opening) {
                    continue;
                }
                $signals[] = [
                    'type' => 'question_wrong_majority', 'entity_type' => 'question', 'entity_id' => $question->id, 'part' => 'questions',
                    'value' => round($wrong / $byLearner->count() * 100, 2), 'impact' => $wrong,
                    'summary' => 'سؤال قرار يُخطئ فيه الأغلبية ('.round($wrong / $byLearner->count() * 100).'٪) في درس «'.($question->lesson?->title ?? '').'».',
                    'meta' => ['lesson_id' => $question->lesson_id, 'wrong' => $wrong, 'total' => $byLearner->count()],
                ];
            }
        }

        return $signals;
    }

    private function unsavedLabs($since): array
    {
        $min = $this->minSample();
        $signals = [];
        $generated = LearnerEvent::query()->where('event', 'lesson.lab_generated')->where('created_at', '>=', $since)->whereNotNull('lesson_id')
            ->selectRaw("lesson_id, count(distinct coalesce(cast(user_id as char), session_token)) as n")->groupBy('lesson_id')->pluck('n', 'lesson_id');
        $saved = LearnerEvent::query()->where('event', 'asset.saved')->where('created_at', '>=', $since)->whereNotNull('lesson_id')
            ->selectRaw("lesson_id, count(distinct coalesce(cast(user_id as char), session_token)) as n")->groupBy('lesson_id')->pluck('n', 'lesson_id');
        foreach ($generated as $lessonId => $n) {
            if ($n < $min) {
                continue;
            }
            $s = (int) ($saved[$lessonId] ?? 0);
            if ($s / $n < 0.2) {
                $lesson = Lesson::query()->find($lessonId);
                $signals[] = [
                    'type' => 'lab_not_saved', 'entity_type' => 'lesson', 'entity_id' => $lessonId, 'part' => 'lab',
                    'value' => round($s / $n * 100, 2), 'impact' => $n - $s,
                    'summary' => 'مختبر درس «'.($lesson?->title ?? '').'» يولّد ('.$n.') ولا يُحفظ مخرجه إلا ('.$s.').',
                    'meta' => ['generated' => $n, 'saved' => $s],
                ];
            }
        }
        // Tools on /tools: generated runs vs. outputs kept (same rule, stage 2 will refine).
        // Public tools only: lesson-lab saves are stored as runs of the unpublished «مختبر الدرس» tool and are covered above.
        $runs = DB::table('tool_runs')->join('tools', 'tools.id', '=', 'tool_runs.tool_id')->where('tools.is_published', true)
            ->where('tool_runs.created_at', '>=', $since)->where('tool_runs.status', 'completed')->selectRaw('tool_runs.tool_id, count(*) as n')->groupBy('tool_runs.tool_id')->pluck('n', 'tool_id');
        foreach ($runs as $toolId => $n) {
            $kept = DB::table('tool_outputs')->join('tool_runs', 'tool_runs.id', '=', 'tool_outputs.tool_run_id')->where('tool_runs.tool_id', $toolId)->where('tool_outputs.created_at', '>=', $since)->whereNull('tool_outputs.lesson_id')->count();
            if ($n >= $min && $kept / $n < 0.2) {
                $tool = Tool::query()->find($toolId);
                $signals[] = [
                    'type' => 'tool_not_saved', 'entity_type' => 'tool', 'entity_id' => $toolId, 'part' => null,
                    'value' => round($kept / $n * 100, 2), 'impact' => $n - $kept,
                    'summary' => 'أداة «'.($tool?->name ?? '').'» تولّد ولا يُحفظ مخرجها.', 'meta' => ['runs' => $n, 'kept' => $kept],
                ];
            }
        }

        return $signals;
    }
}
