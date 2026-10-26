<?php

namespace App\Http\Controllers;

use App\Domain\Academy\Models\Course;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Lab\Models\Tool;
use Illuminate\Http\Request;

class PublicSiteController extends Controller
{
    public function home()
    {
        return view('site.home', [
            'articles' => ContentItem::published()->latest('published_at')->limit(3)->get(),
            'courses' => Course::query()->where('is_published', true)->limit(3)->get(),
            'tools' => Tool::query()->where('is_published', true)->limit(3)->get(),
        ]);
    }

    public function articles(Request $request)
    {
        $query = ContentItem::published()->latest('published_at');
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return view('site.articles.index', ['articles' => $query->paginate(12)]);
    }

    public function article(ContentItem $contentItem)
    {
        abort_unless($contentItem->status === 'published' && $contentItem->published_at?->lte(now()), 404);
        $contentItem->load('sources');

        return view('site.articles.show', ['article' => $contentItem]);
    }

    public function insights()
    {
        return view('site.articles.index', [
            'articles' => ContentItem::published()->whereIn('type', ['translated_analysis', 'news'])->latest('published_at')->paginate(12),
            'insights' => true,
        ]);
    }

    public function academy()
    {
        return view('site.academy.index', ['courses' => Course::query()->where('is_published', true)->withCount('lessons')->get()]);
    }

    public function course(Course $course)
    {
        abort_unless($course->is_published, 404);

        return view('site.academy.show', ['course' => $course->load('lessons')]);
    }

    public function tools()
    {
        return view('site.tools.index', ['tools' => Tool::query()->where('is_published', true)->get()]);
    }

    public function tool(Tool $tool)
    {
        abort_unless($tool->is_published, 404);

        return view('site.tools.show', compact('tool'));
    }

    public function search(Request $request)
    {
        $term = trim((string) $request->query('q'));
        $results = ContentItem::published()
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhere('excerpt', 'like', "%{$term}%")))
            ->limit(20)->get();

        return view('site.search', compact('term', 'results'));
    }

    public function sitemap()
    {
        return response()->view('site.sitemap', [
            'articles' => ContentItem::published()->get(),
            'courses' => Course::query()->where('is_published', true)->get(),
            'tools' => Tool::query()->where('is_published', true)->get(),
        ])->header('Content-Type', 'application/xml');
    }

    public function feed()
    {
        return response()->view('site.feed', ['articles' => ContentItem::published()->latest('published_at')->limit(20)->get()])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
