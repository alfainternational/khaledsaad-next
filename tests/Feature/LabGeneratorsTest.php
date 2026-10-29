<?php

namespace Tests\Feature;

use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Services\LabCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** المرحلة 2 — المختبر /tools وصفحة الأداة (§4.6، §4.7): كل مولّد يعمل للزائر بلا AI وبلا تسجيل. */
class LabGeneratorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Http::fake();
    }

    public function test_lab_index_lists_fifteen_working_tools_with_filters_and_no_coming_soon(): void
    {
        $page = $this->get('/tools')->assertOk()->assertSee('من السؤال إلى أصل قابل للاستخدام')->assertDontSee('قريباً');
        foreach (['الكل', 'التخطيط', 'الجمهور', 'المحتوى', 'الإعلانات', 'القياس', 'الذكاء الاصطناعي'] as $filter) {
            $page->assertSee($filter);
        }
        foreach (array_keys(LabCatalog::generators()) as $slug) {
            $page->assertSee(route('tools.show', $slug), false);
        }
        foreach (array_keys(LabCatalog::calculators()) as $calc) {
            $page->assertSee(route('tools.calculator', $calc), false);
        }
        $page->assertSee(route('tools.simulator'), false)->assertSee('بلا ذكاء اصطناعي يعمل بالقوالب')->assertSee('الرصيد: <b>0</b> بلا AI', false);
        $this->assertSame(15, Tool::query()->where('is_published', true)->count());
        $this->assertSame(10, Tool::query()->where('kind', 'generator')->where('is_published', true)->count());
    }

    public function test_every_generator_produces_a_base_asset_and_six_checks_for_a_visitor_without_ai(): void
    {
        foreach (LabCatalog::generators() as $slug => $definition) {
            $tool = Tool::query()->where('slug', $slug)->firstOrFail();
            $this->get("/tools/{$slug}")->assertOk()->assertSee($tool->name)->assertSee('id="tool-form"', false)->assertSee('مثال جاهز')->assertSee('ابدأ من الصفر')->assertSee('يُغذّى من')->assertSee('هذه الأداة تُنتج');
            $this->post("/tools/{$slug}/generate", $definition['example_input'])->assertRedirect(route('tools.show', $slug).'#asset');
            $result = session('lab.result.'.$slug);
            $this->assertNotEmpty($result['markdown'], "{$slug} produced no markdown");
            $this->assertStringStartsWith('# ', $result['markdown'], $slug);
            $this->assertStringNotContainsString('—  |', $result['markdown']);
            $this->assertCount(6, $result['quality']['checks'], $slug);
            $this->assertFalse($result['enhanced']);
            $page = $this->get("/tools/{$slug}")->assertOk()->assertSee('id="asset"', false)->assertSee('فحص قابلية التنفيذ')->assertSee('حفظ في مشروعي (بحساب)')->assertSee('مشاركة برابط (بحساب)');
            $this->assertSame(6, substr_count($page->getContent(), 'data-check="'), $slug);
        }
        Http::assertNothingSent();
        $this->assertDatabaseCount('tool_runs', 30); // only the seeded illustrative examples: visitors create no runs
    }

    public function test_missing_required_fields_send_the_visitor_back_to_the_inputs(): void
    {
        $this->post('/tools/customer-persona/generate', ['offer' => 'سيروم'])->assertRedirect(route('tools.show', 'customer-persona').'#inputs')->assertSessionHasErrors('lab');
        $this->assertNull(session('lab.result.customer-persona')['markdown'] ?? null);
    }

    public function test_the_chain_feeds_the_next_tool_from_the_asset_generated_in_the_session(): void
    {
        $persona = LabCatalog::find('customer-persona')['example_input'];
        $this->post('/tools/customer-persona/generate', $persona)->assertRedirect();
        $page = $this->get('/tools/value-proposition')->assertOk();
        $page->assertSee('من شخصية العميل')->assertSee('value="'.$persona['audience'].'"', false)->assertSee($persona['pain']);
        $this->assertStringContainsString('data-feed="customer_persona" data-ok="1"', $page->getContent());
        $this->assertStringContainsString('data-plan-item="customer_persona" data-state="generated"', $page->getContent());

        $this->post('/tools/value-proposition/generate', LabCatalog::find('value-proposition')['example_input'])->assertRedirect();
        $page = $this->get('/tools/value-proposition')->assertOk()->assertSee('الخطوة التالية في السلسلة');
        $this->assertStringContainsString($persona['audience'], session('lab.result.value-proposition')['markdown']);
    }

    public function test_example_and_fresh_links_fill_or_clear_the_form(): void
    {
        $example = LabCatalog::find('campaign-brief-builder')['example_input'];
        $this->get('/tools/campaign-brief-builder?example=1')->assertOk()->assertSee('value="'.$example['goal'].'"', false);
        $this->get('/tools/campaign-brief-builder?fresh=1')->assertOk()->assertDontSee('value="'.$example['goal'].'"', false);
    }

    public function test_calculator_and_simulator_slugs_redirect_from_the_generic_tool_route(): void
    {
        $this->get('/tools/cac')->assertRedirect(route('tools.calculator', 'cac'));
        $this->get('/tools/ad-campaign-simulator')->assertRedirect(route('tools.simulator'));
        $this->get('/tools/lesson-lab')->assertNotFound();
    }
}
