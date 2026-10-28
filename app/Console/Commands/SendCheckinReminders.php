<?php

namespace App\Console\Commands;

use App\Domain\Academy\Models\RealWorldCheckin;
use App\Notifications\RealWorldCheckinReminder;
use Illuminate\Console\Command;

class SendCheckinReminders extends Command
{
    protected $signature = 'platform:send-checkin-reminders';

    protected $description = 'Email learners whose real-world check-in is due and still unrecorded';

    public function handle(): int
    {
        $due = RealWorldCheckin::query()->where('mode', 'commit')->whereNull('recorded_at')->whereNull('reminder_sent_at')
            ->whereNotNull('due_at')->where('due_at', '<=', now())->with('user')->get();
        foreach ($due as $checkin) {
            if ($checkin->user) {
                $checkin->user->notify(new RealWorldCheckinReminder($checkin));
            }
            $checkin->update(['reminder_sent_at' => now()]);
        }
        $this->info('Reminders sent: '.$due->count());

        return self::SUCCESS;
    }
}
