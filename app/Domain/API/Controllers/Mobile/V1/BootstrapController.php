<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\UsageTracking;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Admin\Services\BrandingService;
use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Resources\Mobile\UserResource;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything the app needs on a cold start, in one round trip.
 *
 * Six separate calls over a mobile connection is most of a second before the
 * first screen can draw, so profile, tenant, plan, feature flags and badge
 * counts are answered together.
 */
class BootstrapController extends MobileController
{
    public function __construct(
        private readonly BrandingService $branding,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $organization = Organization::query()->findOrFail($this->organizationId($request));

        $subscription = $organization->subscriptions()
            ->with('subscriptionPlan')
            ->latest('id')
            ->first();

        $plan = $subscription?->subscriptionPlan;
        $features = $plan?->features ?? [];
        $branding = $this->branding->getForOrganization($organization);

        return response()->json([
            'user' => new UserResource($user),
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'logo_url' => $organization->logo_path
                    ? \Illuminate\Support\Facades\Storage::disk('public')->url($organization->logo_path)
                    : ($branding['logo_url'] ?: null),
                'brand_color' => $branding['primary_color'] ?? null,
                'timezone' => $organization->timezone,
                'language' => $organization->language,
            ],
            'subscription' => [
                'plan' => $plan?->slug,
                'status' => $subscription?->status,
                'trial_ends_at' => $subscription?->trial_ends_at?->toIso8601String(),
                'features' => $features,
                'limits' => [
                    'max_users' => $plan?->max_users,
                    'max_meetings_per_month' => $plan?->max_meetings_per_month,
                    'max_audio_minutes_per_month' => $plan?->max_audio_minutes_per_month,
                    'max_storage_mb' => $plan?->max_storage_mb,
                ],
                'usage' => $this->usage(),
            ],
            'capabilities' => [
                'transcription' => (bool) ($features['transcription'] ?? false),
                'ai_summaries' => (bool) ($features['ai_summaries'] ?? false),
                'export' => (bool) ($features['export'] ?? false),
                'live_extraction' => (bool) ($features['ai_summaries'] ?? false),
                'ai_enabled' => $this->aiEnabled(),
                'voting' => true,
                'annotations' => false,
            ],
            'unread' => [
                'notifications' => $user->unreadNotifications()->count(),
                'action_items_due' => $this->actionItemsDue($user->id),
                'pending_approvals' => $this->pendingApprovals($user->email),
            ],
            'realtime' => [
                'driver' => config('broadcasting.default'),
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host'),
                'port' => config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme'),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** @return array<string, float|int> */
    private function usage(): array
    {
        $period = now()->format('Y-m');

        $metrics = UsageTracking::query()
            ->where('period', $period)
            ->pluck('value', 'metric');

        return [
            'period' => $period,
            'meetings' => (float) ($metrics['meetings'] ?? 0),
            'audio_minutes' => (float) ($metrics['audio_minutes'] ?? 0),
            'storage_mb' => (float) ($metrics['storage_mb'] ?? 0),
        ];
    }

    private function actionItemsDue(int $userId): int
    {
        return ActionItem::query()
            ->where('assigned_to', $userId)
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->endOfDay())
            ->whereNotIn('status', [ActionItemStatus::Completed->value, ActionItemStatus::Cancelled->value])
            ->count();
    }

    private function pendingApprovals(?string $email): int
    {
        if ($email === null) {
            return 0;
        }

        return MomCirculationRecipient::query()
            ->where('email', $email)
            ->whereNull('response')
            ->count();
    }

    private function aiEnabled(): bool
    {
        return (bool) \App\Domain\Admin\Models\PlatformSetting::getValue('ai_enabled', true);
    }
}
