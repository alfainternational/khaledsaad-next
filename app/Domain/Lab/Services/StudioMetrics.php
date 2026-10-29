<?php

namespace App\Domain\Lab\Services;

use App\Domain\Lab\Models\Experiment;
use App\Domain\Lab\Models\Tool;
use Illuminate\Support\Facades\DB;

/**
 * معدل الحفظ (blueprint §6.4): saved assets ÷ generated assets, per tool and per template version.
 * Generated = learner_events tool.generated/tool.enhanced (visitors and members alike); saved = tool_outputs of the tool,
 * excluding the seeded illustrative examples and lesson-lab saves.
 */
class StudioMetrics
{
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
}
