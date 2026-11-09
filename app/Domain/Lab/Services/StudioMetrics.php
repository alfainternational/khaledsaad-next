<?php

namespace App\Domain\Lab\Services;

use App\Domain\Lab\Models\Experiment;
use App\Domain\Lab\Models\Tool;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * معدل الحفظ (blueprint §6.4): saved assets ÷ generated assets, per tool and per template version.
 * Generated = learner_events tool.generated/tool.enhanced (visitors and members alike); saved = tool_outputs of the tool,
 * excluding the seeded illustrative examples and lesson-lab saves.
 */
class StudioMetrics
{
    /** Minimum exposures per variant before an A/B result may be called significant. */
    public const MIN_EXPOSURES = 100;

    /** Two-sided significance level for adopting a winner automatically. */
    public const ALPHA = 0.05;

    /** @return array<int, array{generated:int, saved:int, rate:?int}> keyed by tool id */
    public function saveRates(): array
    {
        $generated = DB::table('learner_events')->whereIn('event', ['tool.generated', 'tool.enhanced'])->whereNotNull('tool_id')->selectRaw('tool_id, count(*) as n')->groupBy('tool_id')->pluck('n', 'tool_id');
        $saved = DB::table('tool_outputs')->whereNotNull('tool_id')->whereNull('lesson_id')->where('payload', 'not like', '%"illustrative":true%')->selectRaw('tool_id, count(*) as n')->groupBy('tool_id')->pluck('n', 'tool_id');
        $rates = [];
        foreach (Tool::query()->pluck('id') as $id) {
            $g = (int) ($generated[$id] ?? 0);
            $s = (int) ($saved[$id] ?? 0);
            $rates[$id] = ['generated' => $g, 'saved' => $s, 'rate' => $g > 0 ? (int) round(min($s, $g) / $g * 100) : null];
        }

        return $rates;
    }

    /** @return array<int, array{generated:int, saved:int, rate:?int}> keyed by tool_version id */
    public function versionRates(Tool $tool): array
    {
        $rates = [];
        foreach ($tool->versions as $version) {
            $g = DB::table('learner_events')->whereIn('event', ['tool.generated', 'tool.enhanced'])->where('tool_id', $tool->id)->where('meta->version_id', $version->id)->count();
            $s = DB::table('tool_outputs')->where('tool_id', $tool->id)->where('tool_version_id', $version->id)->whereNull('lesson_id')->where('payload', 'not like', '%"illustrative":true%')->count();
            $rates[$version->id] = ['generated' => $g, 'saved' => $s, 'rate' => $g > 0 ? (int) round(min($s, $g) / $g * 100) : null];
        }

        return $rates;
    }

    /** Exposure-based rates for a running experiment (converted ÷ exposed per variant). */
    public function experimentRates(Experiment $experiment): array
    {
        $rows = DB::table('experiment_exposures')->where('experiment_id', $experiment->id)->selectRaw('variant_id, count(*) as exposed, sum(case when converted_at is not null then 1 else 0 end) as converted')->groupBy('variant_id')->get()->keyBy('variant_id');
        $rates = [];
        foreach ($experiment->variants as $variant) {
            $exposed = (int) ($rows[$variant->id]->exposed ?? 0);
            $converted = (int) ($rows[$variant->id]->converted ?? 0);
            $rates[$variant->id] = ['exposed' => $exposed, 'converted' => $converted, 'rate' => $exposed > 0 ? (int) round($converted / $exposed * 100) : null];
        }

        return $rates;
    }

    /** Save rate of one tool inside a period (the credit-economy widget, blueprint §6.1 slot 7). */
    public function toolSaveRate(int $toolId, ?CarbonInterface $since = null): array
    {
        $generated = DB::table('learner_events')->whereIn('event', ['tool.generated', 'tool.enhanced'])->where('tool_id', $toolId)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))->count();
        $saved = DB::table('tool_outputs')->where('tool_id', $toolId)->whereNull('lesson_id')->where('payload', 'not like', '%"illustrative":true%')
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))->count();

        return ['generated' => $generated, 'saved' => $saved, 'rate' => $generated > 0 ? (int) round(min($saved, $generated) / $generated * 100) : null];
    }

    /**
     * Statistical significance of a running A/B (blueprint §6.1 slot 5): a pooled two-proportion z-test on the save rate
     * (converted ÷ exposed) of the two variants. It is only "ready" once every variant has MIN_EXPOSURES exposures, and
     * "significant" when the two-sided p-value is below ALPHA; the winner is then the variant with the higher rate.
     *
     * @return array{ready:bool, significant:bool, z:?float, p:?float, min_sample:int, samples:array<int,int>, rates:array<int,array>, winner_variant_id:?int, leader_variant_id:?int}
     */
    public function significance(Experiment $experiment): array
    {
        $rates = $this->experimentRates($experiment);
        $samples = array_map(fn (array $r) => $r['exposed'], $rates);
        $result = ['ready' => false, 'significant' => false, 'z' => null, 'p' => null, 'min_sample' => self::MIN_EXPOSURES, 'samples' => $samples, 'rates' => $rates, 'winner_variant_id' => null, 'leader_variant_id' => null];
        if (count($rates) !== 2) {
            return $result;
        }
        [$idA, $idB] = array_keys($rates);
        [$a, $b] = array_values($rates);
        if ($a['exposed'] < 1 || $b['exposed'] < 1) {
            return $result;
        }
        $pA = $a['converted'] / $a['exposed'];
        $pB = $b['converted'] / $b['exposed'];
        $result['leader_variant_id'] = $pA === $pB ? null : ($pA > $pB ? $idA : $idB);
        $result['ready'] = min($a['exposed'], $b['exposed']) >= self::MIN_EXPOSURES;
        $pooled = ($a['converted'] + $b['converted']) / ($a['exposed'] + $b['exposed']);
        $se = sqrt($pooled * (1 - $pooled) * (1 / $a['exposed'] + 1 / $b['exposed']));
        if ($se <= 0.0) {
            return $result;
        }
        $z = ($pA - $pB) / $se;
        $p = self::erfc(abs($z) / M_SQRT2);
        $result['z'] = round($z, 3);
        $result['p'] = round($p, 4);
        $result['significant'] = $result['ready'] && $p < self::ALPHA;
        $result['winner_variant_id'] = $result['significant'] ? $result['leader_variant_id'] : null;

        return $result;
    }

    /** Complementary error function (Abramowitz–Stegun 7.1.26, |error| < 1.5e-7): two-sided normal p-value = erfc(|z| / √2). */
    private static function erfc(float $x): float
    {
        $t = 1 / (1 + 0.3275911 * $x);
        $poly = $t * (0.254829592 + $t * (-0.284496736 + $t * (1.421413741 + $t * (-1.453152027 + $t * 1.061405429))));

        return max(0.0, min(1.0, $poly * exp(-$x * $x)));
    }
}
