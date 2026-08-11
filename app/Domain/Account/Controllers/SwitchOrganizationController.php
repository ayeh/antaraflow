<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class SwitchOrganizationController extends Controller
{
    public function __invoke(Organization $organization): RedirectResponse
    {
        $user = auth()->user();

        abort_unless(
            $user->organizations()->whereKey($organization->id)->exists(),
            403
        );

        $user->update(['current_organization_id' => $organization->id]);

        return redirect()->route('dashboard');
    }
}
