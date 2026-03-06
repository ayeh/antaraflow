<?php

declare(strict_types=1);

namespace App\Domain\Account\Services;

use App\Domain\Account\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class SocialAuthService
{
    public function __construct(
        private OrganizationService $organizationService,
    ) {}

    public function findOrCreateUser(string $provider, SocialiteUser $socialUser): User
    {
        $socialAccount = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($socialAccount) {
            return $socialAccount->user;
        }

        $existingUser = User::query()
            ->where('email', $socialUser->getEmail())
            ->first();

        if ($existingUser) {
            $this->linkAccount($existingUser, $provider, $socialUser);

            return $existingUser;
        }

        return DB::transaction(function () use ($provider, $socialUser): User {
            $user = User::query()->create([
                'name' => $socialUser->getName() ?? $socialUser->getEmail(),
                'email' => $socialUser->getEmail(),
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
            ]);

            $this->organizationService->createOrganization(
                $user,
                $user->name."'s Workspace",
            );

            $this->linkAccount($user, $provider, $socialUser);

            $user->update(['password' => null]);

            return $user;
        });
    }

    public function linkAccount(User $user, string $provider, SocialiteUser $socialUser): SocialAccount
    {
        return SocialAccount::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => $provider,
            ],
            [
                'provider_id' => $socialUser->getId(),
                'provider_email' => $socialUser->getEmail(),
                'avatar_url' => $socialUser->getAvatar(),
            ],
        );
    }

    /**
     * @throws \RuntimeException
     */
    public function unlinkAccount(User $user, string $provider): void
    {
        $socialAccountCount = $user->socialAccounts()->count();
        $hasPassword = $user->password !== null;

        if (! $hasPassword && $socialAccountCount <= 1) {
            throw new \RuntimeException('Cannot unlink the only authentication method. Please set a password first.');
        }

        $user->socialAccounts()
            ->where('provider', $provider)
            ->delete();
    }
}
