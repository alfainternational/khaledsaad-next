<?php

namespace App\Domain\Lab\Services;

use App\Domain\Insights\Services\EventRecorder;
use App\Domain\Lab\Models\Experiment;
use App\Domain\Lab\Models\ExperimentExposure;
use App\Domain\Lab\Models\ExperimentVariant;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolVersion;
use Illuminate\Http\Request;

/**
 * A/B between two template versions of a tool (blueprint §6.4): a learner is assigned a variant once per session (sticky),
 * every generation records an exposure and every save converts it, so the studio can compare save rates.
 */
class ExperimentPicker
{
    public function running(Tool $tool): ?Experiment
    {
        return Experiment::query()->where('subject_type', 'tool')->where('subject_id', $tool->id)->where('status', 'running')->with('variants')->first();
    }

    /** The template version to generate with (experiment variant if one runs, else the active version). */
    public function versionFor(Tool $tool, Request $request): ?ToolVersion
    {
        $experiment = $this->running($tool);
        if (! $experiment || $experiment->variants->count() < 2) {
            return $tool->activeVersion();
        }
        $key = 'lab.exp.'.$experiment->id;
        $variantId = (int) $request->session()->get($key, 0);
        $variant = $experiment->variants->firstWhere('id', $variantId);
        if (! $variant) {
            $variant = $this->weighted($experiment->variants->all());
            $request->session()->put($key, $variant->id);
        }

        return ToolVersion::query()->find($variant->tool_version_id) ?? $tool->activeVersion();
    }

    /** Record an exposure for the assigned variant; the exposure id is kept in the session so the next save converts exactly it. */
    public function expose(Tool $tool, Request $request): void
    {
        $experiment = $this->running($tool);
        if (! $experiment) {
            return;
        }
        $variantId = (int) $request->session()->get('lab.exp.'.$experiment->id, 0);
        if ($variantId === 0) {
            return;
        }
        $exposure = ExperimentExposure::query()->create([
            'experiment_id' => $experiment->id, 'variant_id' => $variantId, 'user_id' => $request->user()?->id,
            'session_token' => EventRecorder::sessionToken($request), 'created_at' => now(),
        ]);
        $request->session()->put('lab.exposure.'.$experiment->id, $exposure->id);
    }

    public function convert(Tool $tool, Request $request): void
    {
        $experiment = $this->running($tool);
        if (! $experiment) {
            return;
        }
        $exposureId = (int) $request->session()->get('lab.exposure.'.$experiment->id, 0);
        $query = ExperimentExposure::query()->where('experiment_id', $experiment->id)->whereNull('converted_at');
        if ($exposureId > 0) {
            $query->whereKey($exposureId);
        } else {
            $query->where('session_token', EventRecorder::sessionToken($request))->orderByDesc('id')->limit(1);
        }
        $query->update(['converted_at' => now()]);
    }

    /** @param ExperimentVariant[] $variants */
    private function weighted(array $variants): ExperimentVariant
    {
        $total = max(1, array_sum(array_map(fn ($v) => (int) $v->weight, $variants)));
        $roll = random_int(1, $total);
        $acc = 0;
        foreach ($variants as $variant) {
            $acc += (int) $variant->weight;
            if ($roll <= $acc) {
                return $variant;
            }
        }

        return $variants[0];
    }
}
