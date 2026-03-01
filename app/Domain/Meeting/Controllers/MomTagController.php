<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MomTag;
use App\Domain\Meeting\Requests\CreateMomTagRequest;
use App\Domain\Meeting\Requests\UpdateMomTagRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class MomTagController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', MomTag::class);

        $tags = MomTag::query()->withCount('meetings')->orderBy('name')->get();

        return view('tags.index', compact('tags'));
    }

    public function store(CreateMomTagRequest $request): RedirectResponse
    {
        $this->authorize('create', MomTag::class);

        $data = $request->validated();
        $data['organization_id'] = $request->user()->current_organization_id;

        MomTag::query()->create($data);

        return redirect()->route('tags.index')->with('success', 'Tag created.');
    }

    public function update(UpdateMomTagRequest $request, MomTag $momTag): RedirectResponse
    {
        $this->authorize('update', $momTag);

        $momTag->update($request->validated());

        return redirect()->route('tags.index')->with('success', 'Tag updated.');
    }

    public function destroy(MomTag $momTag): RedirectResponse
    {
        $this->authorize('delete', $momTag);

        $momTag->delete();

        return redirect()->route('tags.index')->with('success', 'Tag deleted.');
    }
}
