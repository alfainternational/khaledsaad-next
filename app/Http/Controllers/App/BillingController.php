<?php

namespace App\Http\Controllers\App;

use App\Domain\Commerce\Services\CreditLedger;
use App\Domain\Commerce\Services\FulfillOrder;
use App\Domain\Commerce\Services\PayPalClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class BillingController extends Controller
{
    public function index(Request $request, CreditLedger $ledger, PayPalClient $paypal): View
    {
        $workspaces = $request->user()->workspaces()->get();
        $ids = $workspaces->pluck('id');
        $balances = DB::table('credit_entries')->whereIn('workspace_id', $ids)->groupBy('workspace_id')->selectRaw('workspace_id, sum(amount) as balance')->pluck('balance', 'workspace_id');
        $subscriptions = DB::table('subscriptions')->whereIn('workspace_id', $ids)->get()->keyBy('workspace_id');
        $workspaces->each(function ($workspace) use ($balances, $subscriptions): void {
            $workspace->balance = (int) ($balances[$workspace->id] ?? 0);
            $workspace->subscription = $subscriptions[$workspace->id] ?? null;
        });

        return view('app.billing', [
            'workspaces' => $workspaces,
            'plans' => DB::table('plans')->where('is_active', true)->get(),
            'paymentsEnabled' => $paypal->isConfigured(),
        ]);
    }

    /** Create (or reuse) a pending order and send the owner to PayPal approval. */
    public function storeOrder(Request $request, PayPalClient $paypal): RedirectResponse
    {
        $data = $request->validate(['workspace_id' => ['required', 'integer'], 'plan_id' => ['required', 'integer']]);
        $workspace = $this->memberWorkspace($request, $data['workspace_id']);
        abort_unless($workspace->pivot->role === 'owner', 403);
        $plan = DB::table('plans')->where('id', $data['plan_id'])->where('is_active', true)->first();
        abort_unless($plan, 404);
        if (! $paypal->isConfigured()) {
            return back()->withErrors(['payment' => 'الدفع عبر PayPal غير مفعّل حالياً. تواصل مع الدعم.']);
        }

        $order = DB::table('orders')
            ->where('workspace_id', $workspace->id)->where('plan_id', $plan->id)->where('status', 'pending')
            ->where('amount', $plan->price)->where('currency', $plan->currency)
            ->first();
        if (! $order) {
            $publicId = (string) Str::uuid();
            DB::table('orders')->insert([
                'public_id' => $publicId, 'workspace_id' => $workspace->id, 'plan_id' => $plan->id,
                'amount' => $plan->price, 'currency' => $plan->currency, 'status' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $order = DB::table('orders')->where('public_id', $publicId)->first();
        }

        try {
            $created = $paypal->createOrder(
                $order->public_id,
                (int) $order->amount,
                (string) $order->currency,
                'أثر — '.$plan->name,
                route('app.billing.paypal.return'),
                route('app.billing.paypal.cancel'),
            );
        } catch (RequestException|RuntimeException $e) {
            Log::error('paypal.create_order.failed', ['order' => $order->public_id, 'error' => $e->getMessage()]);

            return back()->withErrors(['payment' => 'تعذر بدء الدفع عبر PayPal الآن. حاول بعد قليل.']);
        }

        DB::table('orders')->where('id', $order->id)->update(['provider_checkout_id' => $created['id'], 'updated_at' => now()]);

        return redirect()->away($created['approve_url']);
    }

    /** PayPal sends the buyer back here with ?token=<paypal order id> after approval. */
    public function paypalReturn(Request $request, PayPalClient $paypal, FulfillOrder $fulfill): RedirectResponse
    {
        $paypalOrderId = (string) $request->query('token', '');
        $order = $this->ownOrder($request, $paypalOrderId);

        if ($order->status === 'paid') {
            return redirect()->route('app.billing')->with('status', 'تم تفعيل اشتراكك مسبقاً.');
        }

        try {
            $captured = $paypal->captureOrder($paypalOrderId);
            $capture = PayPalClient::completedCapture($captured);
            if (! $capture) {
                return redirect()->route('app.billing')->withErrors(['payment' => 'لم تكتمل الدفعة بعد. سنفعّل الاشتراك تلقائياً عند تأكيد PayPal.']);
            }
            $fulfill->handle($order->public_id, $paypalOrderId, $capture, $captured, 'return');
        } catch (RequestException $e) {
            // ORDER_ALREADY_CAPTURED: the webhook got there first; the subscription is (or will be) active.
            if (str_contains((string) $e->response?->body(), 'ORDER_ALREADY_CAPTURED')) {
                return redirect()->route('app.billing')->with('status', 'تم تأكيد الدفعة. سيظهر الاشتراك خلال لحظات.');
            }
            Log::error('paypal.capture.failed', ['order' => $order->public_id, 'error' => $e->getMessage()]);

            return redirect()->route('app.billing')->withErrors(['payment' => 'تعذر تأكيد الدفعة مع PayPal. إن خُصم المبلغ فسيُفعّل الاشتراك تلقائياً أو تواصل مع الدعم.']);
        } catch (InvalidArgumentException $e) {
            Log::warning('paypal.capture.mismatch', ['order' => $order->public_id, 'reason' => $e->getMessage()]);

            return redirect()->route('app.billing')->withErrors(['payment' => 'الدفعة لا تطابق الطلب. تواصل مع الدعم مع رقم الطلب '.$order->public_id]);
        }

        return redirect()->route('app.billing')->with('status', 'تم الدفع وتفعيل الخطة الاحترافية. شكراً لك.');
    }

    public function paypalCancel(Request $request): RedirectResponse
    {
        $paypalOrderId = (string) $request->query('token', '');
        if ($paypalOrderId !== '') {
            $order = $this->ownOrder($request, $paypalOrderId);
            if ($order->status === 'pending') {
                DB::table('orders')->where('id', $order->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            }
        }

        return redirect()->route('app.billing')->with('status', 'أُلغيت عملية الدفع. يمكنك المحاولة متى شئت.');
    }

    /** Resolve an order by PayPal id, scoped to workspaces the current user belongs to. */
    private function ownOrder(Request $request, string $paypalOrderId): object
    {
        abort_if($paypalOrderId === '' || strlen($paypalOrderId) > 64, 404);
        $workspaceIds = $request->user()->workspaces()->pluck('workspaces.id');
        $order = DB::table('orders')->where('provider_checkout_id', $paypalOrderId)->whereIn('workspace_id', $workspaceIds)->first();
        abort_unless($order, 404);

        return $order;
    }
}
