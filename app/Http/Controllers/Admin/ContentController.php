<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\Actions\PublishContent;
use App\Domain\Content\Models\ContentItem;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    public const TYPES = ['article', 'guide', 'case_study', 'news', 'translated_analysis'];

    /** Editorial states an editor may set by hand; "published" is only reachable through PublishContent. */
    public const EDITABLE_STATUSES = ['draft', 'review', 'seo_review'];

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $query = ContentItem::query()->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('admin.content.index', [
            'items' => $query->paginate(30)->withQueryString(),
            'counts' => ContentItem::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function edit(Request $request, ContentItem $contentItem)
    {
        $this->authorizeAdmin($request);

        return view('admin.content.edit', ['item' => $contentItem, 'types' => self::TYPES, 'statuses' => self::EDITABLE_STATUSES]);
    }

    public function update(Request $request, ContentItem $contentItem): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', Rule::unique('content_items', 'slug')->ignore($contentItem->id)],
            'type' => ['required', Rule::in(self::TYPES)],
            'status' => ['required', Rule::in($contentItem->status === 'published' ? ['published'] : self::EDITABLE_STATUSES)],
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'topic' => ['nullable', 'string', 'max:80'],
            'excerpt' => ['required', 'string', 'max:600'],
            'body' => ['required', 'string', 'max:200000'],
            'author_name' => ['required', 'string', 'max:120'],
            'reviewer_name' => ['nullable', 'string', 'max:120'],
            'reading_minutes' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $before = $contentItem->only(array_keys($data));
        $contentItem->fill($data);
        if (! empty($data['reviewer_name']) && ! $contentItem->reviewed_at) {
            $contentItem->reviewed_at = now();
        }
        $contentItem->save();

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()->id, 'action' => 'content.updated', 'subject_type' => ContentItem::class,
            'subject_id' => (string) $contentItem->id,
            'context' => json_encode(['changed' => array_keys(array_diff_assoc($data, $before))], JSON_UNESCAPED_UNICODE),
            'request_id' => $request->attributes->get('request_id'), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('admin.content.edit', $contentItem)->with('status', 'تم حفظ التعديلات.');
    }

    public function publish(Request $request, ContentItem $contentItem, PublishContent $publisher): RedirectResponse
    {
        $this->authorizeAdmin($request);
        try {
            $publisher->handle($contentItem, $request->user()->id);
        } catch (DomainException $e) {
            return back()->withErrors(['publish' => $e->getMessage()]);
        }

        return back()->with('status', 'تم نشر المادة وحفظ نسخة منها.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(in_array($request->user()?->platform_role, ['editor', 'admin', 'super_admin'], true), 403);
    }
}
