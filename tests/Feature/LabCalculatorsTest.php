<?php

namespace Tests\Feature;

use App\Domain\Lab\Services\Calculators;
use App\Domain\Lab\Services\CampaignSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** الحاسبات (§4.8) والمحاكي (§4.6 رقم 4): قيم معروفة، مجانية للجميع، نتيجة فورية بلا إرسال (JS) ونفس القواعد على الخادم. */
class LabCalculatorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_cac_calculator_renders_known_values_with_meaning_and_benchmark(): void
    {
        $this->get('/tools/calculators/cac?spend=3000&customers=60')->assertOk()->assertSee('50.0 ريال')->assertSee('ماذا يعني الرقم')->assertSee('المعيار المرجعي')->assertSee('نسخ النتيجة')->assertSee('data-calc-input="spend"', false)->assertSee('value="3000"', false);
        $this->assertSame(50.0, Calculators::cac(['spend' => 3000, 'customers' => 60])['values']['cac']);
        $this->assertNull(Calculators::cac(['spend' => 3000, 'customers' => 0])['values']['cac']);
    }

    public function test_ltv_roas_and_conversion_calculators_match_the_declared_formulas(): void
    {
        $ltv = Calculators::ltv(['avg_order' => 180, 'orders_per_year' => 4, 'years' => 2, 'margin' => 40, 'cac' => 50]);
        $this->assertEqualsWithDelta(576.0, $ltv['values']['ltv'], 0.001);
        $this->assertEqualsWithDelta(11.52, $ltv['values']['ratio'], 0.001);
        $this->get('/tools/calculators/ltv?avg_order=180&orders_per_year=4&years=2&margin=40&cac=50')->assertOk()->assertSee('576 ريال')->assertSee('11.5 : 1')->assertSee('نسبة صحية');

        $roas = Calculators::roas(['revenue' => 9000, 'spend' => 3000, 'margin' => 40]);
        $this->assertEqualsWithDelta(3.0, $roas['values']['roas'], 0.001);
        $this->assertEqualsWithDelta(2.5, $roas['values']['break_even'], 0.001);
        $this->get('/tools/calculators/roas?revenue=9000&spend=3000&margin=40')->assertOk()->assertSee('3.00×')->assertSee('2.50×');

        $conv = Calculators::conversion(['visitors' => 2000, 'conversions' => 40, 'lift' => 20]);
        $this->assertEqualsWithDelta(0.02, $conv['values']['rate'], 0.00001);
        $this->assertSame(19600, $conv['values']['sample']);
        $this->get('/tools/calculators/conversion-sample?visitors=2000&conversions=40&lift=20')->assertOk()->assertSee('2.00٪')->assertSee('19,600 زيارة');

        $this->get('/tools/calculators/unknown')->assertNotFound();
    }

    public function test_simulator_applies_declared_benchmarks_and_raises_rule_based_risks(): void
    {
        $result = CampaignSimulator::simulate(['budget' => 3000, 'audience_size' => 150000, 'channel' => 'instagram', 'cvr' => 3]);
        $this->assertEqualsWithDelta(120000, $result['values']['impressions'], 0.01);
        $this->assertEqualsWithDelta(1200, $result['values']['clicks'], 0.01);
        $this->assertEqualsWithDelta(36, $result['values']['leads'], 0.01);
        $this->assertEqualsWithDelta(83.33, $result['values']['cpl'], 0.01);
        $this->assertTrue($result['paid']);
        $levels = array_column($result['risks'], 0);
        $this->assertContains('متوسط', $levels); // CPL above the channel benchmark (40)
        $this->assertNotContains('عالٍ', $levels); // 100 SAR/day, frequency 1.8

        $tiny = CampaignSimulator::simulate(['budget' => 300, 'audience_size' => 2000, 'channel' => 'snapchat', 'cvr' => 2]);
        $this->assertContains('عالٍ', array_column($tiny['risks'], 0));
        $this->assertStringContainsString('أقل من 30 ريالاً', implode(' ', array_column($tiny['risks'], 1)));

        $this->get('/tools/simulator?budget=3000&audience_size=150000&channel=instagram&cvr=3')->assertOk()->assertSee('120,000')->assertSee('36.0')->assertSee('القواعد والمعايير المُعلنة')->assertSee('WordStream')->assertSee('data-sim-input="budget"', false);
        $this->get('/tools/simulator')->assertOk()->assertSee('محاكي الحملة الإعلانية');
    }
}
