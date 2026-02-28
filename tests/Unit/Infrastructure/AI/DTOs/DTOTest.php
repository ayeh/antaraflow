<?php

declare(strict_types=1);

use App\Infrastructure\AI\DTOs\ExtractedActionItem;
use App\Infrastructure\AI\DTOs\ExtractedDecision;
use App\Infrastructure\AI\DTOs\MeetingSummary;
use App\Infrastructure\AI\DTOs\TranscriptionResult;
use App\Infrastructure\AI\DTOs\TranscriptionSegmentData;

test('MeetingSummary can be created', function () {
    $dto = new MeetingSummary('summary', 'key points', 0.95);

    expect($dto->summary)->toBe('summary');
    expect($dto->keyPoints)->toBe('key points');
    expect($dto->confidenceScore)->toBe(0.95);
});

test('TranscriptionResult can be created', function () {
    $segment = new TranscriptionSegmentData(
        text: 'Hello world',
        startTime: 0.0,
        endTime: 1.5,
        speaker: 'Speaker 1',
        confidence: 0.99,
    );

    $dto = new TranscriptionResult(
        fullText: 'Hello world',
        confidence: 0.95,
        segments: [$segment],
        durationSeconds: 90,
    );

    expect($dto->fullText)->toBe('Hello world');
    expect($dto->confidence)->toBe(0.95);
    expect($dto->segments)->toHaveCount(1);
    expect($dto->durationSeconds)->toBe(90);
});

test('TranscriptionResult defaults durationSeconds to null', function () {
    $dto = new TranscriptionResult(
        fullText: 'Hello',
        confidence: 0.9,
        segments: [],
    );

    expect($dto->durationSeconds)->toBeNull();
});

test('TranscriptionSegmentData can be created with minimal arguments', function () {
    $dto = new TranscriptionSegmentData(
        text: 'Hello',
        startTime: 0.0,
        endTime: 1.0,
    );

    expect($dto->text)->toBe('Hello');
    expect($dto->startTime)->toBe(0.0);
    expect($dto->endTime)->toBe(1.0);
    expect($dto->speaker)->toBeNull();
    expect($dto->confidence)->toBeNull();
});

test('ExtractedActionItem can be created', function () {
    $dto = new ExtractedActionItem(
        title: 'Review PR',
        description: 'Review the pull request for feature X',
        assignee: 'John',
        dueDate: '2026-03-01',
        priority: 'high',
    );

    expect($dto->title)->toBe('Review PR');
    expect($dto->description)->toBe('Review the pull request for feature X');
    expect($dto->assignee)->toBe('John');
    expect($dto->dueDate)->toBe('2026-03-01');
    expect($dto->priority)->toBe('high');
});

test('ExtractedActionItem defaults optional fields', function () {
    $dto = new ExtractedActionItem(title: 'Review PR');

    expect($dto->title)->toBe('Review PR');
    expect($dto->description)->toBeNull();
    expect($dto->assignee)->toBeNull();
    expect($dto->dueDate)->toBeNull();
    expect($dto->priority)->toBe('medium');
});

test('ExtractedDecision can be created', function () {
    $dto = new ExtractedDecision(
        decision: 'Use Laravel for the backend',
        context: 'Team discussed framework options',
        madeBy: 'CTO',
    );

    expect($dto->decision)->toBe('Use Laravel for the backend');
    expect($dto->context)->toBe('Team discussed framework options');
    expect($dto->madeBy)->toBe('CTO');
});

test('ExtractedDecision defaults optional fields', function () {
    $dto = new ExtractedDecision(decision: 'Use Laravel');

    expect($dto->decision)->toBe('Use Laravel');
    expect($dto->context)->toBeNull();
    expect($dto->madeBy)->toBeNull();
});
