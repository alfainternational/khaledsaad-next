<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanController extends Controller
{
    public function update(Request $request, string $plan): RedirectResponse
    {
        abort_unless(in_array($request->user()?->platform_role, ['finance', 'admin', 'super_admin'], true), 403);
        $data = $request->validate([
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'credits' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        DB::transaction(function () use ($request, $plan, $data): void {
            $current = DB::table('plans')->where('slug', $plan)->lockForUpdate()->first();
            abort_unless($current, 404);
            DB::table('plans')->where('id', $current->id)->update($data + ['updated_at' => now()]);
            DB::table('audit_logs')->insert([
                'user_id' => $request->user()->id,
                'action' => 'plan.price_changed',
                'subject_type' => 'plan',
                'subject_id' => (string) $current->id,
                'context' => json_encode([
                    'before_hash' => hash('sha256', json_encode(['price' => $current->price, 'credits' => $current->credits])),
                    'after_hash' => hash('sha256', json_encode($data)),
                ]),
                'request_id' => $request->attributes->get('request_id'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('status', 'تم تحديث الخطة وتسجيل التغيير.');
    }
}
