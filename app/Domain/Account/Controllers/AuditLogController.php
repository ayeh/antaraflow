<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\AuditLog;
use App\Domain\Account\Models\Organization;
use App\Domain\Account\Services\AuthorizationService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuthorizationService $authService) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $organization = Organization::findOrFail($user->current_organization_id);

        abort_unless(
            $this->authService->hasPermission($user, $organization, 'view_audit_log'),
            403,
            'Only admins and owners can view audit logs.'
        );

        $logs = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->with('user')
            ->when($request->input('action'), fn ($q, $action) => $q->where('action', $action))
            ->when($request->input('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(50);

        $actions = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->distinct()
            ->pluck('action')
            ->sort()
            ->values();

        $orgUsers = User::query()
            ->whereHas('organizations', fn ($q) => $q->where('organization_id', $user->current_organization_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('audit-log.index', compact('logs', 'actions', 'orgUsers'));
    }
}
