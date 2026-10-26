<?php

namespace Tests\Feature;

use App\Domain\Content\Models\ContentItem;
use App\Domain\Workspace\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('لأصحاب المشاريع')
            ->assertSee('للمسوّقين والوكالات')
            ->assertSee('الأكاديمية')
            ->assertSee('مختبر التسويق')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
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

    public function test_search_course_tool_and_discovery_endpoints_are_available(): void
    {
        $this->seed();

        $this->get('/search?q=ذكاء')->assertOk()->assertSee('نتائج البحث');
        $this->get('/academy')->assertOk()->assertSee('مسارات تعليمية تطبيقية');
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
