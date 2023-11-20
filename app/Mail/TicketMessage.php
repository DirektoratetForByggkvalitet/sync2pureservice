<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Content, Envelope, Attachment};
use Illuminate\Queue\SerializesModels;
use App\Models\Ticket;

class TicketMessage extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Ticket $ticket,
        public bool $includeFonts = false
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope {
        return new Envelope(
            subject: $this->ticket->emailSubject()
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content {
        return new Content(
            view: 'message'
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array {
        $attachments = [];
        if ($this->ticket->attachments && count($this->ticket->attachments)):
            foreach ($this->ticket->attachments as $file):
                $attachments[] = Attachment::fromStorage($file);
            endforeach;
        endif;
        return $attachments;
    }
}
