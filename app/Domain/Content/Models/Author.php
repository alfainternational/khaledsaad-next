<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** المؤلف (E-E-A-T, blueprint §4.10): name, role, bio and public links rendered as a Person schema. */
class Author extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['links' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContentItem::class);
    }

    /** Only http(s) links are ever rendered. */
    public function safeLinks(): array
    {
        return array_values(array_filter($this->links ?? [], fn ($l) => is_array($l) && isset($l['url']) && preg_match('#^https?://#i', (string) $l['url'])));
    }
}
