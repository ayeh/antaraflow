<?php

declare(strict_types=1);

namespace App\Domain\AI\Jobs;

use App\Domain\AI\Services\KnowledgeLinkService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildKnowledgeLinksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public MinutesOfMeeting $meeting,
    ) {}

    public function handle(KnowledgeLinkService $knowledgeLinkService): void
    {
        $knowledgeLinkService->autoLinkDecisionsToActionItems($this->meeting);
        $knowledgeLinkService->autoLinkTopicsAcrossMeetings($this->meeting);
    }
}
