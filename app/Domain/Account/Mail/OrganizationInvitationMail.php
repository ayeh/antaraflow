<?php

declare(strict_types=1);

namespace App\Domain\Account\Mail;

use App\Domain\Account\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public OrganizationInvitation $invitation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->invitation->organization->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.organization-invitation',
            with: [
                'organizationName' => $this->invitation->organization->name,
                'role' => $this->invitation->role->value,
                'acceptUrl' => route('invitations.accept.show', ['token' => $this->invitation->token]),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
