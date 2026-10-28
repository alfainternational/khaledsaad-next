<?php

namespace Tests\Feature;

use App\Domain\Content\Models\ContentItem;
use App\Domain\Workspace\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_is_arabic_rtl_and_presents_the_complete_platform(): void
    {
        $this->seed();

        $this->get('/')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('تعلّم. طبّق. قِس الأثر.')
            ->assertSee('أصحاب المشاريع')
            ->assertSee('المسوّقون والوكالات')
            ->assertSee('جرّب على مشروعك الآن')
            ->assertSee('المختبر')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy')
            ->assertHeader('X-Request-Id');
    }

    public function test_only_published_content_is_public_with_sources_and_schema(): void
    {
        $this->seed();
        $published = ContentItem::query()->where('status', 'published')->firstOrFail();
        $draft = ContentItem::query()->create([
            'type' => 'article',
            'status' => 'draft',
            'title' => 'مسودة داخلية',
            'slug' => 'internal-draft',
            'excerpt' => 'غير منشورة',
            'body' => 'غير منشورة',
            'author_name' => 'فريق أثر',
        ]);

        $this->get(route('articles.show', $published))
            ->assertOk()
            ->assertSee('المصادر')
            ->assertSee('"@type":"Article"', false)
            ->assertSee('<link rel="canonical"', false);

        $this->get(route('articles.show', $draft))->assertNotFound();
    }

    public function test_article_json_ld_escapes_script_terminators(): void
    {
        $article = ContentItem::query()->create([
            'type' => 'article', 'status' => 'published', 'title' => '</script><script>alert(1)</script>',
            'slug' => 'safe-json-ld', 'excerpt' => 'ملخص', 'body' => 'محتوى', 'author_name' => 'فريق أثر', 'published_at' => now(),
        ]);

        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertDontSee('</script><script>', false)
            ->assertSee('\\u003C/script\\u003E', false);
    }

    public function test_configured_legacy_url_redirects_permanently(): void
    {
        DB::table('redirects')->insert([
            'from_path' => '/old-guide', 'to_path' => '/articles', 'status_code' => 301, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get('/old-guide')->assertRedirect('/articles')->assertStatus(301);
    }

    public function test_search_course_tool_and_discovery_endpoints_are_available(): void
    {
        $this->seed();

        $this->get('/search?q=ذكاء')->assertOk()->assertSee('نتائج البحث');
        $this->get('/academy')->assertOk()->assertSee('مسارات تطبيقية عن مشروعك أنت');
        $this->get('/tools')->assertOk()->assertSee('مختبر التسويق');
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml');
        $this->get('/feed.xml')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $this->get('/llms.txt')->assertOk()->assertSee('أثر');
    }

    public function test_member_cannot_view_another_workspaces_project(): void
    {
        $member = User::factory()->create();
        $owner = User::factory()->create();
        $workspace = Workspace::query()->create(['name' => 'مساحة المالك', 'slug' => 'owner-space']);
        $workspace->members()->attach($owner, ['role' => 'owner']);
        $foreignProject = Project::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'مشروع سري',
            'slug' => 'private-project',
            'status' => 'active',
        ]);

        $this->actingAs($member)
            ->get(route('app.projects.show', $foreignProject))
            ->assertForbidden();
    }
}
