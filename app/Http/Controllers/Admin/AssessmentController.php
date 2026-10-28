<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Assessment\Models\AssessmentQuestion;
use App\Domain\Assessment\Models\AssessmentResult;
use App\Domain\Assessment\Models\AssessmentRule;
use App\Domain\Assessment\Services\Catalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** /admin/assessment: questions and routing rules with the coverage map (audience × level × dimension) — blueprint §6.5. */
class AssessmentController extends Controller
{
    private const ROLES = ['editor', 'admin', 'super_admin'];

    public function index(Request $request)
    {
        $this->authorizeEditor($request);
        $rules = AssessmentRule::query()->with('lesson')->get();
        $coverage = [];
        foreach (['owner', 'marketer'] as $audience) {
            foreach (array_keys(Catalog::LEVELS) as $level) {
                foreach (array_keys(Catalog::DIMENSIONS) as $dimension) {
                    $coverage[$audience][$level][$dimension] = $rules->first(fn ($r) => $r->audience === $audience && $r->level === $level && $r->weakest_dimension === $dimension);
                }
            }
        }

        return view('admin.assessment.index', [
            'questions' => AssessmentQuestion::query()->orderBy('audience')->orderBy('position')->get()->groupBy('audience'),
            'rules' => $rules, 'coverage' => $coverage, 'lessons' => Lesson::query()->with('course')->get(),
            'audiences' => Catalog::AUDIENCES, 'levels' => Catalog::LEVELS, 'dimensions' => Catalog::DIMENSIONS,
            'stats' => ['results' => AssessmentResult::query()->count(), 'last_30' => AssessmentResult::query()->where('created_at', '>=', now()->subDays(30))->count()],
        ]);
    }

    public function storeQuestion(Request $request)
    {
        $this->authorizeEditor($request);
        $data = $this->validateQuestion($request);
        $data['position'] = (int) AssessmentQuestion::query()->where('audience', $data['audience'])->max('position') + 1;
        $q = AssessmentQuestion::query()->create($data);
        $this->audit($request, 'assessment_question.created', (string) $q->id);

        return back()->with('status', 'أُضيف السؤال.');
    }

    public function updateQuestion(Request $request, AssessmentQuestion $question)
    {
        $this->authorizeEditor($request);
        $question->update($this->validateQuestion($request) + ['position' => (int) $request->input('position', $question->position)]);
        $this->audit($request, 'assessment_question.updated', (string) $question->id);

        return back()->with('status', 'حُفظ السؤال.');
    }

    public function destroyQuestion(Request $request, AssessmentQuestion $question)
    {
        $this->authorizeEditor($request);
        $question->delete();
        $this->audit($request, 'assessment_question.deleted', (string) $question->id);

        return back()->with('status', 'حُذف السؤال.');
    }

    public function saveRule(Request $request)
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'audience' => ['required', Rule::in(['owner', 'marketer'])], 'level' => ['required', Rule::in(array_keys(Catalog::LEVELS))],
            'weakest_dimension' => ['required', Rule::in(array_keys(Catalog::DIMENSIONS))], 'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'priorities' => ['required', 'array', 'size:3'], 'priorities.*.title' => ['required', 'string', 'max:200'], 'priorities.*.reason' => ['required', 'string', 'max:600'],
            'priorities.*.lesson' => ['nullable', 'string', 'max:120'], 'priorities.*.course' => ['nullable', 'string', 'max:120'],
        ]);
        $rule = AssessmentRule::query()->updateOrCreate(
            ['audience' => $data['audience'], 'level' => $data['level'], 'weakest_dimension' => $data['weakest_dimension']],
            ['lesson_id' => $data['lesson_id'] ?? null, 'priorities' => array_values($data['priorities'])],
        );
        $this->audit($request, 'assessment_rule.saved', (string) $rule->id);

        return back()->with('status', 'حُفظت قاعدة التوجيه.');
    }

    private function validateQuestion(Request $request): array
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(['owner', 'marketer'])], 'dimension' => ['required', Rule::in(array_keys(Catalog::DIMENSIONS))],
            'prompt' => ['required', 'string', 'max:1000'], 'is_active' => ['nullable', 'boolean'],
            'options' => ['required', 'array', 'min:3', 'max:5'], 'options.*.label' => ['required', 'string', 'max:300'], 'options.*.weight' => ['required', 'integer', 'min:0', 'max:3'],
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['options'] = array_values($data['options']);

        return $data;
    }

    private function authorizeEditor(Request $request): void
    {
        abort_unless(in_array($request->user()?->platform_role, self::ROLES, true), 403);
    }

    private function audit(Request $request, string $action, string $subjectId): void
    {
        DB::table('audit_logs')->insert(['user_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'assessment', 'subject_id' => $subjectId, 'context' => null, 'request_id' => $request->attributes->get('request_id'), 'created_at' => now(), 'updated_at' => now()]);
    }
}
