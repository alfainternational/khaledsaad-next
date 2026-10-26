<?php

use App\Http\Controllers\Admin\ContentController as AdminContentController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\ToolRunController;
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
Route::view('/resources', 'site.resources')->name('resources');
Route::view('/about', 'site.about')->name('about');
Route::get('/search', [PublicSiteController::class, 'search'])->name('search');
Route::get('/sitemap.xml', [PublicSiteController::class, 'sitemap'])->name('sitemap');
Route::get('/feed.xml', [PublicSiteController::class, 'feed'])->name('feed');
Route::view('/llms.txt', 'site.llms')->name('llms');
Route::post('/webhooks/payments', PaymentWebhookController::class)->name('webhooks.payments');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/app/projects/{project}', [ProjectController::class, 'show'])->name('app.projects.show');
    Route::post('/app/tools/{tool}/run', [ToolRunController::class, 'store'])->name('app.tools.run');
    Route::get('/admin', function () {
        abort_unless(auth()->user()->platform_role === 'admin', 403);

        return view('admin.index');
    })->name('admin.index');
    Route::get('/admin/content', [AdminContentController::class, 'index'])->name('admin.content.index');
    Route::post('/admin/content/{contentItem:id}/publish', [AdminContentController::class, 'publish'])->name('admin.content.publish');
});

require __DIR__.'/auth.php';
