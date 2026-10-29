<?php

namespace App\Domain\Content\Models;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Lab\Models\Tool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * المادة في المجلة (blueprint §4.10–4.11, §7). type is the editorial kind; section is the public shelf it sits on
 * and is derived from type when not set explicitly, so imported rows and old seeders keep working.
 */
class ContentItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    public const TYPES = ['article', 'guide', 'case_study', 'news', 'translated_analysis', 'research'];

    /** Public sections (§4.10): slug => [label, default type, schema type]. */
    public const SECTIONS = [
        'articles' => ['label' => 'مقالات وأدلة', 'type' => 'article', 'schema' => 'Article', 'kicker' => 'المجلة', 'intro' => 'أدلة ومقالات تربط الفكرة بالقرار والأداة.'],
        'news' => ['label' => 'أخبار', 'type' => 'news', 'schema' => 'NewsArticle', 'kicker' => 'ماذا تغيّر؟', 'intro' => 'تحديثات المنصات والصناعة مختصرة بتاريخ ومصدر، وما تفعله هذا الأسبوع.'],
        'insights' => ['label' => 'رؤى عالمية', 'type' => 'translated_analysis', 'schema' => 'Article', 'kicker' => 'من العالم إلى سياقك', 'intro' => 'ملخصات تحليلية لمصادر عالمية موثوقة، بمراجعة بشرية وسياق عربي إلزامي.'],
        'case-studies' => ['label' => 'دراسات حالة', 'type' => 'case_study', 'schema' => 'Article', 'kicker' => 'من السوق', 'intro' => 'حالات تعليمية من السوق العربي: السياق، القرار، التنفيذ، القياس.'],
        'research' => ['label' => 'أبحاث', 'type' => 'research', 'schema' => 'ScholarlyArticle', 'kicker' => 'أرقام بمصادرها', 'intro' => 'قراءات في تقارير وأرقام منشورة، بحدودها وبما تعنيه لمشروعك.'],
    ];

    public const TYPE_SECTION = ['article' => 'articles', 'guide' => 'articles', 'news' => 'news', 'translated_analysis' => 'insights', 'case_study' => 'case-studies', 'research' => 'research'];

    public const TYPE_LABELS = ['article' => 'مقال', 'guide' => 'دليل', 'case_study' => 'دراسة حالة', 'news' => 'خبر', 'translated_analysis' => 'رؤية عالمية', 'research' => 'بحث'];

    /** سير الترجمة (§6.3): the gates a global source passes before it becomes a published item. */
    public const STAGES = ['captured' => 'التقاط', 'verified' => 'تحقق', 'summarized' => 'ملخص', 'contextualized' => 'سياق عربي', 'reviewed' => 'مراجعة', 'published' => 'منشور'];

    /** Types whose numbers need a primary source before publication. */
    public const SOURCED_TYPES = ['translated_analysis', 'news', 'research'];

    protected static function booted(): void
    {
        static::saving(function (ContentItem $item): void {
            if (blank($item->section) || ($item->isDirty('type') && ! $item->isDirty('section'))) {
                $item->section = self::TYPE_SECTION[$item->type] ?? 'articles';
            }
        });
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'reviewed_at' => 'datetime', 'last_reviewed_at' => 'datetime', 'meta' => 'array', 'faq' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'content_tag');
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
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeSection(Builder $query, string $section): Builder
    {
        return $query->where('section', $section);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->lte(now());
    }

    public function sectionLabel(): string
    {
        return self::SECTIONS[$this->section]['label'] ?? self::SECTIONS['articles']['label'];
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function schemaType(): string
    {
        return $this->type === 'news' ? 'NewsArticle' : (self::SECTIONS[$this->section]['schema'] ?? 'Article');
    }

    public function requiresSources(): bool
    {
        return in_array($this->type, self::SOURCED_TYPES, true);
    }

    /** Display name: the linked author profile wins over the free-text byline. */
    public function authorName(): string
    {
        return $this->author?->name ?: (string) $this->author_name;
    }

    /** The oldest source date behind this item's numbers, or null when it cites nothing dated. */
    public function oldestSourceDate(): ?\Carbon\CarbonInterface
    {
        return $this->sources->filter(fn (Source $s) => $s->published_on !== null)->min('published_on');
    }

    /** §6.3: items whose numbers rest on sources older than twelve months need a refresh reminder. */
    public function hasStaleSources(): bool
    {
        $oldest = $this->oldestSourceDate();

        return $oldest !== null && $this->requiresSources() && $oldest->lt(now()->subMonths(12));
    }

    /** Real FAQ entries only (question + answer both present). */
    public function faqEntries(): array
    {
        return array_values(array_filter($this->faq ?? [], fn ($f) => is_array($f) && filled($f['q'] ?? null) && filled($f['a'] ?? null)));
    }
}
