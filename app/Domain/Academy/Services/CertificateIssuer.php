<?php

namespace App\Domain\Academy\Services;

use App\Domain\Academy\Models\Certificate;
use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\RealWorldCheckin;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Certificate eligibility and issuance (blueprint §4.5 "الإكمال", §4.13, §5.2).
 * Earned = every published lesson complete + one recorded real result in the same course.
 * Issued = earned + the learner's workspace has an active paid plan (the same gate that grants AI credits).
 */
class CertificateIssuer
{
    public function __construct(private readonly ProgressService $progress) {}

    /** All published lessons done and at least one committed real result (zero counts, blank does not) in this course. */
    public function earned(User $user, Course $course): bool
    {
        if (! $course->is_published || ! $this->progress->courseProgress($user, $course)['is_complete']) {
            return false;
        }

        return RealWorldCheckin::query()->where('user_id', $user->id)->where('mode', 'commit')->whereNotNull('result')->whereNotNull('recorded_at')
            ->whereIn('lesson_id', $course->lessons()->select('id'))->get()
            ->contains(fn (RealWorldCheckin $checkin) => collect($checkin->result ?? [])->except(['note', 'practice'])->contains(fn ($value) => filled($value)));
    }

    /** The paid-plan gate: an active subscription on any of the learner's workspaces that has not ended. */
    public function hasPaidPlan(User $user): bool
    {
        $workspaceIds = $user->workspaces()->pluck('workspaces.id');
        if ($workspaceIds->isEmpty()) {
            return false;
        }

        return DB::table('subscriptions')->whereIn('workspace_id', $workspaceIds)->where('status', 'active')->where('plan', '!=', 'free')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))->exists();
    }

    /** Earned but not issued: the progress page shows this with the upgrade link. */
    public function pendingPlan(User $user, Course $course): bool
    {
        return $this->earned($user, $course) && ! $this->hasPaidPlan($user) && ! Certificate::query()->where('user_id', $user->id)->where('course_id', $course->id)->exists();
    }

    /** Issue once; a revoked certificate is never reissued. Returns true when a valid certificate exists after the call. */
    public function issue(User $user, Course $course): bool
    {
        if (! $this->earned($user, $course)) {
            return false;
        }
        $existing = Certificate::query()->where('user_id', $user->id)->where('course_id', $course->id)->first();
        if ($existing) {
            return $existing->revoked_at === null;
        }
        if (! $this->hasPaidPlan($user)) {
            return false;
        }
        // firstOrCreate handles concurrent issuance via the user/course unique key.
        $certificate = Certificate::query()->firstOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id],
            ['verification_code' => (string) Str::uuid(), 'issued_at' => now()]
        );

        return $certificate->revoked_at === null;
    }

    /** Issue every earned-but-missing certificate (called when a plan becomes active). */
    public function issuePending(User $user): int
    {
        $issued = 0;
        $completedLessonCourses = DB::table('activity_completions')->join('lessons', 'lessons.id', '=', 'activity_completions.lesson_id')
            ->where('activity_completions.user_id', $user->id)->distinct()->pluck('lessons.course_id');
        foreach (Course::query()->whereIn('id', $completedLessonCourses)->where('is_published', true)->get() as $course) {
            $before = Certificate::query()->where('user_id', $user->id)->where('course_id', $course->id)->exists();
            if (! $before && $this->issue($user, $course)) {
                $issued++;
            }
        }

        return $issued;
    }
}
