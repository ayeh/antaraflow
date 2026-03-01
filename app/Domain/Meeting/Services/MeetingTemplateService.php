<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Services;

use App\Domain\Account\Services\AuditService;
use App\Domain\Meeting\Models\MeetingTemplate;
use App\Models\User;

class MeetingTemplateService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $user): MeetingTemplate
    {
        $data['created_by'] = $user->id;
        $data['organization_id'] = $user->current_organization_id;

        $template = MeetingTemplate::query()->create($data);
        $this->auditService->log('created', $template);

        return $template->fresh();
    }

    /** @param array<string, mixed> $data */
    public function update(MeetingTemplate $template, array $data): MeetingTemplate
    {
        $template->update($data);
        $this->auditService->log('updated', $template);

        return $template->fresh();
    }

    public function delete(MeetingTemplate $template): void
    {
        $this->auditService->log('deleted', $template);
        $template->delete();
    }
}
