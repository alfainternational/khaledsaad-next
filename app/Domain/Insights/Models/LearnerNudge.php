<?php

namespace App\Domain\Insights\Models;

use App\Domain\Academy\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A reminder sent to a stalled learner (blueprint §6.1 slot 6, §6.6) with its delivery and behavioural result. */
class LearnerNudge extends Model
{
    /** The same learner is not reminded about the same lesson twice inside this window. */
    public const COOLDOWN_DAYS = 7;

    public const STATUSES = ['queued' => 'في الطابور', 'sent' => 'أُرسل', 'failed' => 'فشل الإرسال'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'returned_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
