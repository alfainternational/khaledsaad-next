<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;

/** مشترك النشرة: double opt-in — confirmed_at is set only from the emailed link. */
class NewsletterSubscriber extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->confirmed_at !== null && $this->unsubscribed_at === null;
    }
}
