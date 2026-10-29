<?php

namespace App\Http\Controllers;

use App\Domain\Academy\Models\Course;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\GlossaryTerm;
use App\Domain\Content\Models\Tag;
use App\Domain\Lab\Models\Tool;
use App\Support\Markdown;
use Illuminate\Support\Facades\DB;

/** Discovery endpoints (sitemap, feed) and the pages that pre-date the stage controllers. The magazine lives in Site\MagazineController. */
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

    public function academy()
    {
        return view('site.academy.index', ['courses' => Course::query()->where('is_published', true)->withCount('lessons')->get()]);
    }

    public function course(Course $course)
    {
        abort_unless($course->is_published, 404);
        $course->load('lessons');

        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $course->title,
            'description' => $course->summary,
            'inLanguage' => 'ar',
            'url' => route('academy.show', $course),
            'provider' => ['@type' => 'Organization', 'name' => 'أثر', 'url' => url('/')],
            'educationalLevel' => $course->level,
            'timeRequired' => 'PT'.(int) $course->duration_minutes.'M',
            'numberOfLessons' => $course->lessons->count(),
            'hasCourseInstance' => [
                '@type' => 'CourseInstance',
                'courseMode' => 'online',
                'courseWorkload' => 'PT'.(int) $course->duration_minutes.'M',
            ],
        ];

        return view('site.academy.show', [
            'course' => $course,
            'structuredJson' => json_encode(
                $structuredData,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    public function tools()
    {
        return view('site.tools.index', ['tools' => Tool::query()->where('is_published', true)->get()]);
    }

    public function resources()
    {
        return view('site.resources', [
            'resources' => DB::table('resource_templates')->where('is_published', true)->get(),
        ]);
    }

    public function tool(Tool $tool)
    {
        abort_unless($tool->is_published, 404);

        return view('site.tools.show', compact('tool'));
    }

    public function sitemap()
    {
        $tagCounts = DB::table('content_tag')->join('content_items', 'content_items.id', '=', 'content_tag.content_item_id')
            ->where('content_items.status', 'published')->selectRaw('tag_id, count(*) as n')->groupBy('tag_id')->pluck('n', 'tag_id');

        return response()->view('site.sitemap', [
            'articles' => ContentItem::published()->get(),
            'courses' => Course::query()->where('is_published', true)->with('lessons')->get(),
            'topics' => Course::query()->orderBy('position')->get(),
            'tools' => Tool::query()->where('is_published', true)->where('slug', '!=', 'lesson-lab')->get(),
            'terms' => GlossaryTerm::published()->get(),
            'authors' => Author::query()->get(),
            // Tags with fewer than three items are noindex (§4.10), so they stay out of the sitemap too.
            'tags' => Tag::query()->whereIn('id', $tagCounts->filter(fn ($n) => $n >= 3)->keys())->get(),
        ])->header('Content-Type', 'application/xml');
    }

    public function feed()
    {
        return response()->view('site.feed', ['articles' => ContentItem::published()->with('author')->latest('published_at')->limit(20)->get()])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
