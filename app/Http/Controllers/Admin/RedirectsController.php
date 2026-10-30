<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\ImportRedirects;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** التحويلات — /admin/redirects (blueprint §6.5): searchable table, add, delete with confirmation, CSV import; every change audited. */
class RedirectsController extends Controller
{
    private const ROLES = ['operations', 'admin', 'super_admin'];

    private const SORTABLE = ['from_path', 'to_path', 'status_code', 'is_active', 'created_at'];

    public function index(Request $request): View
    {
        $this->authorizeRole($request, self::ROLES);
        $q = trim((string) $request->query('q', ''));
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $query = DB::table('redirects');
        if ($q !== '') {
            $query->where(fn ($w) => $w->where('from_path', 'like', "%{$q}%")->orWhere('to_path', 'like', "%{$q}%"));
        }
        $redirects = $query->orderBy($sort, $dir)->orderBy('id')->paginate(50)->withQueryString();

        return view('admin.redirects', [
            'redirects' => $redirects, 'q' => $q, 'sort' => $sort, 'dir' => $dir,
            'stats' => [
                'total' => DB::table('redirects')->count(),
                'gone' => DB::table('redirects')->where('status_code', 410)->count(),
                'inactive' => DB::table('redirects')->where('is_active', false)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeRole($request, self::ROLES);
        $data = $request->validate([
            // Reject protocol-relative targets ("//host") and backslashes/whitespace/control chars
            // (browsers normalise "/\host" to "//host", which would be an open redirect).
            'from_path' => ['required', 'string', 'starts_with:/', 'not_regex:/^\/\//', 'not_regex:/[\\\\\s\x00-\x1f]/', 'max:500', 'unique:redirects,from_path'],
            'to_path' => ['required', 'string', 'starts_with:/', 'not_regex:/^\/\//', 'not_regex:/[\\\\\s\x00-\x1f]/', 'max:500'],
            'status_code' => ['required', 'integer', 'in:301,302,307,308,410'],
        ]);
        abort_if($data['from_path'] === $data['to_path'], 422);
        DB::table('redirects')->insert($data + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($request, 'redirect.created', $data['from_path'], ['to' => $data['to_path'], 'status' => $data['status_code']]);

        return back()->with('status', 'تمت إضافة التحويل.');
    }

    public function destroy(Request $request, int $redirect): RedirectResponse
    {
        $this->authorizeRole($request, self::ROLES);
        $current = DB::table('redirects')->where('id', $redirect)->first();
        abort_unless($current, 404);
        DB::table('redirects')->where('id', $redirect)->delete();
        $this->audit($request, 'redirect.deleted', (string) $redirect, ['from' => $current->from_path, 'to' => $current->to_path]);

        return back()->with('status', 'تم حذف التحويل.');
    }

    /** CSV columns: from_path,to_path,status_code (header row optional). Upserts by from_path with the same rules as store(). */
    public function import(Request $request): RedirectResponse
    {
        $this->authorizeRole($request, self::ROLES);
        $request->validate(['csv' => ['required', 'file', 'max:512', 'mimes:csv,txt']]);

        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        abort_unless($handle, 422);
        $inserted = 0;
        $updated = 0;
        $rejected = [];
        $line = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            $row = array_map(fn ($cell) => trim((string) $cell), $row);
            if ($row === [''] || ($row[0] ?? '') === '') {
                continue;
            }
            if ($line === 1 && strtolower($row[0]) === 'from_path') {
                continue;
            }
            if (count($row) < 2) {
                $rejected[] = $line;

                continue;
            }
            $status = (int) ($row[2] ?? 301) ?: 301;
            $from = ImportRedirects::normalize($row[0]);
            $to = $row[1] === '' && $status === 410 ? '/' : ImportRedirects::normalize($row[1]);
            if (ImportRedirects::validate($from, $to, $status) !== null || mb_strlen($from) > 500 || mb_strlen($to) > 500) {
                $rejected[] = $line;

                continue;
            }
            $exists = DB::table('redirects')->where('from_path', $from)->exists();
            DB::table('redirects')->updateOrInsert(
                ['from_path' => $from],
                ['to_path' => $to, 'status_code' => $status, 'is_active' => true, 'updated_at' => now()] + ($exists ? [] : ['created_at' => now()]),
            );
            $exists ? $updated++ : $inserted++;
        }
        fclose($handle);

        $this->audit($request, 'redirect.imported', $request->file('csv')->getClientOriginalName(), ['inserted' => $inserted, 'updated' => $updated, 'rejected' => $rejected]);
        $message = "استُوردت {$inserted}، حُدّثت {$updated}، رُفضت ".count($rejected).(count($rejected) > 0 ? ' (الأسطر: '.implode('، ', $rejected).')' : '').'.';

        return redirect()->route('admin.redirects.index')->with(count($rejected) > 0 ? 'info' : 'status', $message);
    }

    private function audit(Request $request, string $action, string $subjectId, array $context): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'redirect', 'subject_id' => mb_substr($subjectId, 0, 255),
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'request_id' => $request->attributes->get('request_id'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function authorizeRole(Request $request, array $roles): void
    {
        abort_unless(in_array($request->user()?->platform_role, $roles, true), 403);
    }
}
