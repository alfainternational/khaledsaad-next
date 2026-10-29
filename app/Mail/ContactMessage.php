<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessage extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $senderName, public string $senderEmail, public string $messageBody) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'رسالة جديدة من نموذج تواصل أثر', replyTo: [new Address($this->senderEmail, $this->senderName)]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact');
    }
}
