<?php

namespace App\Domain\Content\Models;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Lab\Models\Tool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** مصطلح القاموس (blueprint §4.10): a direct one-line definition, an example, and the lesson/tool that apply it. */
class GlossaryTerm extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['tags' => 'array', 'is_published' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function relatedLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'related_lesson_id');
    }

    public function relatedTool(): BelongsTo
    {
        return $this->belongsTo(Tool::class, 'related_tool_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** Published items sharing a tag with this term (the «مواد مرتبطة» block). */
    public function relatedItems(int $limit = 4)
    {
        $tags = array_values(array_filter($this->tags ?? [], 'is_string'));
        $query = ContentItem::published()->with('author')->latest('published_at');
        if ($tags !== []) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('slug', $tags));
        } else {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$this->term.'%')->orWhere('body', 'like', '%'.$this->term.'%'));
        }

        return $query->limit($limit)->get();
    }
}
