<?php

namespace Tests\Feature;

use App\Domain\Lab\Models\Experiment;
use App\Domain\Lab\Models\ExperimentVariant;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolVersion;
use App\Domain\Lab\Services\StudioMetrics;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** تجربة قيد التشغيل (§6.1 رقم 5): «تُعتمد تلقائياً عند الدلالة الإحصائية» — اختبار z لنسبتين على معدل الحفظ، بحد أدنى للعينة. */
class AdminGapsExperimentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Tool $tool;

    private ToolVersion $v1;

    private ToolVersion $v2;

    private Experiment $experiment;

    private ExperimentVariant $a;

    private ExperimentVariant $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['platform_role' => 'admin']);
        $this->tool = Tool::query()->where('slug', 'campaign-brief-builder')->firstOrFail();
        $this->actingAs($this->admin)->post(route('admin.tools.versions.store', $this->tool), ['label' => 'جدول مختصر', 'output_template' => "# موجز الحملة B — {{offer_short}}\n\n{{goal}}"])->assertRedirect();
        $this->tool->load('versions');
        $this->v1 = $this->tool->versions->firstWhere('version', 1);
        $this->v2 = $this->tool->versions->firstWhere('version', 2);
        $this->actingAs($this->admin)->post(route('admin.tools.experiment.start', $this->tool), ['variant_a' => $this->v1->id, 'variant_b' => $this->v2->id, 'weight_a' => 50, 'name' => 'جدول مقابل قائمة'])->assertRedirect();
        $this->experiment = Experiment::query()->where('subject_id', $this->tool->id)->where('status', 'running')->with('variants')->firstOrFail();
        $this->a = $this->experiment->variants->firstWhere('tool_version_id', $this->v1->id);
        $this->b = $this->experiment->variants->firstWhere('tool_version_id', $this->v2->id);
    }

    private function expose(ExperimentVariant $variant, int $exposed, int $converted): void
    {
        $rows = [];
        for ($i = 0; $i < $exposed; $i++) {
            $rows[] = ['experiment_id' => $this->experiment->id, 'variant_id' => $variant->id, 'session_token' => 's'.$variant->id.'-'.$i, 'converted_at' => $i < $converted ? now() : null, 'created_at' => now()];
        }
        DB::table('experiment_exposures')->insert($rows);
    }

    private function automaticAuditRows(): int
    {
        return DB::table('audit_logs')->where('action', 'tool.experiment_ended')->where('context', 'like', '%"automatic":true%')->count();
    }

    public function test_command_adopts_the_winner_of_a_significant_result_with_an_automatic_audit_row(): void
    {
        // A: 30/120 = 25٪ · B: 60/120 = 50٪ → pooled z = −4.0, p ≈ 0.0001.
        $this->expose($this->a, 120, 30);
        $this->expose($this->b, 120, 60);

        $result = app(StudioMetrics::class)->significance($this->experiment);
        $this->assertTrue($result['ready']);
        $this->assertTrue($result['significant']);
        $this->assertEqualsWithDelta(-4.0, $result['z'], 0.01);
        $this->assertLessThan(0.001, $result['p']);
        $this->assertSame($this->b->id, $result['winner_variant_id']);
        $this->assertSame([$this->a->id => 120, $this->b->id => 120], $result['samples']);

        $this->actingAs($this->admin)->get(route('admin.tools.show', $this->tool))->assertOk()
            ->assertSee('النتيجة دالّة إحصائياً')->assertSee('data-significant="1"', false)->assertSee('120 / 120')->assertSee('-4.00')->assertSee('اعتمد الفائز');
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('دالّة إحصائياً: يُعتمد الفائز تلقائياً');

        $this->artisan('platform:evaluate-experiments')->assertSuccessful();

        $experiment = $this->experiment->fresh();
        $this->assertSame('ended', $experiment->status);
        $this->assertSame($this->b->id, (int) $experiment->winner_variant_id);
        $this->assertNotNull($experiment->ended_at);
        $this->assertTrue($this->v2->fresh()->is_active);
        $this->assertFalse($this->v1->fresh()->is_active);
        $this->assertStringStartsWith('# موجز الحملة B', $this->tool->fresh()->output_template);

        $row = DB::table('audit_logs')->where('action', 'tool.experiment_ended')->where('subject_id', (string) $this->tool->id)->orderByDesc('id')->first();
        $this->assertNull($row->user_id);
        $context = json_decode($row->context, true);
        $this->assertTrue($context['automatic']);
        $this->assertSame([$this->experiment->id, $this->b->id, 2], [$context['experiment_id'], $context['winner_variant_id'], $context['adopted_version']]);
        $this->assertEqualsWithDelta(-4.0, $context['z'], 0.01);

        // Nothing is left running: a second pass changes nothing.
        $this->artisan('platform:evaluate-experiments')->assertSuccessful();
        $this->assertSame(1, $this->automaticAuditRows());
        $this->actingAs($this->admin)->get(route('admin.tools.show', $this->tool))->assertOk()->assertSee('"automatic":true');
    }

    public function test_command_does_nothing_while_the_difference_is_not_significant(): void
    {
        // A: 30/120 = 25٪ · B: 33/120 = 27.5٪ → |z| ≈ 0.44, p ≈ 0.66.
        $this->expose($this->a, 120, 30);
        $this->expose($this->b, 120, 33);

        $result = app(StudioMetrics::class)->significance($this->experiment);
        $this->assertTrue($result['ready']);
        $this->assertFalse($result['significant']);
        $this->assertGreaterThan(0.05, $result['p']);
        $this->assertNull($result['winner_variant_id']);
        $this->assertSame($this->b->id, $result['leader_variant_id']);

        $this->artisan('platform:evaluate-experiments')->assertSuccessful();

        $this->assertSame('running', $this->experiment->fresh()->status);
        $this->assertNull($this->experiment->fresh()->winner_variant_id);
        $this->assertTrue($this->v1->fresh()->is_active);
        $this->assertSame(0, $this->automaticAuditRows());
        $this->actingAs($this->admin)->get(route('admin.tools.show', $this->tool))->assertOk()->assertSee('الفرق غير دالّ بعد')->assertSee('data-significant="0"', false);
    }

    public function test_a_large_difference_below_the_minimum_sample_is_not_adopted(): void
    {
        // 5/50 against 40/50 is a huge z, but each variant needs MIN_EXPOSURES exposures first.
        $this->expose($this->a, 50, 5);
        $this->expose($this->b, 50, 40);

        $result = app(StudioMetrics::class)->significance($this->experiment);
        $this->assertFalse($result['ready']);
        $this->assertFalse($result['significant']);
        $this->assertLessThan(0.05, $result['p']);
        $this->assertSame(100, StudioMetrics::MIN_EXPOSURES);

        $this->artisan('platform:evaluate-experiments')->assertSuccessful();
        $this->assertSame('running', $this->experiment->fresh()->status);
        $this->assertSame(0, $this->automaticAuditRows());
        $this->actingAs($this->admin)->get(route('admin.tools.show', $this->tool))->assertOk()->assertSee('العينة غير كافية بعد')->assertSee('50 / 50');

        // No exposures at all: no division by zero, nothing adopted.
        DB::table('experiment_exposures')->delete();
        $empty = app(StudioMetrics::class)->significance($this->experiment);
        $this->assertSame([null, null, false], [$empty['z'], $empty['p'], $empty['significant']]);
        $this->artisan('platform:evaluate-experiments')->assertSuccessful();
        $this->assertSame('running', $this->experiment->fresh()->status);
    }

    public function test_manual_adoption_still_works_and_is_audited_as_not_automatic_and_the_command_is_scheduled_hourly(): void
    {
        $this->actingAs($this->admin)->post(route('admin.tools.experiment.end', [$this->tool, $this->experiment]), ['winner' => $this->b->id])->assertRedirect()->assertSessionHas('status');
        $this->assertSame('ended', $this->experiment->fresh()->status);
        $this->assertTrue($this->v2->fresh()->is_active);
        $this->assertStringStartsWith('# موجز الحملة B', $this->tool->fresh()->output_template);
        $row = DB::table('audit_logs')->where('action', 'tool.experiment_ended')->where('subject_id', (string) $this->tool->id)->first();
        $this->assertSame($this->admin->id, (int) $row->user_id);
        $this->assertFalse(json_decode($row->context, true)['automatic']);

        $this->artisan('schedule:list')->assertSuccessful();
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'platform:evaluate-experiments'));
        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }
}
