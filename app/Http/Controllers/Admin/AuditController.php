<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** سجل التدقيق — /admin/audit (blueprint §6.5): read-only, filterable by action prefix, user and date range. */
class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeRole($request, ['operations', 'admin', 'super_admin']);
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:120'],
            'user' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $action = trim((string) ($filters['action'] ?? ''));
        $user = trim((string) ($filters['user'] ?? ''));
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $query = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->select('audit_logs.*', 'users.name as user_name', 'users.email as user_email');
        if ($action !== '') {
            // Exact match or prefix ("redirect." lists every redirect action). Escape LIKE wildcards in the input.
            $query->where('audit_logs.action', 'like', addcslashes($action, '%_\\').'%');
        }
        if ($user !== '') {
            ctype_digit($user) ? $query->where('audit_logs.user_id', (int) $user) : $query->where('users.email', $user);
        }
        if ($from) {
            $query->where('audit_logs.created_at', '>=', $from.' 00:00:00');
        }
        if ($to) {
            $query->where('audit_logs.created_at', '<=', $to.' 23:59:59');
        }
        $rows = $query->orderByDesc('audit_logs.id')->paginate(50)->withQueryString();
        $rows->getCollection()->transform(function ($row) {
            $decoded = $row->context ? json_decode((string) $row->context, true) : null;
            $row->context_pretty = $decoded === null ? (string) $row->context : json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $row;
        });

        return view('admin.audit', [
            'rows' => $rows, 'action' => $action, 'user' => $user, 'from' => $from, 'to' => $to,
            'actions' => DB::table('audit_logs')->select('action')->distinct()->orderBy('action')->pluck('action'),
            'total' => DB::table('audit_logs')->count(),
        ]);
    }

    private function authorizeRole(Request $request, array $roles): void
    {
        abort_unless(in_array($request->user()?->platform_role, $roles, true), 403);
    }
}
