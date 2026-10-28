<?php

namespace App\Domain\Academy\Services;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Workspace\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saving an asset (الأصل = tool_outputs). A lesson lab asset is stored as a completed tool run of the lesson's lab tool
 * so it lives beside tool outputs in the project, with versions.
 */
class AssetStore
{
    public function save(User $user, Project $project, Lesson $lesson, string $title, string $type, string $markdown, array $input = [], ?array $quality = null): ToolOutput
    {
        return DB::transaction(function () use ($user, $project, $lesson, $title, $type, $markdown, $input, $quality): ToolOutput {
            $toolId = $lesson->lab_tool_id ?? DB::table('tools')->orderBy('id')->value('id');
            $runId = DB::table('tool_runs')->insertGetId([
                'workspace_id' => $project->workspace_id, 'project_id' => $project->id, 'tool_id' => $toolId,
                'tool_version_id' => null, 'user_id' => $user->id, 'public_id' => (string) Str::uuid(), 'status' => 'completed',
                'input_payload' => json_encode(['lesson' => $lesson->slug, 'input' => $input], JSON_UNESCAPED_UNICODE),
                'output_payload' => json_encode(['title' => $title, 'type' => $type, 'text' => $markdown], JSON_UNESCAPED_UNICODE),
                'reserved_credits' => 0, 'used_credits' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $version = (int) ToolOutput::query()->where('user_id', $user->id)->where('project_id', $project->id)->where('output_type', $type)->max('version') + 1;

            return ToolOutput::query()->create([
                'tool_run_id' => $runId, 'version' => $version, 'title' => $title, 'format' => 'markdown', 'output_type' => $type,
                'body_markdown' => $markdown, 'payload' => ['input' => $input], 'user_id' => $user->id, 'project_id' => $project->id,
                'lesson_id' => $lesson->id, 'quality_report' => $quality,
            ]);
        });
    }
}
