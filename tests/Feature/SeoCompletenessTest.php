<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\ResourceTemplate;
use App\Domain\Content\Models\Tag;
use App\Domain\Lab\Models\Tool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** §3.3: every public page carries a page-specific schema, a share image and breadcrumbs. */
class SeoCompletenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @return list<string> the @type of every JSON-LD block on the page */
    private function schemaTypes(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $matches);
        $types = [];
        foreach ($matches[1] as $json) {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            foreach (array_is_list($data) ? $data : [$data] as $block) {
                $this->assertSame('https://schema.org', $block['@context'] ?? null);
                $types[] = $block['@type'];
            }
        }

        return $types;
    }

    public function test_public_pages_have_their_own_schema_breadcrumbs_and_share_image(): void
    {
        $generator = Tool::query()->where('is_published', true)->where('kind', 'generator')->where('slug', '!=', 'lesson-lab')->firstOrFail();
        $calculator = Tool::query()->where('is_published', true)->where('kind', 'calculator')->firstOrFail();
        $template = ResourceTemplate::query()->where('is_published', true)->firstOrFail();
        $tag = Tag::query()->has('items', '>=', 1)->firstOrFail();

        $pages = [
            '/about' => 'AboutPage',
            '/contact' => 'ContactPage',
            '/privacy' => 'WebPage',
            '/terms' => 'WebPage',
            '/assess' => 'WebPage',
            '/pricing' => 'FAQPage',
            '/tools' => 'CollectionPage',
            '/tools/'.$generator->slug => 'WebApplication',
            '/tools/calculators/'.$calculator->slug => 'WebApplication',
            '/tools/simulator' => 'WebApplication',
            '/resources' => 'CollectionPage',
            '/resources/'.$template->slug => 'DigitalDocument',
            '/articles' => 'CollectionPage',
            '/news' => 'CollectionPage',
            '/insights' => 'CollectionPage',
            '/case-studies' => 'CollectionPage',
            '/research' => 'CollectionPage',
            '/tags/'.$tag->slug => 'CollectionPage',
        ];

        foreach ($pages as $path => $type) {
            $html = (string) $this->get($path)->assertOk()->getContent();
            $types = $this->schemaTypes($html);

            $this->assertContains($type, $types, "{$path} must declare {$type}");
            $this->assertContains('BreadcrumbList', $types, "{$path} must declare BreadcrumbList");
            $this->assertMatchesRegularExpression('#<meta property="og:image" content="https?://[^"]+/images/og-default\.png">#', $html, "{$path} og:image");
            $this->assertStringContainsString('<meta name="twitter:image"', $html, "{$path} twitter:image");
            $this->assertStringContainsString('<link rel="canonical"', $html, "{$path} canonical");
        }
    }

    public function test_listing_schemas_enumerate_real_items(): void
    {
        $html = (string) $this->get('/tools')->assertOk()->getContent();
        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $matches);
        $collection = collect($matches[1])->map(fn ($j) => json_decode($j, true))->firstWhere('@type', 'CollectionPage');

        $published = Tool::query()->where('is_published', true)->where('slug', '!=', 'lesson-lab')->count();
        $this->assertSame($published, $collection['mainEntity']['numberOfItems']);
        $this->assertStringStartsWith('http', $collection['mainEntity']['itemListElement'][0]['url']);
    }

    public function test_pricing_faq_schema_mirrors_the_visible_questions(): void
    {
        $html = (string) $this->get('/pricing')->assertOk()->getContent();
        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $matches);
        $faq = collect($matches[1])->map(fn ($j) => json_decode($j, true))->firstWhere('@type', 'FAQPage');

        $this->assertCount(4, $faq['mainEntity']);
        foreach ($faq['mainEntity'] as $question) {
            $this->assertStringContainsString('<summary>'.e($question['name']).'</summary>', $html);
            $this->assertNotSame('', trim($question['acceptedAnswer']['text']));
        }
    }

    public function test_search_page_has_breadcrumbs_and_assets_exist(): void
    {
        $this->get('/search?q=تسويق')->assertOk()->assertSee('aria-label="مسار الصفحة"', false);

        $this->assertFileExists(public_path('images/og-default.png'));
        $this->assertFileExists(public_path('images/logo.png'));
        foreach ([400, 500, 700] as $weight) {
            $this->assertFileExists(public_path("fonts/ibm-plex-sans-arabic-arabic-{$weight}-normal.woff2"));
            $this->assertFileExists(public_path("fonts/ibm-plex-sans-arabic-latin-{$weight}-normal.woff2"));
        }
        $this->assertStringContainsString('@font-face', (string) file_get_contents(public_path('css/platform.css')));
        $this->get('/')->assertOk()->assertSee('images/logo.png', false)->assertDontSee('favicon.ico"', false);
    }

    public function test_llms_txt_lists_absolute_links_for_paths_tools_and_indexes(): void
    {
        $body = (string) $this->get('/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8')->getContent();
        $base = rtrim(config('app.url'), '/');

        foreach (['/assess', '/academy', '/tools', '/tools/simulator', '/resources', '/glossary', '/sitemap.xml', '/feed.xml', '/pricing', '/about'] as $path) {
            $this->assertStringContainsString(']('.$base.$path.')', $body, "llms.txt must link {$path} absolutely");
        }
        $this->assertSame(12, substr_count($body, ']('.$base.'/academy/'), 'one link per learning path');
        $this->assertStringContainsString($base.'/tools/calculators/', $body);
        $this->assertStringNotContainsString('&quot;', $body);
        $this->assertStringNotContainsString('&amp;', $body);
    }
}
