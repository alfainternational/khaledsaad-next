<?php

namespace App\Domain\Lab\Models;

use App\Domain\Workspace\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ملف محفوظ على قرص (plan 2026-11-09 البند 2): the row is the file's only record — see the migration for why. */
class Media extends Model
{
    protected $table = 'media';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'bytes' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function output(): BelongsTo
    {
        return $this->belongsTo(ToolOutput::class, 'tool_output_id');
    }

    /** A file nothing points at. The prune window is measured from created_at, not from when it was orphaned. */
    public function isOrphan(): bool
    {
        return $this->tool_output_id === null;
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->bytes;

        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' م.ب'
            : number_format(max(1, (int) round($bytes / 1024))).' ك.ب';
    }
}
