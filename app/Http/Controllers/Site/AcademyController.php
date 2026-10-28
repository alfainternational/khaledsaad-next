<?php

namespace App\Http\Controllers\Site;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\LearningPath;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Models\ResourceTemplate;
use App\Domain\Academy\Services\ProgressService;
use App\Domain\Assessment\Models\AssessmentResult;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Insights\Services\EventRecorder;
use App\Http\Controllers\Controller;
use App\Support\Viewer;
use Illuminate\Http\Request;

/**
 * /academy (blueprint §4.3) and /academy/{course} (§4.4).
 */
class AcademyController extends Controller
{
    public function index(Request $request, ProgressService $progress, Viewer $viewer)
    {
        $programs = LearningPath::query()->where('is_published', true)->orderBy('position')->with(['courses' => fn ($q) => $q->withCount('lessons')->orderByPivot('position')])->get();
        $courses = Course::query()->orderBy('position')->withCount(['lessons' => fn ($q) => $q->whereNotNull('published_at')])->get();
        $user = $viewer->isMember() && ! $viewer->isBeginner() ? $request->user() : null;
        $progressByCourse = [];
        if ($user) {
            foreach ($courses->where('is_published', true) as $course) {
                $progressByCourse[$course->id] = $progress->courseProgress($user, $course);
            }
        }
        $assessed = $user ? AssessmentResult::query()->where('user_id', $user->id)->exists() : session()->has('assess.result_token');

        return view('site.academy.index', compact('programs', 'courses', 'progressByCourse', 'assessed') + ['parts' => Lesson::PARTS]);
    }

    public function course(Request $request, Course $course, ProgressService $progress, Viewer $viewer)
    {
        abort_unless($course->is_published, 404);
        $course->load(['lessons' => fn ($q) => $q->whereNotNull('published_at'), 'lessons.template', 'prerequisite', 'next', 'programs']);
        $user = $viewer->isMember() && ! $viewer->isBeginner() ? $request->user() : null;
        $courseProgress = $user ? $progress->courseProgress($user, $course) : null;
        $templates = ResourceTemplate::query()->whereIn('lesson_id', $course->lessons->pluck('id'))->where('is_published', true)->get();
        $articles = ContentItem::published()->when($course->topic, fn ($q) => $q->where('topic', 'like', '%'.$course->topic.'%'))->latest('published_at')->limit(3)->get();
        if ($articles->count() < 3) {
            $articles = $articles->concat(ContentItem::published()->whereNotIn('id', $articles->pluck('id'))->latest('published_at')->limit(3 - $articles->count())->get());
        }
        $structured = [
            '@context' => 'https://schema.org', '@type' => 'Course', 'name' => $course->title, 'description' => $course->summary, 'inLanguage' => 'ar',
            'url' => route('academy.show', $course), 'provider' => ['@type' => 'Organization', 'name' => 'أثر', 'url' => url('/')],
            'educationalLevel' => $course->level, 'timeRequired' => 'PT'.(int) $course->duration_minutes.'M', 'numberOfLessons' => $course->lessons->count(),
            'hasCourseInstance' => ['@type' => 'CourseInstance', 'courseMode' => 'online', 'courseWorkload' => 'PT'.(int) $course->duration_minutes.'M'],
        ];

        return view('site.academy.show', [
            'course' => $course, 'progress' => $courseProgress, 'templates' => $templates, 'articles' => $articles,
            'structuredJson' => json_encode($structured, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR),
        ]);
    }

    /** Template download (Markdown) — free for visitors; saving is for members (blueprint §3.3). */
    public function template(Request $request, ResourceTemplate $template, EventRecorder $events)
    {
        abort_unless($template->is_published, 404);
        $template->increment('downloads_count');
        $events->record($request, 'template.downloaded', ['lesson_id' => $template->lesson_id, 'meta' => ['template' => $template->slug]]);
        $body = $template->body_markdown ?: '# '.$template->name."\n\n".$template->description;

        return response($body, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$template->slug.'.md"',
        ]);
    }
}
