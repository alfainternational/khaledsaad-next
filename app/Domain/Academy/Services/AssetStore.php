<?php

namespace App\Domain\Academy\Services;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Lab\Models\ToolOutput;
use App\Domain\Lab\Services\AssetVault;
use App\Domain\Workspace\Models\Project;
use App\Models\User;

/**
 * Saving a lesson asset (الأصل = tool_outputs). Delegates to the lab's AssetVault so a lesson asset and a tool asset share one
 * versioning path (stage 2 rule: no two logic paths) and show up in each other's chains.
 */
class AssetStore
{
    public function __construct(private readonly AssetVault $vault) {}

    public function save(User $user, Project $project, Lesson $lesson, string $title, string $type, string $markdown, array $input = [], ?array $quality = null): ToolOutput
    {
        return $this->vault->save($user, $project, null, $title, $type, $markdown, $input, $quality, $lesson, null, ['source' => 'lesson']);
    }
}
