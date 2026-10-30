<?php

use App\Http\Controllers\Admin\AcademyController as AdminAcademyController;
use App\Http\Controllers\Admin\AssessmentController as AdminAssessmentController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\ContactMessagesController;
use App\Http\Controllers\Admin\ContentController as AdminContentController;
use App\Http\Controllers\Admin\ControlRoomController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\RedirectsController;
use App\Http\Controllers\Admin\SourcesController;
use App\Http\Controllers\Admin\TaxonomyController;
use App\Http\Controllers\Admin\ToolStudioController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\App\BillingController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\LessonCompletionController;
use App\Http\Controllers\App\OutputController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\ToolRunController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\Site\AcademyController;
use App\Http\Controllers\Site\AssessController;
use App\Http\Controllers\Site\AssetController;
use App\Http\Controllers\Site\CertificateController;
use App\Http\Controllers\Site\EventController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\LabController;
use App\Http\Controllers\Site\LessonController;
use App\Http\Controllers\Site\MagazineController;
use App\Http\Controllers\Site\NewsletterController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\ResourceController;
use App\Http\Controllers\Site\SearchController;
use App\Http\Controllers\Webhooks\PayPalWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// المجلة (§4.10–4.11): الأقسام الخمسة، المادة، القاموس، المواضيع (= المسارات)، الوسوم، المؤلفون.
Route::get('/articles', [MagazineController::class, 'index'])->name('articles.index');
Route::get('/articles/{contentItem}', [MagazineController::class, 'show'])->name('articles.show');
Route::post('/articles/{contentItem}/report', [MagazineController::class, 'report'])->middleware('throttle:reports')->name('articles.report');
Route::get('/insights', fn (Request $request) => app(MagazineController::class)->section($request, 'insights'))->name('insights.index');
Route::get('/news', fn (Request $request) => app(MagazineController::class)->section($request, 'news'))->name('news.index');
Route::get('/case-studies', fn (Request $request) => app(MagazineController::class)->section($request, 'case-studies'))->name('case-studies.index');
Route::get('/research', fn (Request $request) => app(MagazineController::class)->section($request, 'research'))->name('research.index');
Route::get('/glossary', [MagazineController::class, 'glossary'])->name('glossary.index');
Route::get('/glossary/{term}', [MagazineController::class, 'term'])->name('glossary.show');
Route::get('/topics/{topic}', [MagazineController::class, 'topic'])->name('topics.show');
Route::get('/tags/{tag}', [MagazineController::class, 'tag'])->name('tags.show');
Route::get('/authors/{author}', [MagazineController::class, 'author'])->name('authors.show');
// النشرة البريدية (§3.2): مزدوجة الموافقة.
Route::post('/newsletter', [NewsletterController::class, 'store'])->middleware('throttle:newsletter')->name('newsletter.store');
Route::get('/newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])->name('newsletter.confirm');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

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
Route::get('/resources/{template}/download', [ResourceController::class, 'download'])->name('resources.download');
Route::get('/resources/{template}', [ResourceController::class, 'show'])->name('resources.show');
Route::get('/certificates/{code}', [CertificateController::class, 'show'])->middleware('throttle:search')->name('certificates.show');

Route::post('/assets/print', [AssetController::class, 'print'])->name('assets.print');
Route::post('/events', [EventController::class, 'store'])->middleware('throttle:events')->name('events.store');

// المختبر (§4.6–4.8): المولدات تعمل للزائر بلا AI؛ الحاسبات والمحاكي مجانية؛ /a/{token} صفحة قراءة عامة لأصل مشترك.
Route::get('/tools', [LabController::class, 'index'])->name('tools.index');
Route::get('/tools/calculators/{calc}', [LabController::class, 'calculator'])->name('tools.calculator');
Route::get('/tools/simulator', [LabController::class, 'simulator'])->name('tools.simulator');
Route::get('/tools/{tool}', [LabController::class, 'show'])->name('tools.show');
Route::post('/tools/{tool}/generate', [LabController::class, 'generate'])->middleware('throttle:events')->name('tools.generate');
Route::get('/a/{token}', [LabController::class, 'shared'])->name('assets.shared');
Route::get('/resources', [ResourceController::class, 'index'])->name('resources');
Route::view('/about', 'site.about')->name('about');
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::view('/contact', 'site.contact')->name('contact');
Route::post('/contact', [PageController::class, 'contact'])->middleware('throttle:contact')->name('contact.store');
Route::view('/privacy', 'site.privacy')->name('privacy');
Route::view('/terms', 'site.terms')->name('terms');
Route::get('/search', SearchController::class)->middleware('throttle:search')->name('search');
Route::get('/sitemap.xml', [PublicSiteController::class, 'sitemap'])->name('sitemap');
Route::get('/feed.xml', [PublicSiteController::class, 'feed'])->name('feed');
Route::get('/llms.txt', fn () => response()->view('site.llms')->header('Content-Type', 'text/plain; charset=utf-8'))->name('llms');
// Operational health for external monitoring: no secrets, 503 when a background heartbeat is stale.
Route::get('/health.json', function () {
    $now = now()->timestamp;
    $age = fn (string $key) => ($ts = (int) cache()->get($key, 0)) > 0 ? $now - $ts : null;
    $db = rescue(fn () => DB::select('select 1') !== [], false);
    $scheduler = $age('platform:scheduler_heartbeat');
    $worker = $age('platform:worker_heartbeat');
    $ok = $db && $scheduler !== null && $scheduler < 600 && $worker !== null && $worker < 600;

    return response()->json([
        'ok' => $ok, 'database' => $db, 'scheduler_age_seconds' => $scheduler, 'worker_age_seconds' => $worker, 'time' => now()->toIso8601String(),
    ], $ok ? 200 : 503)->header('Cache-Control', 'no-store');
})->middleware('throttle:search')->name('health');
Route::post('/webhooks/paypal', PayPalWebhookController::class)->middleware('throttle:webhooks')->name('webhooks.paypal');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/resources/{template}/save', [ResourceController::class, 'save'])->name('resources.save');
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
    Route::get('/app/projects/{project}/plan', [ProjectController::class, 'plan'])->name('app.projects.plan');
    Route::post('/app/tools/{tool}/run', [ToolRunController::class, 'store'])->middleware('throttle:ai')->name('app.tools.run');
    Route::post('/app/tools/{tool}/save', [OutputController::class, 'store'])->name('app.tools.save');
    // أصولي (§5.3) والمشاركة برابط (§4.7)
    Route::get('/app/outputs', [OutputController::class, 'index'])->name('app.outputs.index');
    Route::get('/app/outputs/{output}', [OutputController::class, 'show'])->name('app.outputs.show');
    Route::delete('/app/outputs/{output}', [OutputController::class, 'destroy'])->name('app.outputs.destroy');
    Route::post('/app/outputs/{output}/share', [OutputController::class, 'share'])->name('app.outputs.share');
    Route::delete('/app/outputs/{output}/share', [OutputController::class, 'unshare'])->name('app.outputs.unshare');

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

    // إدارة المحتوى الموسعة (§6.5): المواد والتصنيفات والوسوم والمؤلفون بجداول قياسية، وكل تغيير في التدقيق.
    Route::get('/admin/content', [AdminContentController::class, 'index'])->name('admin.content.index');
    Route::get('/admin/content/create', [AdminContentController::class, 'create'])->name('admin.content.create');
    Route::post('/admin/content', [AdminContentController::class, 'store'])->name('admin.content.store');
    Route::get('/admin/content/{kind}', [TaxonomyController::class, 'index'])->whereIn('kind', ['categories', 'tags', 'authors'])->name('admin.content.taxonomy');
    Route::post('/admin/content/{kind}', [TaxonomyController::class, 'store'])->whereIn('kind', ['categories', 'tags', 'authors'])->name('admin.content.taxonomy.store');
    Route::put('/admin/content/{kind}/{id}', [TaxonomyController::class, 'update'])->whereIn('kind', ['categories', 'tags', 'authors'])->whereNumber('id')->name('admin.content.taxonomy.update');
    Route::delete('/admin/content/{kind}/{id}', [TaxonomyController::class, 'destroy'])->whereIn('kind', ['categories', 'tags', 'authors'])->whereNumber('id')->name('admin.content.taxonomy.destroy');
    Route::get('/admin/content/{contentItem:id}/edit', [AdminContentController::class, 'edit'])->name('admin.content.edit');
    Route::put('/admin/content/{contentItem:id}', [AdminContentController::class, 'update'])->name('admin.content.update');
    Route::delete('/admin/content/{contentItem:id}', [AdminContentController::class, 'destroy'])->name('admin.content.destroy');
    Route::post('/admin/content/{contentItem:id}/publish', [AdminContentController::class, 'publish'])->name('admin.content.publish');
    Route::post('/admin/content/{contentItem:id}/clean', [AdminContentController::class, 'clean'])->name('admin.content.clean');
    // من المصادر إلى الرؤى (§6.3): لوح البوابات الخمس.
    Route::get('/admin/sources', [SourcesController::class, 'index'])->name('admin.sources.index');
    Route::post('/admin/sources/fetch', [SourcesController::class, 'fetch'])->name('admin.sources.fetch');
    Route::post('/admin/sources/items', [SourcesController::class, 'store'])->name('admin.sources.store');
    Route::get('/admin/sources/items/{item}', [SourcesController::class, 'show'])->name('admin.sources.show');
    Route::post('/admin/sources/items/{item}/verify', [SourcesController::class, 'verify'])->name('admin.sources.verify');
    Route::post('/admin/sources/items/{item}/summarize', [SourcesController::class, 'summarize'])->middleware('throttle:ai')->name('admin.sources.summarize');
    Route::post('/admin/sources/items/{item}/contextualize', [SourcesController::class, 'contextualize'])->name('admin.sources.contextualize');
    Route::post('/admin/sources/items/{item}/review', [SourcesController::class, 'review'])->name('admin.sources.review');
    Route::post('/admin/sources/items/{item}/ignore', [SourcesController::class, 'ignore'])->name('admin.sources.ignore');
    Route::patch('/admin/sources/feeds/{feed}', [SourcesController::class, 'toggleFeed'])->name('admin.sources.feeds.toggle');
    Route::get('/admin/operations', [OperationsController::class, 'index'])->name('admin.operations');
    // الشاشات القياسية (§6.5): المستخدمون والأدوار، الخطط والدفع، التحويلات (+CSV)، سجل التدقيق.
    Route::get('/admin/users', [UsersController::class, 'index'])->name('admin.users.index');
    Route::patch('/admin/users/{user}', [UsersController::class, 'updateRole'])->name('admin.users.update');
    Route::post('/admin/users/{user}/impersonate', [UsersController::class, 'impersonate'])->name('admin.users.impersonate');
    Route::post('/admin/impersonation/stop', [UsersController::class, 'stopImpersonation'])->withoutMiddleware('verified')->name('admin.impersonation.stop');
    Route::get('/admin/plans', [PlanController::class, 'index'])->name('admin.plans.index');
    Route::get('/admin/redirects', [RedirectsController::class, 'index'])->name('admin.redirects.index');
    Route::post('/admin/redirects/import', [RedirectsController::class, 'import'])->name('admin.redirects.import');
    Route::post('/admin/redirects', [RedirectsController::class, 'store'])->name('admin.redirects.store');
    Route::delete('/admin/redirects/{redirect}', [RedirectsController::class, 'destroy'])->whereNumber('redirect')->name('admin.redirects.destroy');
    Route::get('/admin/audit', [AuditController::class, 'index'])->name('admin.audit.index');
    Route::patch('/admin/plans/{plan}', [PlanController::class, 'update'])->name('admin.plans.update');
    // رسائل الاتصال (§4.13): كل رسالة مسجّلة في قاعدة البيانات مع حالتها.
    Route::get('/admin/contact-messages', [ContactMessagesController::class, 'index'])->name('admin.contact-messages.index');
    Route::patch('/admin/contact-messages/{message}', [ContactMessagesController::class, 'update'])->whereNumber('message')->name('admin.contact-messages.update');

    // استوديو الأداة (§6.4)
    Route::get('/admin/tools', [ToolStudioController::class, 'index'])->name('admin.tools.index');
    Route::get('/admin/tools/{tool:id}', [ToolStudioController::class, 'show'])->name('admin.tools.show');
    Route::put('/admin/tools/{tool:id}', [ToolStudioController::class, 'update'])->name('admin.tools.update');
    Route::post('/admin/tools/{tool:id}/versions', [ToolStudioController::class, 'storeVersion'])->name('admin.tools.versions.store');
    Route::post('/admin/tools/{tool:id}/versions/{version}/activate', [ToolStudioController::class, 'activateVersion'])->name('admin.tools.versions.activate');
    Route::post('/admin/tools/{tool:id}/test', [ToolStudioController::class, 'test'])->name('admin.tools.test');
    Route::post('/admin/tools/{tool:id}/experiment', [ToolStudioController::class, 'startExperiment'])->name('admin.tools.experiment.start');
    Route::post('/admin/tools/{tool:id}/experiment/{experiment}/end', [ToolStudioController::class, 'endExperiment'])->name('admin.tools.experiment.end');
});

require __DIR__.'/auth.php';
