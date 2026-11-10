<?php

namespace App\Domain\Insights\Models;

use App\Domain\Workspace\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** صفحة مراقَبة (spec 2026-11-09، الموجة 2): رابط يُفحص دورياً، وما يُقارن هو نصّه المطبّع لا شفرته. */
class WatchedPage extends Model
{
    protected $guarded = [];

    /**
     * The schema's defaults are not the model's: a page created and checked in the same request would otherwise read
     * `is_active` as null — and null casts to false, which silently skips the very check that just created it.
     */
    protected $attributes = [
        'is_active' => true,
        'notify' => true,
        'change_count' => 0,
        'failure_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'notify' => 'boolean',
            'last_checked_at' => 'datetime', 'last_changed_at' => 'datetime',
        ];
    }

    public static function hashUrl(string $url): string
    {
        return hash('sha256', rtrim(strtolower(trim($url)), '/'));
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(PageChange::class)->latest('id');
    }

    public function host(): string
    {
        return (string) (parse_url($this->url, PHP_URL_HOST) ?: $this->url);
    }

    /** A page that has failed repeatedly is paused rather than retried forever; the member is told why. */
    public function isStalled(): bool
    {
        return $this->failure_count >= 5;
    }
}
