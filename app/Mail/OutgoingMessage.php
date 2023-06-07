<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope, Address};
use Illuminate\Queue\SerializesModels;
use App\Models\Message;

class OutgoingMessage extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected Message $message,
    )
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(string $subject = 'Utgående melding'): Envelope
    {
        return new Envelope(
            subject: $subject,
            from: new Address('ikkesvar@dibk.no', 'Direktoratet for byggkvalitet (ikke svar)'),
            replyTo: new Address('post@dibk.no', 'Direktoratet for byggkvalitet')
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(string $view = 'message', array $data = []): Content
    {
        return new Content(
            view: $view,
            with: $data
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
