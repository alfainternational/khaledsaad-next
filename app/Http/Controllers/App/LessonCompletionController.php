<?php

namespace App\Http\Controllers\App;

use App\Domain\Academy\Actions\CompleteLesson;
use App\Domain\Academy\Models\Lesson;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LessonCompletionController extends Controller
{
    public function __invoke(Request $request, Lesson $lesson, CompleteLesson $complete): RedirectResponse
    {
        abort_unless($lesson->course()->where('is_published', true)->exists(), 404);
        $data = $request->validate(['deliverable' => ['nullable', 'string', 'max:5000']]);
        $complete->handle($request->user(), $lesson, ['text' => $data['deliverable'] ?? null]);

        return back()->with('status', 'تم حفظ إكمال الدرس.');
    }
}
