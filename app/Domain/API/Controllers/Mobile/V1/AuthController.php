<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\Account\Services\SocialAuthService;
use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Requests\Mobile\LoginRequest;
use App\Domain\API\Resources\Mobile\OrganizationResource;
use App\Domain\API\Resources\Mobile\UserResource;
use App\Domain\API\Services\MobileAuthService;
use App\Models\User;
use App\Support\Enums\DevicePlatform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends MobileController
{
    public function __construct(
        private readonly MobileAuthService $authService,
        private readonly SocialAuthService $socialAuthService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::validate($request->only('email', 'password'))) {
            return $this->failure(
                __('The provided credentials do not match our records.'),
                'INVALID_CREDENTIALS',
                401,
            );
        }

        /** @var User $user */
        $user = User::query()->where('email', $request->string('email'))->firstOrFail();

        return $this->grant($user, $request);
    }

    public function social(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(['google', 'microsoft', 'mydigitalid'])],
            'access_token' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
            'device_id' => ['required', 'string', 'max:128'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'push_token' => ['sometimes', 'nullable', 'string', 'max:512'],
        ]);

        try {
            $socialUser = Socialite::driver($validated['provider'])->userFromToken($validated['access_token']);
            $user = $this->socialAuthService->findOrCreateUser($validated['provider'], $socialUser);
        } catch (\RuntimeException $e) {
            return $this->failure($e->getMessage(), 'SOCIAL_ACCOUNT_CONFLICT', 409);
        } catch (\Throwable) {
            return $this->failure(
                __('We could not verify that sign-in. Please try again.'),
                'SOCIAL_AUTH_FAILED',
                401,
            );
        }

        return $this->grant($user, $request);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $current = $user->currentAccessToken();
        $name = $current?->name ?? 'mobile:unknown:unknown';

        $current?->delete();

        $token = $user->createToken($name, MobileAuthService::ABILITIES, $this->authService->expiresAt());

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $deviceId = $this->deviceIdFromToken($user->currentAccessToken()?->name);

        if ($deviceId !== null) {
            $this->authService->revokeDevice($user, $deviceId);
        }

        $user->currentAccessToken()?->delete();

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->identityPayload($this->user($request)));
    }

    public function switchOrganization(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $validated = $request->validate([
            'organization_id' => ['required', 'integer'],
        ]);

        if (! $user->organizations()->whereKey($validated['organization_id'])->exists()) {
            return $this->failure(
                __('You do not have access to this organization.'),
                'ORGANIZATION_FORBIDDEN',
                403,
            );
        }

        // Persisted, unlike the per-request X-Organization-Id override: this is
        // the person deliberately changing where they are working.
        $user->update(['current_organization_id' => $validated['organization_id']]);

        return response()->json($this->identityPayload($user->fresh()));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        // Always the same answer, so this cannot be used to discover which
        // email addresses have accounts.
        return response()->json([
            'message' => __('If that email is registered, a reset link is on its way.'),
        ]);
    }

    private function grant(User $user, Request $request): JsonResponse
    {
        if ($user->organizations()->where('is_suspended', true)->exists()
            && ! $user->organizations()->where('is_suspended', false)->exists()) {
            return $this->failure(
                __('This account is suspended. Please contact support.'),
                'ACCOUNT_SUSPENDED',
                423,
            );
        }

        $token = $this->authService->issueToken(
            $user,
            $request->string('device_id')->toString(),
            $request->string('device_name')->toString(),
        );

        $this->authService->registerDevice(
            $user,
            $request->string('device_id')->toString(),
            $request->string('platform')->toString(),
            $request->string('push_token')->toString() ?: null,
            $request->header('X-Client-Version'),
            $user->language,
        );

        $user->update(['last_login_at' => now()]);

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            ...$this->identityPayload($user->fresh()),
        ]);
    }

    /** @return array<string, mixed> */
    private function identityPayload(User $user): array
    {
        $organizations = $user->organizations()->get();

        return [
            'user' => new UserResource($user),
            'organizations' => $organizations->map(
                fn ($organization) => new OrganizationResource($organization, $user->current_organization_id)
            ),
            'abilities' => MobileAuthService::ABILITIES,
        ];
    }

    private function deviceIdFromToken(?string $tokenName): ?string
    {
        if ($tokenName === null || ! str_starts_with($tokenName, 'mobile:')) {
            return null;
        }

        $parts = explode(':', $tokenName);

        return $parts[1] ?? null;
    }
}
