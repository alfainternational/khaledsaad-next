<?php

namespace App\Domain\Insights\Services;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Insights\Models\ContentReport;
use App\Domain\Insights\Models\LearnerEvent;
use App\Domain\Insights\Models\LearnerNudge;
use App\Domain\Insights\Models\QualitySignal;
use App\Domain\Lab\Services\StudioMetrics;
use Illuminate\Support\Facades\DB;

/**
 * غرفة التحكم (blueprint §6.1): the five journey columns, the lesson pipeline heat-map, learner success box and credit economy.
 */
class ControlRoomMetrics
{
    public function journey(int $days): array
    {
        $since = now()->subDays($days);
        $distinct = fn (array $events) => (int) LearnerEvent::query()->whereIn('event', $events)->where('created_at', '>=', $since)
            ->selectRaw("count(distinct coalesce(cast(user_id as char), session_token)) as n")->value('n');
        $counts = [
            'discover' => $distinct(['visit.home', 'assessment.completed', 'home.first_step']),
            'learn' => $distinct(['visit.lesson']),
            'apply' => $distinct(['lesson.lab_generated']),
            'save' => $distinct(['asset.saved']),
            'grow' => $distinct(['checkin.recorded']),
        ];
        $labels = ['discover' => 'اكتشف', 'learn' => 'تعلّم', 'apply' => 'طبّق', 'save' => 'احفظ', 'grow' => 'طوّر'];
        $actions = [
            'discover' => ['ضبط نموذج «جرّب على مشروعك» والأرقام الحيّة في الرئيسية', route('home')],
            'learn' => ['افتح الدرس الأعلى تسرّباً في الاستوديو', route('admin.academy.index')],
            'apply' => ['راجع افتراضيات مختبر الدرس الأول (تعبئة من المشروع)', route('admin.academy.index')],
            'save' => ['أبرز «حفظ في مشروعي» وبوابة التسجيل بعد التوليد', route('admin.academy.index')],
            'grow' => ['ذكّر المتوقفين عن تسجيل النتيجة الواقعية', route('admin.index').'#success'],
        ];
        $columns = [];
        $previous = null;
        foreach ($counts as $key => $n) {
            $rate = $previous === null ? null : ($previous > 0 ? (int) round($n / $previous * 100) : 0);
            $status = $rate === null ? ($n > 0 ? 'ok' : 'watch') : match (true) {
                $previous < 5 => 'watch',
                $rate >= 40 => 'ok',
                $rate >= 20 => 'watch',
                default => 'leak',
            };
            $columns[] = ['key' => $key, 'label' => $labels[$key], 'count' => $n, 'rate' => $rate, 'status' => $status, 'status_label' => ['ok' => 'سليم', 'watch' => 'انتبه', 'leak' => 'تسرّب'][$status], 'action' => $actions[$key]];
            $previous = $n;
        }

        return $columns;
    }

    public function signals(int $limit = 12)
    {
        return QualitySignal::query()->where('status', 'open')->orderByDesc('impact')->orderByDesc('computed_at')->limit($limit)->get()->map(function (QualitySignal $s) {
            $s->setAttribute('action_url', match ($s->entity_type) {
                'lesson' => route('admin.academy.lessons.edit', ['lesson' => $s->entity_id, 'part' => $s->part]),
                'question' => route('admin.academy.lessons.edit', ['lesson' => $s->meta['lesson_id'] ?? 0, 'part' => 'questions']).'#question-'.$s->entity_id,
                'tool' => route('tools.index'),
                'content' => route('admin.content.edit', ['contentItem' => $s->entity_id]).'#sources-fieldset',
                default => route('admin.index'),
            });

            return $s;
        });
    }

    /** 12 courses × lessons; cell state = complete / signal / draft / empty. */
    public function pipeline(): array
    {
        $openSignals = QualitySignal::query()->where('status', 'open')->get();
        $signalLessonIds = $openSignals->flatMap(fn ($s) => $s->entity_type === 'lesson' ? [$s->entity_id] : [$s->meta['lesson_id'] ?? null])->filter()->unique()->all();
        $rows = [];
        foreach (Course::query()->orderBy('position')->with('lessons.questions')->get() as $course) {
            $cells = [];
            foreach ($course->lessons as $lesson) {
                $parts = $lesson->partCompleteness();
                $complete = count(array_filter($parts));
                $state = match (true) {
                    in_array($lesson->id, $signalLessonIds, true) => 'signal',
                    $complete === count($parts) && $lesson->isPublished() => 'complete',
                    default => 'draft',
                };
                $cells[] = ['lesson' => $lesson, 'state' => $state, 'complete' => $complete, 'total' => count($parts)];
            }
            $rows[] = ['course' => $course, 'cells' => $cells];
        }

        return $rows;
    }

    public function successBox(): array
    {
        $stalled = DB::table('learner_events as e')->join('users', 'users.id', '=', 'e.user_id')->whereNotNull('e.lesson_id')
            ->selectRaw('e.user_id, users.name, users.email, max(e.created_at) as last_seen')->groupBy('e.user_id', 'users.name', 'users.email')
            ->havingRaw('max(e.created_at) < ?', [now()->subDays(7)->toDateTimeString()])->orderBy('last_seen')->limit(10)->get();

        // Each stalled learner with the lesson they stopped in and the last «أصلك ينتظرك» reminder about it.
        $cooldown = now()->subDays(LearnerNudge::COOLDOWN_DAYS);
        foreach ($stalled as $row) {
            $row->lesson_id = LearnerEvent::query()->where('user_id', $row->user_id)->whereNotNull('lesson_id')->orderByDesc('created_at')->orderByDesc('id')->value('lesson_id');
            $row->last_nudge = LearnerNudge::query()->where('user_id', $row->user_id)->where('lesson_id', $row->lesson_id)->latest('id')->first();
            $row->can_nudge = $row->lesson_id !== null && ! ($row->last_nudge && $row->last_nudge->status !== 'failed' && $row->last_nudge->created_at->gte($cooldown));
        }
        $this->recordNudgeReturns();
        $recent = LearnerNudge::query()->where('created_at', '>=', now()->subDays(30));

        return [
            'stalled' => $stalled,
            'reports' => ContentReport::query()->with(['lesson', 'contentItem'])->where('status', 'open')->latest()->limit(10)->get(),
            'nudges' => ['sent' => (clone $recent)->where('status', '!=', 'failed')->count(), 'returned' => (clone $recent)->whereNotNull('returned_at')->count()],
        ];
    }

    /** The behavioural result of a nudge (§6.6): the learner's first lesson activity after it. Written once, when it first shows up. */
    public function recordNudgeReturns(): int
    {
        $updated = 0;
        $pending = LearnerNudge::query()->whereNull('returned_at')->where('status', '!=', 'failed')->where('created_at', '>=', now()->subDays(30))->limit(200)->get();
        foreach ($pending as $nudge) {
            $first = LearnerEvent::query()->where('user_id', $nudge->user_id)->whereNotNull('lesson_id')->where('created_at', '>', $nudge->created_at)->min('created_at');
            if ($first !== null) {
                $nudge->update(['returned_at' => $first]);
                $updated++;
            }
        }

        return $updated;
    }

    public function creditEconomy(int $days): array
    {
        $since = now()->subDays($days);
        $aiRuns = DB::table('tool_runs')->join('tools', 'tools.id', '=', 'tool_runs.tool_id')->where('tool_runs.used_credits', '>', 0)->where('tool_runs.created_at', '>=', $since)->where('tool_runs.status', 'completed');
        $runs = (clone $aiRuns)->count();
        $credits = (int) (clone $aiRuns)->sum('tool_runs.used_credits');
        $revenue = (int) DB::table('payments')->where('status', 'paid')->where('created_at', '>=', $since)->sum('amount');
        $saved = DB::table('tool_outputs')->where('created_at', '>=', $since)->count();
        // The most expensive tool of the period = the one that consumed the most credits; its save rate tells whether that spend ends in kept assets.
        $topTool = DB::table('tool_runs')->join('tools', 'tools.id', '=', 'tool_runs.tool_id')->where('tool_runs.created_at', '>=', $since)->where('tool_runs.used_credits', '>', 0)
            ->selectRaw('tools.id, tools.name, sum(tool_runs.used_credits) as credits, count(*) as runs')->groupBy('tools.id', 'tools.name')->orderByDesc('credits')->orderBy('tools.id')->first();

        return [
            'ai_runs' => $runs, 'credits' => $credits, 'revenue_cents' => $revenue, 'saved' => $saved, 'cost_per_saved' => $saved > 0 ? round($credits / $saved, 1) : null, 'top_tool' => $topTool,
            'top_tool_save' => $topTool ? app(StudioMetrics::class)->toolSaveRate((int) $topTool->id, $since) : null,
        ];
    }

    public function operations(): array
    {
        $age = fn (string $key) => ($ts = (int) cache()->get($key, 0)) > 0 ? now()->timestamp - $ts : null;
        $newUsers = DB::table('users')->where('created_at', '>=', now()->subDays(30))->count();
        $verified = DB::table('users')->where('created_at', '>=', now()->subDays(30))->whereNotNull('email_verified_at')->count();

        return [
            'scheduler_age' => $age('platform:scheduler_heartbeat'), 'worker_age' => $age('platform:worker_heartbeat'),
            'queue_pending' => rescue(fn () => DB::table('jobs')->count(), 0), 'queue_failed' => rescue(fn () => DB::table('failed_jobs')->count(), 0),
            'backup' => (string) config('platform.backup_marker') !== '' && is_file((string) config('platform.backup_marker')) ? date('Y/m/d H:i', filemtime((string) config('platform.backup_marker'))) : null,
            'paypal' => filled(config('services.paypal.client_id')), 'ai' => app(\App\Domain\Lab\Services\AiGateway::class)->enabled(),
            'new_users' => $newUsers, 'verified_rate' => $newUsers > 0 ? (int) round($verified / $newUsers * 100) : null,
        ];
    }

    /** Entities for the ⌘K palette. */
    public function palette(): array
    {
        $items = [];
        foreach (Course::query()->orderBy('position')->get() as $course) {
            $items[] = ['label' => $course->title, 'kind' => 'مسار', 'url' => route('admin.academy.index').'#course-'.$course->id];
        }
        foreach (Lesson::query()->with('course')->get() as $lesson) {
            $items[] = ['label' => $lesson->title, 'kind' => 'درس · '.$lesson->course->title, 'url' => route('admin.academy.lessons.edit', $lesson)];
        }
        foreach (DB::table('tools')->get() as $tool) {
            $items[] = ['label' => $tool->name, 'kind' => 'أداة', 'url' => route('tools.show', $tool->slug)];
        }
        $items[] = ['label' => 'أسئلة التشخيص وقواعد التوجيه', 'kind' => 'إدارة', 'url' => route('admin.assessment.index')];
        $items[] = ['label' => 'المحتوى والمجلة', 'kind' => 'إدارة', 'url' => route('admin.content.index')];
        $items[] = ['label' => 'التشغيل والتحويلات والخطط', 'kind' => 'إدارة', 'url' => route('admin.operations')];

        return $items;
    }
}
