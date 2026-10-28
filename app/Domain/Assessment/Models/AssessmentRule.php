<?php

namespace App\Domain\Assessment\Models;

use App\Domain\Academy\Models\Lesson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['priorities' => 'array'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
