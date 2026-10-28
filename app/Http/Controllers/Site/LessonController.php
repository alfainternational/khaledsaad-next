<?php

namespace App\Http\Controllers\Site;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\DecisionJournal;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Models\RealWorldCheckin;
use App\Domain\Academy\Services\AssetStore;
use App\Domain\Academy\Services\LessonCoach;
use App\Domain\Academy\Services\LessonComposer;
use App\Domain\Academy\Services\ProgressService;
use App\Domain\Academy\Services\WhatIfEngine;
use App\Domain\Assessment\Services\Catalog;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Insights\Models\ContentReport;
use App\Domain\Insights\Services\EventRecorder;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Workspace\Models\Project;
use App\Http\Controllers\Controller;
use App\Support\Markdown;
use App\Support\Viewer;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * صفحة الدرس — /academy/{course}/{lesson} (blueprint §4.5): the full form, built on the learner's own project.
 */
class LessonController extends Controller
{
    public function __construct(
        private readonly LessonComposer $composer,
        private readonly WhatIfEngine $whatIf,
        private readonly ProgressService $progress,
        private readonly EventRecorder $events,
        private readonly Viewer $viewer,
    ) {}

    public function show(Request $request, Course $course, Lesson $lesson)
    {
        $this->guard($course, $lesson);
        $lesson->load(['questions', 'template', 'assetLinks']);
        $user = $this->member($request);
        $project = $user ? $this->project($request) : null;
        $hasProfile = $project && ! empty($project->profile['offer']);
        $mode = $request->query('situation', $hasProfile ? 'project' : 'general') === 'project' && $hasProfile ? 'project' : 'general';

        $situation = $this->composer->situation($lesson, $mode === 'project' ? $project : null, $mode);
        $input = session('lab.input.'.$lesson->id, []);
        $values = $this->composer->values($lesson, $situation, $input);
        $generated = session('lab.asset.'.$lesson->id);
        $whatIfState = $this->whatIf->evaluate($lesson->whatif_rules ?? [], $values);

        $lessons = $course->lessons()->whereNotNull('published_at')->get();
        $index = $lessons->search(fn ($l) => $l->id === $lesson->id);
        $journal = $user ? DecisionJournal::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)->first() : null;
        $checkin = $user ? RealWorldCheckin::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)->latest()->first() : null;
        $savedAsset = $user ? ToolOutput::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)->latest()->first() : null;
        $answered = $user ? $this->progress->hasAnsweredQuestions($user, $lesson) : false;
        $completed = $user ? isset($this->progress->completedLessonIds($user)[$lesson->id]) : false;
        $courseProgress = $user ? $this->progress->courseProgress($user, $course) : null;
        $questions = $lesson->questions->each(function ($q) use ($situation): void {
            $q->setAttribute('scenario', Markdown::fill((string) $q->scenario, $situation));
        });

        $this->events->record($request, 'visit.lesson', ['lesson_id' => $lesson->id, 'meta' => ['mode' => $mode]]);

        return view('site.academy.lesson', [
            'course' => $course, 'lesson' => $lesson, 'lessons' => $lessons, 'index' => $index === false ? 0 : $index,
            'next' => $lessons[$index + 1] ?? null, 'prev' => $index > 0 ? $lessons[$index - 1] : null,
            'mode' => $mode, 'hasProfile' => $hasProfile, 'project' => $project, 'situation' => $situation,
            'scenario' => $this->composer->scenarioSentence($lesson, $situation),
            'commonMistake' => Markdown::fill((string) $lesson->common_mistake, $situation),
            'guided' => $this->composer->guidedSteps($lesson, $situation, $input),
            'labFields' => $this->composer->labFields($lesson, $situation, $input),
            'values' => $values, 'generated' => $generated, 'whatIf' => $whatIfState,
            'incoming' => $this->composer->incomingAssets($lesson, $user?->id, $project?->id),
            'opening' => $questions->firstWhere('is_opening', true), 'questions' => $questions->where('is_opening', false)->values(),
            'journal' => $journal, 'checkin' => $checkin, 'savedAsset' => $savedAsset, 'answered' => $answered, 'completed' => $completed,
            'courseProgress' => $courseProgress, 'member' => (bool) $user, 'coachEnabled' => LessonCoach::enabled() && $user,
            'coachAnswer' => session('coach.answer.'.$lesson->id), 'parts' => Lesson::PARTS, 'channels' => Catalog::CHANNELS,
            'relatedTool' => Tool::query()->where('is_published', true)->first(),
            'relatedArticle' => ContentItem::published()->when($course->topic, fn ($q) => $q->where('topic', 'like', '%'.$course->topic.'%'))->latest('published_at')->first() ?? ContentItem::published()->latest('published_at')->first(),
            'structuredJson' => json_encode([
                '@context' => 'https://schema.org', '@type' => 'LearningResource', 'name' => $lesson->title, 'description' => $lesson->objective,
                'inLanguage' => 'ar', 'learningResourceType' => 'Lesson', 'timeRequired' => 'PT'.(int) $lesson->duration_minutes.'M',
                'isPartOf' => ['@type' => 'Course', 'name' => $course->title, 'url' => route('academy.show', $course)],
                'provider' => ['@type' => 'Organization', 'name' => 'أثر', 'url' => url('/')], 'url' => route('academy.lesson', [$course, $lesson]),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR),
        ]);
    }

    /** Part 5: generate the asset from guided steps + lab fields (rules and templates, no AI). */
    public function lab(Request $request, Course $course, Lesson $lesson)
    {
        $this->guard($course, $lesson);
        $user = $this->member($request);
        $project = $user ? $this->project($request) : null;
        $mode = $request->input('situation') === 'project' && $project && ! empty($project->profile['offer']) ? 'project' : 'general';
        $situation = $this->composer->situation($lesson, $mode === 'project' ? $project : null, $mode);
        $keys = collect(array_merge($lesson->guided_steps ?? [], $lesson->lab_config['fields'] ?? []))->pluck('key')->all();
        $input = [];
        foreach ($keys as $key) {
            $value = $request->input($key);
            if (is_string($value)) {
                $input[$key] = trim(mb_substr($value, 0, 1500));
            } elseif (is_numeric($value)) {
                $input[$key] = $value;
            }
        }
        $values = $this->composer->values($lesson, $situation, $input);
        $missing = $this->composer->missing($lesson, $values);
        if ($missing !== []) {
            session(['lab.input.'.$lesson->id => $input]);

            return redirect()->to(route('academy.lesson', [$course, $lesson, 'situation' => $mode]).'#part-lab')->withErrors(['lab' => 'أكمل الحقول: '.implode('، ', $missing)]);
        }
        $markdown = $this->composer->renderAsset($lesson, $values);
        if ($lesson->assetLinks()->count() >= 5) {
            $markdown .= $this->composer->incomingMarkdown($this->composer->incomingAssets($lesson, $user?->id, $project?->id));
        }
        $state = $this->whatIf->evaluate($lesson->whatif_rules ?? [], $values);
        session(['lab.input.'.$lesson->id => $input, 'lab.asset.'.$lesson->id => ['markdown' => $markdown, 'verdict' => $state['verdict'], 'generated_at' => now()->toDateTimeString()]]);
        $prefilled = collect(array_merge($this->composer->guidedSteps($lesson, $situation, []), $this->composer->labFields($lesson, $situation, [])))->filter(fn ($f) => $f['prefilled'])->pluck('key')->all();
        $this->events->record($request, 'lesson.lab_generated', ['lesson_id' => $lesson->id, 'tool_id' => $lesson->lab_tool_id, 'part' => 'lab', 'meta' => ['filled' => array_keys(array_filter($input, fn ($v) => trim((string) $v) !== '')), 'prefilled' => $prefilled, 'mode' => $mode]]);

        return redirect()->to(route('academy.lesson', [$course, $lesson, 'situation' => $mode]).'#asset')->with('status', 'أُنشئ أصلك. جرّب «ماذا لو» ثم احفظه في مشروعك.');
    }

    /** "ماذا لو" JSON: one variable → outputs + verdict (declared rules evaluated server-side). */
    public function whatIf(Request $request, Course $course, Lesson $lesson)
    {
        $this->guard($course, $lesson);
        $data = $request->validate(['value' => ['required', 'numeric'], 'situation' => ['nullable', 'string']]);
        $user = $this->member($request);
        $project = $user ? $this->project($request) : null;
        $mode = ($data['situation'] ?? '') === 'project' && $project ? 'project' : 'general';
        $situation = $this->composer->situation($lesson, $mode === 'project' ? $project : null, $mode);
        $values = $this->composer->values($lesson, $situation, session('lab.input.'.$lesson->id, []));
        $this->events->record($request, 'lesson.whatif_used', ['lesson_id' => $lesson->id, 'part' => 'lab']);

        return response()->json($this->whatIf->evaluate($lesson->whatif_rules ?? [], $values, (float) $data['value']));
    }

    /** Opening and final decisions (principle 2). Members: decision_journal; visitors: handled in the browser. */
    public function decision(Request $request, Course $course, Lesson $lesson)
    {
        $this->guard($course, $lesson);
        $data = $request->validate(['stage' => ['required', Rule::in(['first', 'final'])], 'choice' => ['required', 'integer', 'min:0', 'max:5'], 'note' => ['nullable', 'string', 'max:500']]);
        $user = $this->member($request);
        $this->events->record($request, $data['stage'] === 'first' ? 'lesson.opening_decided' : 'lesson.final_decided', ['lesson_id' => $lesson->id, 'part' => $data['stage'] === 'first' ? 'opening' : 'questions', 'meta' => ['choice' => $data['choice']]]);
        if ($user) {
            $journal = DecisionJournal::query()->firstOrNew(['user_id' => $user->id, 'lesson_id' => $lesson->id]);
            if ($data['stage'] === 'first') {
                $journal->first_choice = $data['choice'];
            } else {
                $journal->final_choice = $data['choice'];
                $journal->note = $data['note'] ?? $journal->note;
            }
            $journal->save();
        }
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->to(route('academy.lesson', [$course, $lesson]).($data['stage'] === 'first' ? '#part-goal' : '#part-template'))->with('status', 'سُجّل قرارك.');
    }

    /** Part 8: commit to a real action (with email reminder) or complete as practice. */
    public function checkin(Request $request, Course $course, Lesson $lesson)
    {
        $this->guard($course, $lesson);
        $user = $this->member($request);
        abort_unless($user, 403);
        $data = $request->validate(['mode' => ['required', Rule::in(['commit', 'practice'])], 'action' => ['nullable', 'string', 'max:1000'], 'due_at' => ['nullable', 'date', 'after:now']]);
        $project = $this->project($request);
        $checkin = RealWorldCheckin::query()->create([
            'user_id' => $user->id, 'lesson_id' => $lesson->id, 'project_id' => $project?->id, 'mode' => $data['mode'],
            'action' => $data['mode'] === 'practice' ? 'أُكمل كتدريب (لا أملك حملة الآن).' : ($data['action'] ?: (string) $lesson->real_action_template),
            'due_at' => $data['mode'] === 'practice' ? null : ($data['due_at'] ?? now()->addDays(7)),
            'result' => $data['mode'] === 'practice' ? ['practice' => true] : null, 'recorded_at' => $data['mode'] === 'practice' ? now() : null,
        ]);
        $this->events->record($request, 'checkin.committed', ['lesson_id' => $lesson->id, 'part' => 'apply', 'meta' => ['mode' => $data['mode']]]);

        return redirect()->to(route('academy.lesson', [$course, $lesson]).'#part-apply')->with('status', $data['mode'] === 'practice' ? 'أُكمل الجزء كتدريب.' : 'التزمت بفعل واقعي. سنذكّرك بالبريد يوم '.$checkin->due_at->format('Y/m/d').' لتسجيل النتيجة.');
    }

    /** Record the real result (after the week) — updates the dashboard and unlocks the next lesson's numbers. */
    public function result(Request $request, Course $course, Lesson $lesson)
    {
        $this->guard($course, $lesson);
        $user = $this->member($request);
        abort_unless($user, 403);
        $checkin = RealWorldCheckin::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)->where('mode', 'commit')->latest()->firstOrFail();
        $rules = [];
        foreach ($lesson->real_result_fields ?? [] as $field) {
            $rules['result.'.$field['key']] = ($field['type'] ?? 'text') === 'number' ? ['nullable', 'numeric'] : ['nullable', 'string', 'max:500'];
        }
        $data = $request->validate($rules + ['note' => ['nullable', 'string', 'max:1000']]);
        $checkin->update(['result' => ($data['result'] ?? []) + ['note' => $data['note'] ?? null], 'recorded_at' => now()]);
        $this->events->record($request, 'checkin.recorded', ['lesson_id' => $lesson->id, 'part' => 'apply']);
        $this->progress->maybeIssueCertificate($user, $course);

        return redirect()->to(route('academy.lesson', [$course, $lesson]).'#part-apply')->with('status', 'سُجّلت نتيجتك الواقعية وتحدّثت لوحتك.');
    }

    /** Save the generated asset into the member's project (بطاقة الأصل → حفظ في مشروعي). */
    public function save(Request $request, Course $course, Lesson $lesson, AssetStore $store)
    {
        $this->guard($course, $lesson);
        $user = $this->member($request);
        abort_unless($user, 403);
        $project = $this->project($request);
        abort_unless($project, 422, 'لا يوجد مشروع في مساحتك.');
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'type' => ['nullable', 'string', 'max:60'], 'markdown' => ['required', 'string', 'max:60000']]);
        $generated = session('lab.asset.'.$lesson->id);
        // Browsers submit hidden-field newlines as CRLF; keep the stored asset byte-identical to the generated one.
        $data['markdown'] = str_replace("
", "
", $data['markdown']);
        $store->save($user, $project, $lesson, $data['title'], $data['type'] ?: ($lesson->outcome_type ?? 'asset'), $data['markdown'], session('lab.input.'.$lesson->id, []), $generated ? ['verdict' => $generated['verdict']] : null);
        $this->events->record($request, 'asset.saved', ['lesson_id' => $lesson->id, 'tool_id' => $lesson->lab_tool_id, 'part' => 'lab']);
        $this->progress->maybeComplete($user, $lesson);

        return redirect()->to(route('academy.lesson', [$course, $lesson]).'#asset')->with('status', 'حُفظ الأصل في مشروعك «'.$project->name.'».');
    }

    /** The scoped coach (principle 6): only with an AI key; 1 credit. */
    public function coach(Request $request, Course $course, Lesson $lesson, LessonCoach $coach)
    {
        $this->guard($course, $lesson);
        $user = $this->member($request);
        abort_unless($user && LessonCoach::enabled(), 404);
        $data = $request->validate(['question' => ['required', 'string', 'max:800'], 'compare' => ['nullable', 'boolean']]);
        $workspace = $user->workspaces()->first();
        abort_unless($workspace, 422);
        $asset = ToolOutput::query()->where('user_id', $user->id)->where('lesson_id', $lesson->id)->latest()->first();
        try {
            $answer = $coach->ask($user, $workspace, $lesson, $data['question'], $asset, (bool) ($data['compare'] ?? false));
        } catch (DomainException $e) {
            return redirect()->to(route('academy.lesson', [$course, $lesson]).'#coach')->withErrors(['coach' => $e->getMessage()]);
        }
        $this->events->record($request, 'coach.asked', ['lesson_id' => $lesson->id]);

        return redirect()->to(route('academy.lesson', [$course, $lesson]).'#coach')->with('coach.answer.'.$lesson->id, $answer);
    }

    /** «لا يناسب حالتي»: content report tied to a part; feeds quality signals. */
    public function report(Request $request, Course $course, Lesson $lesson)
    {
        $this->guard($course, $lesson);
        $data = $request->validate(['part' => ['nullable', Rule::in(array_keys(Lesson::PARTS))], 'reason' => ['required', Rule::in(['not_my_case', 'wrong_numbers', 'unclear', 'other'])], 'note' => ['nullable', 'string', 'max:800']]);
        ContentReport::query()->create(['user_id' => $request->user()?->id, 'lesson_id' => $lesson->id, 'part' => $data['part'] ?? null, 'reason' => $data['reason'], 'note' => $data['note'] ?? null]);
        $this->events->record($request, 'report.sent', ['lesson_id' => $lesson->id, 'part' => $data['part'] ?? null, 'meta' => ['reason' => $data['reason']]]);

        return redirect()->to(route('academy.lesson', [$course, $lesson]))->with('status', 'وصل بلاغك وسيراجعه المحرر. شكراً لك.');
    }

    private function guard(Course $course, Lesson $lesson): void
    {
        abort_unless($course->is_published && $lesson->course_id === $course->id && $lesson->isPublished(), 404);
    }

    private function member(Request $request)
    {
        return $this->viewer->isMember() && ! $this->viewer->isBeginner() ? $request->user() : null;
    }

    private function project(Request $request): ?Project
    {
        $workspace = $request->user()?->workspaces()->with('projects')->first();

        return $workspace?->projects->first();
    }
}
