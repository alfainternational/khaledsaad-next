<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Support\Models\ContactMessage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** رسائل الاتصال (§4.13 + §6.5): a plain, searchable table; every status change is audited. */
class ContactMessagesController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeStaff($request);
        $filters = $request->validate(['status' => ['nullable', 'in:new,handled,spam'], 'q' => ['nullable', 'string', 'max:120']]);
        $messages = ContactMessage::query()->with(['user', 'handler'])
            ->when($filters['status'] ?? 'new', fn ($q, $status) => $q->where('status', $status))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')->orWhere('message', 'like', '%'.$term.'%')))
            ->orderByDesc('created_at')->paginate(25)->withQueryString();
        $counts = ContactMessage::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.contact-messages', ['messages' => $messages, 'filters' => $filters + ['status' => $filters['status'] ?? 'new'], 'counts' => $counts, 'statuses' => ContactMessage::STATUSES]);
    }

    public function update(Request $request, ContactMessage $message): RedirectResponse
    {
        $this->authorizeStaff($request);
        $data = $request->validate(['status' => ['required', 'in:new,handled,spam']]);
        $before = $message->status;
        $message->update([
            'status' => $data['status'],
            'handled_at' => $data['status'] === 'handled' ? now() : null,
            'handled_by' => $data['status'] === 'handled' ? $request->user()->id : null,
        ]);
        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id, 'action' => 'contact_message.status_changed', 'subject_type' => 'contact_message',
            'subject_id' => (string) $message->id, 'context' => json_encode(['before' => $before, 'after' => $data['status']]),
            'request_id' => $request->attributes->get('request_id'), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('status', 'حُدّثت حالة الرسالة.');
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless(in_array($request->user()?->platform_role, ['operations', 'admin', 'super_admin'], true), 403);
    }
}
