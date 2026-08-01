<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Account\Services\AuditService;
use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Admin\Services\AiControlService;
use App\Domain\Export\Blocks\BlockRegistry;
use App\Domain\Export\Blocks\Renderers\ActionItemsRenderer;
use App\Domain\Export\Blocks\Renderers\ActionTagRenderer;
use App\Domain\Export\Blocks\Renderers\AgendaRenderer;
use App\Domain\Export\Blocks\Renderers\AttendeesRenderer;
use App\Domain\Export\Blocks\Renderers\DividerRenderer;
use App\Domain\Export\Blocks\Renderers\FooterRenderer;
use App\Domain\Export\Blocks\Renderers\ImageRenderer;
use App\Domain\Export\Blocks\Renderers\LetterheadRenderer;
use App\Domain\Export\Blocks\Renderers\MetaRenderer;
use App\Domain\Export\Blocks\Renderers\PageBreakRenderer;
use App\Domain\Export\Blocks\Renderers\RichTextRenderer;
use App\Domain\Export\Blocks\Renderers\SignatureRenderer;
use App\Domain\Export\Blocks\Renderers\TableRenderer;
use App\Domain\Export\Blocks\Renderers\TitleRenderer;
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

        $this->app->singleton(BlockRegistry::class, function () {
            $registry = new BlockRegistry;
            $registry->register(new LetterheadRenderer);
            $registry->register(new TitleRenderer);
            $registry->register(new MetaRenderer);
            $registry->register(new AttendeesRenderer);
            $registry->register(new AgendaRenderer);
            $registry->register(new ActionItemsRenderer);
            $registry->register(new RichTextRenderer);
            $registry->register(new ActionTagRenderer);
            $registry->register(new TableRenderer);
            $registry->register(new ImageRenderer);
            $registry->register(new SignatureRenderer);
            $registry->register(new DividerRenderer);
            $registry->register(new PageBreakRenderer);
            $registry->register(new FooterRenderer);

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->app->make(AiControlService::class)->applyRuntimeOverrides();
    }
}
