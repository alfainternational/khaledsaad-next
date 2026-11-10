<?php

namespace App\Console\Commands;

use App\Domain\Assessment\Models\AssessmentFollowup;
use App\Notifications\RemeasureDue;
use Illuminate\Console\Command;

/**
 * platform:send-remeasure-reminders (spec 2026-11-09، الموجة 2).
 *
 * Only the oldest open point per member is ever reminded: three due points would send three mails on the same
 * morning to someone who has not retaken the assessment at all, which teaches them to filter us.
 */
class SendRemeasureReminders extends Command
{
    protected $signature = 'platform:send-remeasure-reminders';

    protected $description = 'Email members whose 30/60/90-day re-measurement point has come due';

    /**
     * A member who has not retaken yet must not be reminded again tomorrow for the next point. Three points come due
     * within sixty days of each other, so without this the loop would nag three times in three days and be filtered.
     */
    public const QUIET_DAYS = 14;

    public function handle(): int
    {
        $recentlyReminded = AssessmentFollowup::query()
            ->whereNotNull('reminder_sent_at')
            ->where('reminder_sent_at', '>=', now()->subDays(self::QUIET_DAYS))
            ->pluck('user_id')
            ->unique();

        $due = AssessmentFollowup::query()
            ->whereNull('completed_at')
            ->whereNull('reminder_sent_at')
            ->where('due_at', '<=', now())
            ->whereNotIn('user_id', $recentlyReminded)
            ->with(['user', 'baseline'])
            ->orderBy('due_at')
            ->get()
            ->unique('user_id');

        foreach ($due as $followup) {
            if ($followup->user && $followup->baseline) {
                $followup->user->notify(new RemeasureDue($followup));
            }
            $followup->forceFill(['reminder_sent_at' => now()])->save();
        }

        $this->info('Re-measure reminders sent: '.$due->count());

        return self::SUCCESS;
    }
}
