<?php

namespace App\Domain\Assessment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** نقطة إعادة قياس (spec 2026-11-09، الموجة 2): موعد يُسأل فيه السؤال نفسه مرة أخرى، ليُعرف هل تحرّك الرقم. */
class AssessmentFollowup extends Model
{
    protected $guarded = [];

    /** The three points: close enough to matter, far enough apart that a number can actually move. */
    public const DAYS = [30, 60, 90];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime', 'reminder_sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function baseline(): BelongsTo
    {
        return $this->belongsTo(AssessmentResult::class, 'baseline_result_id');
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(AssessmentResult::class, 'result_id');
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    public function isDue(): bool
    {
        return ! $this->isDone() && $this->due_at->isPast();
    }
}
