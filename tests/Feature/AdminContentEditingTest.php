<?php

namespace Tests\Feature;

use App\Domain\Content\Models\ContentItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminContentEditingTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['platform_role' => 'editor']);
    }

    private function draft(array $overrides = []): ContentItem
    {
        return ContentItem::query()->create(array_merge([
            'type' => 'article', 'status' => 'draft', 'title' => '1', 'slug' => '1', 'excerpt' => 'ملخص قصير',
            'body' => "فقرة أولى.\n\nفقرة ثانية.", 'author_name' => 'خالد سعد', 'reading_minutes' => 3,
            'meta' => ['legacy' => ['id' => 9, 'body_html' => '<p>أصل</p><script>x</script>']],
        ], $overrides));
    }

    public function test_members_cannot_open_or_submit_the_editor(): void
    {
        $item = $this->draft();
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('admin.content.edit', $item))->assertForbidden();
        $this->actingAs($member)->put(route('admin.content.update', $item), ['title' => 'x'])->assertForbidden();
        $this->assertSame('1', $item->fresh()->title);
    }

    public function test_editor_can_fix_title_slug_and_move_to_review_then_publish(): void
    {
        $item = $this->draft();
        $editor = $this->editor();

        $this->actingAs($editor)->get(route('admin.content.edit', $item))->assertOk()->assertSee('النسخة الأصلية')->assertDontSee('<script>', false);

        $this->actingAs($editor)->put(route('admin.content.update', $item), [
            'title' => 'إدارة الأزمات التسويقية', 'slug' => 'إدارة-الأزمات-التسويقية', 'type' => 'article', 'status' => 'review',
            'eyebrow' => 'من الأرشيف', 'topic' => 'استراتيجية', 'excerpt' => 'كيف تتعامل مع الأزمة.', 'body' => "نص محدّث.\n\nفقرة.",
            'author_name' => 'خالد سعد', 'reviewer_name' => 'خالد', 'reading_minutes' => 5,
        ])->assertRedirect(route('admin.content.edit', $item->fresh()))->assertSessionHas('status');

        $fresh = $item->fresh();
        $this->assertSame('review', $fresh->status);
        $this->assertSame('إدارة-الأزمات-التسويقية', $fresh->slug);
        $this->assertNotNull($fresh->reviewed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'content.updated', 'subject_id' => (string) $item->id]);

        $this->actingAs($editor)->post(route('admin.content.publish', $fresh))->assertRedirect()->assertSessionHas('status');
        $this->assertSame('published', $fresh->fresh()->status);
        $this->get('/articles/'.rawurlencode('إدارة-الأزمات-التسويقية'))->assertOk()->assertSee('إدارة الأزمات التسويقية');
    }

    public function test_publishing_a_draft_explains_instead_of_crashing(): void
    {
        $item = $this->draft();

        $this->actingAs($this->editor())->from(route('admin.content.index'))->post(route('admin.content.publish', $item))
            ->assertRedirect(route('admin.content.index'))->assertSessionHasErrors('publish');
        $this->assertSame('draft', $item->fresh()->status);
    }

    public function test_slug_must_be_unique_and_well_formed_and_published_status_is_locked(): void
    {
        $this->draft(['slug' => 'taken', 'title' => 'آخر']);
        $item = $this->draft();
        $editor = $this->editor();
        $valid = ['title' => 'عنوان', 'type' => 'article', 'status' => 'draft', 'excerpt' => 'م', 'body' => 'ن', 'author_name' => 'خ', 'reading_minutes' => 2];

        $this->actingAs($editor)->put(route('admin.content.update', $item), $valid + ['slug' => 'taken'])->assertSessionHasErrors('slug');
        $this->actingAs($editor)->put(route('admin.content.update', $item), $valid + ['slug' => 'bad slug/here'])->assertSessionHasErrors('slug');
        $this->actingAs($editor)->put(route('admin.content.update', $item), array_merge($valid, ['slug' => 'ok', 'status' => 'published']))->assertSessionHasErrors('status');

        $published = $this->draft(['slug' => 'live', 'status' => 'published', 'published_at' => now()]);
        $this->actingAs($editor)->put(route('admin.content.update', $published), array_merge($valid, ['slug' => 'live', 'status' => 'draft']))->assertSessionHasErrors('status');
        $this->actingAs($editor)->put(route('admin.content.update', $published), array_merge($valid, ['slug' => 'live', 'status' => 'published']))->assertSessionHasNoErrors();
        $this->assertSame('published', $published->fresh()->status);
    }

    public function test_index_filters_by_status_and_shows_counts(): void
    {
        $this->draft();
        $this->draft(['slug' => 'r', 'status' => 'review']);

        $this->actingAs($this->editor())->get(route('admin.content.index', ['status' => 'review']))
            ->assertOk()->assertSee('مسودة (1)')->assertSee('جاهزة للمراجعة (1)')->assertSee('نشر');
    }

    public function test_health_endpoint_reports_heartbeats(): void
    {
        $this->getJson('/health.json')->assertStatus(503)->assertJson(['ok' => false, 'database' => true, 'scheduler_age_seconds' => null]);

        Cache::put('platform:scheduler_heartbeat', now()->timestamp, 900);
        Cache::put('platform:worker_heartbeat', now()->subSeconds(30)->timestamp, 900);
        $this->getJson('/health.json')->assertOk()->assertJson(['ok' => true])->assertJsonPath('worker_age_seconds', fn ($v) => $v >= 29 && $v <= 35);
    }
}
