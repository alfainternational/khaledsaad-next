<?php

namespace App\Domain\Content\Models;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Lab\Models\Tool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * عنصر ملتقط من مصدر (blueprint §6.3): moves through the five gates
 * captured → verified → summarized → contextualized → reviewed → published, or is ignored with a reason.
 */
class SourceItem extends Model
{
    protected $guarded = [];

    public const STAGES = ['captured' => 'التقاط', 'verified' => 'تحقق', 'summarized' => 'ملخص', 'contextualized' => 'سياق عربي', 'reviewed' => 'مراجعة ونشر', 'published' => 'منشور', 'ignored' => 'متجاهل'];

    /** The five gates shown as columns on the board (§6.3). */
    public const GATES = ['captured', 'verified', 'summarized', 'contextualized', 'reviewed'];

    public const MIN_CONTEXT_WORDS = 120;

    protected function casts(): array
    {
        return ['published_on' => 'date', 'matched_keywords' => 'array', 'verification' => 'array'];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(SourceFeed::class, 'source_feed_id');
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function relatedLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'related_lesson_id');
    }

    public function relatedTool(): BelongsTo
    {
        return $this->belongsTo(Tool::class, 'related_tool_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('stage', '!=', 'ignored');
    }

    public static function hashUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/[?#].*$/', '', $url) ?? $url; // tracking params never make a duplicate unique
        $url = preg_replace('#^https?://(www\.)?#i', '', $url) ?? $url;

        return hash('sha256', mb_strtolower(rtrim($url, '/')));
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? $this->stage;
    }

    public function publisherName(): string
    {
        return $this->publisher ?: ($this->feed?->name ?? parse_url((string) $this->url, PHP_URL_HOST) ?: 'مصدر');
    }

    public function contextWordCount(): int
    {
        return str_word_count_utf8($this->arabic_context);
    }
}
