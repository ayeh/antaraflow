<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Derives the friendly "Chrome on macOS" device string the Inputs list shows
 * next to who recorded each input.
 *
 * Deliberately small and dependency-free: this only has to tell a MacBook's
 * browser from a phone's app, not fingerprint the device. A User-Agent it does
 * not recognise yields null, and the UI falls back to its plain source badge.
 */
final class DeviceLabel
{
    /**
     * The tag that marks a recording as made in a web browser, appended so the
     * list reads "Chrome on macOS · Web" and lines up with the app's own tag.
     */
    public const WEB_CHANNEL = 'Web';

    /**
     * The tag the mobile app's device labels carry, e.g.
     * "iPhone 15 Pro · antaraNote app".
     */
    public const APP_CHANNEL = 'antaraNote app';

    /**
     * A web capture's label from its request User-Agent, or null when the
     * agent is missing or unrecognised.
     */
    public static function fromUserAgent(?string $userAgent): ?string
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '') {
            return null;
        }

        $browser = self::browser($userAgent);
        $os = self::operatingSystem($userAgent);

        if ($browser === null && $os === null) {
            return null;
        }

        $device = $browser ?? 'Browser';

        if ($os !== null) {
            $device .= ' on '.$os;
        }

        return self::web($device);
    }

    /**
     * Appends the web channel tag to a bare device string.
     */
    public static function web(string $device): string
    {
        return self::withChannel($device, self::WEB_CHANNEL);
    }

    /**
     * Appends the app channel tag to a device string reported by the mobile
     * app, e.g. "iPhone 15 Pro" becomes "iPhone 15 Pro · antaraNote app".
     */
    public static function app(string $device): string
    {
        return self::withChannel($device, self::APP_CHANNEL);
    }

    private static function withChannel(string $device, string $channel): string
    {
        $device = trim($device);

        if ($device === '') {
            return $channel;
        }

        return $device.' · '.$channel;
    }

    private static function browser(string $userAgent): ?string
    {
        return match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/'), str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Chrome'), str_contains($userAgent, 'CriOS') => 'Chrome',
            // Safari's token trails Chrome's, so it is only Safari once Chrome
            // and the Chrome-on-iOS token are ruled out above.
            str_contains($userAgent, 'Safari') => 'Safari',
            default => null,
        };
    }

    private static function operatingSystem(string $userAgent): ?string
    {
        return match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X'), str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'CrOS') => 'ChromeOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };
    }
}
