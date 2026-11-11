<?php

namespace Tests\Feature;

use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Services\LegacyRedirects;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CutoverToolsTest extends TestCase
{
    use RefreshDatabase;

    private function legacySchema(): void
    {
        Schema::connection('legacy')->dropIfExists('contents');
        Schema::connection('legacy')->dropIfExists('content_categories');
        Schema::connection('legacy')->create('content_categories', function (Blueprint $t): void {
            $t->id();
            $t->string('name');
        });
        Schema::connection('legacy')->create('contents', function (Blueprint $t): void {
            $t->id();
            $t->string('type')->default('article');
            $t->unsignedBigInteger('category_id')->nullable();
            $t->string('title');
            $t->string('slug');
            $t->text('excerpt')->nullable();
            $t->text('body_json')->nullable();
            $t->text('body_html')->nullable();
            $t->string('cover_image_path')->nullable();
            $t->unsignedSmallInteger('duration_minutes')->nullable();
            $t->string('status')->default('draft');
            $t->string('access_level')->default('public');
            $t->timestamp('published_at')->nullable();
            $t->string('seo_title')->nullable();
            $t->text('seo_description')->nullable();
            $t->timestamps();
        });
    }

    /** An unreviewed article exactly as platform:import-legacy leaves it: real row, draft status. */
    private function draftItem(string $slug, string $type): ContentItem
    {
        return ContentItem::query()->create([
            'title' => 'عنوان '.$slug, 'slug' => $slug, 'type' => $type, 'excerpt' => 'ملخص قصير',
            'body' => "الفقرة الأولى.\n\nالفقرة الثانية.", 'author_name' => 'خالد سعد', 'status' => 'draft',
        ]);
    }

    public function test_redirect_csv_import_upserts_and_rejects_bad_rows(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'redir').'.csv';
        file_put_contents($csv, implode("\n", [
            'from_path,to_path,status_code',
            '/blog,/articles,301',
            'https://khaledsaad.net/pricing,/register,301',
            '/download/android,/,410',
            '/bad path,/articles,301',
            '/loop,/loop,301',
            '/evil,//evil.com,301',
        ]));

        $this->artisan('platform:import-redirects', ['file' => $csv])->assertSuccessful();
        $this->artisan('platform:import-redirects', ['file' => $csv])->assertSuccessful();

        $this->assertDatabaseCount('redirects', 3);
        $this->assertDatabaseHas('redirects', ['from_path' => '/pricing', 'to_path' => '/register', 'status_code' => 301]);
        $this->assertDatabaseHas('redirects', ['from_path' => '/download/android', 'status_code' => 410]);
        $this->assertDatabaseMissing('redirects', ['from_path' => '/evil']);

        $this->get('/pricing')->assertOk()->assertSee('مقارنة الخطط');
        $this->head('/pricing')->assertOk();
        $this->get('/blog')->assertRedirect('/articles');
        $this->get('/download/android')->assertStatus(410);
        unlink($csv);
    }

    public function test_shipped_redirect_map_is_valid_and_targets_existing_routes(): void
    {
        $this->seed();
        $this->artisan('platform:import-redirects', ['file' => base_path('docs/cutover/redirects.csv')])
            ->expectsOutputToContain('skipped 0')
            ->assertSuccessful();

        foreach (DB::table('redirects')->where('status_code', '!=', 410)->pluck('to_path') as $target) {
            $status = $this->get($target)->getStatusCode();
            $this->assertContains($status, [200, 302], "redirect target {$target} answered {$status}");
        }
        $this->get('/profile')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/profile')->assertOk()->assertSee('id="send-verification"', false);
    }

    public function test_legacy_articles_import_as_drafts_with_arabic_slug_redirects(): void
    {
        $this->legacySchema();
        DB::connection('legacy')->table('content_categories')->insert(['id' => 7, 'name' => 'المحتوى']);
        $base = ['category_id' => null, 'excerpt' => null, 'duration_minutes' => null, 'published_at' => null, 'created_at' => now(), 'updated_at' => now()];
        DB::connection('legacy')->table('contents')->insert([
            ['type' => 'article', 'category_id' => 7, 'title' => 'صوتك في السوق', 'slug' => 'صوتك-في-السوق',
                'body_html' => '<h2>مقدمة</h2><p>الفقرة &amp; الأولى.</p><p>الفقرة الثانية.<br>سطر.</p><script>alert(1)</script>',
                'status' => 'published', 'access_level' => 'public', 'published_at' => '2025-03-01 10:00:00', 'duration_minutes' => 4] + $base,
            ['type' => 'article', 'title' => 'مسودة', 'slug' => 'draft-one', 'body_html' => '<p>x</p>',
                'status' => 'draft', 'access_level' => 'public'] + $base,
            ['type' => 'lesson', 'title' => 'خاص', 'slug' => 'members-only', 'body_html' => '<p>y</p>',
                'status' => 'published', 'access_level' => 'members', 'published_at' => '2025-01-01 00:00:00'] + $base,
        ]);

        $this->artisan('platform:import-legacy')->assertSuccessful();

        $item = ContentItem::query()->where('slug', 'صوتك-في-السوق')->firstOrFail();
        $this->assertSame('draft', $item->status);
        $this->assertSame("مقدمة\n\nالفقرة & الأولى.\n\nالفقرة الثانية.\n\nسطر.", $item->body);
        $this->assertSame('المحتوى', $item->topic);
        $this->assertSame(4, $item->reading_minutes);
        $this->assertSame('legacy_import', $item->source_mode);
        $this->assertStringNotContainsString('alert', $item->body);
        $this->assertDatabaseCount('content_items', 1);
        $this->assertDatabaseHas('redirects', ['from_path' => '/blog/صوتك-في-السوق', 'to_path' => '/articles/صوتك-في-السوق', 'status_code' => 301]);

        // Encoded legacy URL hits the decoded redirect row. The article is still a draft, so the member is sent to
        // its section — temporarily, so nothing caches a destination the editor is about to change.
        $this->get('/blog/'.rawurlencode('صوتك-في-السوق'))->assertStatus(302)->assertRedirect('/articles');

        // Re-running updates in place and never republishes/downgrades an editor's decision.
        $item->update(['status' => 'published', 'published_at' => now()]);
        $this->artisan('platform:import-legacy')->assertSuccessful();
        $this->assertDatabaseCount('content_items', 1);
        $this->assertSame('published', $item->fresh()->status);
    }

    public function test_legacy_import_can_publish_with_original_dates(): void
    {
        $this->legacySchema();
        DB::connection('legacy')->table('contents')->insert([
            'type' => 'article', 'title' => 'قديم', 'slug' => 'old-post', 'body_html' => '<p>نص</p>',
            'status' => 'published', 'access_level' => 'public', 'published_at' => '2024-06-01 09:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('platform:import-legacy', ['--publish' => true])->assertSuccessful();

        $item = ContentItem::query()->where('slug', 'old-post')->firstOrFail();
        $this->assertSame('published', $item->status);
        $this->assertSame('2024-06-01', $item->published_at->toDateString());
        $this->get('/articles/old-post')->assertOk()->assertSee('قديم');
    }

    /**
     * The cutover blocker found on 2026-11-11: twenty indexed /blog/{slug} URLs carried a 301 to articles that the
     * legacy import deliberately left as drafts, so cutover day would have answered them with a permanent redirect
     * into a 404. The destination is now decided when the request arrives, not when the row was written.
     */
    public function test_a_redirect_to_an_unpublished_article_lands_on_its_section_until_it_goes_live(): void
    {
        $this->seed();
        $draft = $this->draftItem('weekly-change', 'news');
        DB::table('redirects')->insert([
            ['from_path' => '/blog/weekly-change', 'to_path' => '/articles/weekly-change', 'status_code' => 301, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['from_path' => '/blog/vanished', 'to_path' => '/articles/vanished', 'status_code' => 301, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // A draft goes to the shelf it will appear on, temporarily: a 301 here would outlive the draft in every
        // cache that saw it, and keep sending readers to the index long after the article was published.
        $fallback = $this->get('/blog/weekly-change')->assertStatus(302)->assertRedirect('/news');
        // Nothing may cache a destination the editor can change this afternoon — not even heuristically.
        $this->assertStringContainsString('no-store', $fallback->headers->get('Cache-Control', ''));
        // An article that no longer exists at all has no shelf of its own; the magazine index is the honest answer.
        $this->get('/blog/vanished')->assertStatus(302)->assertRedirect('/articles');

        // The row is untouched: it still records what the editor meant to happen.
        $this->assertDatabaseHas('redirects', ['from_path' => '/blog/weekly-change', 'to_path' => '/articles/weekly-change', 'status_code' => 301]);
        $this->assertSame(['/blog/weekly-change' => 'weekly-change', '/blog/vanished' => 'vanished'], LegacyRedirects::brokenTargets());
        $this->artisan('platform:doctor --env=production')->expectsOutputToContain('[WARN] legacy redirects reach their article (2 waiting on review)');

        // Publishing is the only thing that has to happen, and nothing has to be re-imported or re-pointed.
        $draft->update(['status' => 'published', 'published_at' => now()->subMinute()]);

        $this->get('/blog/weekly-change')->assertStatus(301)->assertRedirect('/articles/weekly-change');
        $this->assertSame(['/blog/vanished' => 'vanished'], LegacyRedirects::brokenTargets());
    }

    public function test_the_fallback_serves_the_section_instead_of_looping_on_it(): void
    {
        $this->seed();
        $this->draftItem('not-yet', 'article');
        DB::table('redirects')->insert([
            // A section index pointed into one of its own unready articles: resolving it yields the path that was
            // asked for. Aborting 500 on that would take the magazine down to avoid a redirect loop.
            ['from_path' => '/articles', 'to_path' => '/articles/not-yet', 'status_code' => 301, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            // Targets outside /articles/{slug} are not second-guessed at all.
            ['from_path' => '/knowledge', 'to_path' => '/articles', 'status_code' => 301, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->get('/articles')->assertOk();
        $this->get('/knowledge')->assertStatus(301)->assertRedirect('/articles');
        // An inactive row is still invisible, and a 410 still means gone rather than "look on the shelf".
        DB::table('redirects')->where('from_path', '/knowledge')->update(['status_code' => 410]);
        $this->get('/knowledge')->assertStatus(410);
    }
}
