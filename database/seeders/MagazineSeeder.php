<?php

namespace Database\Seeders;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Models\ContentCategory;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\GlossaryTerm;
use App\Domain\Content\Models\SourceFeed;
use App\Domain\Content\Models\Tag;
use App\Domain\Lab\Models\Tool;
use App\Support\FrontMatter;
use App\Support\Markdown;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * المرحلة 3 — المجلة (blueprint §8): categories, tags, authors, the 14 watched source feeds, the glossary and the
 * magazine items read from database/content/*.md. Idempotent: everything is updateOrCreate by slug, and items an
 * editor already published are refreshed in content only (status and publish date are never downgraded).
 * Existing production rows (legacy imports) get a category and author assigned when they have none, without touching their text.
 */
class MagazineSeeder extends Seeder
{
    public const CATEGORIES = [
        ['slug' => 'strategy', 'name' => 'الاستراتيجية والتخطيط', 'description' => 'من الهدف التجاري إلى خطة تسويق قابلة للقياس.'],
        ['slug' => 'audience', 'name' => 'الجمهور والعملاء', 'description' => 'الشرائح، الشخصيات، ورحلة العميل.'],
        ['slug' => 'content', 'name' => 'المحتوى والسوشيال', 'description' => 'الكتابة، التقويم، والقنوات الاجتماعية.'],
        ['slug' => 'ads', 'name' => 'الإعلانات والنمو', 'description' => 'الحملات المدفوعة، التوسع، وقواعد الإيقاف.'],
        ['slug' => 'measurement', 'name' => 'القياس والتحليلات', 'description' => 'المؤشرات، الإسناد، وتحسين التحويل.'],
        ['slug' => 'ai', 'name' => 'الذكاء الاصطناعي والأتمتة', 'description' => 'الموجّهات، سير العمل، والحدود المسؤولة.'],
    ];

    public const TAGS = [
        'seo' => 'SEO', 'geo' => 'GEO ومحركات الإجابة', 'content-marketing' => 'التسويق بالمحتوى', 'social-media' => 'السوشيال ميديا',
        'paid-ads' => 'الإعلانات المدفوعة', 'email-marketing' => 'التسويق بالبريد', 'crm' => 'CRM', 'analytics' => 'التحليلات',
        'cro' => 'تحسين التحويل', 'ai-tools' => 'أدوات الذكاء الاصطناعي', 'prompts' => 'الموجّهات (Prompts)', 'automation' => 'الأتمتة',
        'branding' => 'العلامة', 'positioning' => 'التموضع', 'pricing' => 'التسعير', 'customer-journey' => 'رحلة العميل',
        'personas' => 'شخصيات العميل', 'saudi-market' => 'السوق السعودي', 'ecommerce' => 'التجارة الإلكترونية', 'b2b' => 'B2B',
        'small-business' => 'المشاريع الصغيرة', 'startups' => 'الشركات الناشئة', 'retention' => 'الاحتفاظ بالعملاء', 'attribution' => 'الإسناد',
        'landing-pages' => 'صفحات الهبوط', 'copywriting' => 'كتابة الإعلان',
    ];

    public const AUTHORS = [
        ['slug' => 'khaled-saad', 'name' => 'خالد سعد', 'role' => 'المؤسس والمحرر الرئيسي',
            'bio' => 'مستشار تسويق رقمي وذكاء اصطناعي بخبرة تتجاوز خمس عشرة سنة مع شركات ومشاريع في السوق السعودي والعربي. أسّس «أثر» لتحويل التعلّم التسويقي إلى أصول قابلة للقياس على مشروع المتعلم نفسه. يكتب في الاستراتيجية والقياس والذكاء الاصطناعي، ويراجع كل مادة عالمية قبل نشرها.',
            'links' => [['label' => 'الموقع', 'url' => 'https://khaledsaad.net'], ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/khaledsaad']]],
        ['slug' => 'layan-alharbi', 'name' => 'ليان الحربي', 'role' => 'محررة الرؤى العالمية والترجمة',
            'bio' => 'محررة متخصصة في تلخيص الأبحاث والتقارير التسويقية العالمية وإضافة السياق العربي إليها. تشرف على سير الترجمة بالبوابات الخمس: التقاط، تحقق، ملخص، سياق عربي، مراجعة. لا تمرّر رقماً بلا مصدر أولي.',
            'links' => []],
        ['slug' => 'abdullah-alshehri', 'name' => 'عبدالله الشهري', 'role' => 'محرر دراسات الحالة والأبحاث',
            'bio' => 'محلل تسويق يعمل على تحويل تجارب المشاريع الصغيرة والمتوسطة إلى حالات تعليمية بمؤشرات واضحة، ويقرأ التقارير الرقمية بحدودها قبل أرقامها. يكتب في القياس والنمو ودراسات الحالة.',
            'links' => []],
    ];

    /** The 14 watched global sources (blueprint §6.3): real RSS/Atom endpoints of established marketing publishers. */
    public const FEEDS = [
        ['name' => 'Google Search Central Blog', 'url' => 'https://feeds.feedburner.com/blogspot/amDG', 'site_url' => 'https://developers.google.com/search/blog', 'topic' => 'seo'],
        ['name' => 'Search Engine Land', 'url' => 'https://searchengineland.com/feed', 'site_url' => 'https://searchengineland.com', 'topic' => 'seo'],
        ['name' => 'Search Engine Journal', 'url' => 'https://www.searchenginejournal.com/feed/', 'site_url' => 'https://www.searchenginejournal.com', 'topic' => 'seo'],
        ['name' => 'Moz Blog', 'url' => 'https://moz.com/posts/rss/blog', 'site_url' => 'https://moz.com/blog', 'topic' => 'seo'],
        ['name' => 'Ahrefs Blog', 'url' => 'https://ahrefs.com/blog/feed/', 'site_url' => 'https://ahrefs.com/blog', 'topic' => 'seo'],
        ['name' => 'Semrush Blog', 'url' => 'https://www.semrush.com/blog/feed/', 'site_url' => 'https://www.semrush.com/blog', 'topic' => 'content-marketing'],
        ['name' => 'HubSpot Marketing Blog', 'url' => 'https://blog.hubspot.com/marketing/rss.xml', 'site_url' => 'https://blog.hubspot.com/marketing', 'topic' => 'content-marketing'],
        ['name' => 'Content Marketing Institute', 'url' => 'https://contentmarketinginstitute.com/feed/', 'site_url' => 'https://contentmarketinginstitute.com', 'topic' => 'content-marketing'],
        ['name' => 'Copyblogger', 'url' => 'https://copyblogger.com/feed/', 'site_url' => 'https://copyblogger.com', 'topic' => 'copywriting'],
        ['name' => 'Social Media Examiner', 'url' => 'https://www.socialmediaexaminer.com/feed/', 'site_url' => 'https://www.socialmediaexaminer.com', 'topic' => 'social-media'],
        ['name' => 'Sprout Social Insights', 'url' => 'https://sproutsocial.com/insights/feed/', 'site_url' => 'https://sproutsocial.com/insights', 'topic' => 'social-media'],
        ['name' => 'Nielsen Norman Group', 'url' => 'https://www.nngroup.com/feed/rss/', 'site_url' => 'https://www.nngroup.com/articles', 'topic' => 'cro'],
        ['name' => 'Marketing Week', 'url' => 'https://www.marketingweek.com/feed/', 'site_url' => 'https://www.marketingweek.com', 'topic' => 'strategy'],
        ['name' => "Seth Godin's Blog", 'url' => 'https://seths.blog/feed/', 'site_url' => 'https://seths.blog', 'topic' => 'strategy'],
    ];

    public function run(): void
    {
        $categories = [];
        foreach (self::CATEGORIES as $i => $c) {
            $categories[$c['slug']] = ContentCategory::query()->updateOrCreate(['slug' => $c['slug']], ['name' => $c['name'], 'description' => $c['description'], 'position' => $i + 1]);
        }
        $tags = [];
        foreach (self::TAGS as $slug => $name) {
            $tags[$slug] = Tag::query()->updateOrCreate(['slug' => $slug], ['name' => $name]);
        }
        $authors = [];
        foreach (self::AUTHORS as $a) {
            $authors[$a['slug']] = Author::query()->updateOrCreate(['slug' => $a['slug']], ['name' => $a['name'], 'role' => $a['role'], 'bio' => $a['bio'], 'links' => $a['links']]);
        }
        foreach (self::FEEDS as $f) {
            SourceFeed::query()->updateOrCreate(['url' => $f['url']], ['name' => $f['name'], 'site_url' => $f['site_url'], 'topic' => $f['topic'], 'language' => 'en', 'is_active' => true]);
        }

        $this->seedGlossary($tags);
        $this->seedItems($categories, $tags, $authors);
        $this->adoptExistingItems($categories, $authors);
        Markdown::flushToolCache();
    }

    private function seedGlossary(array $tags): void
    {
        foreach ($this->files(database_path('content/glossary')) as $file) {
            [$attrs, $body] = FrontMatter::parse(File::get($file));
            if (blank($attrs['slug'] ?? null) || blank($attrs['term'] ?? null) || blank($attrs['definition'] ?? null)) {
                continue;
            }
            GlossaryTerm::query()->updateOrCreate(['slug' => $attrs['slug']], [
                'term' => $attrs['term'], 'definition' => $attrs['definition'], 'example' => $attrs['example'] ?? null, 'body' => $body,
                'related_lesson_id' => $this->lessonId($attrs['lesson'] ?? null), 'related_tool_id' => $this->toolId($attrs['tool'] ?? null),
                'tags' => array_values(array_intersect((array) ($attrs['tags'] ?? []), array_keys($tags))), 'is_published' => true,
            ]);
        }
    }

    private function seedItems(array $categories, array $tags, array $authors): void
    {
        foreach ($this->files(database_path('content/magazine')) as $file) {
            [$attrs, $body] = FrontMatter::parse(File::get($file));
            if (blank($attrs['slug'] ?? null) || blank($attrs['title'] ?? null) || $body === '') {
                continue;
            }
            $type = in_array($attrs['type'] ?? null, ContentItem::TYPES, true) ? $attrs['type'] : 'article';
            $section = array_key_exists($attrs['section'] ?? '', ContentItem::SECTIONS) ? $attrs['section'] : ContentItem::TYPE_SECTION[$type];
            $author = $authors[$attrs['author'] ?? 'khaled-saad'] ?? $authors['khaled-saad'];
            $publishedAt = $this->date($attrs['published_at'] ?? null) ?? now()->subDays(7);
            $words = Markdown::wordCount($body);
            $sourced = in_array($type, ContentItem::SOURCED_TYPES, true);
            $meta = ['seeded' => true, 'word_count' => $words];
            if ($sourced || filled($attrs['arabic_context'] ?? null)) {
                $meta['arabic_context'] = $attrs['arabic_context'] ?? Str::limit($attrs['tldr'] ?? $attrs['excerpt'] ?? '', 400, '');
            }
            if (filled($attrs['topic'] ?? null)) {
                $meta['topic_slug'] = $attrs['topic']; // the topic pages (/topics/{course}) group items by course slug
            }

            $values = [
                'type' => $type, 'section' => $section, 'title' => $attrs['title'], 'eyebrow' => $attrs['eyebrow'] ?? ContentItem::TYPE_LABELS[$type],
                'excerpt' => Str::limit((string) ($attrs['excerpt'] ?? $attrs['tldr'] ?? ''), 600, ''), 'tldr' => $attrs['tldr'] ?? null, 'body' => $body,
                'author_name' => $author->name, 'author_id' => $author->id, 'reviewer_name' => $attrs['reviewer'] ?? 'خالد سعد',
                'topic' => $this->topicName($attrs['topic'] ?? null), 'category_id' => ($categories[$attrs['category'] ?? ''] ?? null)?->id,
                'related_lesson_id' => $this->lessonId($attrs['lesson'] ?? null), 'related_tool_id' => $this->toolId($attrs['tool'] ?? null),
                'source_mode' => $sourced ? ($type === 'news' ? 'summary' : 'analysis') : 'original',
                'reading_minutes' => (int) ($attrs['reading_minutes'] ?? max(3, (int) ceil($words / 180))),
                'meta' => $meta, 'faq' => $this->faq($attrs['faq'] ?? []),
            ];

            $item = ContentItem::query()->where('slug', $attrs['slug'])->first();
            if ($item) {
                $item->fill($values);
                if ($item->status !== 'published') {
                    $item->fill(['status' => 'published', 'published_at' => $publishedAt, 'translation_stage' => 'published', 'reviewed_at' => $publishedAt, 'last_reviewed_at' => $publishedAt]);
                }
                $item->save();
            } else {
                $item = ContentItem::query()->create($values + [
                    'slug' => $attrs['slug'], 'status' => 'published', 'published_at' => $publishedAt, 'translation_stage' => 'published',
                    'reviewed_at' => $publishedAt, 'last_reviewed_at' => $publishedAt,
                ]);
            }
            $item->tags()->sync(collect((array) ($attrs['tags'] ?? []))->map(fn ($t) => $tags[$t]->id ?? null)->filter()->values()->all());

            $item->sources()->delete();
            foreach ((array) ($attrs['sources'] ?? []) as $source) {
                if (! is_array($source) || blank($source['url'] ?? null)) {
                    continue;
                }
                $item->sources()->create([
                    'publisher' => $source['publisher'] ?? parse_url((string) $source['url'], PHP_URL_HOST) ?? 'مصدر',
                    'title' => $source['title'] ?? $source['publisher'] ?? 'المصدر',
                    'url' => $source['url'], 'published_on' => $this->date($source['date'] ?? null), 'language' => $source['language'] ?? 'en',
                ]);
            }
        }
    }

    /** Rows that pre-date the magazine (legacy imports, stage-1 seeds): give them a shelf, a category and the main author without rewriting their text. */
    private function adoptExistingItems(array $categories, array $authors): void
    {
        $byTopic = [
            'الاستراتيجية' => 'strategy', 'النمو' => 'ads', 'الإعلانات' => 'ads', 'المحتوى' => 'content', 'البريد الإلكتروني' => 'content',
            'الذكاء الاصطناعي' => 'ai', 'البحث وGEO' => 'content', 'SEO وGEO' => 'content', 'القياس' => 'measurement',
        ];
        ContentItem::query()->whereNull('author_id')->orWhereNull('category_id')->get()->each(function (ContentItem $item) use ($byTopic, $categories, $authors): void {
            $update = [];
            if (! $item->author_id) {
                $match = collect($authors)->first(fn (Author $a) => $a->name === $item->author_name) ?? $authors['khaled-saad'];
                $update['author_id'] = $match->id;
            }
            if (! $item->category_id) {
                $slug = $byTopic[$item->topic ?? ''] ?? match ($item->section) {
                    'news', 'insights' => 'content', 'research' => 'measurement', default => 'strategy',
                };
                $update['category_id'] = $categories[$slug]->id;
            }
            if ($update !== []) {
                $item->forceFill($update)->saveQuietly();
            }
        });
    }

    private function faq(mixed $raw): ?array
    {
        $out = [];
        foreach ((array) $raw as $f) {
            if (is_array($f) && filled($f['q'] ?? null) && filled($f['a'] ?? null)) {
                $out[] = ['q' => trim((string) $f['q']), 'a' => trim((string) $f['a'])];
            }
        }

        return $out === [] ? null : $out;
    }

    private function files(string $dir): array
    {
        if (! File::isDirectory($dir)) {
            return [];
        }
        $files = array_map(fn ($f) => $f->getPathname(), File::allFiles($dir));
        $files = array_values(array_filter($files, fn ($f) => str_ends_with($f, '.md')));
        sort($files);

        return $files;
    }

    private function date(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private array $lessons = [];

    private array $tools = [];

    private array $courses = [];

    private function lessonId(?string $slug): ?int
    {
        if (blank($slug)) {
            return null;
        }
        $this->lessons[$slug] ??= Lesson::query()->where('slug', $slug)->value('id') ?? 0;

        return $this->lessons[$slug] ?: null;
    }

    private function toolId(?string $slug): ?int
    {
        if (blank($slug)) {
            return null;
        }
        $this->tools[$slug] ??= Tool::query()->where('slug', $slug)->value('id') ?? 0;

        return $this->tools[$slug] ?: null;
    }

    /** The human topic name is the course title's topic column; the slug lives in meta.topic_slug. */
    private function topicName(?string $courseSlug): ?string
    {
        if (blank($courseSlug)) {
            return null;
        }
        $this->courses[$courseSlug] ??= \App\Domain\Academy\Models\Course::query()->where('slug', $courseSlug)->value('topic') ?? $courseSlug;

        return $this->courses[$courseSlug];
    }
}
