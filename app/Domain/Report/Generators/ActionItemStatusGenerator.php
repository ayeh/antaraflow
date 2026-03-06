<?php

declare(strict_types=1);

namespace App\Domain\Report\Generators;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Report\Models\ReportTemplate;
use App\Support\Enums\ActionItemStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ActionItemStatusGenerator
{
    public function generate(ReportTemplate $template): string
    {
        $query = ActionItem::withoutGlobalScopes()
            ->where('organization_id', $template->organization_id)
            ->with(['assignedTo', 'meeting']);

        $openItems = (clone $query)
            ->whereIn('status', [ActionItemStatus::Open, ActionItemStatus::InProgress])
            ->orderBy('due_date')
            ->get();

        $overdueItems = $openItems->filter(fn ($item) => $item->due_date && $item->due_date->isPast());

        $byAssignee = $openItems->groupBy(fn ($item) => $item->assignedTo?->name ?? 'Unassigned');
        $byPriority = $openItems->groupBy(fn ($item) => $item->priority?->value ?? 'none');

        $totalOpen = $openItems->count();
        $totalOverdue = $overdueItems->count();

        $data = [
            'template' => $template,
            'openItems' => $openItems,
            'overdueItems' => $overdueItems,
            'byAssignee' => $byAssignee,
            'byPriority' => $byPriority,
            'totalOpen' => $totalOpen,
            'totalOverdue' => $totalOverdue,
        ];

        $pdf = Pdf::loadView('reports.pdf.action-item-status', $data);
        $filename = 'reports/'.$template->organization_id.'/action-item-status-'.now()->format('Y-m-d-His').'.pdf';

        Storage::disk('local')->put($filename, $pdf->output());

        return $filename;
    }
}
