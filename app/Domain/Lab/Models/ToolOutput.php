<?php

namespace App\Domain\Lab\Models;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Workspace\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** الأصل = tool_outputs: a versioned, shareable Markdown asset owned by a member inside a project. */
class ToolOutput extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'quality_report' => 'array', 'shared_at' => 'datetime'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ToolRun::class, 'tool_run_id');
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_output_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_output_id');
    }

    public function isShared(): bool
    {
        return $this->share_token !== null;
    }

    /** All versions of this asset line (same project + type), newest first. */
    public function lineage()
    {
        return self::query()->where('project_id', $this->project_id)->where('output_type', $this->output_type)->orderByDesc('version')->get();
    }
}
