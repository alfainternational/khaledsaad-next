<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Insights\Services\ControlRoomMetrics;
use App\Domain\Insights\Services\QualitySignalCalculator;
use App\Http\Controllers\Controller;
use App\Support\Viewer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** غرفة التحكم — /admin (blueprint §6.1). */
class ControlRoomController extends Controller
{
    public function index(Request $request, ControlRoomMetrics $metrics, QualitySignalCalculator $signals)
    {
        $this->authorizeStaff($request);
        $days = (int) $request->query('days', 7) === 30 ? 30 : 7;
        $computedAt = (int) cache()->get('platform:quality_signals_computed_at', 0);
        if ($computedAt < now()->subHour()->timestamp) {
            $signals->compute();
        }

        return view('admin.index', [
            'days' => $days,
            'journey' => $metrics->journey($days),
            'signals' => $metrics->signals(),
            'pipeline' => $metrics->pipeline(),
            'success' => $metrics->successBox(),
            'economy' => $metrics->creditEconomy($days),
            'operations' => $metrics->operations(),
            'palette' => $metrics->palette(),
            'previews' => Viewer::PREVIEWS,
            'computedAt' => cache()->get('platform:quality_signals_computed_at'),
            'experiments' => $this->runningExperiments(),
        ]);
    }

    public function recompute(Request $request, QualitySignalCalculator $signals)
    {
        $this->authorizeStaff($request);
        $n = $signals->compute();

        return back()->with('status', "أُعيد حساب إشارات الجودة: {$n} إشارة مفتوحة.");
    }

    public function signalStatus(Request $request, int $signal)
    {
        $this->authorizeStaff($request);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'handled', 'ignored'])]]);
        \App\Domain\Insights\Models\QualitySignal::query()->whereKey($signal)->update(['status' => $data['status'], 'updated_at' => now()]);

        return back()->with('status', 'حُدّثت حالة الإشارة.');
    }

    /** «اعرض المنصة كـ»: زائر / عضو / مبتدئ — real preview without logging out. */
    public function preview(Request $request, string $as)
    {
        $this->authorizeStaff($request);
        if ($as === 'off') {
            session()->forget('admin_preview_as');

            return redirect()->route('admin.index')->with('status', 'انتهت المعاينة.');
        }
        abort_unless(isset(Viewer::PREVIEWS[$as]), 404);
        session(['admin_preview_as' => $as]);

        return redirect()->route('home');
    }

    /** Running A/B experiments (stage 2: template versions of tools, measured by save rate). */
    private function runningExperiments(): array
    {
        $metrics = app(\App\Domain\Lab\Services\StudioMetrics::class);
        $out = [];
        foreach (\App\Domain\Lab\Models\Experiment::query()->where('status', 'running')->with('variants')->get() as $exp) {
            $tool = \App\Domain\Lab\Models\Tool::query()->find($exp->subject_id);
            if (! $tool) {
                continue;
            }
            $rates = $metrics->experimentRates($exp);
            $versions = $tool->versions()->get()->keyBy('id');
            $out[] = ['name' => $exp->name, 'tool' => $tool, 'started' => $exp->started_at?->diffForHumans() ?? '—', 'variants' => $exp->variants->map(fn ($v) => ['label' => $v->label, 'weight' => $v->weight, 'version' => $versions[$v->tool_version_id]->version ?? '—'] + ($rates[$v->id] ?? ['exposed' => 0, 'converted' => 0, 'rate' => null]))->all()];
        }

        return $out;
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless(in_array($request->user()?->platform_role, Viewer::STAFF_ROLES, true), 403);
    }
}
