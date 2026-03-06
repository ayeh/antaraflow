<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\BoardSetting;
use App\Domain\Meeting\Requests\UpdateBoardSettingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class BoardSettingController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $boardSetting = BoardSetting::firstOrNew(
            ['organization_id' => $user->current_organization_id],
            [
                'quorum_type' => 'percentage',
                'quorum_value' => 50,
                'require_chair' => false,
                'require_secretary' => false,
                'voting_enabled' => true,
                'chair_casting_vote' => false,
                'block_finalization_without_quorum' => false,
            ]
        );

        return view('settings.board-settings', compact('boardSetting'));
    }

    public function update(UpdateBoardSettingRequest $request): RedirectResponse
    {
        $user = $request->user();

        BoardSetting::updateOrCreate(
            ['organization_id' => $user->current_organization_id],
            [
                'quorum_type' => $request->validated('quorum_type'),
                'quorum_value' => $request->validated('quorum_value'),
                'require_chair' => $request->boolean('require_chair'),
                'require_secretary' => $request->boolean('require_secretary'),
                'voting_enabled' => $request->boolean('voting_enabled'),
                'chair_casting_vote' => $request->boolean('chair_casting_vote'),
                'block_finalization_without_quorum' => $request->boolean('block_finalization_without_quorum'),
            ]
        );

        return redirect()->route('settings.board.edit')
            ->with('success', 'Board settings updated successfully.');
    }
}
