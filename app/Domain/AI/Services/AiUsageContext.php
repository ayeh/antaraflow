<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use Illuminate\Support\Facades\Auth;

/**
 * Ambient attribution for AI usage — carries the organization, user, and
 * product feature that a call should be billed to. Web requests fall back to
 * the authenticated user; queued jobs (which have no auth) set it explicitly.
 *
 * Registered as a singleton so it lives for one request/job lifecycle.
 */
class AiUsageContext
{
    private ?int $organizationId = null;

    private ?int $userId = null;

    private ?string $feature = null;

    public function set(?int $organizationId = null, ?int $userId = null, ?string $feature = null): void
    {
        if ($organizationId !== null) {
            $this->organizationId = $organizationId;
        }

        if ($userId !== null) {
            $this->userId = $userId;
        }

        if ($feature !== null) {
            $this->feature = $feature;
        }
    }

    public function forFeature(string $feature): void
    {
        $this->feature = $feature;
    }

    public function reset(): void
    {
        $this->organizationId = null;
        $this->userId = null;
        $this->feature = null;
    }

    public function organizationId(): ?int
    {
        return $this->organizationId ?? Auth::user()?->current_organization_id;
    }

    public function userId(): ?int
    {
        return $this->userId ?? Auth::id();
    }

    public function feature(): ?string
    {
        return $this->feature;
    }
}
