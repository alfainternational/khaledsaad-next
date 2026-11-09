<?php

namespace Tests\Feature;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Models\LessonVariant;
use App\Domain\Insights\Services\StudioAssistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** استوديو الدرس (§6.2، §6.6): متغيّر الدرس يُحفظ في lesson_variants ويُطبَّق كإصدار جديد قابل للرجوع عبر مسار الإصدارات نفسه. */
class AdminGapsLessonVariantsTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['services.openai.key' => null]);
        $this->editor = User::factory()->create(['platform_role' => 'editor']);
        $this->lesson = Course::query()->where('slug', 'marketing-foundations')->firstOrFail()->lessons()->firstOrFail();
    }

    public function test_editor_saves_a_variant_as_a_draft_without_touching_the_lesson(): void
    {
        $original = $this->lesson->scenario_template;
        $url = '/admin/academy/lessons/'.$this->lesson->id;

        $this->actingAs($this->editor)->get($url)->assertOk()->assertSee('المتغيّرات المحفوظة')->assertSee('لا متغيّرات محفوظة بعد')->assertSee('احفظ كمسودة')->assertDontSee('لم يُحفظ');

        $this->actingAs($this->editor)->post($url.'/variant', ['audience' => 'الوكالات', 'scenario' => 'وكالة تدير {{offer}} لعميل بميزانية {{budget_label}} على {{channel_label}}.'])
            ->assertRedirect()->assertSessionHas('status');
        $variant = LessonVariant::query()->firstOrFail();
        $this->assertSame([$this->lesson->id, 'الوكالات', 'manual', 'draft', $this->editor->id], [$variant->lesson_id, $variant->audience, $variant->source, $variant->status, $variant->created_by]);
        $this->assertNull($variant->applied_at);
        $this->assertSame($original, $this->lesson->fresh()->scenario_template);
        $this->assertSame(0, $this->lesson->versions()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'lesson.variant_saved', 'subject_type' => 'lesson', 'subject_id' => (string) $this->lesson->id, 'user_id' => $this->editor->id]);

        $this->actingAs($this->editor)->get($url.'?part=situation')->assertOk()
            ->assertSee('وكالة تدير')->assertSee('الوكالات')->assertSee('مسودة')->assertSee('طبّق كإصدار جديد')->assertSee('كتابة يدوية')
            ->assertSee(route('admin.academy.lessons.variants.apply', [$this->lesson, $variant]), false);

        // Without an AI key an empty text cannot be generated: a clear error and nothing saved.
        $this->actingAs($this->editor)->post($url.'/variant', ['audience' => 'المتاجر'])->assertSessionHasErrors('variant');
        $this->actingAs($this->editor)->post($url.'/variant', ['scenario' => 'بلا جمهور'])->assertSessionHasErrors('audience');
        $this->assertSame(1, LessonVariant::query()->count());
        $this->actingAs(User::factory()->create())->post($url.'/variant', ['audience' => 'x', 'scenario' => 'y'])->assertForbidden();
    }

    public function test_a_generated_variant_is_persisted_with_its_source(): void
    {
        $this->mock(StudioAssistant::class, fn ($mock) => $mock->shouldReceive('generateVariant')->once()->andReturn('  موقف مولّد لوكالة تبيع {{offer}}.  '));

        $this->actingAs($this->editor)->post('/admin/academy/lessons/'.$this->lesson->id.'/variant', ['audience' => 'المسوّقون والوكالات'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('lesson_variants', ['lesson_id' => $this->lesson->id, 'audience' => 'المسوّقون والوكالات', 'source' => 'ai', 'status' => 'draft', 'scenario' => 'موقف مولّد لوكالة تبيع {{offer}}.']);
    }

    public function test_applying_a_variant_writes_a_new_lesson_version_that_restore_rolls_back(): void
    {
        $original = $this->lesson->scenario_template;
        $publishedAt = $this->lesson->published_at;
        $variant = LessonVariant::query()->create(['lesson_id' => $this->lesson->id, 'audience' => 'الوكالات', 'scenario' => 'وكالة تدير {{offer}} لعميلها.', 'source' => 'manual', 'status' => 'draft', 'created_by' => $this->editor->id]);
        $url = '/admin/academy/lessons/'.$this->lesson->id;

        $this->actingAs($this->editor)->post($url.'/variants/'.$variant->id.'/apply')->assertRedirect()->assertSessionHas('status');

        $lesson = $this->lesson->fresh();
        $this->assertSame('وكالة تدير {{offer}} لعميلها.', $lesson->scenario_template);
        $this->assertEquals($publishedAt, $lesson->published_at);
        $version = $lesson->versions()->firstOrFail();
        $this->assertSame(1, $version->version);
        $this->assertSame($this->editor->id, $version->user_id);
        $this->assertStringContainsString('الوكالات', $version->reason);
        $this->assertSame($original, $version->snapshot['lesson']['scenario_template']);
        $variant->refresh();
        $this->assertSame(['applied', 1], [$variant->status, $variant->applied_version]);
        $this->assertNotNull($variant->applied_at);
        $row = DB::table('audit_logs')->where('action', 'lesson.variant_applied')->where('subject_id', (string) $this->lesson->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(['variant_id' => $variant->id, 'version' => 1], array_intersect_key(json_decode($row->context, true), ['variant_id' => 1, 'version' => 1]));

        $this->actingAs($this->editor)->get($url.'?part=situation')->assertOk()->assertSee('مطبّق الآن')->assertSee('آخر إصدار v1');

        // The studio's own restore brings the previous scenario back (and is itself a new version).
        $this->actingAs($this->editor)->post($url.'/restore/1')->assertRedirect();
        $this->assertSame($original, $this->lesson->fresh()->scenario_template);
        $this->assertSame(2, $this->lesson->versions()->count());
        $this->actingAs($this->editor)->get($url.'?part=situation')->assertOk()->assertSee('طُبّق في v1')->assertDontSee('مطبّق الآن');
    }

    public function test_variant_actions_are_scoped_to_their_lesson_and_to_editors_and_delete_is_audited(): void
    {
        $other = Lesson::query()->where('id', '!=', $this->lesson->id)->firstOrFail();
        $variant = LessonVariant::query()->create(['lesson_id' => $this->lesson->id, 'audience' => 'الوكالات', 'scenario' => 'نص بديل', 'source' => 'manual', 'status' => 'draft']);

        $this->actingAs($this->editor)->post('/admin/academy/lessons/'.$other->id.'/variants/'.$variant->id.'/apply')->assertNotFound();
        $this->actingAs($this->editor)->delete('/admin/academy/lessons/'.$other->id.'/variants/'.$variant->id)->assertNotFound();
        $this->actingAs(User::factory()->create())->post('/admin/academy/lessons/'.$this->lesson->id.'/variants/'.$variant->id.'/apply')->assertForbidden();
        $this->actingAs(User::factory()->create(['platform_role' => 'finance']))->post('/admin/academy/lessons/'.$this->lesson->id.'/variants/'.$variant->id.'/apply')->assertForbidden();
        $this->assertSame(0, $other->versions()->count() + $this->lesson->versions()->count());

        $this->actingAs($this->editor)->delete('/admin/academy/lessons/'.$this->lesson->id.'/variants/'.$variant->id)->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseMissing('lesson_variants', ['id' => $variant->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lesson.variant_deleted', 'subject_id' => (string) $this->lesson->id, 'user_id' => $this->editor->id]);
    }
}
