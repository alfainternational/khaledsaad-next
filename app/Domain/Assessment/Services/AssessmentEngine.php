<?php

namespace App\Domain\Assessment\Services;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Assessment\Models\AssessmentQuestion;
use App\Domain\Assessment\Models\AssessmentResult;
use App\Domain\Assessment\Models\AssessmentRule;
use App\Support\Markdown;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * تشخيص مشروعك (blueprint §4.2): questions phrased from the project profile, dimension scores,
 * level, weakest dimension, routing rule → priorities map, benchmark against similar projects.
 */
class AssessmentEngine
{
    public const BENCHMARK_MIN_SAMPLE = 30;

    /** Questions for the audience with {{placeholders}} filled from the project snapshot. */
    public function questionsFor(string $audience, array $project): Collection
    {
        $values = $this->placeholders($project);

        return AssessmentQuestion::query()->where('audience', $audience)->where('is_active', true)->orderBy('position')->get()
            ->map(function (AssessmentQuestion $q) use ($values) {
                $q->setAttribute('rendered', Markdown::fill($q->prompt, $values));

                return $q;
            });
    }

    public function placeholders(array $project): array
    {
        $budget = (int) ($project['budget'] ?? 0);

        return [
            'offer' => Str::limit(trim((string) ($project['offer'] ?? '')), 60, '') ?: 'ما تبيعه',
            'budget' => $budget > 0 ? number_format($budget).' ريال' : 'بلا ميزانية إعلانية',
            'budget_number' => $budget,
            'channel' => Catalog::label(Catalog::CHANNELS, $project['channel'] ?? null, 'قناتك الحالية'),
            'goal' => Catalog::label(Catalog::GOALS, $project['goal'] ?? null, 'هدفك'),
            'goal_number' => (int) ($project['goal_number'] ?? 0) > 0 ? number_format((int) $project['goal_number']) : 'رقمك المستهدف',
            'sector' => Catalog::label(Catalog::SECTORS, $project['sector'] ?? null, 'قطاعك'),
            'city' => trim((string) ($project['city'] ?? '')) ?: 'مدينتك',
        ];
    }

    /** @param array<int,int> $answers question id → option index */
    public function score(string $audience, array $answers): array
    {
        $questions = AssessmentQuestion::query()->where('audience', $audience)->where('is_active', true)->get()->keyBy('id');
        $sums = [];
        $max = [];
        foreach ($questions as $id => $question) {
            $options = $question->options ?? [];
            $weights = array_map(fn ($o) => (int) ($o['weight'] ?? 0), $options);
            $top = $weights === [] ? 0 : max($weights);
            $chosen = $answers[$id] ?? null;
            $weight = $chosen !== null && isset($options[$chosen]) ? (int) ($options[$chosen]['weight'] ?? 0) : 0;
            $sums[$question->dimension] = ($sums[$question->dimension] ?? 0) + $weight;
            $max[$question->dimension] = ($max[$question->dimension] ?? 0) + $top;
        }
        $scores = [];
        foreach (Catalog::DIMENSIONS as $key => $label) {
            $scores[$key] = isset($max[$key]) && $max[$key] > 0 ? (int) round($sums[$key] / $max[$key] * 100) : 0;
        }
        $overall = (int) round(array_sum($scores) / max(1, count($scores)));
        asort($scores);
        $weakest = (string) array_key_first($scores);
        ksort($scores);

        return ['scores' => $scores, 'overall' => $overall, 'level' => Catalog::levelFor($overall), 'weakest' => $weakest];
    }

    public function rule(string $audience, string $level, string $weakest): ?AssessmentRule
    {
        return AssessmentRule::query()->where(compact('audience', 'level'))->where('weakest_dimension', $weakest)->with('lesson.course')->first()
            ?? AssessmentRule::query()->where('audience', $audience)->where('weakest_dimension', $weakest)->with('lesson.course')->first();
    }

    /** Build the priorities map (3 items: now / this month / after) from the rule, resolving lesson/course links. */
    public function priorities(?AssessmentRule $rule, array $project): array
    {
        $values = $this->placeholders($project);
        $items = [];
        foreach (array_slice($rule?->priorities ?? [], 0, 3) as $i => $priority) {
            $link = null;
            $linkLabel = null;
            if (! empty($priority['lesson'])) {
                $lesson = Lesson::query()->where('slug', $priority['lesson'])->with('course')->first();
                if ($lesson) {
                    $link = route('academy.lesson', [$lesson->course, $lesson]);
                    $linkLabel = 'الدرس: '.$lesson->title;
                }
            } elseif (! empty($priority['course'])) {
                $course = Course::query()->where('slug', $priority['course'])->first();
                if ($course) {
                    $link = $course->is_published ? route('academy.show', $course) : route('academy.index');
                    $linkLabel = 'المسار: '.$course->title.($course->is_published ? '' : ' (قريباً)');
                }
            }
            $items[] = [
                'when' => ['الآن', 'هذا الشهر', 'بعدها'][$i] ?? 'بعدها',
                'title' => Markdown::fill($priority['title'] ?? '', $values),
                'reason' => Markdown::fill($priority['reason'] ?? '', $values),
                'link' => $link, 'link_label' => $linkLabel,
            ];
        }

        return $items;
    }

    public function diagnosisSentence(array $project, array $scored): string
    {
        $v = $this->placeholders($project);
        $level = Catalog::LEVELS[$scored['level']];
        $weak = Catalog::DIMENSIONS[$scored['weakest']];
        $budget = (int) ($project['budget'] ?? 0) > 0 ? "بميزانية {$v['budget']} شهرياً" : 'بلا ميزانية إعلانية';
        $channel = ($project['channel'] ?? 'none') !== 'none' ? "وقناة أساسية هي {$v['channel']}" : 'وبلا قناة ثابتة بعد';

        return "مستواك الحالي {$level}: مشروعك يعمل {$budget} {$channel}، وأضعف بُعد لديك هو «{$weak}»؛ هناك تبدأ أول خطوة لأن تحسينه يرفع عائد كل ما بعده.";
    }

    public function benchmarkGroup(string $audience, array $project): string
    {
        return $audience.':'.($project['sector'] ?? 'other').':'.Catalog::budgetBand((int) ($project['budget'] ?? 0));
    }

    /** Average dimension scores of similar projects when the sample is large enough (≥30). */
    public function benchmark(string $group): ?array
    {
        $rows = AssessmentResult::query()->where('benchmark_group', $group)->latest()->limit(500)->get(['scores']);
        if ($rows->count() < self::BENCHMARK_MIN_SAMPLE) {
            return null;
        }
        $avg = [];
        foreach (array_keys(Catalog::DIMENSIONS) as $key) {
            $avg[$key] = (int) round($rows->avg(fn ($r) => (int) ($r->scores[$key] ?? 0)));
        }

        return ['sample' => $rows->count(), 'scores' => $avg];
    }

    public function similarCount(string $group): int
    {
        return AssessmentResult::query()->where('benchmark_group', $group)->count();
    }

    /** Average improvement (overall points) for learners who assessed twice around a completed course. */
    public function averageImprovement(): ?array
    {
        $pairs = DB::table('assessment_results as a')
            ->join('assessment_results as b', function ($join): void {
                $join->on('a.user_id', '=', 'b.user_id')->on('b.id', '>', 'a.id');
            })
            ->whereNotNull('a.user_id')
            ->selectRaw('a.user_id, min(a.overall) as first_score, max(b.overall) as last_score')
            ->groupBy('a.user_id')
            ->get();
        if ($pairs->count() < 1) {
            return null;
        }

        return ['sample' => $pairs->count(), 'points' => (int) round($pairs->avg(fn ($p) => (int) $p->last_score - (int) $p->first_score))];
    }

    public function create(string $audience, array $project, array $answers, ?int $userId, ?int $projectId = null): AssessmentResult
    {
        $scored = $this->score($audience, $answers);
        $rule = $this->rule($audience, $scored['level'], $scored['weakest']);

        return AssessmentResult::query()->create([
            'user_id' => $userId,
            'project_id' => $projectId,
            'audience' => $audience,
            'project_snapshot' => $project,
            'answers' => $answers,
            'scores' => $scored['scores'],
            'overall' => $scored['overall'],
            'level' => $scored['level'],
            'weakest_dimension' => $scored['weakest'],
            'priorities' => $this->priorities($rule, $project),
            'start_lesson_id' => $rule?->lesson_id,
            'benchmark_group' => $this->benchmarkGroup($audience, $project),
            'session_token' => Str::random(48),
        ]);
    }

    public function prioritiesMarkdown(AssessmentResult $result): string
    {
        $v = $this->placeholders($result->project_snapshot);
        $md = "# خريطة أولويات مشروعك\n\n**المشروع:** {$v['offer']} · **الميزانية:** {$v['budget']} · **القناة:** {$v['channel']}\n\n**المستوى:** ".Catalog::LEVELS[$result->level]." (".$result->overall."/100)\n\n";
        foreach ($result->priorities as $i => $p) {
            $md .= '## '.($i + 1).'. '.$p['when'].': '.$p['title']."\n\n".$p['reason']."\n\n";
            if (! empty($p['link'])) {
                $md .= '- '.$p['link_label'].': '.$p['link']."\n\n";
            }
        }
        $md .= "## أبعادك\n\n| البُعد | الدرجة |\n|---|---|\n";
        foreach (Catalog::DIMENSIONS as $key => $label) {
            $md .= "| {$label} | ".($result->scores[$key] ?? 0)." |\n";
        }

        return $md."\nأُنشئت على منصة أثر — ".route('assess.start');
    }
}
