<?php

namespace App\Domain\Academy\Services;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Assessment\Services\Catalog;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Services\GeneratorEngine;
use App\Domain\Workspace\Models\Project;
use App\Support\Markdown;
use Illuminate\Support\Collection;

/**
 * Builds the learner-specific lesson (blueprint §4.5 principles 1 and 3): the situation from the project profile
 * (or the general training situation), pre-filled guided steps and lab fields, and the assets that flow in from earlier lessons.
 */
class LessonComposer
{
    public function __construct(private readonly RuleEvaluator $evaluator, private readonly GeneratorEngine $engine) {}

    /** The value bag the situation, steps, lab and what-if read from. */
    public function situation(Lesson $lesson, ?Project $project, string $mode): array
    {
        $general = $lesson->general_scenario ?? [];
        $profile = $mode === 'project' && $project ? ($project->profile ?? []) : [];
        $values = array_merge($general, array_filter($profile, fn ($v) => $v !== null && $v !== ''));
        $values['mode'] = $mode === 'project' && $project ? 'project' : 'general';
        $values['project_name'] = $project?->name ?? ($general['project_name'] ?? 'المشروع التدريبي');
        $values['budget'] = (int) ($values['budget'] ?? 0);
        $values['goal_number'] = (int) ($values['goal_number'] ?? 0);
        $values['channel_label'] = Catalog::label(Catalog::CHANNELS, $values['channel'] ?? null, 'قناة غير محددة');
        $values['goal_label'] = Catalog::label(Catalog::GOALS, $values['goal'] ?? null, 'هدف غير محدد');
        $values['sector_label'] = Catalog::label(Catalog::SECTORS, $values['sector'] ?? null, 'قطاع غير محدد');
        $values['budget_label'] = $values['budget'] > 0 ? number_format($values['budget']).' ريال' : 'بلا ميزانية إعلانية';

        return $values;
    }

    public function scenarioSentence(Lesson $lesson, array $situation): string
    {
        $template = $lesson->scenario_template ?: 'مشروع {{project_name}} يبيع {{offer}} بميزانية {{budget_label}} عبر {{channel_label}}، وهدفه {{goal_label}}.';

        return Markdown::fill($template, $situation);
    }

    /** Guided steps with values: from the request (after a POST), else from the situation. */
    public function guidedSteps(Lesson $lesson, array $situation, array $input = []): array
    {
        $steps = [];
        foreach ($lesson->guided_steps ?? [] as $step) {
            $key = $step['key'];
            $fromInput = array_key_exists($key, $input) && trim((string) $input[$key]) !== '';
            $source = $step['source'] ?? null;
            $fromSituation = ! $fromInput && $source && isset($situation[$source]) && $situation[$source] !== '' && $situation[$source] !== 0;
            $steps[] = $step + [
                'value' => $fromInput ? $input[$key] : ($fromSituation ? $situation[$source] : ($step['default'] ?? '')),
                'prefilled' => $fromSituation && ($situation['mode'] ?? 'general') === 'project',
            ];
        }

        return $steps;
    }

    /** Lab fields (not the guided ones) with values resolved the same way. */
    public function labFields(Lesson $lesson, array $situation, array $input = []): array
    {
        $fields = [];
        foreach ($lesson->lab_config['fields'] ?? [] as $field) {
            $key = $field['key'];
            $fromInput = array_key_exists($key, $input) && trim((string) $input[$key]) !== '';
            $source = $field['source'] ?? null;
            $fromSituation = ! $fromInput && $source && isset($situation[$source]) && $situation[$source] !== '' && $situation[$source] !== 0;
            $fields[] = $field + [
                'value' => $fromInput ? $input[$key] : ($fromSituation ? $situation[$source] : ($field['default'] ?? '')),
                'prefilled' => $fromSituation && ($situation['mode'] ?? 'general') === 'project',
            ];
        }

        return $fields;
    }

    /** Merge situation + guided + lab input into the value bag the templates read. */
    public function values(Lesson $lesson, array $situation, array $input): array
    {
        $values = $situation;
        foreach ($this->guidedSteps($lesson, $situation, $input) as $step) {
            $values[$step['key']] = $step['value'];
        }
        foreach ($this->labFields($lesson, $situation, $input) as $field) {
            $values[$field['key']] = $field['value'];
        }
        foreach (['channel' => Catalog::CHANNELS, 'goal' => Catalog::GOALS, 'sector' => Catalog::SECTORS] as $key => $map) {
            if (isset($values[$key]) && isset($map[$values[$key]])) {
                $values[$key.'_label'] = $map[$values[$key]];
            }
        }

        return $this->evaluator->compute($lesson->lab_config['computed'] ?? [], $values);
    }

    /** Render the asset Markdown from the lab template. */
    public function renderAsset(Lesson $lesson, array $values): string
    {
        $template = $lesson->lab_config['template'] ?? "# {{title}}\n\n{{offer}}";
        $values['title'] = $lesson->outcome_title ?? $lesson->title;
        $values['project_name_line'] = $values['project_name'] ?? '';
        $values['date'] = now()->format('Y/m/d');

        // One filler for lesson labs and lab tools (stage 2): the lab engine renders the template.
        return $this->engine->renderTemplate($template, $values);
    }

    /** Missing required fields (keys → labels) for the lab. */
    public function missing(Lesson $lesson, array $values): array
    {
        $missing = [];
        foreach (array_merge($lesson->guided_steps ?? [], $lesson->lab_config['fields'] ?? []) as $field) {
            if (! empty($field['required']) && trim((string) ($values[$field['key']] ?? '')) === '') {
                $missing[$field['key']] = $field['label'];
            }
        }

        return $missing;
    }

    /** Assets from earlier lessons this lesson builds on (asset continuity), with the learner's saved ones. */
    public function incomingAssets(Lesson $lesson, ?int $userId, ?int $projectId): Collection
    {
        $links = $lesson->assetLinks()->get();
        if ($links->isEmpty()) {
            return collect();
        }
        $saved = $userId
            ? ToolOutput::query()->where('user_id', $userId)->when($projectId, fn ($q) => $q->where('project_id', $projectId))
                ->whereIn('output_type', $links->pluck('required_tool_output_type'))->latest()->get()->keyBy('output_type')
            : collect();
        $lessonsByType = Lesson::query()->whereIn('outcome_type', $links->pluck('required_tool_output_type'))->with('course')->get()->keyBy('outcome_type');

        return $links->map(fn ($link) => [
            'type' => $link->required_tool_output_type,
            'label' => $link->label,
            'asset' => $saved[$link->required_tool_output_type] ?? null,
            'lesson' => $lessonsByType[$link->required_tool_output_type] ?? null,
        ]);
    }

    /** Markdown of incoming assets, appended to the final (aggregating) lesson's output. */
    public function incomingMarkdown(Collection $incoming): string
    {
        $md = '';
        foreach ($incoming as $item) {
            if ($item['asset']) {
                $md .= "\n\n---\n\n".preg_replace('/^# /m', '## ', (string) $item['asset']->body_markdown);
            }
        }

        return $md;
    }
}
