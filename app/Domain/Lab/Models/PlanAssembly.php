<?php

namespace App\Domain\Lab\Models;

use App\Domain\Academy\Models\Course;
use App\Domain\Workspace\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** «خطة مشروعك تتجمّع»: the one-page plan assembled from a complete asset chain (blueprint §4.7). */
class PlanAssembly extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['outputs' => 'array', 'assembled_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
