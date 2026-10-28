<?php

use App\Http\Controllers\Admin\AcademyController as AdminAcademyController;
use App\Http\Controllers\Admin\AssessmentController as AdminAssessmentController;
use App\Http\Controllers\Admin\ContentController as AdminContentController;
use App\Http\Controllers\Admin\ControlRoomController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\App\BillingController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\LessonCompletionController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\ToolRunController;
use App\Http\Controllers\App\ToolRunOutputController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\Site\AcademyController;
use App\Http\Controllers\Site\AssessController;
use App\Http\Controllers\Site\AssetController;
use App\Http\Controllers\Site\EventController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\LessonController;
use App\Http\Controllers\Webhooks\PayPalWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/articles', [PublicSiteController::class, 'articles'])->name('articles.index');
Route::get('/articles/{contentItem}', [PublicSiteController::class, 'article'])->name('articles.show');
Route::get('/insights', [PublicSiteController::class, 'insights'])->name('insights.index');

// تشخيص مشروعك (§4.2)
Route::get('/assess', [AssessController::class, 'start'])->name('assess.start');
Route::post('/assess/project', [AssessController::class, 'storeProject'])->name('assess.project');
Route::get('/assess/questions', [AssessController::class, 'questions'])->name('assess.questions');
Route::post('/assess/submit', [AssessController::class, 'submit'])->name('assess.submit');
Route::get('/assess/result', [AssessController::class, 'result'])->name('assess.result');

// الأكاديمية (§4.3–4.5)
Route::get('/academy', [AcademyController::class, 'index'])->name('academy.index');
Route::get('/academy/{course}', [AcademyController::class, 'course'])->name('academy.show');
Route::get('/academy/{course}/{lesson}', [LessonController::class, 'show'])->name('academy.lesson');
Route::post('/academy/{course}/{lesson}/lab', [LessonController::class, 'lab'])->name('academy.lesson.lab');
Route::get('/academy/{course}/{lesson}/whatif', [LessonController::class, 'whatIf'])->middleware('throttle:events')->name('academy.lesson.whatif');
Route::post('/academy/{course}/{lesson}/decision', [LessonController::class, 'decision'])->middleware('throttle:events')->name('academy.lesson.decision');
Route::post('/academy/{course}/{lesson}/report', [LessonController::class, 'report'])->middleware('throttle:reports')->name('academy.lesson.report');
Route::get('/resources/{template}/download', [AcademyController::class, 'template'])->name('resources.download');

Route::post('/assets/print', [AssetController::class, 'print'])->name('assets.print');
Route::post('/events', [EventController::class, 'store'])->middleware('throttle:events')->name('events.store');

Route::get('/tools', [PublicSiteController::class, 'tools'])->name('tools.index');
Route::get('/tools/{tool}', [PublicSiteController::class, 'tool'])->name('tools.show');
Route::get('/resources', [PublicSiteController::class, 'resources'])->name('resources');
Route::view('/about', 'site.about')->name('about');
Route::get('/search', [PublicSiteController::class, 'search'])->middleware('throttle:search')->name('search');
Route::get('/sitemap.xml', [PublicSiteController::class, 'sitemap'])->name('sitemap');
Route::get('/feed.xml', [PublicSiteController::class, 'feed'])->name('feed');
Route::get('/llms.txt', fn () => response()->view('site.llms')->header('Content-Type', 'text/plain; charset=utf-8'))->name('llms');
// Operational health for external monitoring: no secrets, 503 when a background heartbeat is stale.
Route::get('/health.json', function () {
    $now = now()->timestamp;
    $age = fn (string $key) => ($ts = (int) cache()->get($key, 0)) > 0 ? $now - $ts : null;
    $db = rescue(fn () => \Illuminate\Support\Facades\DB::select('select 1') !== [], false);
    $scheduler = $age('platform:scheduler_heartbeat');
    $worker = $age('platform:worker_heartbeat');
    $ok = $db && $scheduler !== null && $scheduler < 600 && $worker !== null && $worker < 600;

    return response()->json([
        'ok' => $ok, 'database' => $db, 'scheduler_age_seconds' => $scheduler, 'worker_age_seconds' => $worker, 'time' => now()->toIso8601String(),
    ], $ok ? 200 : 503)->header('Cache-Control', 'no-store');
})->middleware('throttle:search')->name('health');
Route::post('/webhooks/paypal', PayPalWebhookController::class)->middleware('throttle:webhooks')->name('webhooks.paypal');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/app/progress', [DashboardController::class, 'progress'])->name('app.progress');
    Route::post('/app/assess/claim', [AssessController::class, 'claim'])->name('assess.claim');
    Route::post('/academy/{course}/{lesson}/save', [LessonController::class, 'save'])->name('academy.lesson.save');
    Route::post('/academy/{course}/{lesson}/checkin', [LessonController::class, 'checkin'])->name('academy.lesson.checkin');
    Route::post('/academy/{course}/{lesson}/result', [LessonController::class, 'result'])->name('academy.lesson.result');
    Route::post('/academy/{course}/{lesson}/coach', [LessonController::class, 'coach'])->middleware('throttle:ai')->name('academy.lesson.coach');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/app/projects', [ProjectController::class, 'store'])->name('app.projects.store');
    Route::get('/app/projects/{project}', [ProjectController::class, 'show'])->name('app.projects.show');
    Route::patch('/app/projects/{project}', [ProjectController::class, 'update'])->name('app.projects.update');
    Route::delete('/app/projects/{project}', [ProjectController::class, 'destroy'])->name('app.projects.destroy');
    Route::post('/app/lessons/{lesson}/complete', LessonCompletionController::class)->name('app.lessons.complete');
    Route::get('/app/billing', [BillingController::class, 'index'])->name('app.billing');
    Route::post('/app/billing/orders', [BillingController::class, 'storeOrder'])->middleware('throttle:checkout')->name('app.billing.orders.store');
    Route::get('/app/billing/paypal/return', [BillingController::class, 'paypalReturn'])->name('app.billing.paypal.return');
    Route::get('/app/billing/paypal/cancel', [BillingController::class, 'paypalCancel'])->name('app.billing.paypal.cancel');
    Route::post('/app/tools/{tool}/run', [ToolRunController::class, 'store'])->middleware('throttle:ai')->name('app.tools.run');
    Route::delete('/app/tool-runs/{toolRun}', [ToolRunOutputController::class, 'destroy'])->name('app.tool-runs.destroy');

    // غرفة التحكم (§6.1) والاستوديو (§6.2) وإدارة الاختبار (§6.5)
    Route::get('/admin', [ControlRoomController::class, 'index'])->name('admin.index');
    Route::post('/admin/quality/recompute', [ControlRoomController::class, 'recompute'])->name('admin.quality.recompute');
    Route::patch('/admin/quality/{signal}', [ControlRoomController::class, 'signalStatus'])->name('admin.quality.status');
    Route::post('/admin/preview/{as}', [ControlRoomController::class, 'preview'])->name('admin.preview');
    Route::get('/admin/academy', [AdminAcademyController::class, 'index'])->name('admin.academy.index');
    Route::patch('/admin/academy/courses/{course}', [AdminAcademyController::class, 'updateCourse'])->name('admin.academy.courses.update');
    Route::get('/admin/academy/lessons/{lesson:id}', [AdminAcademyController::class, 'edit'])->name('admin.academy.lessons.edit');
    Route::put('/admin/academy/lessons/{lesson:id}', [AdminAcademyController::class, 'update'])->name('admin.academy.lessons.update');
    Route::post('/admin/academy/lessons/{lesson:id}/restore/{version}', [AdminAcademyController::class, 'restore'])->name('admin.academy.lessons.restore');
    Route::post('/admin/academy/lessons/{lesson:id}/variant', [AdminAcademyController::class, 'variant'])->middleware('throttle:ai')->name('admin.academy.lessons.variant');
    Route::get('/admin/assessment', [AdminAssessmentController::class, 'index'])->name('admin.assessment.index');
    Route::post('/admin/assessment/questions', [AdminAssessmentController::class, 'storeQuestion'])->name('admin.assessment.questions.store');
    Route::put('/admin/assessment/questions/{question}', [AdminAssessmentController::class, 'updateQuestion'])->name('admin.assessment.questions.update');
    Route::delete('/admin/assessment/questions/{question}', [AdminAssessmentController::class, 'destroyQuestion'])->name('admin.assessment.questions.destroy');
    Route::post('/admin/assessment/rules', [AdminAssessmentController::class, 'saveRule'])->name('admin.assessment.rules.save');

    Route::get('/admin/content', [AdminContentController::class, 'index'])->name('admin.content.index');
    Route::get('/admin/content/{contentItem:id}/edit', [AdminContentController::class, 'edit'])->name('admin.content.edit');
    Route::put('/admin/content/{contentItem:id}', [AdminContentController::class, 'update'])->name('admin.content.update');
    Route::post('/admin/content/{contentItem:id}/publish', [AdminContentController::class, 'publish'])->name('admin.content.publish');
    Route::get('/admin/operations', [OperationsController::class, 'index'])->name('admin.operations');
    Route::post('/admin/redirects', [OperationsController::class, 'storeRedirect'])->name('admin.redirects.store');
    Route::delete('/admin/redirects/{redirect}', [OperationsController::class, 'destroyRedirect'])->name('admin.redirects.destroy');
    Route::patch('/admin/plans/{plan}', [PlanController::class, 'update'])->name('admin.plans.update');
});

require __DIR__.'/auth.php';
