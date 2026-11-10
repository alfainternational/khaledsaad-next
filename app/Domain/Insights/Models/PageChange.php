<?php

namespace App\Domain\Insights\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** تغيّر واحد على صفحة مراقَبة: الفرق أولاً، والملخّص النصّي إن توفّر نموذج — لا العكس. */
class PageChange extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['added' => 'array', 'removed' => 'array', 'seen' => 'boolean'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(WatchedPage::class, 'watched_page_id');
    }
}
