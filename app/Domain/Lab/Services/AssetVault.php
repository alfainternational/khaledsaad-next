<?php

namespace App\Domain\Lab\Services;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Lab\Models\PlanAssembly;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Models\ToolVersion;
use App\Domain\Workspace\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saving, versioning, sharing and assembling assets (الأصل = tool_outputs). One path for the tool page and the lesson lab:
 * a new save of the same asset type in the same project is version n+1 with parent_output_id → the previous one; nothing is overwritten.
 */
class AssetVault
{
    public function save(User $user, Project $project, ?Tool $tool, string $title, string $type, string $markdown, array $input = [], ?array $quality = null, ?Lesson $lesson = null, ?ToolVersion $version = null, array $extraPayload = []): ToolOutput
    {
        return DB::transaction(function () use ($user, $project, $tool, $title, $type, $markdown, $input, $quality, $lesson, $version, $extraPayload): ToolOutput {
            $toolId = $tool?->id ?? $lesson?->lab_tool_id ?? DB::table('tools')->orderBy('id')->value('id');
            $runId = DB::table('tool_runs')->insertGetId([
                'workspace_id' => $project->workspace_id, 'project_id' => $project->id, 'tool_id' => $toolId, 'tool_version_id' => $version?->id,
                'user_id' => $user->id, 'public_id' => (string) Str::uuid(), 'status' => 'completed',
                'input_payload' => json_encode(['input' => $input, 'lesson' => $lesson?->slug], JSON_UNESCAPED_UNICODE),
                'output_payload' => json_encode(['title' => $title, 'type' => $type, 'text' => $markdown], JSON_UNESCAPED_UNICODE),
                'reserved_credits' => 0, 'used_credits' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $previous = ToolOutput::query()->where('project_id', $project->id)->where('output_type', $type)->orderByDesc('version')->first();

            return ToolOutput::query()->create([
                'tool_run_id' => $runId, 'tool_id' => $toolId, 'tool_version_id' => $version?->id, 'parent_output_id' => $previous?->id,
                'version' => ($previous?->version ?? 0) + 1, 'title' => $title, 'format' => 'markdown', 'output_type' => $type,
                'body_markdown' => str_replace("\r\n", "\n", $markdown), 'payload' => ['input' => $input] + $extraPayload,
                'user_id' => $user->id, 'project_id' => $project->id, 'lesson_id' => $lesson?->id, 'quality_report' => $quality,
            ]);
        });
    }

    public function share(ToolOutput $output): ToolOutput
    {
        if ($output->share_token === null) {
            $output->forceFill(['share_token' => Str::random(32), 'shared_at' => now()])->save();
        }

        return $output;
    }

    public function unshare(ToolOutput $output): ToolOutput
    {
        $output->forceFill(['share_token' => null, 'shared_at' => null])->save();

        return $output;
    }

    /** Assemble the one-page plan when the chain is complete; returns the assembly or null. */
    public function assemblePlan(Project $project, array $plan): ?PlanAssembly
    {
        if (! ($plan['complete'] ?? false)) {
            return null;
        }
        $course = Course::query()->where('slug', 'integrated-marketing-plan')->first() ?? Course::query()->where('slug', 'marketing-foundations')->first();
        $outputs = collect($plan['items'])->map(fn ($i) => ['type' => $i['type'], 'label' => $i['label'], 'output_id' => $i['output_id'] ?? null, 'title' => $i['title'], 'version' => $i['version']])->all();

        return PlanAssembly::query()->updateOrCreate(['project_id' => $project->id], ['course_id' => $course?->id, 'outputs' => $outputs, 'assembled_at' => now()]);
    }

    /** The assembled plan as one Markdown document (latest version of each chain asset, in plan order). */
    public function planMarkdown(Project $project): string
    {
        $latest = ToolOutput::query()->where('project_id', $project->id)->whereIn('output_type', array_keys(LabCatalog::CHAIN))->orderByDesc('version')->get()->unique('output_type')->keyBy('output_type');
        $md = '# خطة التسويق — '.$project->name."\n\nمجمّعة من ".$latest->count().' أصول في '.now()->format('Y/m/d').".\n";
        foreach (LabCatalog::CHAIN as $type => $label) {
            if (! isset($latest[$type])) {
                continue;
            }
            $md .= "\n\n---\n\n".preg_replace('/^# /m', '## ', (string) $latest[$type]->body_markdown);
        }

        return $md;
    }
}
