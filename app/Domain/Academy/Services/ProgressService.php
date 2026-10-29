<?php

namespace App\Domain\Academy\Services;

use App\Domain\Academy\Models\Certificate;
use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Models\RealWorldCheckin;
use App\Domain\Insights\Models\LearnerEvent;
use App\Domain\Lab\Models\ToolOutput;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Progress, completion rules and certificates (blueprint §4.5 "الإكمال", §5.1, §5.2).
 * Completion of a lesson = saved asset + answered decision questions. Course certificate = all lessons + one recorded real result.
 */
class ProgressService
{
    public function completedLessonIds(User $user): Collection
    {
        return DB::table('activity_completions')->where('user_id', $user->id)->whereNotNull('lesson_id')->pluck('completed_at', 'lesson_id');
    }

    public function courseProgress(User $user, Course $course): array
    {
        $lessons = $course->relationLoaded('lessons') ? $course->lessons : $course->lessons()->get();
        $published = $lessons->filter(fn (Lesson $l) => $l->isPublished())->values();
        $completed = $this->completedLessonIds($user);
        $done = $published->filter(fn (Lesson $l) => isset($completed[$l->id]))->count();
        $next = $published->first(fn (Lesson $l) => ! isset($completed[$l->id]));

        return [
            'total' => $published->count(),
            'done' => $done,
            'percent' => $published->count() > 0 ? (int) round($done / $published->count() * 100) : 0,
            'next' => $next,
            'completed_ids' => $completed,
            'is_complete' => $published->count() > 0 && $done === $published->count(),
        ];
    }

    public function hasAnsweredQuestions(User $user, Lesson $lesson): bool
    {
        $required = $lesson->questions()->where('is_opening', false)->count();
        if ($required === 0) {
            return true;
        }
        $answered = LearnerEvent::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)->where('event', 'question.answered')
            ->whereIn('question_id', $lesson->questions()->where('is_opening', false)->select('id'))->distinct()->count('question_id');

        return $answered >= $required;
    }

    public function hasSavedAsset(User $user, Lesson $lesson): bool
    {
        return ToolOutput::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)->exists();
    }

    /** Mark the lesson complete when both conditions hold; then try to issue the course certificate. */
    public function maybeComplete(User $user, Lesson $lesson): bool
    {
        if (! $this->hasSavedAsset($user, $lesson) || ! $this->hasAnsweredQuestions($user, $lesson)) {
            return false;
        }
        $already = DB::table('activity_completions')->where('user_id', $user->id)->where('lesson_id', $lesson->id)->exists();
        if (! $already) {
            DB::table('activity_completions')->insert([
                'user_id' => $user->id, 'lesson_id' => $lesson->id, 'activity_id' => null, 'completed_at' => now(),
                'deliverable' => json_encode(['auto' => 'asset+questions'], JSON_UNESCAPED_UNICODE),
            ]);
            LearnerEvent::query()->create(['user_id' => $user->id, 'event' => 'lesson.completed', 'lesson_id' => $lesson->id, 'created_at' => now()]);
        }
        $this->maybeIssueCertificate($user, $lesson->course);

        return true;
    }

    public function maybeIssueCertificate(User $user, Course $course): bool
    {
        if (! $course->is_published) {
            return false;
        }
        $progress = $this->courseProgress($user, $course);
        if (! $progress['is_complete']) {
            return false;
        }
        $hasRealResult = RealWorldCheckin::query()->where('user_id', $user->id)->where('mode', 'commit')->whereNotNull('result')->whereNotNull('recorded_at')
            ->whereIn('lesson_id', $course->lessons()->pluck('id'))->get()
            ->contains(fn (RealWorldCheckin $checkin) => collect($checkin->result ?? [])->except(['note', 'practice'])->contains(fn ($value) => filled($value)));
        if (! $hasRealResult) {
            return false;
        }
        // firstOrCreate handles concurrent issuance via the existing user/course unique key.
        $certificate = Certificate::query()->firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['verification_code' => (string) Str::uuid(), 'issued_at' => now()]
        );

        return $certificate->revoked_at === null;
    }

    /** The lesson the learner touched last (for "أكمل من حيث توقفت"). */
    public function lastLesson(User $user): ?Lesson
    {
        $id = LearnerEvent::query()->where('user_id', $user->id)->whereNotNull('lesson_id')->latest('created_at')->value('lesson_id');

        return $id ? Lesson::query()->with('course')->find($id) : null;
    }

    public function coursesStarted(User $user): Collection
    {
        $lessonIds = LearnerEvent::query()->where('user_id', $user->id)->whereNotNull('lesson_id')->distinct()->pluck('lesson_id');
        $courseIds = Lesson::query()->whereIn('id', $lessonIds)->distinct()->pluck('course_id');

        return Course::query()->whereIn('id', $courseIds)->with('lessons')->orderBy('position')->get();
    }
}
