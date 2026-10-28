<?php

namespace App\Http\Controllers\Site;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\LearningPath;
use App\Domain\Assessment\Models\AssessmentResult;
use App\Domain\Assessment\Services\AssessmentEngine;
use App\Domain\Assessment\Services\Catalog;
use App\Domain\Insights\Services\EventRecorder;
use App\Domain\Lab\Models\Tool;
use App\Http\Controllers\Controller;
use App\Support\Viewer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * تشخيص مشروعك — /assess (three stages) and /assess/result (blueprint §4.2).
 */
class AssessController extends Controller
{
    /** Stage 1: about your project. Pre-filled from the home form (query) or from the member's project profile. */
    public function start(Request $request, Viewer $viewer)
    {
        $project = session('assess.project', []);
        foreach (['offer', 'problem', 'budget', 'sector', 'city', 'channel', 'goal', 'goal_number'] as $key) {
            if ($request->filled($key)) {
                $project[$key] = $request->query($key);
            }
        }
        if ($project === [] && $viewer->isMember() && ! $viewer->isBeginner()) {
            $profile = $this->memberProject($request)?->profile ?? [];
            $project = array_intersect_key($profile, array_flip(['offer', 'problem', 'budget', 'sector', 'city', 'channel', 'goal', 'goal_number']));
        }
        $audience = session('assess.audience', $request->query('audience', $request->user()?->audience ?? 'owner'));

        return view('site.assess.start', ['project' => $project, 'audience' => in_array($audience, ['owner', 'marketer'], true) ? $audience : 'owner', 'stage' => 1]);
    }

    public function storeProject(Request $request, EventRecorder $events)
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(['owner', 'marketer'])],
            'offer' => ['required', 'string', 'max:200'],
            'sector' => ['required', Rule::in(array_keys(Catalog::SECTORS))],
            'city' => ['nullable', 'string', 'max:60'],
            'budget' => ['required', 'integer', 'min:0', 'max:10000000'],
            'channel' => ['required', Rule::in(array_keys(Catalog::CHANNELS))],
            'goal' => ['required', Rule::in(array_keys(Catalog::GOALS))],
            'goal_number' => ['required', 'integer', 'min:1', 'max:1000000'],
            'problem' => ['nullable', Rule::in(array_keys(Catalog::PROBLEMS))],
        ]);
        $audience = $data['audience'];
        unset($data['audience']);
        session(['assess.project' => $data, 'assess.audience' => $audience]);
        $events->record($request, 'assessment.started', ['meta' => ['audience' => $audience]]);

        return redirect()->route('assess.questions');
    }

    /** Stage 2: eight situational questions phrased from the project data. */
    public function questions(Request $request, AssessmentEngine $engine)
    {
        $project = session('assess.project');
        if (! $project) {
            return redirect()->route('assess.start');
        }
        $audience = session('assess.audience', 'owner');
        $questions = $engine->questionsFor($audience, $project);
        abort_if($questions->isEmpty(), 503, 'أسئلة التشخيص غير مهيأة بعد.');

        return view('site.assess.questions', [
            'questions' => $questions, 'project' => $project, 'audience' => $audience, 'stage' => 2,
            'similar' => $engine->similarCount($engine->benchmarkGroup($audience, $project)),
            'dimensions' => Catalog::DIMENSIONS,
        ]);
    }

    public function submit(Request $request, AssessmentEngine $engine, EventRecorder $events, Viewer $viewer)
    {
        $project = session('assess.project');
        if (! $project) {
            return redirect()->route('assess.start');
        }
        $audience = session('assess.audience', 'owner');
        $questions = $engine->questionsFor($audience, $project);
        $rules = [];
        foreach ($questions as $q) {
            $rules['q.'.$q->id] = ['required', 'integer', 'min:0', 'max:'.(count($q->options ?? []) - 1)];
        }
        $data = $request->validate($rules, ['*.required' => 'أجب عن كل الأسئلة الثمانية.']);
        $answers = [];
        foreach ($data['q'] as $id => $index) {
            $answers[(int) $id] = (int) $index;
        }
        $user = $viewer->isMember() ? $request->user() : null;
        $memberProject = $user ? $this->memberProject($request) : null;
        $result = $engine->create($audience, $project, $answers, $user?->id, $memberProject?->id);
        if ($user && $memberProject) {
            $memberProject->update(['profile' => array_merge($memberProject->profile ?? [], $project)]);
            if (! $user->audience) {
                $user->forceFill(['audience' => $audience])->save();
            }
        }
        session(['assess.result_token' => $result->session_token]);
        session()->forget('assess.project');
        $events->record($request, 'assessment.completed', ['meta' => ['level' => $result->level, 'weakest' => $result->weakest_dimension, 'result_id' => $result->id]]);

        return redirect()->route('assess.result', ['t' => $result->session_token]);
    }

    /** Stage 3: priorities map. Reachable by token (query, session, or localStorage via JS redirect). */
    public function result(Request $request, AssessmentEngine $engine, Viewer $viewer)
    {
        $token = (string) $request->query('t', session('assess.result_token', ''));
        $result = $token !== '' ? AssessmentResult::query()->where('session_token', $token)->first() : null;
        if (! $result && $viewer->isMember()) {
            $result = AssessmentResult::query()->where('user_id', $request->user()->id)->latest()->first();
        }
        if (! $result) {
            return view('site.assess.result-missing');
        }
        $user = $viewer->isMember() ? $request->user() : null;
        $completedCourse = $user ? DB::table('certificates')->where('user_id', $user->id)->exists()
            || DB::table('activity_completions')->where('user_id', $user->id)->whereNotNull('lesson_id')->count() >= 6 : false;
        $program = LearningPath::query()->where('audience', $result->audience)->with(['courses' => fn ($q) => $q->orderByPivot('position')])->first();
        $previous = $user ? AssessmentResult::query()->where('user_id', $user->id)->where('id', '<', $result->id)->latest()->first() : null;

        return view('site.assess.result', [
            'result' => $result, 'stage' => 3,
            'diagnosis' => $engine->diagnosisSentence($result->project_snapshot, ['level' => $result->level, 'weakest' => $result->weakest_dimension]),
            'benchmark' => $engine->benchmark($result->benchmark_group),
            'similar' => $engine->similarCount($result->benchmark_group),
            'improvement' => $engine->averageImprovement(),
            'markdown' => $engine->prioritiesMarkdown($result),
            'program' => $program,
            'tool' => Tool::query()->where('is_published', true)->where('credit_cost', 0)->first(),
            'canReassess' => $completedCourse,
            'previous' => $previous,
            'isOwner' => $user && $result->user_id === $user->id,
            'levels' => Catalog::LEVELS, 'dimensions' => Catalog::DIMENSIONS,
        ]);
    }

    /** Member claims a visitor result (after registering): sets user_id and builds the project profile from the snapshot. */
    public function claim(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:64']]);
        $result = AssessmentResult::query()->where('session_token', $data['token'])->whereNull('user_id')->first();
        if ($result) {
            $project = $this->memberProject($request);
            $result->update(['user_id' => $request->user()->id, 'project_id' => $project?->id]);
            $project?->update(['profile' => array_merge($project->profile ?? [], $result->project_snapshot)]);
            if (! $request->user()->audience) {
                $request->user()->forceFill(['audience' => $result->audience])->save();
            }
            session(['assess.result_token' => $result->session_token]);
        }

        return $request->expectsJson() ? response()->json(['claimed' => (bool) $result]) : redirect()->route('dashboard')->with('status', 'حُفظت خريطة أولوياتك في حسابك وأُنشئ ملف مشروعك.');
    }

    private function memberProject(Request $request)
    {
        $workspace = $request->user()?->workspaces()->with('projects')->first();

        return $workspace?->projects->first();
    }
}
