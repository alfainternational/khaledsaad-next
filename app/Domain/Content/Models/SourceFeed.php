<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** مصدر عالمي مراقب (blueprint §6.3, §6.6): an RSS/Atom feed the capture gate polls daily. */
class SourceFeed extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_fetched_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SourceItem::class);
    }
}
