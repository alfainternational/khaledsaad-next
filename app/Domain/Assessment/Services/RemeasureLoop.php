<?php

namespace App\Domain\Assessment\Services;

use App\Domain\Assessment\Models\AssessmentFollowup;
use App\Domain\Assessment\Models\AssessmentResult;
use Illuminate\Support\Collection;

/**
 * الحلقة المغلقة (spec 2026-11-09 §5، الموجة 2): قِس، ثم أعد القياس بعد 30 و60 و90 يوماً.
 *
 * A diagnosis nobody returns to is advice. This schedules the return, closes it when the member actually retakes the
 * assessment, and computes the delta per dimension — which is the only output here that is worth anything: not
 * "you scored 61", but "the thing you were told to fix moved 14 points and nothing else did".
 *
 * Nothing in this class guesses. The delta is arithmetic between two results the member produced themselves.
 */
class RemeasureLoop
{
    /** Three points scheduled the moment a baseline exists; a loop you have to remember to start never starts. */
    public function schedule(AssessmentResult $result): Collection
    {
        if ($result->user_id === null) {
            return collect(); // a visitor has nowhere to be reminded
        }

        return collect(AssessmentFollowup::DAYS)->map(fn (int $days) => AssessmentFollowup::query()->firstOrCreate(
            ['baseline_result_id' => $result->id, 'days' => $days],
            ['user_id' => $result->user_id, 'due_at' => $result->created_at->copy()->addDays($days)],
        ));
    }

    /**
     * Close the oldest open point with the retake the member just produced. One retake closes one point: closing all
     * three at once would turn a 90-day loop into a single afternoon and the deltas would compare nothing.
     */
    public function close(AssessmentResult $result): ?AssessmentFollowup
    {
        if ($result->user_id === null) {
            return null;
        }

        $followup = AssessmentFollowup::query()
            ->where('user_id', $result->user_id)
            ->whereNull('completed_at')
            ->where('baseline_result_id', '!=', $result->id)
            ->where('due_at', '<=', now())
            ->orderBy('due_at')
            ->first();

        if (! $followup) {
            return null;
        }

        $followup->forceFill([
            'result_id' => $result->id,
            'completed_at' => now(),
            'delta' => (int) $result->overall - (int) $followup->baseline->overall,
        ])->save();

        return $followup;
    }

    /**
     * Per-dimension movement between a baseline and a retake.
     *
     * @return array<int, array{key:string, label:string, from:int, to:int, delta:int, was_weakest:bool}>
     */
    public function delta(AssessmentResult $baseline, AssessmentResult $latest): array
    {
        $from = (array) $baseline->scores;
        $to = (array) $latest->scores;

        $rows = [];
        foreach ($from as $key => $value) {
            $rows[] = [
                'key' => $key,
                'label' => Catalog::DIMENSIONS[$key] ?? $key,
                'from' => (int) $value,
                'to' => (int) ($to[$key] ?? $value),
                'delta' => (int) ($to[$key] ?? $value) - (int) $value,
                'was_weakest' => $baseline->weakest_dimension === $key,
            ];
        }

        // The dimension they were told to fix first, so the one sentence this page owes them is about that one.
        usort($rows, fn ($a, $b) => [$b['was_weakest'], abs($b['delta'])] <=> [$a['was_weakest'], abs($a['delta'])]);

        return $rows;
    }

    /** One honest sentence about the movement, with no encouragement the numbers do not support. */
    public function verdict(array $rows, int $overallDelta): string
    {
        $weakest = collect($rows)->firstWhere('was_weakest');

        if ($weakest && $weakest['delta'] >= 10) {
            return 'تحرّك ما كان يجب أن يتحرّك: «'.$weakest['label'].'» ارتفع '.$weakest['delta'].' نقطة. أعد التشخيص بعد 30 يوماً أخرى وثبّت المكسب قبل فتح جبهة جديدة.';
        }
        if ($weakest && $weakest['delta'] <= -5) {
            return '«'.$weakest['label'].'» تراجع '.abs($weakest['delta']).' نقطة، وهو البند الذي كان أولوية. راجع ما نُفّذ فعلاً منه قبل إضافة أي شيء جديد.';
        }
        if ($weakest && $weakest['delta'] < 10) {
            return '«'.$weakest['label'].'» لم يتحرّك ('.($weakest['delta'] >= 0 ? '+' : '').$weakest['delta'].' نقطة). الأغلب أن الخطوة الأولى لم تُنفّذ لا أنها لم تنفع — ابدأ بها قبل أي شيء آخر.';
        }
        if ($overallDelta >= 8) {
            return 'المجموع ارتفع '.$overallDelta.' نقطة. هذا تقدّم حقيقي، لكن تأكد أن مصدره ما خططت له لا موسماً عابراً.';
        }

        return 'الصورة شبه ثابتة. التشخيص وحده لا يحرّك رقماً؛ الحركة تأتي من تنفيذ الأولوية الأولى، لا من إعادة القياس.';
    }
}
