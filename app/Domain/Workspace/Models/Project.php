<?php

namespace App\Domain\Workspace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(fn (Project $project) => $project->public_id ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['profile' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
