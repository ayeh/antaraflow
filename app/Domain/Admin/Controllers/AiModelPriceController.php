<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use App\Domain\AI\Models\AiModelPrice;
use App\Domain\AI\Services\AiPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AiModelPriceController extends Controller
{
    public function index(): View
    {
        $prices = AiModelPrice::query()
            ->orderBy('provider')
            ->orderByDesc('priority')
            ->orderBy('pattern')
            ->get();

        return view('admin.ai.prices', compact('prices'));
    }

    public function store(Request $request): RedirectResponse
    {
        AiModelPrice::query()->create($this->validated($request));
        AiPricingService::flushCache();

        return redirect()->route('admin.ai.prices.index')->with('success', __('Model price added.'));
    }

    public function update(Request $request, AiModelPrice $price): RedirectResponse
    {
        $price->update($this->validated($request));
        AiPricingService::flushCache();

        return redirect()->route('admin.ai.prices.index')->with('success', __('Model price updated.'));
    }

    public function destroy(AiModelPrice $price): RedirectResponse
    {
        $price->delete();
        AiPricingService::flushCache();

        return redirect()->route('admin.ai.prices.index')->with('success', __('Model price removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:50'],
            'pattern' => ['required', 'string', 'max:191'],
            'input_per_mtok' => ['nullable', 'numeric', 'min:0'],
            'output_per_mtok' => ['nullable', 'numeric', 'min:0'],
            'cached_input_per_mtok' => ['nullable', 'numeric', 'min:0'],
            'per_minute' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $data['is_regex'] = $request->boolean('is_regex');

        return $data;
    }
}
