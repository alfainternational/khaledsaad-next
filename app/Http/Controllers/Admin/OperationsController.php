<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.operations', [
            'redirects' => DB::table('redirects')->latest()->get(),
            'plans' => DB::table('plans')->orderBy('price')->get(),
            'stats' => [
                'payments' => DB::table('payments')->count(),
                'subscriptions' => DB::table('subscriptions')->where('status', 'active')->count(),
                'failed_runs' => DB::table('tool_runs')->where('status', 'failed')->count(),
            ],
        ]);
    }

    public function storeRedirect(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            // Reject protocol-relative targets ("//host") and backslashes/whitespace/control chars
            // (browsers normalise "/\host" to "//host", which would be an open redirect).
            'from_path' => ['required', 'string', 'starts_with:/', 'not_regex:/^\/\//', 'not_regex:/[\\\\\s\x00-\x1f]/', 'max:500', 'unique:redirects,from_path'],
            'to_path' => ['required', 'string', 'starts_with:/', 'not_regex:/^\/\//', 'not_regex:/[\\\\\s\x00-\x1f]/', 'max:500'],
            'status_code' => ['required', 'integer', 'in:301,302,307,308,410'],
        ]);
        abort_if($data['from_path'] === $data['to_path'], 422);
        DB::table('redirects')->insert($data + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id, 'action' => 'redirect.created', 'subject_type' => 'redirect',
            'subject_id' => $data['from_path'], 'context' => json_encode(['to' => $data['to_path'], 'status' => $data['status_code']]),
            'request_id' => $request->attributes->get('request_id'), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('status', 'تمت إضافة التحويل.');
    }

    public function destroyRedirect(Request $request, int $redirect): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $current = DB::table('redirects')->where('id', $redirect)->first();
        abort_unless($current, 404);
        DB::table('redirects')->where('id', $redirect)->delete();
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id, 'action' => 'redirect.deleted', 'subject_type' => 'redirect',
            'subject_id' => (string) $redirect, 'context' => json_encode(['from' => $current->from_path, 'to' => $current->to_path]),
            'request_id' => $request->attributes->get('request_id'), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('status', 'تم حذف التحويل.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(in_array($request->user()?->platform_role, ['operations', 'admin', 'super_admin'], true), 403);
    }
}
