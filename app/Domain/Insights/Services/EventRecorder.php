<?php

namespace App\Domain\Insights\Services;

use App\Domain\Insights\Models\LearnerEvent;
use Illuminate\Http\Request;

/**
 * learner_events (blueprint §6.6): behaviour events (part reached, generated, saved, copied, answered, downloaded…)
 * for members and visitors alike; visitors are keyed by a hashed session id.
 */
class EventRecorder
{
    public const EVENTS = [
        'visit.home', 'visit.lesson', 'lesson.part_reached', 'lesson.opening_decided', 'lesson.final_decided', 'lesson.lab_generated',
        'lesson.whatif_used', 'asset.saved', 'asset.copied', 'asset.downloaded', 'question.answered', 'template.downloaded',
        'assessment.started', 'assessment.completed', 'checkin.committed', 'checkin.recorded', 'coach.asked', 'report.sent', 'lesson.completed',
        'home.first_step', 'visit.tool', 'tool.generated', 'tool.enhanced', 'asset.shared', 'calculator.used', 'simulator.used',
    ];

    public static function sessionToken(Request $request): string
    {
        return substr(hash('sha256', (string) $request->session()->getId()), 0, 40);
    }

    public function record(Request $request, string $event, array $attrs = []): ?LearnerEvent
    {
        if (! in_array($event, self::EVENTS, true)) {
            return null;
        }

        return LearnerEvent::query()->create([
            'user_id' => $request->user()?->id,
            'session_token' => self::sessionToken($request),
            'event' => $event,
            'lesson_id' => $attrs['lesson_id'] ?? null,
            'part' => isset($attrs['part']) ? substr((string) $attrs['part'], 0, 20) : null,
            'tool_id' => $attrs['tool_id'] ?? null,
            'question_id' => $attrs['question_id'] ?? null,
            'meta' => $attrs['meta'] ?? null,
            'created_at' => now(),
        ]);
    }
}
