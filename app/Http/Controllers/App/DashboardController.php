<?php

namespace App\Http\Controllers\App;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\LearningPath;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Models\RealWorldCheckin;
use App\Domain\Academy\Services\CertificateIssuer;
use App\Domain\Academy\Services\ProgressService;
use App\Domain\Assessment\Models\AssessmentResult;
use App\Domain\Assessment\Services\Catalog;
use App\Domain\Insights\Models\LearnerEvent;
use App\Domain\Lab\Models\ToolOutput;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * لوحتي — /dashboard (blueprint §5.1) and تقدمي — /app/progress (§5.2).
 */
class DashboardController extends Controller
{
    public function __construct(private readonly ProgressService $progress) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $workspaces = $user->workspaces()->with('projects')->get();
        $workspaceIds = $workspaces->pluck('id');
        $last = $this->progress->lastLesson($user);
        $started = $this->progress->coursesStarted($user)->map(fn (Course $c) => ['course' => $c, 'progress' => $this->progress->courseProgress($user, $c)]);
        $assessment = AssessmentResult::query()->where('user_id', $user->id)->latest()->first();
        $assets = ToolOutput::query()->where('user_id', $user->id)->with('lesson')->latest()->limit(5)->get();
        $checkins = RealWorldCheckin::query()->where('user_id', $user->id)->with('lesson.course')->latest()->get();
        $pending = $checkins->where('mode', 'commit')->whereNull('recorded_at');
        $completedCount = DB::table('activity_completions')->where('user_id', $user->id)->whereNotNull('lesson_id')->count();
        $balance = (int) DB::table('credit_entries')->whereIn('workspace_id', $workspaceIds)->sum('amount');
        $subscription = DB::table('subscriptions')->whereIn('workspace_id', $workspaceIds)->first();
        $activity = LearnerEvent::query()->where('user_id', $user->id)->with('lesson')->latest('created_at')->limit(8)->get();

        // Derived weekly plan: next lesson, due check-ins, re-assess, commit after completion.
        $plan = [];
        $nextLesson = $started->first(fn ($s) => $s['progress']['next'])['progress']['next'] ?? null;
        if (! $last && ! $assessment) {
            $plan[] = ['label' => 'ابدأ بتشخيص مشروعك (10 دقائق)', 'url' => route('assess.start')];
        }
        if ($nextLesson) {
            $plan[] = ['label' => 'أكمل الدرس: '.$nextLesson->title, 'url' => route('academy.lesson', [$nextLesson->course, $nextLesson])];
        } elseif ($assessment && $assessment->startLesson) {
            $plan[] = ['label' => 'ابدأ بالأولوية 1: '.$assessment->startLesson->title, 'url' => route('academy.lesson', [$assessment->startLesson->course, $assessment->startLesson])];
        }
        foreach ($pending->take(2) as $c) {
            $plan[] = ['label' => 'سجّل نتيجتك الواقعية لدرس «'.$c->lesson->title.'»'.($c->due_at ? ' (موعدها '.$c->due_at->format('Y/m/d').')' : ''), 'url' => route('academy.lesson', [$c->lesson->course, $c->lesson]).'#part-apply'];
        }
        foreach ($started as $s) {
            if ($s['progress']['is_complete'] && (! $assessment || $assessment->created_at->lt(now()->subDays(14)))) {
                $plan[] = ['label' => 'أعد التشخيص لترى أثر مسار «'.$s['course']->title.'» رقماً', 'url' => route('assess.start')];
                break;
            }
        }
        $completedWithoutCommit = DB::table('activity_completions')->where('user_id', $user->id)->whereNotNull('lesson_id')->pluck('lesson_id')->diff($checkins->pluck('lesson_id'));
        if ($completedWithoutCommit->isNotEmpty()) {
            $lesson = Lesson::query()->with('course')->find($completedWithoutCommit->first());
            if ($lesson) {
                $plan[] = ['label' => 'التزم بفعل واقعي لدرس «'.$lesson->title.'»', 'url' => route('academy.lesson', [$lesson->course, $lesson]).'#part-apply'];
            }
        }

        // Next certificate: the started course closest to completion + the real-result requirement.
        $nextCertificate = $started->sortByDesc(fn ($s) => $s['progress']['percent'])->first();
        $hasRealResult = $nextCertificate ? $checkins->where('mode', 'commit')->whereNotNull('recorded_at')->whereIn('lesson_id', $nextCertificate['course']->lessons->pluck('id'))->isNotEmpty() : false;
        $certificates = DB::table('certificates')->where('user_id', $user->id)->get();

        return view('app.dashboard', [
            'user' => $user, 'workspaces' => $workspaces, 'last' => $last, 'started' => $started, 'assessment' => $assessment, 'assets' => $assets,
            'plan' => array_slice($plan, 0, 5), 'activity' => $activity, 'balance' => $balance, 'subscription' => $subscription,
            'stats' => ['lessons' => $completedCount, 'assets' => ToolOutput::query()->where('user_id', $user->id)->count(), 'results' => $checkins->whereNotNull('recorded_at')->where('mode', 'commit')->count(), 'credits' => $balance],
            'nextCertificate' => $nextCertificate, 'hasRealResult' => $hasRealResult, 'certificates' => $certificates,
            'dimensions' => Catalog::DIMENSIONS, 'levels' => Catalog::LEVELS,
            'suggestions' => $this->suggestions($assessment, $nextLesson),
        ]);
    }

    public function progress(Request $request)
    {
        $user = $request->user();
        $program = LearningPath::query()->where('audience', $user->audience ?? 'owner')->with(['courses' => fn ($q) => $q->with('lessons')->orderByPivot('position')])->first()
            ?? LearningPath::query()->orderBy('position')->with(['courses' => fn ($q) => $q->with('lessons')->orderByPivot('position')])->first();
        $courses = $program?->courses->map(fn (Course $c) => ['course' => $c, 'progress' => $c->is_published ? $this->progress->courseProgress($user, $c) : null]) ?? collect();
        $completed = DB::table('activity_completions')->join('lessons', 'lessons.id', '=', 'activity_completions.lesson_id')
            ->join('courses', 'courses.id', '=', 'lessons.course_id')->where('activity_completions.user_id', $user->id)
            ->orderByDesc('activity_completions.completed_at')->get(['lessons.title as lesson', 'courses.title as course', 'courses.slug as course_slug', 'lessons.slug as lesson_slug', 'activity_completions.completed_at']);
        $certificates = DB::table('certificates')->join('courses', 'courses.id', '=', 'certificates.course_id')->where('certificates.user_id', $user->id)->get(['courses.title', 'certificates.verification_code', 'certificates.issued_at', 'certificates.revoked_at']);
        $realResults = RealWorldCheckin::query()->where('user_id', $user->id)->where('mode', 'commit')->whereNotNull('recorded_at')->count();
        // Earned (all lessons + real result) but held by the plan gate (blueprint §4.13): shown with the upgrade link.
        $issuer = app(CertificateIssuer::class);
        $hasPaidPlan = $issuer->hasPaidPlan($user);
        $pendingCertificates = $courses->filter(fn ($c) => $c['progress'] && $c['progress']['is_complete'] && $issuer->pendingPlan($user, $c['course']))->map(fn ($c) => $c['course'])->values();

        return view('app.progress', compact('program', 'courses', 'completed', 'certificates', 'realResults', 'hasPaidPlan', 'pendingCertificates'));
    }

    private function suggestions(?AssessmentResult $assessment, $nextLesson): array
    {
        $out = [];
        if ($assessment) {
            foreach (array_slice($assessment->priorities, 0, 2) as $p) {
                if (! empty($p['link'])) {
                    $out[] = ['label' => $p['when'].': '.$p['title'], 'url' => $p['link']];
                }
            }
        }
        if ($nextLesson) {
            $out[] = ['label' => 'الدرس التالي: '.$nextLesson->title, 'url' => route('academy.lesson', [$nextLesson->course, $nextLesson])];
        }
        $out[] = ['label' => 'افتح المختبر لتوليد أصل جديد', 'url' => route('tools.index')];

        return array_slice($out, 0, 4);
    }
}
