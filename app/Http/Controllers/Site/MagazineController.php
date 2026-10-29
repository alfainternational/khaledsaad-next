<?php

namespace App\Http\Controllers\Site;

use App\Domain\Academy\Models\Course;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Models\ContentCategory;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\GlossaryTerm;
use App\Domain\Content\Models\Tag;
use App\Domain\Insights\Models\ContentReport;
use App\Domain\Insights\Services\EventRecorder;
use App\Domain\Lab\Models\Tool;
use App\Http\Controllers\Controller;
use App\Support\Markdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * المجلة (blueprint §4.10–4.11): /articles and its sections, the item in its full form, the glossary,
 * topic pages (= the twelve academy paths), tags and authors.
 */
class MagazineController extends Controller
{
    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR;

    public function index(Request $request)
    {
        return $this->listing($request, null);
    }

    public function section(Request $request, string $section)
    {
        abort_unless(array_key_exists($section, ContentItem::SECTIONS), 404);

        return $this->listing($request, $section);
    }

    private function listing(Request $request, ?string $section)
    {
        $query = ContentItem::published()->with(['author', 'category'])->latest('published_at');
        if ($section) {
            $query->section($section);
        }
        $filters = [
            'type' => (string) $request->query('type', ''),
            'topic' => (string) $request->query('topic', ''),
            'author' => (string) $request->query('author', ''),
        ];
        if ($filters['type'] !== '' && in_array($filters['type'], ContentItem::TYPES, true)) {
            $query->where('type', $filters['type']);
        } else {
            $filters['type'] = '';
        }
        if ($filters['topic'] !== '') {
            $category = ContentCategory::query()->where('slug', $filters['topic'])->first();
            $category ? $query->where('category_id', $category->id) : $filters['topic'] = '';
        }
        if ($filters['author'] !== '') {
            $author = Author::query()->where('slug', $filters['author'])->first();
            $author ? $query->where('author_id', $author->id) : $filters['author'] = '';
        }
        $items = $query->paginate(12)->withQueryString();
        $meta = $section ? ContentItem::SECTIONS[$section] : ['label' => 'المجلة', 'kicker' => 'المجلة', 'intro' => 'أدلة، تحليلات ودراسات حالة تربط الفكرة بالقرار والأداة.'];

        return view('site.articles.index', [
            'items' => $items, 'section' => $section, 'meta' => $meta, 'filters' => $filters,
            'categories' => ContentCategory::query()->orderBy('position')->get(),
            'authors' => Author::query()->orderBy('id')->get(),
            'types' => $section ? [] : ContentItem::TYPE_LABELS,
            'sections' => ContentItem::SECTIONS,
            'hasFilters' => array_filter($filters) !== [],
        ]);
    }

    public function show(Request $request, ContentItem $contentItem, EventRecorder $events)
    {
        abort_unless($contentItem->isPublished(), 404);
        $contentItem->load(['sources', 'author', 'category', 'tags', 'relatedLesson.course', 'relatedTool']);
        $article = $contentItem;
        // Visits feed the «مادة تجلب زيارات بلا أداة» and stale-numbers signals (§6.1); the session token keeps visitors anonymous.
        $events->record($request, 'visit.article', ['tool_id' => $article->related_tool_id, 'meta' => ['content_item_id' => $article->id, 'section' => $article->section]]);

        $related = ContentItem::published()->where('id', '!=', $article->id)->with('author')
            ->where(function (Builder $q) use ($article): void {
                $q->where('section', $article->section);
                if ($article->category_id) {
                    $q->orWhere('category_id', $article->category_id);
                }
                $tagIds = $article->tags->pluck('id');
                if ($tagIds->isNotEmpty()) {
                    $q->orWhereHas('tags', fn (Builder $t) => $t->whereIn('tags.id', $tagIds));
                }
            })
            ->latest('published_at')->limit(3)->get();
        $previous = ContentItem::published()->section($article->section)->where('published_at', '<', $article->published_at)->latest('published_at')->first();
        $next = ContentItem::published()->section($article->section)->where('published_at', '>', $article->published_at)->oldest('published_at')->first();

        $bodyHtml = Markdown::toHtml((string) $article->body, headingIds: true, literalLevels: true);
        $headings = Markdown::headings((string) $article->body);
        $tool = $article->relatedTool && $article->relatedTool->is_published ? $article->relatedTool : null;
        $lesson = $article->relatedLesson && $article->relatedLesson->isPublished() && $article->relatedLesson->course?->is_published ? $article->relatedLesson : null;
        $topicCourse = $this->topicCourse($article);

        $structured = [
            '@context' => 'https://schema.org',
            '@type' => $article->schemaType(),
            'headline' => $article->title,
            'description' => $article->excerpt,
            'inLanguage' => 'ar',
            'datePublished' => $article->published_at?->toIso8601String(),
            'dateModified' => ($article->last_reviewed_at ?? $article->updated_at)?->toIso8601String(),
            'author' => array_filter(['@type' => 'Person', 'name' => $article->authorName(), 'url' => $article->author ? route('authors.show', $article->author) : null, 'jobTitle' => $article->author?->role]),
            'publisher' => ['@type' => 'Organization', 'name' => 'أثر', 'url' => url('/')],
            'mainEntityOfPage' => route('articles.show', $article),
            'articleSection' => $article->sectionLabel(),
            'keywords' => $article->tags->pluck('name')->implode(', '),
        ];
        if ($article->sources->isNotEmpty()) {
            $structured['citation'] = $article->sources->map(fn ($s) => ['@type' => 'CreativeWork', 'name' => $s->title, 'url' => $s->url, 'publisher' => $s->publisher])->values()->all();
        }
        $faq = $article->faqEntries();
        $faqSchema = $faq === [] ? null : [
            '@context' => 'https://schema.org', '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $faq),
        ];

        // Editors see the freshness warning (§4.11 / §6.3); visitors never do.
        $isEditor = in_array($request->user()?->platform_role, ['editor', 'admin', 'super_admin'], true);

        return view('site.articles.show', [
            'article' => $article, 'bodyHtml' => $bodyHtml, 'headings' => $headings, 'related' => $related, 'previous' => $previous, 'next' => $next,
            'tool' => $tool, 'toolUrl' => $tool ? Markdown::toolUrl($tool) : null, 'lesson' => $lesson, 'topicCourse' => $topicCourse, 'faq' => $faq,
            'structuredJson' => json_encode($structured, self::JSON), 'faqJson' => $faqSchema ? json_encode($faqSchema, self::JSON) : null,
            'staleWarning' => $isEditor && $article->hasStaleSources(), 'oldestSource' => $article->oldestSourceDate(),
            'reportReasons' => ['wrong_numbers' => 'رقم أو مصدر غير دقيق', 'outdated' => 'المحتوى قديم', 'unclear' => 'الشرح غير واضح', 'other' => 'شيء آخر'],
        ]);
    }

    /** بلاغ من داخل المادة (§4.11): reuses content_reports; throttle:reports at the route. */
    public function report(Request $request, ContentItem $contentItem, EventRecorder $events)
    {
        abort_unless($contentItem->isPublished(), 404);
        $data = $request->validate([
            'reason' => ['required', Rule::in(['wrong_numbers', 'outdated', 'unclear', 'other'])],
            'note' => ['nullable', 'string', 'max:800'],
        ]);
        ContentReport::query()->create(['user_id' => $request->user()?->id, 'content_item_id' => $contentItem->id, 'reason' => $data['reason'], 'note' => $data['note'] ?? null]);
        $events->record($request, 'report.sent', ['meta' => ['content_item_id' => $contentItem->id, 'reason' => $data['reason']]]);

        return redirect()->to(route('articles.show', $contentItem).'#report')->with('status', 'وصل بلاغك وسيراجعه المحرر. شكراً لك.');
    }

    public function glossary(Request $request)
    {
        $letter = (string) $request->query('letter', '');
        $terms = GlossaryTerm::published()->orderBy('term')->get();
        $groups = $terms->groupBy(fn (GlossaryTerm $t) => mb_substr(preg_replace('/^(ال)/u', '', $t->term) ?: $t->term, 0, 1));

        return view('site.glossary.index', ['terms' => $terms, 'groups' => $groups, 'letter' => $letter]);
    }

    public function term(GlossaryTerm $term)
    {
        abort_unless($term->is_published, 404);
        $term->load(['relatedLesson.course', 'relatedTool']);
        GlossaryTerm::query()->whereKey($term->id)->increment('views_count');
        $tool = $term->relatedTool && $term->relatedTool->is_published ? $term->relatedTool : null;
        $lesson = $term->relatedLesson && $term->relatedLesson->isPublished() && $term->relatedLesson->course?->is_published ? $term->relatedLesson : null;
        $others = GlossaryTerm::published()->where('id', '!=', $term->id)
            ->when(filled($term->tags), fn ($q) => $q->where(function (Builder $w) use ($term): void {
                foreach ($term->tags as $tag) {
                    $w->orWhereJsonContains('tags', $tag);
                }
            }))
            ->orderBy('term')->limit(6)->get();

        $structured = [
            '@context' => 'https://schema.org', '@type' => 'DefinedTerm', 'name' => $term->term, 'description' => $term->definition,
            'inLanguage' => 'ar', 'url' => route('glossary.show', $term),
            'inDefinedTermSet' => ['@type' => 'DefinedTermSet', 'name' => 'قاموس التسويق — أثر', 'url' => route('glossary.index')],
        ];

        return view('site.glossary.show', [
            'term' => $term, 'bodyHtml' => Markdown::toHtml((string) $term->body, literalLevels: true), 'items' => $term->relatedItems(), 'others' => $others,
            'tool' => $tool, 'toolUrl' => $tool ? Markdown::toolUrl($tool) : null, 'lesson' => $lesson,
            'structuredJson' => json_encode($structured, self::JSON),
        ]);
    }

    /** /topics/{topic}: the twelve topics are the twelve paths (§4.10) — definition + items + the path + a tool. */
    public function topic(string $topic)
    {
        $course = Course::query()->where('slug', $topic)->firstOrFail();
        $items = ContentItem::published()->with(['author', 'category'])
            ->where(fn (Builder $q) => $q->where('meta->topic_slug', $course->slug)->orWhere('topic', $course->topic))
            ->latest('published_at')->paginate(12);
        $terms = GlossaryTerm::published()->whereHas('relatedLesson', fn (Builder $q) => $q->where('course_id', $course->id))->orderBy('term')->limit(8)->get();
        $tool = $items->getCollection()->pluck('relatedTool')->filter()->first()
            ?? Tool::query()->where('is_published', true)->where('category', $this->toolCategoryFor($course))->orderBy('position')->first()
            ?? Tool::query()->where('is_published', true)->where('slug', '!=', 'lesson-lab')->orderBy('position')->first();
        $structured = [
            '@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $course->topic.' — المجلة', 'description' => $course->summary,
            'inLanguage' => 'ar', 'url' => route('topics.show', $course->slug), 'about' => ['@type' => 'Course', 'name' => $course->title, 'url' => route('academy.show', $course)],
        ];

        return view('site.topics.show', [
            'course' => $course, 'items' => $items, 'terms' => $terms, 'tool' => $tool, 'toolUrl' => $tool ? Markdown::toolUrl($tool) : null,
            'structuredJson' => json_encode($structured, self::JSON),
            'topics' => Course::query()->orderBy('position')->get(['slug', 'title', 'topic', 'is_published']),
        ]);
    }

    public function tag(Tag $tag)
    {
        $items = ContentItem::published()->with(['author', 'category'])->whereHas('tags', fn (Builder $q) => $q->where('tags.id', $tag->id))->latest('published_at')->paginate(12);

        return view('site.tags.show', ['tag' => $tag, 'items' => $items, 'noindex' => $items->total() < 3]);
    }

    public function author(Author $author)
    {
        $items = ContentItem::published()->with('category')->where('author_id', $author->id)->latest('published_at')->paginate(12);
        $structured = [
            '@context' => 'https://schema.org', '@type' => 'Person', 'name' => $author->name, 'jobTitle' => $author->role, 'description' => $author->bio,
            'url' => route('authors.show', $author), 'worksFor' => ['@type' => 'Organization', 'name' => 'أثر', 'url' => url('/')],
            'sameAs' => array_column($author->safeLinks(), 'url'),
        ];

        return view('site.authors.show', ['author' => $author, 'items' => $items, 'structuredJson' => json_encode($structured, self::JSON),
            'sections' => ContentItem::query()->published()->where('author_id', $author->id)->selectRaw('section, count(*) as n')->groupBy('section')->pluck('n', 'section')]);
    }

    private function topicCourse(ContentItem $item): ?Course
    {
        $slug = data_get($item->meta, 'topic_slug');
        $course = $slug ? Course::query()->where('slug', $slug)->first() : null;
        if (! $course && $item->topic) {
            $course = Course::query()->where('topic', $item->topic)->first();
        }

        return $course;
    }

    private function toolCategoryFor(Course $course): string
    {
        return match ($course->slug) {
            'audience-and-personas', 'offer-value-positioning' => 'الجمهور',
            'content-writing-persuasion', 'social-media', 'seo-content-marketing', 'email-and-crm' => 'المحتوى',
            'paid-ads' => 'الإعلانات',
            'measurement-analytics-cro' => 'القياس',
            'ai-in-marketing', 'automation-workflows' => 'الذكاء الاصطناعي',
            default => 'التخطيط',
        };
    }
}
