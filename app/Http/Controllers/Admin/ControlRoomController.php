<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Insights\Models\ContentReport;
use App\Domain\Insights\Models\LearnerEvent;
use App\Domain\Insights\Models\LearnerNudge;
use App\Domain\Insights\Models\QualitySignal;
use App\Domain\Insights\Services\ControlRoomMetrics;
use App\Domain\Insights\Services\QualitySignalCalculator;
use App\Http\Controllers\Controller;
use App\Mail\AssetWaitingReminder;
use App\Models\User;
use App\Support\Viewer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
        $this->audit($request, 'quality_signal.recomputed', 'quality_signal', null, ['open' => $n]);

        return back()->with('status', "أُعيد حساب إشارات الجودة: {$n} إشارة مفتوحة.");
    }

    /** عُولج / تجاهل / إعادة فتح. A dismissed (ignored) signal is not reopened by the hourly computation. */
    public function signalStatus(Request $request, int $signal)
    {
        $this->authorizeStaff($request);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'handled', 'ignored'])]]);
        $row = QualitySignal::query()->findOrFail($signal);
        $before = $row->status;
        if ($before !== $data['status']) {
            $row->update(['status' => $data['status']]);
            $this->audit($request, 'quality_signal.status_changed', 'quality_signal', (string) $row->id, [
                'before' => $before, 'after' => $data['status'], 'type' => $row->type, 'entity_type' => $row->entity_type, 'entity_id' => $row->entity_id, 'part' => $row->part,
            ]);
        }

        return back()->with('status', $data['status'] === 'ignored' ? 'تُجوهلت الإشارة، ولن يعيد الحساب الآلي فتحها.' : 'حُدّثت حالة الإشارة.');
    }

    /** بلاغات الدروس والمواد (§6.1 slot 6): close a report as resolved or dismissed; every change is audited. */
    public function reportStatus(Request $request, ContentReport $report)
    {
        $this->authorizeStaff($request);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ContentReport::CLOSED_STATUSES))]]);
        $before = $report->status;
        if ($before !== $data['status']) {
            $report->update(['status' => $data['status']]);
            $this->audit($request, 'report.'.$data['status'], 'content_report', (string) $report->id, [
                'before' => $before, 'after' => $data['status'], 'lesson_id' => $report->lesson_id, 'content_item_id' => $report->content_item_id, 'part' => $report->part, 'reason' => $report->reason,
            ]);
        }

        return back()->with('status', $data['status'] === 'resolved' ? 'أُغلق البلاغ كمُعالَج.' : 'أُغلق البلاغ بالتجاهل.');
    }

    /**
     * «أصلك ينتظرك» (§6.1 slot 6): a queued email to a learner who stopped inside a lesson, recorded in learner_nudges.
     * The same learner is not reminded about the same lesson twice within LearnerNudge::COOLDOWN_DAYS.
     */
    public function nudge(Request $request)
    {
        $this->authorizeStaff($request);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id'], 'lesson_id' => ['required', 'integer', 'exists:lessons,id']]);
        $learner = User::query()->findOrFail($data['user_id']);
        if (! LearnerEvent::query()->where('user_id', $learner->id)->where('lesson_id', $data['lesson_id'])->exists()) {
            return back()->withErrors(['nudge' => 'هذا المتعلم لم يبدأ هذا الدرس، فلا تذكير يُرسل عنه.']);
        }

        // The cooldown check and the insert share one locked transaction, so two simultaneous clicks cannot both pass it.
        $nudge = DB::transaction(function () use ($request, $learner, $data): ?LearnerNudge {
            User::query()->whereKey($learner->id)->lockForUpdate()->first();
            $recent = LearnerNudge::query()->where('user_id', $learner->id)->where('lesson_id', $data['lesson_id'])->where('status', '!=', 'failed')
                ->where('created_at', '>=', now()->subDays(LearnerNudge::COOLDOWN_DAYS))->exists();

            return $recent ? null : LearnerNudge::query()->create([
                'user_id' => $learner->id, 'lesson_id' => $data['lesson_id'], 'sent_by' => $request->user()->id, 'kind' => 'asset_waiting', 'channel' => 'email', 'status' => 'queued',
            ]);
        });
        if (! $nudge) {
            return back()->withErrors(['nudge' => 'أُرسل تذكير لـ«'.$learner->name.'» عن هذا الدرس خلال آخر '.LearnerNudge::COOLDOWN_DAYS.' أيام. انتظر قبل تذكيره مجدداً.']);
        }

        Mail::to($learner)->queue(new AssetWaitingReminder($nudge));
        $this->audit($request, 'learner.nudged', 'user', (string) $learner->id, ['nudge_id' => $nudge->id, 'lesson_id' => $nudge->lesson_id, 'kind' => $nudge->kind]);

        return back()->with('status', 'أُضيف تذكير «أصلك ينتظرك» إلى طابور البريد لـ«'.$learner->name.'».');
    }

    /** «اعرض المنصة كـ»: زائر / عضو / مبتدئ — real preview without logging out. */
    public function preview(Request $request, string $as)
    {
        $this->authorizeStaff($request);
        if ($as === 'off') {
            $was = session()->pull('admin_preview_as');
            if (is_string($was)) {
                $this->audit($request, 'admin.preview_ended', 'preview', $was, []);
            }

            return redirect()->route('admin.index')->with('status', 'انتهت المعاينة.');
        }
        abort_unless(isset(Viewer::PREVIEWS[$as]), 404);
        session(['admin_preview_as' => $as]);
        $this->audit($request, 'admin.preview_started', 'preview', $as, []);

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
            $out[] = ['name' => $exp->name, 'tool' => $tool, 'significance' => $metrics->significance($exp),'started' => $exp->started_at?->diffForHumans() ?? '—', 'variants' => $exp->variants->map(fn ($v) => ['label' => $v->label, 'weight' => $v->weight, 'version' => $versions[$v->tool_version_id]->version ?? '—'] + ($rates[$v->id] ?? ['exposed' => 0, 'converted' => 0, 'rate' => null]))->all()];
        }

        return $out;
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless(in_array($request->user()?->platform_role, Viewer::STAFF_ROLES, true), 403);
    }

    private function audit(Request $request, string $action, string $subjectType, ?string $subjectId, array $context): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id, 'action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE), 'request_id' => $request->attributes->get('request_id'), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
