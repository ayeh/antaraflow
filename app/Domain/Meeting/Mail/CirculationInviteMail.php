<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Mail;

use App\Domain\Meeting\Models\MomCirculationRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CirculationInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly MomCirculationRecipient $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->recipient->circulation->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mom-circulation-invite',
        );
    }
}
