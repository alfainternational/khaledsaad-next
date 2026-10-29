<?php

namespace App\Domain\Content\Services;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\GlossaryTerm;
use App\Domain\Lab\Models\Tool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * البحث (blueprint §4.12): one field, grouped results (courses, lessons, tools, items, terms), 10 per group.
 * MySQL uses the FULLTEXT indexes in BOOLEAN MODE on title/excerpt/body (items) and term/definition/body (terms);
 * SQLite (local, browser suite) falls back to LIKE on the same columns. Courses, lessons and tools are small tables: LIKE everywhere.
 */
class SearchService
{
    public const PER_GROUP = 10;

    public const GROUPS = ['courses' => 'مسارات', 'lessons' => 'دروس', 'tools' => 'أدوات', 'items' => 'مواد', 'terms' => 'مصطلحات'];

    /** The driver decides FULLTEXT vs LIKE; injectable so both branches are unit-testable on any database. */
    public function __construct(private readonly ?string $driver = null) {}

    public function driver(): string
    {
        return $this->driver ?? DB::getDriverName();
    }

    public function search(string $term): array
    {
        $term = $this->normalize($term);
        if (mb_strlen($term) < 2) {
            return ['term' => $term, 'groups' => [], 'total' => 0];
        }
        $groups = [
            'courses' => $this->courses($term),
            'lessons' => $this->lessons($term),
            'tools' => $this->tools($term),
            'items' => $this->items($term),
            'terms' => $this->terms($term),
        ];
        $groups = array_filter($groups, fn (Collection $c) => $c->isNotEmpty());

        return ['term' => $term, 'groups' => $groups, 'total' => array_sum(array_map(fn (Collection $c) => $c->count(), $groups))];
    }

    /** Never an empty page (§4.12): latest items, the most consulted terms and the published courses. */
    public function suggestions(): array
    {
        return [
            'items' => ContentItem::published()->latest('published_at')->limit(5)->get(),
            'terms' => GlossaryTerm::published()->orderByDesc('views_count')->orderBy('term')->limit(8)->get(),
            'courses' => Course::query()->where('is_published', true)->orderBy('position')->limit(6)->get(),
        ];
    }

    public function items(string $term): Collection
    {
        return $this->textSearch(ContentItem::published()->with(['author', 'category']), $term, ['title', 'excerpt', 'body'], 'content_items')
            ->limit(self::PER_GROUP)->get();
    }

    public function terms(string $term): Collection
    {
        return $this->textSearch(GlossaryTerm::published(), $term, ['term', 'definition', 'body'], 'glossary_terms')
            ->limit(self::PER_GROUP)->get();
    }

    private function courses(string $term): Collection
    {
        return Course::query()->where('is_published', true)
            ->where(fn (Builder $q) => $q->where('title', 'like', "%{$term}%")->orWhere('summary', 'like', "%{$term}%")->orWhere('topic', 'like', "%{$term}%")->orWhere('outcome', 'like', "%{$term}%"))
            ->orderBy('position')->limit(self::PER_GROUP)->get();
    }

    private function lessons(string $term): Collection
    {
        return Lesson::query()->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereHas('course', fn (Builder $q) => $q->where('is_published', true))->with('course')
            ->where(fn (Builder $q) => $q->where('title', 'like', "%{$term}%")->orWhere('goal', 'like', "%{$term}%")->orWhere('concept_body', 'like', "%{$term}%"))
            ->orderBy('position')->limit(self::PER_GROUP)->get();
    }

    private function tools(string $term): Collection
    {
        return Tool::query()->where('is_published', true)->where('slug', '!=', 'lesson-lab')
            ->where(fn (Builder $q) => $q->where('name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")->orWhere('tagline', 'like', "%{$term}%")->orWhere('category', 'like', "%{$term}%"))
            ->orderBy('position')->limit(self::PER_GROUP)->get();
    }

    /** FULLTEXT on MySQL, LIKE elsewhere — same columns, same meaning. */
    public function textSearch(Builder $query, string $term, array $columns, string $table): Builder
    {
        if ($this->driver() === 'mysql') {
            $boolean = $this->booleanQuery($term);
            $cols = implode(', ', array_map(fn ($c) => "{$table}.{$c}", $columns));

            return $query->whereRaw("MATCH ({$cols}) AGAINST (? IN BOOLEAN MODE)", [$boolean])
                ->orderByRaw("MATCH ({$cols}) AGAINST (? IN BOOLEAN MODE) DESC", [$boolean]);
        }

        return $query->where(function (Builder $q) use ($term, $columns): void {
            foreach ($columns as $i => $column) {
                $i === 0 ? $q->where($column, 'like', "%{$term}%") : $q->orWhere($column, 'like', "%{$term}%");
            }
        })->orderByDesc($table === 'content_items' ? 'published_at' : 'views_count');
    }

    /** Every word required (+word*) so «تكلفة اكتساب» finds items with both words; operators typed by the visitor are stripped. */
    public function booleanQuery(string $term): string
    {
        $words = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = [];
        foreach ($words as $word) {
            $word = preg_replace('/[+\-~<>()"*@]/u', '', $word) ?? '';
            if (mb_strlen($word) < 2) {
                continue;
            }
            $parts[] = '+'.$word.'*';
        }

        return implode(' ', $parts) ?: '+'.$term;
    }

    /** Arabic normalisation: trim, collapse spaces, strip tatweel and diacritics; keeps the visitor's letters otherwise. */
    public function normalize(string $term): string
    {
        $term = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', trim($term)) ?? $term;
        $term = preg_replace('/\s+/u', ' ', $term) ?? $term;

        return mb_substr($term, 0, 80);
    }
}
