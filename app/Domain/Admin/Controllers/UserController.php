<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->withTrashed()->with('organizations');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user): View
    {
        $user->load(['organizations', 'currentOrganization']);

        $meetingCount = MinutesOfMeeting::query()
            ->whereIn('organization_id', $user->organizations->pluck('id'))
            ->count();

        $actionItemCount = ActionItem::query()
            ->where('assigned_to', $user->id)
            ->count();

        return view('admin.users.show', compact('user', 'meetingCount', 'actionItemCount'));
    }

    public function suspend(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()->route('admin.users.show', $user)
            ->with('success', "User {$user->name} has been suspended.");
    }

    public function unsuspend(User $user): RedirectResponse
    {
        $user->restore();

        return redirect()->route('admin.users.show', $user)
            ->with('success', "User {$user->name} has been unsuspended.");
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        $request->session()->put('admin_impersonating', auth('admin')->id());

        auth('web')->loginUsingId($user->id);

        return redirect()->route('dashboard')
            ->with('success', "Now impersonating {$user->name}. Use the banner to return.");
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $users = User::query()->with('organizations')->get();

        return response()->streamDownload(function () use ($users) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Organizations', 'Registered', 'Last Login']);

            foreach ($users as $user) {
                fputcsv($handle, [
                    $user->name,
                    $user->email,
                    $user->organizations->pluck('name')->implode(', '),
                    $user->created_at->format('Y-m-d'),
                    $user->last_login_at?->format('Y-m-d H:i') ?? 'Never',
                ]);
            }

            fclose($handle);
        }, 'users-export-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
