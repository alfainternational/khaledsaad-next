<?php

namespace App\Notifications;

use App\Domain\Academy\Models\RealWorldCheckin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email reminder on the check-in due date: "سجّل نتيجتك الحقيقية" (blueprint §4.5 principle 5). */
class RealWorldCheckinReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly RealWorldCheckin $checkin) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lesson = $this->checkin->lesson()->with('course')->first();
        $url = $lesson ? route('academy.lesson', [$lesson->course, $lesson]).'#part-apply' : route('dashboard');

        return (new MailMessage)
            ->subject('حان وقت تسجيل نتيجتك الواقعية — أثر')
            ->greeting('مرحباً '.$notifiable->name.'،')
            ->line('قبل أسبوع التزمت بفعل واقعي في درس «'.($lesson?->title ?? '').'»:')
            ->line('«'.$this->checkin->action.'»')
            ->line('سجّل الآن ما حدث فعلاً؛ تُحدّث لوحتك ويفتح الدرس التالي بأرقامك.')
            ->action('سجّل النتيجة', $url)
            ->line('إن لم تنفّذه بعد، لا بأس: سجّل ما تستطيع، فالرقم الصغير أفضل من الإحساس.');
    }
}
