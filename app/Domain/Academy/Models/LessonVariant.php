<?php

namespace App\Domain\Academy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An alternative scenario of a lesson for another audience (blueprint §6.2): saved as a draft, applied by an editor as a new lesson version. */
class LessonVariant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['applied_at' => 'datetime', 'applied_version' => 'integer'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
