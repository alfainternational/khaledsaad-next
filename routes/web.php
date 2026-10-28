<?php

use App\Http\Controllers\Admin\ContentController as AdminContentController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\App\BillingController;
use App\Http\Controllers\App\LessonCompletionController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\ToolRunController;
use App\Http\Controllers\App\ToolRunOutputController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicSiteController::class, 'home'])->name('home');
Route::get('/articles', [PublicSiteController::class, 'articles'])->name('articles.index');
Route::get('/articles/{contentItem}', [PublicSiteController::class, 'article'])->name('articles.show');
Route::get('/insights', [PublicSiteController::class, 'insights'])->name('insights.index');
Route::get('/academy', [PublicSiteController::class, 'academy'])->name('academy.index');
Route::get('/academy/{course}', [PublicSiteController::class, 'course'])->name('academy.show');
Route::get('/tools', [PublicSiteController::class, 'tools'])->name('tools.index');
Route::get('/tools/{tool}', [PublicSiteController::class, 'tool'])->name('tools.show');
Route::get('/resources', [PublicSiteController::class, 'resources'])->name('resources');
Route::view('/about', 'site.about')->name('about');
Route::get('/search', [PublicSiteController::class, 'search'])->middleware('throttle:search')->name('search');
Route::get('/sitemap.xml', [PublicSiteController::class, 'sitemap'])->name('sitemap');
Route::get('/feed.xml', [PublicSiteController::class, 'feed'])->name('feed');
Route::get('/llms.txt', fn () => response()->view('site.llms')->header('Content-Type', 'text/plain; charset=utf-8'))->name('llms');
Route::post('/webhooks/payments', PaymentWebhookController::class)->middleware('throttle:webhooks')->name('webhooks.payments');

Route::get('/dashboard', function () {
    return view('dashboard', ['workspaces' => auth()->user()->workspaces()->with('projects')->get()]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/app/projects', [ProjectController::class, 'store'])->name('app.projects.store');
    Route::get('/app/projects/{project}', [ProjectController::class, 'show'])->name('app.projects.show');
    Route::patch('/app/projects/{project}', [ProjectController::class, 'update'])->name('app.projects.update');
    Route::delete('/app/projects/{project}', [ProjectController::class, 'destroy'])->name('app.projects.destroy');
    Route::post('/app/lessons/{lesson}/complete', LessonCompletionController::class)->name('app.lessons.complete');
    Route::get('/app/billing', [BillingController::class, 'index'])->name('app.billing');
    Route::post('/app/billing/orders', [BillingController::class, 'storeOrder'])->name('app.billing.orders.store');
    Route::post('/app/tools/{tool}/run', [ToolRunController::class, 'store'])->middleware('throttle:ai')->name('app.tools.run');
    Route::delete('/app/tool-runs/{toolRun}', [ToolRunOutputController::class, 'destroy'])->name('app.tool-runs.destroy');
    Route::get('/admin', function () {
        abort_unless(in_array(auth()->user()->platform_role, ['editor', 'operations', 'finance', 'admin', 'super_admin'], true), 403);

        return view('admin.index');
    })->name('admin.index');
    Route::get('/admin/content', [AdminContentController::class, 'index'])->name('admin.content.index');
    Route::post('/admin/content/{contentItem:id}/publish', [AdminContentController::class, 'publish'])->name('admin.content.publish');
    Route::get('/admin/operations', [OperationsController::class, 'index'])->name('admin.operations');
    Route::post('/admin/redirects', [OperationsController::class, 'storeRedirect'])->name('admin.redirects.store');
    Route::delete('/admin/redirects/{redirect}', [OperationsController::class, 'destroyRedirect'])->name('admin.redirects.destroy');
    Route::patch('/admin/plans/{plan}', [PlanController::class, 'update'])->name('admin.plans.update');
});

require __DIR__.'/auth.php';
