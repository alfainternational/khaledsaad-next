<?php

namespace App\Http\Controllers\App;

use App\Domain\Insights\Models\PageChange;
use App\Domain\Insights\Models\WatchedPage;
use App\Domain\Insights\Services\EventRecorder;
use App\Domain\Insights\Services\PageWatcher;
use App\Domain\Workspace\Models\Project;
use App\Http\Controllers\Controller;
use App\Support\Web\UrlGuard;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * مراقبة المنافسين — /app/watch (spec 2026-11-09 §5، الموجة 2).
 *
 * The one screen here that answers the same question every day instead of once, which is what the assessment meant
 * by «هنا يولد الاشتراك المتكرّر». A member adds the pages a competitor actually changes — pricing, offers, the home
 * page promise — and is told what changed, in lines, the morning after it did.
 *
 * The watchlist is capped per workspace on purpose: an unbounded list is a way to point our server at a hundred
 * sites, and the fetcher's per-host window would make the tail useless anyway.
 */
class WatchController extends Controller
{
    public const MAX_PAGES_PER_WORKSPACE = 10;

    public function index(Request $request): View
    {
        $workspaceIds = $request->user()->workspaces()->pluck('workspaces.id');
        $pages = WatchedPage::query()
            ->whereIn('workspace_id', $workspaceIds)
            ->with(['changes' => fn ($q) => $q->limit(3), 'project'])
            ->orderByDesc('last_changed_at')
            ->orderBy('label')
            ->get();

        $projects = Project::query()->whereIn('workspace_id', $workspaceIds)->orderBy('id')->get();

        return view('app.watch.index', [
            'pages' => $pages,
            'projects' => $projects,
            'limit' => self::MAX_PAGES_PER_WORKSPACE,
            'unseen' => PageChange::query()->whereIn('watched_page_id', $pages->pluck('id'))->where('seen', false)->count(),
            'lastRun' => cache('platform:watched_checked_at'),
        ]);
    }

    public function store(Request $request, UrlGuard $guard, EventRecorder $events): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'label' => ['required', 'string', 'max:140'],
            'url' => ['required', 'string', 'max:2000'],
        ]);
        $project = Project::query()->findOrFail($data['project_id']);
        $workspace = $this->memberWorkspace($request, $project->workspace_id);
        abort_unless(in_array($workspace->pivot->role, ['owner', 'editor'], true), 403);

        try {
            $guard->assertFetchable($data['url']);
        } catch (DomainException $exception) {
            return back()->withErrors(['watch' => $exception->getMessage()])->withInput();
        }

        if (WatchedPage::query()->where('workspace_id', $workspace->id)->count() >= self::MAX_PAGES_PER_WORKSPACE) {
            return back()->withErrors(['watch' => 'بلغت الحد: '.self::MAX_PAGES_PER_WORKSPACE.' صفحات مراقَبة لكل مساحة عمل. احذف واحدة قبل إضافة أخرى.'])->withInput();
        }

        $hash = WatchedPage::hashUrl($data['url']);
        if (WatchedPage::query()->where('workspace_id', $workspace->id)->where('url_hash', $hash)->exists()) {
            return back()->withErrors(['watch' => 'هذه الصفحة مراقَبة بالفعل.'])->withInput();
        }

        WatchedPage::query()->create([
            'workspace_id' => $workspace->id, 'project_id' => $project->id, 'user_id' => $request->user()->id,
            'label' => $data['label'], 'url' => $data['url'], 'url_hash' => $hash,
        ]);
        $events->record($request, 'watch.added', ['meta' => ['host' => parse_url($data['url'], PHP_URL_HOST)]]);

        return back()->with('status', 'أُضيفت الصفحة. تُؤخذ نسختها المرجعية في أول فحص، ثم يصلك ما تغيّر بعدها.');
    }

    /** Checking by hand is how a member proves to themselves that the watcher works, before trusting a daily report. */
    public function check(Request $request, WatchedPage $page, PageWatcher $watcher): RedirectResponse
    {
        $this->authorizePage($request, $page);

        $result = $watcher->check($page->fresh());

        return back()->with('status', match ($result['status']) {
            'first_run' => 'أُخذت النسخة المرجعية. ما يتغيّر بعد الآن سيظهر هنا.',
            'changed' => 'رُصد تغيّر: +'.$result['change']->added_lines.' / −'.$result['change']->removed_lines.' سطراً.',
            'unchanged' => 'لا تغيير منذ آخر فحص.',
            'failed' => 'تعذّر الفحص: '.$result['error'],
            default => 'الصفحة موقوفة.',
        });
    }

    public function seen(Request $request, PageChange $change): RedirectResponse
    {
        $this->authorizePage($request, $change->page);
        $change->forceFill(['seen' => true])->save();

        return back();
    }

    public function destroy(Request $request, WatchedPage $page): RedirectResponse
    {
        $this->authorizePage($request, $page, mutate: true);
        $page->delete();

        return back()->with('status', 'حُذفت الصفحة المراقَبة وسجلّ تغيّراتها.');
    }

    private function authorizePage(Request $request, WatchedPage $page, bool $mutate = false): void
    {
        $workspace = $this->memberWorkspace($request, $page->workspace_id);
        if ($mutate) {
            abort_unless(in_array($workspace->pivot->role, ['owner', 'editor'], true), 403);
        }
    }
}
