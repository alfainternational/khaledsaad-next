<?php

namespace App\Mail;

use App\Domain\Insights\Models\LearnerNudge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * «أصلك ينتظرك» (blueprint §6.1 slot 6): a reminder to a learner who stopped inside a lesson for 7 days.
 * Queued like every other mail here; the delivery result is written back to the learner_nudges row.
 */
class AssetWaitingReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public LearnerNudge $nudge) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'أصلك ينتظرك في أثر');
    }

    public function content(): Content
    {
        $lesson = $this->nudge->lesson()->with('course')->first();
        $live = $lesson && $lesson->isPublished() && $lesson->course?->is_published;

        return new Content(markdown: 'mail.asset-waiting-reminder', with: [
            'name' => $this->nudge->user?->name,
            'lessonTitle' => $lesson?->title,
            'assetTitle' => $lesson?->outcome_title,
            'url' => $live ? route('academy.lesson', [$lesson->course, $lesson]) : route('dashboard'),
        ]);
    }

    /** Runs inside the queue worker: a delivered message marks the nudge as sent. */
    public function send($mailer)
    {
        $sent = parent::send($mailer);
        LearnerNudge::query()->whereKey($this->nudge->getKey())->update(['status' => 'sent', 'sent_at' => now(), 'error' => null, 'updated_at' => now()]);

        return $sent;
    }

    /** Called by the queue once the job has exhausted its tries. */
    public function failed(Throwable $e): void
    {
        LearnerNudge::query()->whereKey($this->nudge->getKey())->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 480, ''), 'updated_at' => now()]);
    }
}
