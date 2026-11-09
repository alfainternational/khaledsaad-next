<?php

namespace Tests\Feature;

use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\GlossaryTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ContentItem::create(['slug' => 'customer-journey-map', 'title' => 'رحلة العميل', 'type' => 'article', 'status' => 'published', 'published_at' => now(), 'author_name' => 'فريق أثر', 'excerpt' => 'قرار وتطبيق', 'body' => 'شرح قصير.']);
        ContentItem::create(['slug' => 'ai-writing-without-sounding-like-everyone-else', 'title' => 'الكتابة والمراجعة', 'type' => 'article', 'status' => 'published', 'published_at' => now()->subDay(), 'author_name' => 'فريق أثر', 'excerpt' => 'قراءة مطولة', 'body' => collect(['سؤال القرار', 'الأدلة', 'التطبيق', 'المراجعة'])->map(fn ($title) => '## '.$title."\n\n".str_repeat('شرح مرتبط بالتطبيق والأدلة وحدود الاستنتاج. ', 35))->implode("\n\n")]);
        GlossaryTerm::create(['slug' => 'cac', 'term' => 'تكلفة اكتساب العميل', 'definition' => 'الإنفاق مقسوماً على العملاء الجدد.', 'body' => 'مثال وطريقة حساب.', 'is_published' => true]);
    }

    public function test_short_reading_has_a_focused_column_and_no_empty_author_sidebar(): void
    {
        $item = ContentItem::where('slug', 'customer-journey-map')->firstOrFail();
        $item->update(['body' => "فقرة تشرح القرار.\n\nفقرة تشرح التطبيق.", 'reading_minutes' => 99]);
        $this->get(route('articles.show', $item))->assertOk()
            ->assertSee('reading-page--focused', false)->assertSee('reading-layout--focused', false)
            ->assertDontSee('class="article-side', false)->assertSee('reading-trust', false)
            ->assertSee('دقيقة قراءة')->assertDontSee('99 دقائق')
            ->assertSee('id="report"', false)->assertSee('"@type":"Article"', false);
    }

    public function test_long_reading_keeps_useful_toc_sources_and_a_single_primary_application(): void
    {
        $item = ContentItem::where('slug', 'ai-writing-without-sounding-like-everyone-else')->firstOrFail();
        $page = $this->get(route('articles.show', $item))->assertOk()
            ->assertSee('reading-layout--indexed', false)->assertSee('data-toc=', false)
            ->assertSee('toc-mobile-box', false)->assertSee('id="sources"', false)
            ->assertSee('reading-action-primary', false)->assertSee('id="related"', false);
        $this->assertSame(1, substr_count($page->getContent(), 'class="btn btn-primary reading-action-primary"'));
    }

    public function test_reference_and_about_pages_share_a_reading_rhythm(): void
    {
        $this->get('/glossary/cac')->assertOk()->assertSee('reading-page--reference', false)->assertSee('"@type":"DefinedTerm"', false);
        $this->get('/about')->assertOk()->assertSee('reading-page--about', false)->assertSee('reading-width', false);
    }
}
