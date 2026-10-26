<?php

namespace App\Http\Controllers\App;

use App\Domain\Commerce\Services\CreditLedger;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request, CreditLedger $ledger): View
    {
        $workspaces = $request->user()->workspaces()->get()->map(function ($workspace) use ($ledger) {
            $workspace->balance = $ledger->balance($workspace);
            $workspace->subscription = DB::table('subscriptions')->where('workspace_id', $workspace->id)->first();

            return $workspace;
        });

        return view('app.billing', ['workspaces' => $workspaces, 'plans' => DB::table('plans')->where('is_active', true)->get()]);
    }

    public function storeOrder(Request $request): RedirectResponse
    {
        $data = $request->validate(['workspace_id' => ['required', 'integer'], 'plan_id' => ['required', 'integer']]);
        $workspace = $this->memberWorkspace($request, $data['workspace_id']);
        abort_unless($workspace->pivot->role === 'owner', 403);
        $plan = DB::table('plans')->where('id', $data['plan_id'])->where('is_active', true)->first();
        abort_unless($plan, 404);
        $orderId = (string) Str::uuid();
        DB::table('orders')->insert([
            'public_id' => $orderId, 'workspace_id' => $workspace->id, 'plan_id' => $plan->id,
            'amount' => $plan->price, 'currency' => $plan->currency, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('status', "تم إنشاء طلب آمن رقم {$orderId}. اربطه ببوابة الدفع المهيأة.");
    }
}
