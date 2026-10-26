<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\Actions\PublishContent;
use App\Domain\Content\Models\ContentItem;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('admin.content.index', [
            'items' => ContentItem::query()->latest()->paginate(30),
        ]);
    }

    public function publish(Request $request, ContentItem $contentItem, PublishContent $publisher): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $publisher->handle($contentItem);

        return back()->with('status', 'تم نشر المادة وحفظ نسخة منها.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->platform_role === 'admin', 403);
    }
}
