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
     * Oppretter en ny e-postmelding fra en sak
     * Det er også mulig å oppgi
     * $subject, $content og $attachments for å overstyre saksdataene
     */
    public function __construct(
        public Ticket|null $ticket = null,
        public bool $includeFonts = false,
        public string|null $title = null,
        public string|null $contents = null,
        public array|null $attachFiles = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope {
        return new Envelope(
            subject: $this->title ? $this->title : $this->ticket->emailSubject()
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content {
        return new Content(
            view: $this->contents ? 'rawmessage' : 'message'
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array {
        $attachments = [];
        $attachmentArray = $this->attachFiles ? $this->attachFiles : $this->ticket->attachments;
        if (count($attachmentArray)):
            foreach ($attachmentArray as $file):
                $attachments[] = Attachment::fromStorage($file);
            endforeach;
        endif;
        return $attachments;
    }
}
