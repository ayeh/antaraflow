<?php

declare(strict_types=1);

namespace App\Domain\Export\Mail;

use App\Domain\Export\Models\MomEmailDistribution;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MomDistributionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public MomEmailDistribution $distribution) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->distribution->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.mom-distribution',
            with: ['distribution' => $this->distribution],
        );
    }
}
