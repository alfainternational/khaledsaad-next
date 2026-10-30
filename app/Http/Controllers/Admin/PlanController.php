<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** الخطط والدفع — /admin/plans (blueprint §6.5): plans table with inline edit, last payments, active subscriptions. */
class PlanController extends Controller
{
    private const ROLES = ['finance', 'admin', 'super_admin'];

    public function index(Request $request): View
    {
        $this->authorizeRole($request, self::ROLES);

        $plans = DB::table('plans')->orderBy('price')->get()->map(function ($plan) {
            $plan->entitlements_pretty = $plan->entitlements
                ? json_encode(json_decode((string) $plan->entitlements, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : null;

            return $plan;
        });
        $payments = DB::table('payments')
            ->leftJoin('workspaces', 'workspaces.id', '=', 'payments.workspace_id')
            ->select('payments.*', 'workspaces.name as workspace_name')
            ->orderByDesc('payments.id')->limit(50)->get();
        $subscriptions = DB::table('subscriptions')
            ->leftJoin('workspaces', 'workspaces.id', '=', 'subscriptions.workspace_id')
            ->where('subscriptions.status', 'active')
            ->select('subscriptions.*', 'workspaces.name as workspace_name')
            ->orderByDesc('subscriptions.id')->limit(100)->get();

        return view('admin.plans', [
            'plans' => $plans, 'payments' => $payments, 'subscriptions' => $subscriptions,
            'stats' => [
                'payments' => DB::table('payments')->count(),
                'paid' => DB::table('payments')->where('status', 'paid')->count(),
                'active' => DB::table('subscriptions')->where('status', 'active')->count(),
                'revenue' => (int) DB::table('payments')->where('status', 'paid')->sum('amount'),
            ],
        ]);
    }

    public function update(Request $request, string $plan): RedirectResponse
    {
        $this->authorizeRole($request, self::ROLES);
        $data = $request->validate([
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'credits' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        DB::transaction(function () use ($request, $plan, $data): void {
            $current = DB::table('plans')->where('slug', $plan)->lockForUpdate()->first();
            abort_unless($current, 404);
            DB::table('plans')->where('id', $current->id)->update($data + ['updated_at' => now()]);
            $before = ['price' => (int) $current->price, 'credits' => (int) $current->credits, 'is_active' => (bool) $current->is_active];
            DB::table('audit_logs')->insert([
                'user_id' => $request->user()->id,
                'action' => 'plan.price_changed',
                'subject_type' => 'plan',
                'subject_id' => (string) $current->id,
                'context' => json_encode([
                    'slug' => $current->slug,
                    'before' => $before,
                    'after' => $data,
                    'before_hash' => hash('sha256', json_encode(['price' => $current->price, 'credits' => $current->credits])),
                    'after_hash' => hash('sha256', json_encode(['price' => $data['price'], 'credits' => $data['credits']])),
                ]),
                'request_id' => $request->attributes->get('request_id'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('status', 'تم تحديث الخطة وتسجيل التغيير.');
    }

    private function authorizeRole(Request $request, array $roles): void
    {
        abort_unless(in_array($request->user()?->platform_role, $roles, true), 403);
    }
}
