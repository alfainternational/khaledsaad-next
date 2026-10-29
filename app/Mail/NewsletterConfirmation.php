<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Double opt-in confirmation for the footer newsletter. Queued: the scheduler drains the queue every minute on shared hosting. */
class NewsletterConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'أكّد اشتراكك في نشرة أثر');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.newsletter-confirmation', with: [
            'confirmUrl' => route('newsletter.confirm', $this->token),
            'unsubscribeUrl' => route('newsletter.unsubscribe', $this->token),
        ]);
    }
}
