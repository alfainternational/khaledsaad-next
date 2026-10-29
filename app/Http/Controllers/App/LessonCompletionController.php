<?php

namespace App\Http\Controllers\App;

use App\Domain\Academy\Models\Lesson;
use App\Domain\Academy\Services\ProgressService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LessonCompletionController extends Controller
{
    public function __invoke(Request $request, Lesson $lesson, ProgressService $progress): RedirectResponse
    {
        abort_unless($lesson->course()->where('is_published', true)->exists() && $lesson->isPublished(), 404);
        if (! $progress->maybeComplete($request->user(), $lesson)) {
            return back()->withErrors(['completion' => 'احفظ أصل الدرس وأجب عن أسئلة القرار أولاً.']);
        }

        return back()->with('status', 'تم حفظ إكمال الدرس.');
    }
}
