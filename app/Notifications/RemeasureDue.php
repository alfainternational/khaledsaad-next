<?php

namespace App\Notifications;

use App\Domain\Assessment\Models\AssessmentFollowup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تذكير إعادة القياس (spec 2026-11-09، الموجة 2). The mail names the ONE thing they were told to fix, because a
 * reminder that only says "retake the assessment" is a chore, while one that says "you were told to fix X — did it
 * move?" is the question they actually want answered.
 */
class RemeasureDue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AssessmentFollowup $followup) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $baseline = $this->followup->baseline;
        $weakest = \App\Domain\Assessment\Services\Catalog::DIMENSIONS[$baseline->weakest_dimension ?? ''] ?? null;

        $mail = (new MailMessage)
            ->subject('مرّ '.$this->followup->days.' يوماً — هل تحرّك الرقم؟ — أثر')
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('قبل '.$this->followup->days.' يوماً شخّصت مشروعك، وكانت النتيجة '.$baseline->overall.' من 100.');

        if ($weakest) {
            $mail->line('والأولوية الأولى كانت: **'.$weakest.'**.');
        }

        return $mail
            ->line('أعد التشخيص الآن — نفس الأسئلة — لترى ما تحرّك فعلاً وما بقي مكانه. يستغرق دقائق.')
            ->action('أعد التشخيص', route('assess.start'))
            ->line('إن لم تنفّذ شيئاً بعد، فإعادة القياس تخبرك بذلك بصراحة أكثر من الإحساس — وهذا بذاته مفيد.');
    }
}
