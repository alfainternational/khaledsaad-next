<?php

namespace App\Domain\Insights\Models;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Content\Models\ContentItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentReport extends Model
{
    /** open → resolved (fixed) | dismissed (no action needed). */
    public const CLOSED_STATUSES = ['resolved' => 'عُولج', 'dismissed' => 'تُجوهل'];

    public const REASONS = ['not_my_case' => 'لا يناسب حالتي', 'wrong_numbers' => 'أرقام غير واقعية', 'outdated' => 'معلومة متقادمة', 'unclear' => 'غير واضح', 'other' => 'أخرى'];

    protected $guarded = [];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }
}
