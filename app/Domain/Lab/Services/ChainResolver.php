<?php

namespace App\Domain\Lab\Services;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Assessment\Services\Catalog;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Workspace\Models\Project;
use Illuminate\Support\Collection;

/**
 * شريط السلسلة (blueprint §4.7 item 2) and the pre-filled inputs (item 3): what feeds this tool (project profile and earlier
 * assets, saved in the project for members or generated in this session for visitors), what it produces, what it feeds,
 * and where it sits in the plan. Also «خطة مشروعك تتجمّع» (item 5).
 */
class ChainResolver
{
    /** Saved assets of a project keyed by output_type (latest version of each). */
    public function savedAssets(?Project $project): Collection
    {
        if (! $project) {
            return collect();
        }

        return ToolOutput::query()->where('project_id', $project->id)->orderByDesc('version')->orderByDesc('id')->get()->unique('output_type')->keyBy('output_type');
    }

    /**
     * Asset bag used for pre-filling: type => ['input' => [...], 'title' => ..., 'source' => 'saved'|'session'].
     * Session assets (visitor) are overridden by saved ones (member).
     */
    public function assets(?Project $project, array $sessionAssets): array
    {
        $bag = [];
        foreach ($sessionAssets as $type => $asset) {
            $bag[$type] = ['input' => $asset['input'] ?? [], 'title' => $asset['title'] ?? LabCatalog::label($type), 'source' => 'session', 'url' => null, 'output' => null];
        }
        foreach ($this->savedAssets($project) as $type => $output) {
            $bag[$type] = ['input' => $output->payload['input'] ?? [], 'title' => $output->title ?? LabCatalog::label($type), 'source' => 'saved', 'url' => route('app.outputs.show', $output), 'output' => $output];
        }

        return $bag;
    }

    /** @return array<string, array{value: mixed, source: string, label: string}> */
    public function prefill(Tool $tool, ?Project $project, array $assets): array
    {
        $profile = $project?->profile ?? [];
        if (! empty($profile['goal']) || ! empty($profile['goal_number'])) {
            $profile['goal_sentence'] = trim(((int) ($profile['goal_number'] ?? 0) > 0 ? (int) $profile['goal_number'].' ' : '').Catalog::label(Catalog::GOALS, $profile['goal'] ?? null, 'نتيجة محددة').' خلال 30 يوماً');
        }
        $filled = [];
        foreach ($tool->fields() as $field) {
            $key = $field['key'];
            $from = $field['from_asset'] ?? null;
            if ($from && isset($assets[$from['type']]['input'][$from['key']]) && trim((string) $assets[$from['type']]['input'][$from['key']]) !== '') {
                $filled[$key] = ['value' => $assets[$from['type']]['input'][$from['key']], 'source' => 'asset', 'label' => 'من '.LabCatalog::label($from['type'])];

                continue;
            }
            $source = $field['source'] ?? null;
            if ($source && isset($profile[$source]) && $profile[$source] !== '' && $profile[$source] !== null) {
                $filled[$key] = ['value' => $profile[$source], 'source' => 'profile', 'label' => 'من المشروع'];
            }
        }

        return $filled;
    }

    /** The chain bar: feeds_from with ✓, produces, feeds_into (with the tool that makes each), plan position and percent. */
    public function chain(Tool $tool, ?Project $project, array $assets): array
    {
        $toolsByType = Tool::query()->where('is_published', true)->where('kind', 'generator')->get()->keyBy('output_type');
        $lessonsByType = Lesson::query()->whereIn('outcome_type', array_keys(LabCatalog::LESSON_ASSETS))->whereNotNull('published_at')->with('course')->get()->keyBy('outcome_type');
        $feedsFrom = [];
        foreach ($tool->feeds_from ?? [] as $type) {
            if ($type === 'profile') {
                $ok = $project && ! empty($project->profile['offer']);
                $feedsFrom[] = ['type' => 'profile', 'label' => 'ملف المشروع', 'ok' => $ok, 'url' => $project ? route('app.projects.show', $project) : route('assess.start'), 'title' => $ok ? $project->name : null];

                continue;
            }
            $asset = $assets[$type] ?? null;
            $maker = $toolsByType[$type] ?? null;
            $lesson = $lessonsByType[$type] ?? null;
            $feedsFrom[] = [
                'type' => $type, 'label' => LabCatalog::label($type), 'ok' => $asset !== null, 'title' => $asset['title'] ?? null, 'source' => $asset['source'] ?? null,
                'url' => $asset['url'] ?? ($maker ? route('tools.show', $maker) : ($lesson ? route('academy.lesson', [$lesson->course, $lesson]) : null)),
            ];
        }
        $feedsInto = [];
        foreach ($tool->feeds_into ?? [] as $type) {
            $maker = $toolsByType[$type] ?? null;
            $feedsInto[] = ['type' => $type, 'label' => LabCatalog::label($type), 'url' => $maker ? route('tools.show', $maker) : null, 'ok' => isset($assets[$type])];
        }
        $position = array_search($tool->output_type, array_keys(LabCatalog::CHAIN), true);
        $plan = $this->plan($project, $assets, $toolsByType);
        $course = $tool->course_slug ? Course::query()->where('slug', $tool->course_slug)->first() : null;

        return [
            'feeds_from' => $feedsFrom, 'produces' => ['type' => $tool->output_type, 'label' => LabCatalog::label((string) $tool->output_type)], 'feeds_into' => $feedsInto,
            'position' => $position === false ? null : $position + 1, 'total' => count(LabCatalog::CHAIN), 'plan' => $plan, 'course' => $course,
            'next' => collect($feedsInto)->first(fn ($f) => ! $f['ok'] && $f['url']) ?? ($feedsInto[0] ?? null),
        ];
    }

    /** «خطة مشروعك تتجمّع»: every chain asset with its state (saved / generated this session / missing). */
    public function plan(?Project $project, array $assets, ?Collection $toolsByType = null): array
    {
        $toolsByType ??= Tool::query()->where('is_published', true)->where('kind', 'generator')->get()->keyBy('output_type');
        $items = [];
        $saved = 0;
        foreach (LabCatalog::CHAIN as $type => $label) {
            $asset = $assets[$type] ?? null;
            $state = $asset === null ? 'missing' : ($asset['source'] === 'saved' ? 'saved' : 'generated');
            if ($state === 'saved') {
                $saved++;
            }
            $maker = $toolsByType[$type] ?? null;
            $items[] = ['type' => $type, 'label' => $label, 'state' => $state, 'title' => $asset['title'] ?? null, 'url' => $asset['url'] ?? ($maker ? route('tools.show', $maker) : null), 'version' => $asset['output']?->version ?? null, 'output_id' => $asset['output']?->id ?? null];
        }
        $total = count(LabCatalog::CHAIN);

        return ['items' => $items, 'saved' => $saved, 'total' => $total, 'percent' => (int) round($saved / $total * 100), 'complete' => $saved === $total, 'project' => $project];
    }
}
