<?php

declare(strict_types=1);

use App\Domain\AI\Services\AiPricingService;
use App\Infrastructure\AI\Providers\DisabledTranscriber;
use App\Infrastructure\AI\Providers\OpenAiTranscriber;
use App\Infrastructure\AI\Providers\OpenAIWhisperTranscriber;
use App\Infrastructure\AI\Providers\WhisperLocalTranscriber;
use App\Infrastructure\AI\TranscriberFactory;
use App\Support\Enums\TranscriptionMode;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps uploads on whisper and sends live chunks to the cheaper model', function (): void {
    $factory = app(TranscriberFactory::class);

    expect($factory->for(TranscriptionMode::Upload))
        ->toBeInstanceOf(OpenAIWhisperTranscriber::class)
        ->and($factory->for(TranscriptionMode::Live))
        ->toBeInstanceOf(OpenAiTranscriber::class)
        ->and($factory->for(TranscriptionMode::Live)->supportsDiarization())
        ->toBeFalse();
});

it('switches uploads to the diarizing model when it is configured', function (): void {
    config(['ai.providers.openai.upload_transcription_model' => 'gpt-4o-transcribe-diarize']);

    $upload = app(TranscriberFactory::class)->for(TranscriptionMode::Upload);

    expect($upload)->toBeInstanceOf(OpenAiTranscriber::class)
        ->and($upload->supportsDiarization())->toBeTrue();
});

it('honours the local whisper transcriber setting', function (): void {
    config(['ai.transcriber' => 'whisper_local']);

    expect(app(TranscriberFactory::class)->for(TranscriptionMode::Upload))
        ->toBeInstanceOf(WhisperLocalTranscriber::class);
});

it('returns the disabled transcriber when AI is switched off', function (): void {
    App\Domain\Admin\Models\PlatformSetting::query()->updateOrCreate(
        ['key' => 'ai_enabled'],
        ['value' => json_encode(false)],
    );

    expect(app(TranscriberFactory::class)->for(TranscriptionMode::Upload))
        ->toBeInstanceOf(DisabledTranscriber::class);
});

it('costs a duration-billed transcription model per minute', function (): void {
    expect(app(AiPricingService::class)->transcriptionCost('gpt-transcribe', 120))
        ->toBe(round(2 * 0.0045, 6));
});

it('costs a token-billed transcription model from its token counts', function (): void {
    // 60k audio-input tokens at $2.50/1M plus 2k output at $10/1M.
    expect(app(AiPricingService::class)->transcriptionCost('gpt-4o-transcribe-diarize', 600, 60_000, 2_000))
        ->toBe(round(0.15 + 0.02, 6));
});

it('does not charge a token-billed model on duration alone', function (): void {
    expect(app(AiPricingService::class)->transcriptionCost('gpt-4o-transcribe-diarize', 600))
        ->toBe(0.0);
});
