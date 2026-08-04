<?php

declare(strict_types=1);

test('mom translation file exists and has required keys', function () {
    $translations = require dirname(__DIR__, 3).'/lang/ms/mom.php';

    expect($translations)->toBeArray()
        ->toHaveKey('confirm_button')
        ->toHaveKey('amendment_button')
        ->toHaveKey('deadline_banner')
        ->toHaveKey('deadline_warning')
        ->toHaveKey('confirm_success')
        ->toHaveKey('withdraw_success')
        ->toHaveKey('deadline_passed')
        ->toHaveKey('monitoring_panel_title');
});

test('mom english translation file exists and has the same keys', function () {
    $ms = require dirname(__DIR__, 3).'/lang/ms/mom.php';
    $en = require dirname(__DIR__, 3).'/lang/en/mom.php';

    expect(array_keys($en))->toBe(array_keys($ms));
});
