<?php

declare(strict_types=1);

use App\Support\DeviceLabel;

test('it names common desktop browser and OS pairs', function (string $userAgent, string $expected) {
    expect(DeviceLabel::fromUserAgent($userAgent))->toBe($expected);
})->with([
    'Chrome on macOS' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Chrome on macOS · Web',
    ],
    'Safari on macOS' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
        'Safari on macOS · Web',
    ],
    'Edge on Windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
        'Edge on Windows · Web',
    ],
    'Firefox on Windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Firefox on Windows · Web',
    ],
    'Safari on iPhone' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        'Safari on iPhone · Web',
    ],
    'Chrome on Android' => [
        'Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        'Chrome on Android · Web',
    ],
]);

test('it returns null for a missing or unrecognised user agent', function (?string $userAgent) {
    expect(DeviceLabel::fromUserAgent($userAgent))->toBeNull();
})->with([
    'null' => [null],
    'empty' => [''],
    'a bare tool name' => ['Symfony BrowserKit'],
    'curl' => ['curl/8.4.0'],
]);

test('it tags a mobile device string with the app channel', function () {
    expect(DeviceLabel::app('iPhone 15 Pro'))->toBe('iPhone 15 Pro · antaraNote app')
        ->and(DeviceLabel::app('  Google Pixel 7  '))->toBe('Google Pixel 7 · antaraNote app');
});
