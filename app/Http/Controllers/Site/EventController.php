<?php

namespace App\Http\Controllers\Site;

use App\Domain\Academy\Models\Question;
use App\Domain\Academy\Services\ProgressService;
use App\Domain\Insights\Services\EventRecorder;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Browser-side learner events (part reached, copied, downloaded, question answered…). */
class EventController extends Controller
{
    public function store(Request $request, EventRecorder $events, ProgressService $progress)
    {
        // Beacons fire while the browser follows a redirect; keep that request from consuming the page's flash message.
        if ($request->hasSession()) {
            $request->session()->reflash();
        }
        $payload = $request->isJson() ? (array) $request->json()->all() : $request->all();
        if (is_string($payload['meta'] ?? null)) {
            $payload['meta'] = json_decode($payload['meta'], true) ?: [];
        }
        $validator = validator($payload, [
            'event' => ['required', Rule::in(EventRecorder::EVENTS)],
            'meta' => ['nullable', 'array'],
            'meta.lesson_id' => ['nullable', 'integer'], 'meta.part' => ['nullable', 'string', 'max:20'],
            'meta.question_id' => ['nullable', 'integer'], 'meta.choice' => ['nullable', 'integer'],
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false], 422);
        }
        $meta = $payload['meta'] ?? [];
        $attrs = ['lesson_id' => $meta['lesson_id'] ?? null, 'part' => $meta['part'] ?? null, 'question_id' => $meta['question_id'] ?? null, 'meta' => $meta];
        if ($payload['event'] === 'question.answered' && ! empty($meta['question_id'])) {
            $question = Question::query()->find($meta['question_id']);
            if (! $question) {
                return response()->json(['ok' => false], 422);
            }
            $attrs['lesson_id'] = $question->lesson_id;
            $attrs['meta']['correct'] = $question->bestIndex() !== null && (int) ($meta['choice'] ?? -1) === $question->bestIndex();
        }
        $events->record($request, $payload['event'], $attrs);
        if ($payload['event'] === 'question.answered' && $request->user() && ! empty($attrs['lesson_id'])) {
            $lesson = $question->lesson;
            if ($lesson) {
                $progress->maybeComplete($request->user(), $lesson);
            }
        }

        return response()->json(['ok' => true]);
    }
}
