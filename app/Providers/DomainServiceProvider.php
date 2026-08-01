<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Account\Services\AuditService;
use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Admin\Services\AiControlService;
use App\Infrastructure\AI\AIProviderFactory;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\Providers\DisabledAIProvider;
use App\Infrastructure\AI\TranscriberFactory;
use App\Support\Enums\TranscriptionMode;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AIProviderInterface::class, function ($app) {
            if (! $app->make(AiControlService::class)->isEnabled()) {
                return new DisabledAIProvider;
            }

            $defaultProvider = config('ai.default', 'openai');
            $config = config("ai.providers.{$defaultProvider}", []);

            return AIProviderFactory::make($defaultProvider, $config);
        });

        // Callers that do not care which model answers — voice notes, ad-hoc
        // checks — get the cheaper text-only transcriber. Jobs that need
        // speaker labels ask the factory for the upload model instead.
        $this->app->bind(
            TranscriberInterface::class,
            fn ($app) => $app->make(TranscriberFactory::class)->for(TranscriptionMode::Live),
        );

        $this->app->singleton(AuditService::class);
        $this->app->singleton(AuthorizationService::class);
        $this->app->singleton(\App\Domain\AI\Services\AiUsageContext::class);
    }

    public function boot(): void
    {
        $this->app->make(AiControlService::class)->applyRuntimeOverrides();
    }
}
