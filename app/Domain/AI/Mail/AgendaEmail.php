<?php

declare(strict_types=1);

namespace App\Domain\AI\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AgendaEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $emailBody,
        public string $meetingTitle,
        public ?string $meetingDate = null,
        public ?string $meetingUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.agenda',
            with: [
                'emailBody' => $this->emailBody,
                'meetingTitle' => $this->meetingTitle,
                'meetingDate' => $this->meetingDate,
                'meetingUrl' => $this->meetingUrl,
            ],
        );
    }
}
