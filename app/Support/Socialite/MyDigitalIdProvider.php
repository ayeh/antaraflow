<?php

declare(strict_types=1);

namespace App\Support\Socialite;

use GuzzleHttp\RequestOptions;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

/**
 * Socialite driver for MyDigital ID — Malaysia's National Digital Identity.
 *
 * MyDigital ID is a standard OpenID Connect provider. All endpoints, scopes and
 * PKCE behaviour are resolved from the `services.mydigitalid` config so the
 * integration can be pointed at the sandbox or production issuer without code
 * changes once credentials are provisioned from the developer portal
 * (https://developer.digital-id.my).
 */
class MyDigitalIdProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The scopes requested from MyDigital ID.
     *
     * @var array<int, string>
     */
    protected $scopes = ['openid', 'profile', 'email'];

    /**
     * OpenID Connect uses a space to separate scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->endpoint('authorize_uri', '/auth'), $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->endpoint('token_uri', '/token');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->endpoint('userinfo_uri', '/me'), [
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ],
        ]);

        return json_decode((string) $response->getBody(), true) ?? [];
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['sub'] ?? $user['id'] ?? null,
            'name' => $user['name'] ?? $user['full_name'] ?? $user['preferred_username'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['picture'] ?? null,
        ]);
    }

    protected function usesPKCE(): bool
    {
        return (bool) config('services.mydigitalid.pkce', true);
    }

    /**
     * Resolve a configured absolute endpoint, falling back to a path appended
     * to the configured base URL.
     */
    protected function endpoint(string $key, string $defaultPath): string
    {
        $configured = config("services.mydigitalid.{$key}");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $base = rtrim((string) config('services.mydigitalid.base_url'), '/');

        return $base.$defaultPath;
    }
}
