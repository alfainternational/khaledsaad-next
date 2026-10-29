<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\Course;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\GlossaryTerm;
use App\Domain\Content\Models\Tag;
use App\Domain\Content\Services\SearchService;
use App\Models\User;
use App\Support\FrontMatter;
use App\Support\Markdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** المرحلة 3 — المجلة (المخطط §4.10–4.12): الأقسام، المادة بالشكل الكامل، القاموس، المواضيع، الوسوم، المؤلفون، البحث المجمّع. */
class MagazineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_seeded_magazine_meets_the_stage_three_content_bar(): void
    {
        $this->assertGreaterThanOrEqual(40, GlossaryTerm::published()->count());
        foreach (array_keys(ContentItem::SECTIONS) as $section) {
            $this->assertGreaterThanOrEqual(10, ContentItem::published()->section($section)->count(), "section {$section}");
        }
        $this->assertGreaterThanOrEqual(5, \App\Domain\Content\Models\ContentCategory::count());
        $this->assertGreaterThanOrEqual(20, Tag::count());
        $this->assertSame(3, Author::count());
        // Every seeded item links a tool, has a tldr and sits inside 500–900 words; sourced types cite at least one source.
        ContentItem::query()->where('meta->seeded', true)->with('sources')->get()->each(function (ContentItem $item): void {
            $this->assertNotNull($item->related_tool_id, $item->slug.' has no tool');
            $this->assertNotEmpty($item->tldr, $item->slug.' has no tldr');
            $this->assertNotNull($item->category_id, $item->slug.' has no category');
            $this->assertNotNull($item->author_id, $item->slug.' has no author');
            $words = Markdown::wordCount($item->body);
            $this->assertTrue($words >= 500 && $words <= 900, "{$item->slug} has {$words} words");
            if ($item->requiresSources()) {
                $this->assertTrue($item->sources->isNotEmpty(), $item->slug.' needs sources');
                $this->assertNotEmpty(data_get($item->meta, 'arabic_context'));
            }
        });
        // Legacy rows (pre-stage-3 seeds) were adopted: section derived from type, author and category assigned.
        $legacy = ContentItem::query()->where('slug', 'ai-search-arabic-marketers')->firstOrFail();
        $this->assertSame('insights', $legacy->section);
        $this->assertNotNull($legacy->author_id);
        $this->assertNotNull($legacy->category_id);
    }

    public function test_sections_render_with_filters_pagination_and_the_right_schema(): void
    {
        $this->get('/articles')->assertOk()->assertSee('كل المواد')->assertSee('مادة')->assertSee('name="topic"', false)->assertSee('name="author"', false)->assertSee('name="type"', false);
        $this->get('/news')->assertOk()->assertSee('أخبار')->assertSee('"@type":"BreadcrumbList"', false);
        $this->get('/insights')->assertOk()->assertSee('رؤى عالمية');
        $this->get('/case-studies')->assertOk()->assertSee('دراسات حالة');
        $this->get('/research')->assertOk()->assertSee('أبحاث');
        $this->get('/articles?page=2')->assertOk()->assertSee('noindex', false);
        $this->get('/articles?type=guide')->assertOk()->assertDontSee('خبر</span>', false);
        $this->get('/articles?author=khaled-saad')->assertOk();
        $this->get('/articles?topic=measurement')->assertOk();
        $this->get('/unknown-section')->assertNotFound();

        $news = ContentItem::published()->section('news')->firstOrFail();
        $this->get(route('articles.show', $news))->assertOk()->assertSee('"@type":"NewsArticle"', false);
    }

    public function test_article_page_has_the_full_form(): void
    {
        $item = ContentItem::published()->whereNotNull('tldr')->whereNotNull('faq')->whereIn('type', ContentItem::SOURCED_TYPES)->with('sources')->firstOrFail();
        $response = $this->get(route('articles.show', $item))->assertOk()
            ->assertSee('في دقيقة')->assertSee($item->tldr)
            ->assertSee('بقلم')->assertSee('راجعه')->assertSee('دقائق قراءة')->assertSee('نُشر')
            ->assertSee('في هذه المادة')->assertSee('data-toc=', false) // sticky H2 index
            ->assertSee('apply-now', false)->assertSee('طبّق الآن') // «طبّق الآن» box linking a tool
            ->assertSee('المصادر')->assertSee($item->sources->first()->publisher)->assertSee(strtoupper($item->sources->first()->language))
            ->assertSee('طبّق ما قرأت')->assertSee('الأداة المرتبطة')
            ->assertSee('التالي')->assertSee('مواد مرتبطة')
            ->assertSee('بلّغ عن مشكلة')->assertSee(route('articles.report', $item), false)
            ->assertSee('"@type":"FAQPage"', false)->assertSee('"@type":"Person"', false)->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('<h2 id="', false);
        // The H2 index points at real heading ids inside the body.
        $headings = Markdown::headings($item->body);
        $this->assertNotEmpty($headings);
        $response->assertSee('href="#'.array_key_first($headings).'"', false);
        // Visitors never see the editor-only freshness note.
        $response->assertDontSee('تنبيه للمحرر فقط');
    }

    public function test_editor_sees_the_stale_sources_warning_and_visitors_do_not(): void
    {
        $item = ContentItem::published()->whereIn('type', ContentItem::SOURCED_TYPES)->firstOrFail();
        $item->sources()->update(['published_on' => now()->subMonths(20)]);
        $this->get(route('articles.show', $item))->assertOk()->assertDontSee('تنبيه للمحرر فقط');
        $this->actingAs(User::factory()->create(['platform_role' => 'editor']))->get(route('articles.show', $item))->assertOk()->assertSee('تنبيه للمحرر فقط')->assertSee('أقدم من 12 شهراً');
    }

    public function test_visitor_can_report_an_article_and_the_report_is_stored(): void
    {
        $item = ContentItem::published()->firstOrFail();
        $this->post(route('articles.report', $item), ['reason' => 'wrong_numbers', 'note' => 'الرقم في الجدول بلا مصدر'])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('content_reports', ['content_item_id' => $item->id, 'reason' => 'wrong_numbers', 'status' => 'open']);
        $this->post(route('articles.report', $item), ['reason' => 'nonsense'])->assertSessionHasErrors('reason');
        $draft = ContentItem::query()->create(['type' => 'article', 'status' => 'draft', 'title' => 'x', 'slug' => 'draft-x', 'excerpt' => 'x', 'body' => 'x', 'author_name' => 'x']);
        $this->post(route('articles.report', $draft), ['reason' => 'other'])->assertNotFound();
    }

    public function test_glossary_index_and_term_pages(): void
    {
        $this->get('/glossary')->assertOk()->assertSee('مصطلحاً بتعريف مباشر')->assertSee('"@type":"DefinedTermSet"', false)->assertSee('/glossary/cac', false);
        $term = GlossaryTerm::query()->where('slug', 'cac')->firstOrFail();
        $this->get('/glossary/cac')->assertOk()
            ->assertSee($term->term)->assertSee($term->definition)->assertSee('مثال')->assertSee('طبّق المصطلح')
            ->assertSee('"@type":"DefinedTerm"', false)->assertSee('"@type":"BreadcrumbList"', false)->assertSee('مصطلحات قريبة');
        $this->assertSame(1, $term->fresh()->views_count);
        // The definition is the first visible line of the page body (direct answer, §4.10).
        $html = $this->get('/glossary/cac')->getContent();
        $this->assertLessThan(strpos($html, 'article-body'), strpos($html, 'id="definition"'));
        $term->forceFill(['is_published' => false])->save();
        $this->get('/glossary/cac')->assertNotFound();
        $this->get('/glossary/nope')->assertNotFound();
    }

    public function test_topic_tag_and_author_pages(): void
    {
        $course = Course::query()->where('slug', 'measurement-analytics-cro')->firstOrFail();
        $this->get('/topics/measurement-analytics-cro')->assertOk()
            ->assertSee($course->topic)->assertSee('المواد في هذا الموضوع')->assertSee('"@type":"CollectionPage"', false)->assertSee('الأداة')->assertSee('كل المواضيع')
            ->assertDontSee('name="robots"', false);
        $this->get('/topics/marketing-foundations')->assertOk()->assertSee('ابدأ المسار');
        $this->get('/topics/nope')->assertNotFound();

        $busy = Tag::query()->withCount(['items' => fn ($q) => $q->published()])->get()->firstWhere('items_count', '>=', 3);
        $this->get('/tags/'.$busy->slug)->assertOk()->assertSee($busy->name)->assertDontSee('name="robots"', false);
        $thin = Tag::query()->create(['name' => 'نادر', 'slug' => 'rare-tag']);
        $this->get('/tags/rare-tag')->assertOk()->assertSee('noindex', false);

        $author = Author::query()->where('slug', 'khaled-saad')->firstOrFail();
        $this->get('/authors/khaled-saad')->assertOk()->assertSee($author->name)->assertSee($author->role)->assertSee('المواد المنشورة')->assertSee('"@type":"Person"', false)->assertSee('sameAs', false);
    }

    public function test_discovery_endpoints_include_the_magazine(): void
    {
        $sitemap = $this->get('/sitemap.xml')->assertOk();
        foreach (['/news', '/insights', '/case-studies', '/research', '/glossary', '/glossary/cac', '/topics/paid-ads', '/authors/khaled-saad'] as $path) {
            $sitemap->assertSee(url($path).'</loc>', false);
        }
        $sitemap->assertDontSee('/tags/rare-tag', false);
        $this->get('/feed.xml')->assertOk()->assertSee('<dc:creator>', false)->assertSee('<category>', false);
        $this->get('/llms.txt')->assertOk()->assertSee('/glossary')->assertSee('/research')->assertSee('/topics/');
        $this->get('/')->assertOk()->assertSee(route('glossary.index'), false)->assertSee(route('newsletter.store'), false);
    }

    public function test_search_groups_results_and_never_returns_an_empty_page(): void
    {
        $this->get('/search?q=تكلفة اكتساب')->assertOk()
            ->assertSee('نتائج البحث')->assertSee('data-group="terms"', false)->assertSee('data-group="items"', false)->assertSee('/glossary/cac', false);
        $this->get('/search?q=شخصية العميل')->assertOk()->assertSee('data-group="tools"', false);
        $this->get('/search?q=التسويق')->assertOk()->assertSee('data-group="courses"', false)->assertSee('data-group="lessons"', false);
        $this->get('/search?q=zzzzqqqq')->assertOk()->assertSee('لا نتائج مطابقة')->assertSee('أحدث المواد')->assertSee('أشهر المصطلحات')->assertSee('المسارات')->assertSee('data-suggestions', false);
        $this->get('/search')->assertOk()->assertSee('ابحث في المنصة')->assertSee('data-suggestions', false);
        $this->get('/search?q=تسويق')->assertSee('noindex', false);
    }

    public function test_search_uses_fulltext_on_mysql_and_like_elsewhere(): void
    {
        $mysql = new SearchService('mysql');
        $sql = $mysql->textSearch(ContentItem::query(), 'تكلفة اكتساب', ['title', 'excerpt', 'body'], 'content_items')->toSql();
        $this->assertStringContainsString('MATCH (content_items.title, content_items.excerpt, content_items.body) AGAINST (? IN BOOLEAN MODE)', $sql);
        $this->assertSame('+تكلفة* +اكتساب*', $mysql->booleanQuery('تكلفة اكتساب'));
        $this->assertSame('+cac* +ltv*', $mysql->booleanQuery('+cac -ltv "x"'));

        $sqlite = new SearchService('sqlite');
        $sql = $sqlite->textSearch(ContentItem::query(), 'تكلفة', ['title', 'excerpt', 'body'], 'content_items')->toSql();
        $this->assertStringNotContainsString('MATCH', $sql);
        $this->assertStringContainsString('"title" like ?', $sql);
        $this->assertStringContainsString('"body" like ?', $sql);
        $this->assertGreaterThanOrEqual(1, $sqlite->items('تكلفة')->count());
        $this->assertLessThanOrEqual(SearchService::PER_GROUP, $sqlite->items('ال')->count());
    }

    public function test_search_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get('/search?q=تسويق')->assertOk();
        }
        $this->get('/search?q=تسويق')->assertStatus(429);
    }

    public function test_front_matter_and_markdown_helpers(): void
    {
        [$attrs, $body] = FrontMatter::parse("---\ntitle: \"سؤال: جواب\"\ntags: [a, b]\nn: 7\nsources:\n  - publisher: X\n    url: https://x.test/a?b=c\n  - publisher: Y\n    url: https://y.test\nfaq: []\n---\n## س؟\nنص.");
        $this->assertSame('سؤال: جواب', $attrs['title']);
        $this->assertSame(['a', 'b'], $attrs['tags']);
        $this->assertSame(7, $attrs['n']);
        $this->assertSame('https://x.test/a?b=c', $attrs['sources'][0]['url']);
        $this->assertSame('Y', $attrs['sources'][1]['publisher']);
        $this->assertSame([], $attrs['faq']);
        $this->assertStringStartsWith('## س؟', $body);

        $html = Markdown::toHtml("## لماذا؟\n\nفقرة **مهمة** مع [رابط](https://a.test) و<script>x</script>.\n\n> اقتباس\n\n:::apply customer-persona\nافعل.\n:::\n\n| أ | ب |\n|---|---|\n| 1 | 2 |", headingIds: true, literalLevels: true);
        $this->assertStringContainsString('<h2 id="', $html);
        $this->assertStringContainsString('<strong>مهمة</strong>', $html);
        $this->assertStringContainsString('rel="nofollow noopener"', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<blockquote>', $html);
        $this->assertStringContainsString('class="apply-now"', $html);
        $this->assertStringContainsString(route('tools.show', 'customer-persona'), $html);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringNotContainsString('<script>', $html);

        $clean = Markdown::cleanParagraphs("سطر أول\nمكسور في المنتصف.\n\n\n\nفقرة ثانية   بمسافات.");
        $this->assertSame("سطر أول مكسور في المنتصف.\n\nفقرة ثانية بمسافات.", $clean);
    }
}
