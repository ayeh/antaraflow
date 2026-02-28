<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Account\Services\AuditService;
use App\Domain\Account\Services\AuthorizationService;
use App\Infrastructure\AI\AIProviderFactory;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AIProviderInterface::class, function () {
            $defaultProvider = config('ai.default', 'openai');
            $config = config("ai.providers.{$defaultProvider}", []);

            return AIProviderFactory::make($defaultProvider, $config);
        });

        $this->app->singleton(AuditService::class);
        $this->app->singleton(AuthorizationService::class);
    }
}
