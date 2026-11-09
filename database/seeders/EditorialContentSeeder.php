<?php

namespace Database\Seeders;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Models\ContentCategory;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\Tag;
use App\Domain\Lab\Models\Tool;
use App\Support\FrontMatter;
use App\Support\Markdown;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/** Owner-run release. No network requests; only checked-in starter baselines may be replaced. */
class EditorialContentSeeder extends Seeder
{
    public const REVISION = 'reading-2026-10';

    public function run(bool $freshInstall = false): void
    {
        $baseline = require database_path('content/editorial-baselines.php');
        $baselineState = require database_path('content/editorial-baseline-state.php');
        foreach ($this->documents() as [$attrs, $body]) {
            $slug = $attrs['slug'] ?? '';
            if (! isset($baseline[$slug]) || $body === '') {
                continue;
            }
            DB::transaction(function () use ($attrs, $body, $slug, $baseline, $baselineState, $freshInstall): void {
                $item = ContentItem::query()->where('slug', $slug)->lockForUpdate()->first();
                if (! $item) {
                    $this->command?->warn('MISSING '.$slug.' — not created by editorial release');

                    return;
                }
                $hash = hash('sha256', $item->body ?? '');
                $nextHash = hash('sha256', $body);
                $nextSourceHash = hash('sha256', json_encode([$attrs, $body], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                $meta = $item->meta ?? [];
                $alreadyApplied = ($meta['editorial_revision'] ?? null) === self::REVISION;
                $stateHash = $this->stateHash($item);
                // A mutable meta hash never authorizes replacement of unknown editor text.
                $approvedPrior = $alreadyApplied && DB::table('content_editorial_revisions')
                    ->where('content_item_id', $item->id)->where('revision', self::REVISION)
                    ->where('after_hash', $hash)->where('after_state_hash', $stateHash)->exists();
                $safeFreshBody = $freshInstall && ($hash === $nextHash || $hash === $baseline[$slug]) && ! $alreadyApplied;
                $originalState = ! $alreadyApplied && $hash === $baseline[$slug]
                    && $this->matchesBaseline($item, $baselineState[$slug], $attrs);
                if (! $originalState && ! $approvedPrior && ! $safeFreshBody) {
                    $this->command?->warn('CONFLICT '.$slug.' — editor content or metadata retained');

                    return;
                }
                if ($alreadyApplied && ($meta['editorial_hash'] ?? null) === $nextHash
                    && ($meta['editorial_source_hash'] ?? null) === $nextSourceHash && $hash === $nextHash) {
                    return;
                }

                $values = ['body' => $body, 'reading_minutes' => max(1, (int) ceil(Markdown::wordCount($body) / 180))];
                foreach (['title', 'excerpt', 'tldr', 'eyebrow', 'faq'] as $field) {
                    if (array_key_exists($field, $attrs)) {
                        $values[$field] = $attrs[$field];
                    }
                }
                foreach (['category' => [ContentCategory::class, 'category_id'], 'author' => [Author::class, 'author_id'], 'tool' => [Tool::class, 'related_tool_id'], 'lesson' => [Lesson::class, 'related_lesson_id']] as $key => [$model, $column]) {
                    if (isset($attrs[$key])) {
                        $related = $model::query()->where('slug', $attrs[$key])->first();
                        $values[$column] = $related?->id;
                        if ($key === 'author' && $related) {
                            $values['author_name'] = $related->name;
                        }
                    }
                }
                if (isset($attrs['topic'])) {
                    $values['topic'] = Course::query()->where('slug', $attrs['topic'])->value('topic') ?? $attrs['topic'];
                    $meta['topic_slug'] = $attrs['topic'];
                }
                if (isset($attrs['arabic_context'])) {
                    $meta['arabic_context'] = $attrs['arabic_context'];
                }
                // A duration correction alone is not a claim that this material was reviewed.
                if (($attrs['editorial_revision'] ?? null) === self::REVISION && isset($attrs['last_reviewed_at'])) {
                    $values['last_reviewed_at'] = $attrs['last_reviewed_at'];
                }
                $meta['word_count'] = Markdown::wordCount($body);
                $meta['editorial_revision'] = self::REVISION;
                $meta['editorial_hash'] = $nextHash;
                $meta['editorial_source_hash'] = $nextSourceHash;
                $values['meta'] = $meta;

                // Full state and original source IDs/timestamps make the replacement recoverable.
                $revisionId = DB::table('content_editorial_revisions')->insertGetId([
                    'content_item_id' => $item->id, 'slug' => $slug, 'revision' => self::REVISION,
                    'before_hash' => $hash, 'after_hash' => $nextHash,
                    'before_state_hash' => $stateHash,
                    'snapshot' => json_encode(['item' => $item->attributesToArray(), 'sources' => $item->sources()->get()->toArray(), 'tag_ids' => $item->tags()->pluck('tags.id')->all()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
                $item->fill($values)->save();
                if (array_key_exists('tags', $attrs)) {
                    $item->tags()->sync(Tag::query()->whereIn('slug', (array) $attrs['tags'])->pluck('id')->all());
                }
                if (array_key_exists('sources', $attrs)) {
                    $item->sources()->delete();
                    foreach ((array) $attrs['sources'] as $source) {
                        if (! is_array($source) || ! filter_var($source['url'] ?? '', FILTER_VALIDATE_URL) || ! in_array(parse_url($source['url'], PHP_URL_SCHEME), ['http', 'https'], true)) {
                            throw new \RuntimeException('Invalid editorial source for '.$slug);
                        }
                        $item->sources()->create([
                            'publisher' => $source['publisher'] ?? parse_url($source['url'], PHP_URL_HOST),
                            'title' => $source['title'] ?? $source['publisher'] ?? $source['url'],
                            'url' => $source['url'], 'published_on' => $source['date'] ?? null,
                            'language' => $source['language'] ?? 'en',
                        ]);
                    }
                }
                DB::table('content_editorial_revisions')->where('id', $revisionId)->update(['after_state_hash' => $this->stateHash($item->fresh())]);
                $this->command?->info('UPDATED '.$slug);
            });
        }
        Markdown::flushToolCache();
    }

    /** Compare immutable original values only for fields this release can replace. */
    private function matchesBaseline(ContentItem $item, array $baseline, array $incoming): bool
    {
        $attrs = $baseline['attrs'];
        $legacy = $baseline['legacy'];
        foreach (['title', 'excerpt', 'tldr', 'eyebrow', 'faq'] as $field) {
            if (array_key_exists($field, $incoming)) {
                $expected = $attrs[$field] ?? ($field === 'eyebrow' && ! $legacy ? ContentItem::TYPE_LABELS[$attrs['type']] : null);
                if ($item->{$field} !== $expected) {
                    return false;
                }
            }
        }
        $minutes = (int) ($attrs['reading_minutes'] ?? max(3, (int) ceil(($baseline['word_count'] ?? 0) / 180)));
        if ((int) $item->reading_minutes !== $minutes) {
            return false;
        }
        $topic = $legacy ? ($attrs['topic'] ?? null) : (Course::query()->where('slug', $attrs['topic'] ?? '')->value('topic') ?? ($attrs['topic'] ?? null));
        if (isset($incoming['topic']) && $item->topic !== $topic) {
            return false;
        }
        $legacyCategory = match ($attrs['topic'] ?? '') {
            'النمو', 'الإعلانات' => 'ads', 'المحتوى', 'البريد الإلكتروني', 'البحث وGEO', 'SEO وGEO' => 'content',
            'الذكاء الاصطناعي' => 'ai', 'القياس' => 'measurement', default => 'strategy',
        };
        foreach (['category' => 'category', 'author' => 'author', 'tool' => 'relatedTool', 'lesson' => 'relatedLesson'] as $key => $relation) {
            if (isset($incoming[$key])) {
                $expected = $key === 'category' && $legacy ? $legacyCategory : ($attrs[$key] ?? ($key === 'author' && ! $legacy ? 'khaled-saad' : null));
                if ($item->{$relation}?->slug !== $expected) {
                    return false;
                }
                if ($key === 'author') {
                    $declared = collect(MagazineSeeder::AUTHORS)->firstWhere('slug', $expected);
                    if ($item->author_name !== ($declared['name'] ?? null)) {
                        return false;
                    }
                }
            }
        }
        $meta = $item->meta ?? [];
        if (isset($meta['editorial_revision']) || isset($meta['editorial_hash']) || isset($meta['editorial_source_hash'])
            || ($meta['word_count'] ?? null) !== ($baseline['word_count'] ?? null)
            || ($meta['topic_slug'] ?? null) !== ($legacy ? null : ($attrs['topic'] ?? null))) {
            return false;
        }
        if (isset($incoming['arabic_context'])) {
            $context = $legacy ? 'تحليل يربط المصدر العالمي بقرارات التسويق في السوق العربي.'
                : ($attrs['arabic_context'] ?? (in_array($attrs['type'], ContentItem::SOURCED_TYPES, true) ? Str::limit($attrs['tldr'] ?? $attrs['excerpt'] ?? '', 400, '') : null));
            if (($meta['arabic_context'] ?? null) !== $context) {
                return false;
            }
        }
        if (isset($incoming['last_reviewed_at'])) {
            $legacyOffsets = [
                'digital-marketing-strategy-guide' => 1, 'responsible-marketing-ai' => 2, 'customer-journey-map' => 3,
                'ai-search-arabic-marketers' => 4, 'ad-platform-updates-framework' => 5, 'topic-cluster-case-study' => 6,
                'email-newsletter-business-guide' => 7, 'before-scaling-ad-budget' => 8, 'helpful-content-generative-answers' => 9,
            ];
            $expectedDate = isset($attrs['published_at']) && ! $legacy
                ? Carbon::parse($attrs['published_at'])
                : $item->created_at->copy()->subDays($legacy ? $legacyOffsets[$item->slug] : 7);
            if ($item->last_reviewed_at?->format('Y-m-d H:i:s') !== $expectedDate->format('Y-m-d H:i:s')) {
                return false;
            }
        }
        $expectedTags = array_values(array_intersect((array) ($attrs['tags'] ?? []), array_keys(MagazineSeeder::TAGS)));
        sort($expectedTags);
        if ($item->tags()->orderBy('slug')->pluck('slug')->all() !== $expectedTags) {
            return false;
        }
        $expectedSources = $attrs['sources'] ?? [];
        if ($legacy) {
            $translated = $attrs['type'] === 'translated_analysis';
            $expectedSources = [[
                'publisher' => $translated ? 'Google Search Central' : 'منهجية أثر',
                'title' => $translated ? 'إرشادات إنشاء محتوى مفيد وموثوق' : 'مرجع تحريري داخلي',
                'url' => $translated ? 'https://developers.google.com/search/docs/fundamentals/creating-helpful-content' : 'https://khaledsaad.net/methodology',
                'language' => $translated ? 'en' : 'ar',
            ]];
        }
        $actualSources = $item->sources()->orderBy('id')->get();
        if ($actualSources->count() !== count($expectedSources)) {
            return false;
        }
        foreach ($expectedSources as $i => $source) {
            $actual = $actualSources[$i];
            foreach (['publisher', 'title', 'url', 'language'] as $key) {
                $expected = $source[$key] ?? ($key === 'language' ? 'en' : ($key === 'publisher' ? parse_url($source['url'], PHP_URL_HOST) : ($source['publisher'] ?? 'المصدر')));
                if ($actual->{$key} !== $expected) {
                    return false;
                }
            }
            // Legacy seed dates were relative to creation; never use today's date as their baseline.
            $date = $legacy ? $actual->created_at->copy()->subMonth()->format('Y-m-d') : ($source['date'] ?? null);
            if ($actual->published_on?->format('Y-m-d') !== $date) {
                return false;
            }
        }

        return true;
    }

    private function stateHash(ContentItem $item): string
    {
        // Only editorial fields this release can replace; publication state, counters and timestamps are independent.
        $attributes = array_intersect_key($item->attributesToArray(), array_flip([
            'id', 'slug', 'title', 'excerpt', 'body', 'tldr', 'eyebrow', 'faq', 'topic', 'reading_minutes',
            'category_id', 'author_id', 'author_name', 'related_tool_id', 'related_lesson_id', 'last_reviewed_at', 'meta',
        ]));
        $sources = $item->sources()->orderBy('id')->get()->map(function ($source): array {
            return array_intersect_key($source->attributesToArray(), array_flip([
                'id', 'content_item_id', 'publisher', 'title', 'url', 'published_on', 'language',
            ]));
        })->all();
        $state = ['item' => $attributes, 'sources' => $sources, 'tag_ids' => $item->tags()->orderBy('tags.id')->pluck('tags.id')->all()];
        $canonicalize = function (&$value) use (&$canonicalize): void {
            if (is_array($value)) {
                if (! array_is_list($value)) {
                    ksort($value);
                }
                foreach ($value as &$child) {
                    $canonicalize($child);
                }
            }
        };
        $canonicalize($state);

        return hash('sha256', json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return iterable<array{0: array, 1: string}> */
    protected function documents(): iterable
    {
        foreach (['magazine', 'editorial'] as $directory) {
            if (! File::isDirectory(database_path('content/'.$directory))) {
                continue;
            }
            foreach (File::allFiles(database_path('content/'.$directory)) as $file) {
                if ($file->getExtension() === 'md') {
                    yield FrontMatter::parse(File::get($file->getPathname()));
                }
            }
        }
    }
}
