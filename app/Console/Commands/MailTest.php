<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Send a plain test email through the configured mailer and surface the transport error
 * (without secrets) so SMTP problems can be diagnosed from the terminal instead of a 500 page.
 */
class MailTest extends Command
{
    protected $signature = 'platform:mail-test {to : Recipient address}';

    protected $description = 'Send a test email and report the mailer configuration and any transport error';

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $smtp = (array) config('mail.mailers.smtp', []);
        $this->line(sprintf(
            'mailer=%s host=%s port=%s scheme=%s from=%s',
            $mailer,
            $smtp['host'] ?? '-',
            $smtp['port'] ?? '-',
            $smtp['scheme'] ?? '(none)',
            config('mail.from.address'),
        ));

        try {
            Mail::raw('رسالة اختبار من منصة أثر — إذا وصلتك فالبريد يعمل.', function ($message): void {
                $message->to($this->argument('to'))->subject('اختبار البريد — أثر');
            });
        } catch (Throwable $e) {
            $this->error(get_class($e).': '.preg_replace('/(password|pass|pwd)=\S+/i', '$1=***', $e->getMessage()));

            return self::FAILURE;
        }

        $this->info('Sent. Check the inbox (and spam) of '.$this->argument('to'));

        return self::SUCCESS;
    }
}
