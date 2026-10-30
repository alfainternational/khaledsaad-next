<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** العمليات والصحة — /admin/operations (blueprint §6.5): health cards and links to the standard tables. */
class OperationsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeRole($request, ['operations', 'admin', 'super_admin']);
        // Same heartbeat keys and 600s threshold as /health.json.
        $age = fn (string $key) => ($ts = (int) cache()->get($key, 0)) > 0 ? now()->timestamp - $ts : null;
        $scheduler = $age('platform:scheduler_heartbeat');
        $worker = $age('platform:worker_heartbeat');

        return view('admin.operations', [
            'health' => [
                'database' => rescue(fn () => DB::select('select 1') !== [], false),
                'scheduler_age' => $scheduler, 'scheduler_ok' => $scheduler !== null && $scheduler < 600,
                'worker_age' => $worker, 'worker_ok' => $worker !== null && $worker < 600,
                'queue_pending' => rescue(fn () => DB::table('jobs')->count(), 0),
                'queue_failed' => rescue(fn () => DB::table('failed_jobs')->count(), 0),
                'backup' => (string) config('platform.backup_marker') !== '' && is_file((string) config('platform.backup_marker')) ? date('Y/m/d H:i', filemtime((string) config('platform.backup_marker'))) : null,
                'paypal' => filled(config('services.paypal.client_id')), 'ai' => filled(config('services.openai.key')),
            ],
            'stats' => [
                'payments' => DB::table('payments')->count(),
                'subscriptions' => DB::table('subscriptions')->where('status', 'active')->count(),
                'failed_runs' => DB::table('tool_runs')->where('status', 'failed')->count(),
                'redirects' => DB::table('redirects')->count(),
                'users' => DB::table('users')->count(),
                'audit' => DB::table('audit_logs')->count(),
            ],
            'recentAudit' => DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
                ->select('audit_logs.action', 'audit_logs.subject_type', 'audit_logs.subject_id', 'audit_logs.created_at', 'users.name as user_name')
                ->orderByDesc('audit_logs.id')->limit(10)->get(),
        ]);
    }

    private function authorizeRole(Request $request, array $roles): void
    {
        abort_unless(in_array($request->user()?->platform_role, $roles, true), 403);
    }
}
