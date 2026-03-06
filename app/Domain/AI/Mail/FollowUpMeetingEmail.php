<?php

declare(strict_types=1);

namespace App\Domain\AI\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FollowUpMeetingEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $emailBody,
        public string $meetingTitle,
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
            view: 'emails.follow-up',
            with: [
                'emailBody' => $this->emailBody,
                'meetingTitle' => $this->meetingTitle,
                'meetingUrl' => $this->meetingUrl,
            ],
        );
    }
}
