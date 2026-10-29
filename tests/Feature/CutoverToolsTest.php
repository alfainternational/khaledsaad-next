<?php

namespace Tests\Feature;

use App\Domain\Content\Models\ContentItem;
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

        // Encoded legacy URL hits the decoded redirect row.
        $this->get('/blog/'.rawurlencode('صوتك-في-السوق'))->assertRedirect('/articles/صوتك-في-السوق');

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
}
