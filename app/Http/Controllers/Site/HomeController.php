<?php

namespace App\Http\Controllers\Site;

use App\Domain\Academy\Models\Course;
use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Services\ProgressService;
use App\Domain\Assessment\Services\AssessmentEngine;
use App\Domain\Assessment\Services\Catalog;
use App\Domain\Assessment\Services\FirstStepAdvisor;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Insights\Services\EventRecorder;
use App\Domain\Lab\Models\Tool;
use App\Domain\Lab\Models\ToolOutput;
use App\Http\Controllers\Controller;
use App\Support\Viewer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __invoke(Request $request, FirstStepAdvisor $advisor, AssessmentEngine $engine, ProgressService $progress, EventRecorder $events, Viewer $viewer)
    {
        $events->record($request, 'visit.home');

        $continue = null;
        if ($viewer->isMember() && ! $viewer->isBeginner()) {
            $last = $progress->lastLesson($request->user());
            if ($last && $last->course) {
                $continue = ['lesson' => $last, 'progress' => $progress->courseProgress($request->user(), $last->course)];
            }
        }

        // Server-side first step when the form is submitted without JavaScript (?offer=&problem=&budget=).
        $firstStep = null;
        if ($request->filled('problem')) {
            $firstStep = $advisor->advise((string) $request->query('offer'), (string) $request->query('problem'), (string) $request->query('budget', '0'));
            $events->record($request, 'home.first_step', ['meta' => ['problem' => $request->query('problem')]]);
        }

        return view('site.home', [
            'continue' => $continue,
            'firstStep' => $firstStep,
            'rules' => $advisor->exportForBrowser(),
            'numbers' => $this->liveNumbers($engine),
            'courses' => Course::query()->where('is_published', true)->orderBy('position')->limit(3)->get()
                ->concat(Course::query()->where('is_published', false)->orderBy('position')->limit(3)->get())->take(3),
            'tools' => Tool::query()->where('is_published', true)->limit(3)->get(),
            'articles' => ContentItem::published()->latest('published_at')->limit(3)->get(),
            'firstLesson' => Lesson::query()->whereNotNull('published_at')->with('course')->orderBy('position')->first(),
        ]);
    }

    /** Real numbers from the database; a cell is hidden when its number is below 10 (blueprint §4.1 row 2). */
    private function liveNumbers(AssessmentEngine $engine): array
    {
        // Live numbers count learners' assets only, never the seeded illustrative examples of the lab.
        $assetsWeek = ToolOutput::query()->where('created_at', '>=', now()->subDays(7))->where('payload', 'not like', '%"illustrative":true%')->count();
        $results = DB::table('real_world_checkins')->whereNotNull('recorded_at')->count();
        $improvement = $engine->averageImprovement();
        $last = ToolOutput::query()->whereNotNull('output_type')->where('payload', 'not like', '%"illustrative":true%')->latest()->first();
        $lastProject = $last?->project_id ? DB::table('projects')->where('id', $last->project_id)->value('profile') : null;
        $profile = $lastProject ? (json_decode($lastProject, true) ?: []) : [];
        $typeLabels = ['value_card' => 'بطاقة قيمة', 'market_map' => 'خريطة سوق', 'segment_profile' => 'ملف شريحة', 'core_message' => 'رسالة أساسية', 'channel_plan' => 'خطة قناة', 'marketing_plan' => 'خطة تسويق'];

        return [
            'assets_week' => $assetsWeek >= 10 ? $assetsWeek : null,
            'results' => $results >= 10 ? $results : null,
            'improvement' => $improvement && $improvement['sample'] >= 10 ? $improvement['points'] : null,
            'last_asset' => $last ? [
                'type' => $typeLabels[$last->output_type] ?? $last->title,
                'sector' => Catalog::label(Catalog::SECTORS, $profile['sector'] ?? null, 'قطاع غير محدد'),
                'city' => $profile['city'] ?? 'مدينة غير محددة',
                'when' => $last->created_at->diffForHumans(),
            ] : null,
        ];
    }
}
