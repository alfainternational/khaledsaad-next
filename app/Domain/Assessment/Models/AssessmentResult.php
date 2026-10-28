<?php

namespace App\Domain\Assessment\Models;

use App\Domain\Academy\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentResult extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['project_snapshot' => 'array', 'answers' => 'array', 'scores' => 'array', 'priorities' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function startLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'start_lesson_id');
    }
}
