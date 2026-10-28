<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\LearningPath;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Models\LessonVersion;
use App\Domain\Academy\Models\ResourceTemplate;
use App\Domain\Insights\Models\QualitySignal;
use App\Domain\Insights\Services\QualitySignalCalculator;
use App\Domain\Insights\Services\StudioAssistant;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * /admin/academy (programs, courses, lessons order) and استوديو الدرس /admin/academy/lessons/{lesson} (blueprint §6.2).
 */
class AcademyController extends Controller
{
    private const ROLES = ['editor', 'admin', 'super_admin'];

    public function index(Request $request)
    {
        $this->authorizeEditor($request);

        return view('admin.academy.index', [
            'programs' => LearningPath::query()->orderBy('position')->with('courses')->get(),
            'courses' => Course::query()->orderBy('position')->with(['lessons.questions'])->get(),
            'openSignals' => QualitySignal::query()->where('status', 'open')->get()->groupBy(fn ($s) => $s->entity_type === 'lesson' ? $s->entity_id : ($s->meta['lesson_id'] ?? 0)),
        ]);
    }

    /** Reorder lessons inside a course and toggle course publication; the public course page reflects it immediately. */
    public function updateCourse(Request $request, Course $course)
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'positions' => ['nullable', 'array'], 'positions.*' => ['integer', 'min:1', 'max:99'],
            'is_published' => ['nullable', 'boolean'], 'outcome' => ['nullable', 'string', 'max:200'], 'summary' => ['nullable', 'string', 'max:600'],
        ]);
        foreach ($data['positions'] ?? [] as $lessonId => $position) {
            Lesson::query()->where('course_id', $course->id)->whereKey($lessonId)->update(['position' => $position]);
        }
        $course->update(array_filter(['outcome' => $data['outcome'] ?? null, 'summary' => $data['summary'] ?? null], fn ($v) => $v !== null) + ['is_published' => (bool) ($data['is_published'] ?? false)]);
        $this->audit($request, 'course.updated', (string) $course->id, ['positions' => $data['positions'] ?? [], 'published' => $course->is_published]);

        return back()->with('status', 'حُفظ ترتيب الدروس وبيانات المسار.');
    }

    public function edit(Request $request, Lesson $lesson, QualitySignalCalculator $signals, StudioAssistant $assistant)
    {
        $this->authorizeEditor($request);
        $lesson->load(['questions', 'course', 'template', 'versions', 'assetLinks']);
        $part = (string) $request->query('part', '');
        $openSignals = QualitySignal::query()->where('status', 'open')->get()->filter(fn ($s) => ($s->entity_type === 'lesson' && $s->entity_id === $lesson->id) || (int) ($s->meta['lesson_id'] ?? 0) === $lesson->id);
        if ($part === '' || ! isset(Lesson::PARTS[$part])) {
            $part = $openSignals->first()?->part ?? 'opening';
        }
        $completeness = $lesson->partCompleteness();

        return view('admin.academy.lesson', [
            'lesson' => $lesson, 'part' => $part, 'parts' => Lesson::PARTS, 'completeness' => $completeness,
            'reach' => $signals->partReach($lesson), 'signals' => $openSignals,
            'suggestions' => $assistant->suggestions($lesson), 'mapping' => $assistant->fieldMapping($lesson),
            'templates' => ResourceTemplate::query()->orderBy('name')->get(), 'courses' => Course::query()->orderBy('position')->get(),
            'variant' => session('studio.variant'),
        ]);
    }

    /** Every save is a new restorable version; publishing needs an explicit human action (published_at). */
    public function update(Request $request, Lesson $lesson)
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'], 'goal' => ['nullable', 'string', 'max:1000'], 'objective' => ['nullable', 'string', 'max:500'],
            'concept_body' => ['nullable', 'string', 'max:60000'], 'common_mistake' => ['nullable', 'string', 'max:2000'], 'market_example' => ['nullable', 'string', 'max:4000'],
            'quick_summary' => ['nullable', 'string', 'max:4000'], 'scenario_template' => ['nullable', 'string', 'max:2000'], 'real_action_template' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:120'], 'template_id' => ['nullable', 'integer', 'exists:resource_templates,id'],
            'outcome_title' => ['nullable', 'string', 'max:160'], 'outcome_type' => ['nullable', 'string', 'max:60', 'regex:/^[a-z_]+$/'],
            'guided_steps' => ['nullable', 'string', 'max:20000'], 'lab_config' => ['nullable', 'string', 'max:40000'], 'whatif_rules' => ['nullable', 'string', 'max:20000'],
            'real_result_fields' => ['nullable', 'string', 'max:10000'], 'general_scenario' => ['nullable', 'string', 'max:10000'],
            'questions' => ['nullable', 'array'], 'questions.*.scenario' => ['nullable', 'string', 'max:2000'], 'questions.*.prompt' => ['nullable', 'string', 'max:500'],
            'questions.*.options' => ['nullable', 'array'], 'questions.*.options.*.label' => ['nullable', 'string', 'max:300'], 'questions.*.options.*.feedback' => ['nullable', 'string', 'max:600'],
            'questions.*.best' => ['nullable', 'integer', 'min:0', 'max:5'],
            'publish' => ['nullable', 'boolean'], 'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $json = [];
        foreach (['guided_steps', 'lab_config', 'whatif_rules', 'real_result_fields', 'general_scenario'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && trim($data[$key]) !== '') {
                $decoded = json_decode($data[$key], true);
                if (! is_array($decoded)) {
                    return back()->withErrors([$key => "حقل {$key} ليس JSON صالحاً."])->withInput();
                }
                $json[$key] = $decoded;
            }
        }

        DB::transaction(function () use ($request, $lesson, $data, $json): void {
            $version = (int) $lesson->versions()->max('version') + 1;
            LessonVersion::query()->create([
                'lesson_id' => $lesson->id, 'version' => $version, 'user_id' => $request->user()->id, 'reason' => $data['reason'] ?? 'تعديل من الاستوديو',
                'snapshot' => ['lesson' => $lesson->getAttributes(), 'questions' => $lesson->questions()->get()->map->getAttributes()->all()],
            ]);
            $lesson->fill(array_intersect_key($data, array_flip(['title', 'goal', 'objective', 'concept_body', 'common_mistake', 'market_example', 'quick_summary', 'scenario_template', 'real_action_template', 'duration_minutes', 'template_id', 'outcome_title', 'outcome_type'])) + $json);
            if (($data['publish'] ?? null) !== null) {
                $lesson->published_at = $data['publish'] ? ($lesson->published_at ?? now()) : null;
            }
            $lesson->save();
            foreach ($data['questions'] ?? [] as $questionId => $q) {
                $question = $lesson->questions()->whereKey($questionId)->first();
                if (! $question) {
                    continue;
                }
                $options = [];
                foreach ($q['options'] ?? [] as $i => $opt) {
                    if (trim((string) ($opt['label'] ?? '')) === '') {
                        continue;
                    }
                    $options[] = ['label' => $opt['label'], 'feedback' => $opt['feedback'] ?? '', 'is_best' => ! $question->is_opening && (int) ($q['best'] ?? -1) === $i];
                }
                $question->update(['scenario' => $q['scenario'] ?? $question->scenario, 'prompt' => $q['prompt'] ?? $question->prompt, 'options' => $options ?: $question->options]);
            }
        });
        $this->audit($request, 'lesson.updated', (string) $lesson->id, ['reason' => $data['reason'] ?? null, 'published' => $lesson->isPublished()]);

        return redirect()->route('admin.academy.lessons.edit', ['lesson' => $lesson, 'part' => $request->input('part', 'opening')])->with('status', 'حُفظ الدرس كإصدار جديد (v'.$lesson->versions()->max('version').').');
    }

    public function restore(Request $request, Lesson $lesson, LessonVersion $version)
    {
        $this->authorizeEditor($request);
        abort_unless($version->lesson_id === $lesson->id, 404);
        DB::transaction(function () use ($request, $lesson, $version): void {
            $current = (int) $lesson->versions()->max('version') + 1;
            LessonVersion::query()->create(['lesson_id' => $lesson->id, 'version' => $current, 'user_id' => $request->user()->id, 'reason' => 'قبل الرجوع إلى v'.$version->version, 'snapshot' => ['lesson' => $lesson->getAttributes(), 'questions' => $lesson->questions()->get()->map->getAttributes()->all()]]);
            $snapshot = $version->snapshot['lesson'] ?? [];
            unset($snapshot['id'], $snapshot['created_at'], $snapshot['updated_at']);
            $lesson->forceFill($snapshot)->save();
            foreach ($version->snapshot['questions'] ?? [] as $q) {
                $lesson->questions()->whereKey($q['id'] ?? 0)->update(['scenario' => $q['scenario'] ?? null, 'prompt' => $q['prompt'] ?? '', 'options' => $q['options'] ?? '[]']);
            }
        });
        $this->audit($request, 'lesson.restored', (string) $lesson->id, ['version' => $version->version]);

        return back()->with('status', 'استُرجع الإصدار v'.$version->version.' كإصدار جديد.');
    }

    public function variant(Request $request, Lesson $lesson, StudioAssistant $assistant)
    {
        $this->authorizeEditor($request);
        $data = $request->validate(['audience' => ['required', 'string', 'max:60']]);
        try {
            $text = $assistant->generateVariant($lesson, $data['audience']);
        } catch (DomainException $e) {
            return back()->withErrors(['variant' => $e->getMessage()]);
        }

        return back()->with('studio.variant', $text)->with('status', 'اقتراح الموقف البديل جاهز للمراجعة (لم يُحفظ).');
    }

    private function authorizeEditor(Request $request): void
    {
        abort_unless(in_array($request->user()?->platform_role, self::ROLES, true), 403);
    }

    private function audit(Request $request, string $action, string $subjectId, array $context): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'lesson', 'subject_id' => $subjectId,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE), 'request_id' => $request->attributes->get('request_id'), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
