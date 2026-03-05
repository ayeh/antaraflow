<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(Request $request, int $step): View|RedirectResponse
    {
        if ($request->user()->onboarding_completed_at) {
            return redirect()->route('dashboard');
        }

        return match ($step) {
            1 => view('onboarding.step1', ['user' => $request->user()]),
            2 => view('onboarding.step2', [
                'organization' => $request->user()->currentOrganization,
            ]),
            3 => view('onboarding.step3'),
            default => redirect()->route('onboarding.step', ['step' => 1]),
        };
    }

    public function update(Request $request, int $step): RedirectResponse
    {
        return match ($step) {
            1 => $this->updateStep1($request),
            2 => $this->updateStep2($request),
            3 => $this->updateStep3($request),
            default => redirect()->route('onboarding.step', ['step' => 1]),
        };
    }

    public function skip(Request $request): RedirectResponse
    {
        $request->user()->update(['onboarding_completed_at' => now()]);

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to antaraFLOW! Create your first meeting to get started.');
    }

    private function updateStep1(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $request->user()->update($validated);

        return redirect()->route('onboarding.step', ['step' => 2]);
    }

    private function updateStep2(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'timezone' => 'required|string|timezone',
            'language' => 'required|string|in:en,ms',
        ]);

        $request->user()->currentOrganization->update($validated);

        return redirect()->route('onboarding.step', ['step' => 3]);
    }

    private function updateStep3(Request $request): RedirectResponse
    {
        $request->user()->update(['onboarding_completed_at' => now()]);

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to antaraFLOW! Create your first meeting to get started.');
    }
}
