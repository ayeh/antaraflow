<?php

declare(strict_types=1);

namespace App\Domain\AI\Controllers;

use App\Domain\AI\Models\ProactiveInsight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class MemoAdvisorController extends Controller
{
    public function index(): View
    {
        $insights = ProactiveInsight::query()
            ->active()
            ->orderByRaw("FIELD(severity, 'critical', 'warning', 'info')")
            ->orderByDesc('generated_at')
            ->paginate(20);

        return view('insights.index', compact('insights'));
    }

    public function markRead(ProactiveInsight $insight): RedirectResponse
    {
        $insight->update(['is_read' => true]);

        return redirect()->back()->with('success', 'Insight marked as read.');
    }

    public function dismiss(ProactiveInsight $insight): RedirectResponse
    {
        $insight->update(['is_dismissed' => true]);

        return redirect()->back()->with('success', 'Insight dismissed.');
    }
}
