<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Models\ContentCategory;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\SourceFeed;
use App\Domain\Content\Models\SourceItem;
use App\Domain\Content\Models\Tag;
use App\Domain\Content\Services\SourceFetcher;
use App\Domain\Lab\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** إدارة المحتوى الموسعة (§6.5) ولوح المصادر بالبوابات الخمس (§6.3) وأمر الجلب. */
class AdminMagazineTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->editor = User::factory()->create(['platform_role' => 'editor']);
    }

    public function test_content_table_is_searchable_sortable_and_filterable(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/content')->assertForbidden();
        $this->actingAs($this->editor)->get('/admin/content')->assertOk()->assertSee('المحتوى')->assertSee('مادة جديدة')->assertSee('aria-sort=', false)->assertSee('التصنيفات')->assertSee('الوسوم')->assertSee('المؤلفون')->assertSee('صندوق المصادر');
        $this->actingAs($this->editor)->get('/admin/content?section=news&status=published&sort=title&dir=asc')->assertOk()->assertSee('aria-sort="ascending"', false);
        $this->actingAs($this->editor)->get('/admin/content?q=cac')->assertOk()->assertSee('natural-skincare-store-true-cac');
        $this->actingAs($this->editor)->get('/admin/content?stage=published')->assertOk();
        $this->actingAs($this->editor)->get('/admin/content?q=zzzz-none')->assertOk()->assertSee('لا مواد مطابقة');
    }

    public function test_editor_creates_edits_cleans_and_deletes_an_item_with_audit(): void
    {
        $category = ContentCategory::query()->where('slug', 'measurement')->firstOrFail();
        $author = Author::query()->where('slug', 'layan-alharbi')->firstOrFail();
        $tags = Tag::query()->whereIn('slug', ['analytics', 'cro'])->pluck('id')->all();
        $lesson = Lesson::query()->where('slug', 'first-marketing-plan')->firstOrFail();
        $tool = Tool::query()->where('slug', 'cac')->firstOrFail();

        $this->actingAs($this->editor)->get('/admin/content/create')->assertOk()->assertSee('مادة جديدة');
        $payload = [
            'title' => 'هل يكفي قياس التحويل وحده؟', 'slug' => 'is-conversion-enough', 'type' => 'research', 'section' => 'research', 'status' => 'draft',
            'category_id' => $category->id, 'author_id' => $author->id, 'author_name' => 'x', 'reviewer_name' => 'خالد سعد', 'reading_minutes' => 6,
            'excerpt' => 'ملخص.', 'tldr' => 'لا؛ اقرأ التحويل مع الربحية.', 'body' => "## لماذا؟\n\nفقرة.\n\n:::apply cac\nاحسب.\n:::",
            'related_lesson_id' => $lesson->id, 'related_tool_id' => $tool->id, 'tags' => $tags, 'arabic_context' => 'سياق عربي قصير للاختبار.',
            'faq' => [['q' => 'هل ينطبق على B2B؟', 'a' => 'نعم مع نافذة أطول.'], ['q' => '', 'a' => '']],
            'sources' => [['publisher' => 'DataReportal', 'title' => 'Digital 2025', 'url' => 'https://datareportal.com/reports/digital-2025', 'date' => '2025-02-01', 'language' => 'en'], ['publisher' => '', 'url' => '']],
        ];
        $this->actingAs($this->editor)->post('/admin/content', $payload)->assertRedirect();
        $item = ContentItem::query()->where('slug', 'is-conversion-enough')->firstOrFail();
        $this->assertSame('research', $item->section);
        $this->assertSame('ليان الحربي', $item->author_name);
        $this->assertSame($lesson->id, $item->related_lesson_id);
        $this->assertCount(2, $item->tags);
        $this->assertCount(1, $item->sources);
        $this->assertCount(1, $item->faq);
        $this->assertSame('سياق عربي قصير للاختبار.', data_get($item->meta, 'arabic_context'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.created', 'subject_id' => (string) $item->id]);

        $this->actingAs($this->editor)->get(route('admin.content.edit', $item))->assertOk()->assertSee('في دقيقة')->assertSee('الأسئلة الشائعة')->assertSee('المصادر')->assertSee('نظّف الفقرات');

        // Update to review, then publish (sources + reviewer + arabic context are present for a research item).
        $this->actingAs($this->editor)->put(route('admin.content.update', $item), array_merge($payload, ['status' => 'review', 'tags' => [$tags[0]], 'mark_reviewed' => 1]))->assertRedirect()->assertSessionHas('status');
        $item->refresh();
        $this->assertSame('review', $item->status);
        $this->assertCount(1, $item->tags);
        $this->assertNotNull($item->last_reviewed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.updated', 'subject_id' => (string) $item->id]);
        $this->actingAs($this->editor)->post(route('admin.content.publish', $item))->assertRedirect()->assertSessionHas('status');
        $this->assertSame('published', $item->fresh()->status);
        $this->assertSame('published', $item->fresh()->translation_stage);
        $this->get('/research')->assertOk()->assertSee('هل يكفي قياس التحويل وحده؟');

        // Clean paragraphs on an imported-looking body.
        $legacy = ContentItem::query()->create(['type' => 'article', 'status' => 'draft', 'title' => 'قديم', 'slug' => 'legacy-x', 'excerpt' => 'x', 'body' => "سطر\nمكسور.\n\n\nثانية.", 'author_name' => 'x', 'meta' => ['legacy' => ['id' => 1]]]);
        $this->actingAs($this->editor)->post(route('admin.content.clean', $legacy))->assertRedirect()->assertSessionHas('status');
        $this->assertSame("سطر مكسور.\n\nثانية.", $legacy->fresh()->body);
        $this->assertSame("سطر\nمكسور.\n\n\nثانية.", data_get($legacy->fresh()->meta, 'body_before_clean'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.cleaned', 'subject_id' => (string) $legacy->id]);

        // Delete needs confirmation.
        $this->actingAs($this->editor)->delete(route('admin.content.destroy', $legacy))->assertSessionHasErrors('confirm');
        $this->actingAs($this->editor)->delete(route('admin.content.destroy', $legacy), ['confirm' => 1])->assertRedirect(route('admin.content.index'));
        $this->assertDatabaseMissing('content_items', ['id' => $legacy->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.deleted', 'subject_id' => (string) $legacy->id]);
    }

    public function test_categories_tags_and_authors_have_standard_tables_with_crud_and_audit(): void
    {
        foreach (['categories', 'tags', 'authors'] as $kind) {
            $this->actingAs($this->editor)->get("/admin/content/{$kind}")->assertOk()->assertSee('aria-sort=', false)->assertSee('name="confirm"', false);
            $this->actingAs($this->editor)->get("/admin/content/{$kind}?q=zzz-none&sort=items_count&dir=desc")->assertOk();
        }
        $this->actingAs(User::factory()->create())->get('/admin/content/tags')->assertForbidden();

        $this->actingAs($this->editor)->post('/admin/content/categories', ['name' => 'التسعير', 'slug' => 'pricing-cat', 'description' => 'd', 'position' => 9])->assertRedirect();
        $category = ContentCategory::query()->where('slug', 'pricing-cat')->firstOrFail();
        $this->actingAs($this->editor)->put("/admin/content/categories/{$category->id}", ['name' => 'التسعير والعروض', 'slug' => 'pricing-cat'])->assertRedirect();
        $this->assertSame('التسعير والعروض', $category->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.categories.updated', 'subject_id' => (string) $category->id]);

        $this->actingAs($this->editor)->post('/admin/content/tags', ['name' => 'وسم جديد'])->assertRedirect();
        $tag = Tag::query()->where('name', 'وسم جديد')->firstOrFail();
        $this->assertNotEmpty($tag->slug);
        $this->actingAs($this->editor)->post('/admin/content/tags', ['name' => 'آخر', 'slug' => $tag->slug])->assertSessionHasErrors('slug');
        $this->actingAs($this->editor)->delete("/admin/content/tags/{$tag->id}")->assertSessionHasErrors('confirm');
        $this->actingAs($this->editor)->delete("/admin/content/tags/{$tag->id}", ['confirm' => 1])->assertRedirect();
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.tags.deleted', 'subject_id' => (string) $tag->id]);

        $this->actingAs($this->editor)->post('/admin/content/authors', ['name' => 'محرر رابع', 'role' => 'محرر', 'bio' => 'سيرة.', 'links' => "LinkedIn | https://linkedin.com/in/x\nhttps://example.com\njavascript:alert(1)"])->assertRedirect();
        $author = Author::query()->where('name', 'محرر رابع')->firstOrFail();
        $this->assertCount(2, $author->safeLinks());
        $this->get(route('authors.show', $author))->assertOk()->assertSee('محرر رابع');
        // Renaming an author updates the byline of their items; deleting an author with items is refused.
        $khaled = Author::query()->where('slug', 'khaled-saad')->firstOrFail();
        $this->actingAs($this->editor)->put("/admin/content/authors/{$khaled->id}", ['name' => 'خالد سعد المطيري', 'slug' => 'khaled-saad'])->assertRedirect();
        $this->assertSame('خالد سعد المطيري', ContentItem::query()->where('author_id', $khaled->id)->value('author_name'));
        $this->actingAs($this->editor)->delete("/admin/content/authors/{$khaled->id}", ['confirm' => 1])->assertSessionHasErrors('delete');
        $this->actingAs($this->editor)->delete("/admin/content/authors/{$author->id}", ['confirm' => 1])->assertRedirect();
        $this->assertDatabaseMissing('authors', ['id' => $author->id]);
    }

    public function test_fetch_command_captures_feeds_scores_relevance_dedupes_and_survives_network_failures(): void
    {
        $rss = '<?xml version="1.0"?><rss version="2.0"><channel><title>t</title>'
            .'<item><title>Google Ads benchmarks 2026: CAC and ROAS by industry</title><link>https://example.com/post-1?utm_source=rss</link><pubDate>Mon, 05 Oct 2026 10:00:00 GMT</pubDate><description><![CDATA[<p>A new marketing study on conversion rates for small business in Saudi Arabia.</p>]]></description></item>'
            .'<item><title>Office party photos</title><link>https://example.com/post-2</link><description>Cake.</description></item>'
            .'<item><title>Duplicate</title><link>https://www.example.com/post-1/</link><description>same link, different tracking</description></item>'
            .'</channel></rss>';
        $atom = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><title>a</title><entry><title>Email marketing automation for ecommerce</title><link rel="alternate" href="https://atom.test/e1"/><updated>2026-10-01T00:00:00Z</updated><summary>CRM and email sequence data.</summary></entry></feed>';
        SourceFeed::query()->update(['is_active' => false]);
        $ok = SourceFeed::query()->create(['name' => 'Feed RSS', 'url' => 'https://rss.test/feed', 'is_active' => true]);
        $atomFeed = SourceFeed::query()->create(['name' => 'Feed Atom', 'url' => 'https://atom.test/feed', 'is_active' => true]);
        $down = SourceFeed::query()->create(['name' => 'Feed Down', 'url' => 'https://down.test/feed', 'is_active' => true]);
        Http::fake([
            'rss.test/*' => Http::response($rss, 200),
            'atom.test/*' => Http::response($atom, 200),
            'down.test/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'),
        ]);

        $this->artisan('platform:fetch-sources')->assertSuccessful()->expectsOutputToContain('1 feed(s) unreachable');
        $this->assertSame(3, SourceItem::count()); // duplicate collapsed by url_hash (host, scheme, trailing slash and query ignored)
        $hit = SourceItem::query()->where('url', 'https://example.com/post-1?utm_source=rss')->firstOrFail();
        $this->assertGreaterThan(SourceFetcher::RELEVANCE_FLOOR, $hit->relevance_score);
        $this->assertContains('saudi', $hit->matched_keywords);
        $this->assertSame('2026-10-05', $hit->published_on->toDateString());
        $this->assertStringNotContainsString('<p>', $hit->excerpt);
        $this->assertLessThan(SourceFetcher::RELEVANCE_FLOOR, SourceItem::query()->where('url', 'https://example.com/post-2')->value('relevance_score'));
        $this->assertSame('Email marketing automation for ecommerce', SourceItem::query()->where('url', 'https://atom.test/e1')->value('title'));
        $this->assertNotNull($down->fresh()->last_error);
        $this->assertNotNull($ok->fresh()->last_fetched_at);
        $this->assertNull($ok->fresh()->last_error);
        // Re-running creates nothing new.
        $this->artisan('platform:fetch-sources')->assertSuccessful();
        $this->assertSame(3, SourceItem::count());
        // Scheduled daily.
        $this->assertTrue(collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->contains(fn ($e) => str_contains($e->command ?? '', 'platform:fetch-sources')));
    }

    public function test_an_editor_walks_a_manual_link_through_the_five_gates_to_publication(): void
    {
        Http::fake();
        $lesson = Lesson::query()->where('slug', 'first-channel-and-budget')->firstOrFail();
        $tool = Tool::query()->where('slug', 'roas')->firstOrFail();

        $this->actingAs(User::factory()->create())->get('/admin/sources')->assertForbidden();
        $this->actingAs($this->editor)->get('/admin/sources')->assertOk()
            ->assertSee('من المصادر إلى الرؤى')->assertSee('التقاط')->assertSee('تحقق')->assertSee('ملخص')->assertSee('سياق عربي')->assertSee('مراجعة ونشر')
            ->assertSee('Search Engine Land')->assertSee('رابط يدوي')->assertSee('تذكيرات تحديث');
        $this->assertSame(14, SourceFeed::count());

        // Gate 1 — capture (manual link); duplicates refused.
        $this->actingAs($this->editor)->post('/admin/sources/items', ['url' => 'https://www.thinkwithgoogle.com/intl/en-145/consumer-insights/', 'title' => 'Consumer insights: search behaviour report', 'publisher' => 'Think with Google', 'excerpt' => 'Google data on search and conversion for small business marketing.', 'language' => 'en', 'published_on' => '2026-09-01'])->assertRedirect();
        $item = SourceItem::query()->latest('id')->firstOrFail();
        $this->assertSame('captured', $item->stage);
        $this->assertSame($this->editor->id, $item->added_by);
        $this->actingAs($this->editor)->post('/admin/sources/items', ['url' => 'https://thinkwithgoogle.com/intl/en-145/consumer-insights'])->assertSessionHasErrors('url');
        $this->actingAs($this->editor)->get(route('admin.sources.show', $item))->assertOk()->assertSee('2. تحقق')->assertSee('تفتح بعد بوابة التحقق');

        // Gates are strictly ordered.
        $this->actingAs($this->editor)->post(route('admin.sources.contextualize', $item), [])->assertStatus(422);

        // Gate 2 — verify: a number without a primary source does not pass.
        $verify = ['published_on' => '2026-09-01', 'market_fit' => 'ينطبق على المتاجر السعودية التي تعتمد البحث المدفوع.', 'has_numbers' => 1, 'primary_source' => ''];
        $this->actingAs($this->editor)->post(route('admin.sources.verify', $item), $verify)->assertSessionHasErrors('primary_source');
        $this->assertSame('captured', $item->fresh()->stage);
        $this->actingAs($this->editor)->post(route('admin.sources.verify', $item), array_merge($verify, ['primary_source' => 'Google/Ipsos survey 2026 — https://www.thinkwithgoogle.com/data']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('verified', $item->fresh()->stage);
        $this->assertSame($this->editor->name, data_get($item->fresh()->verification, 'verified_by'));

        // Gate 3 — summary by rules (no AI key): raw material, flagged as not publishable.
        $this->actingAs($this->editor)->post(route('admin.sources.summarize', $item), ['ai' => 1])->assertRedirect()->assertSessionHas('status');
        $item->refresh();
        $this->assertSame('summarized', $item->stage);
        $this->assertSame('rules', $item->summary_mode);
        $this->assertStringContainsString('لا تُنشر كما هي', $item->summary);
        $this->assertStringContainsString('Think with Google', $item->summary);

        // Gate 4 — Arabic context: 120+ words, local example, lesson + tool → translated_analysis draft at stage contextualized.
        $short = str_repeat('كلمة ', 50);
        $long = str_repeat('هذا سياق عربي يشرح أثر النتيجة على قرار صاحب المشروع السعودي. ', 20);
        $context = ['title' => 'ماذا يعني سلوك البحث الجديد لمتجرك؟', 'tldr' => 'ابدأ بقياس الطلب قبل الإنفاق.', 'local_example' => 'متجر عطور في جدة قاس الطلب قبل حملة الإطلاق.', 'related_lesson_id' => $lesson->id, 'related_tool_id' => $tool->id];
        $this->actingAs($this->editor)->post(route('admin.sources.contextualize', $item), $context + ['arabic_context' => $short])->assertSessionHasErrors('arabic_context');
        $this->actingAs($this->editor)->post(route('admin.sources.contextualize', $item), $context + ['arabic_context' => $long])->assertRedirect()->assertSessionHas('status');
        $item->refresh();
        $this->assertSame('contextualized', $item->stage);
        $content = ContentItem::query()->findOrFail($item->content_item_id);
        $this->assertSame('translated_analysis', $content->type);
        $this->assertSame('insights', $content->section);
        $this->assertSame('contextualized', $content->translation_stage);
        $this->assertSame('draft', $content->status);
        $this->assertSame($lesson->id, $content->related_lesson_id);
        $this->assertSame($tool->id, $content->related_tool_id);
        $this->assertCount(2, $content->sources); // the captured link + the primary source for the numbers
        $this->assertStringContainsString('ماذا يعني هذا في السوق العربي؟', $content->body);
        $this->get(route('articles.show', $content))->assertNotFound(); // not public yet

        // Gate 5 — review + publish: all five checks and a reviewer name required.
        $this->actingAs($this->editor)->post(route('admin.sources.review', $item), ['checks' => ['primary' => 1], 'reviewer_name' => 'خالد سعد', 'action' => 'publish'])->assertSessionHasErrors('checks');
        $checks = array_fill_keys(['primary', 'context', 'links', 'voice', 'freshness'], 1);
        $this->actingAs($this->editor)->post(route('admin.sources.review', $item), ['checks' => $checks, 'reviewer_name' => 'خالد سعد', 'action' => 'approve'])->assertRedirect();
        $this->assertSame('reviewed', $item->fresh()->stage);
        $this->assertSame('review', $content->fresh()->status);
        $this->actingAs($this->editor)->post(route('admin.sources.review', $item), ['checks' => $checks, 'reviewer_name' => 'خالد سعد', 'action' => 'publish'])->assertRedirect(route('admin.sources.index'));
        $this->assertSame('published', $item->fresh()->stage);
        $content->refresh();
        $this->assertSame('published', $content->status);
        $this->assertSame('خالد سعد', $content->reviewer_name);
        $this->get(route('articles.show', $content))->assertOk()->assertSee('ماذا يعني سلوك البحث الجديد لمتجرك؟')->assertSee('Think with Google');
        $this->get('/insights')->assertOk()->assertSee('ماذا يعني سلوك البحث الجديد لمتجرك؟');
        $this->assertDatabaseHas('audit_logs', ['action' => 'sources.item.published', 'subject_id' => (string) $item->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.published', 'subject_id' => (string) $content->id]);
        $this->actingAs($this->editor)->get('/admin/sources')->assertOk()->assertSee('نُشر من الصندوق');

        // Ignore with a reason; toggling a feed.
        $other = app(SourceFetcher::class)->capture(['url' => 'https://example.org/x', 'title' => 'x'], null, $this->editor->id);
        $this->actingAs($this->editor)->post(route('admin.sources.ignore', $other), ['reason' => 'إعادة تدوير'])->assertRedirect();
        $this->assertSame('ignored', $other->fresh()->stage);
        $feed = SourceFeed::query()->first();
        $this->actingAs($this->editor)->patch(route('admin.sources.feeds.toggle', $feed))->assertRedirect();
        $this->assertFalse($feed->fresh()->is_active);
    }

    public function test_stale_sources_show_as_refresh_reminders_and_quality_signals(): void
    {
        $item = ContentItem::published()->whereIn('type', ContentItem::SOURCED_TYPES)->firstOrFail();
        $item->sources()->update(['published_on' => now()->subYears(30)]); // the oldest of all, so it leads the reminder list
        $this->actingAs($this->editor)->get('/admin/sources')->assertOk()->assertSee('تذكيرات تحديث (')->assertSee($item->title);
        $this->actingAs($this->editor)->get('/admin/content')->assertOk()->assertSee('تذكيرات تحديث');
        $this->artisan('platform:compute-quality-signals')->assertSuccessful();
        $this->assertDatabaseHas('quality_signals', ['type' => 'stale_sources', 'entity_type' => 'content', 'entity_id' => $item->id, 'status' => 'open']);
        $admin = User::factory()->create(['platform_role' => 'admin']);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('صندوق المصادر')->assertDontSee('المرحلة 3)');
    }
}
