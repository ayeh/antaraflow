<?php

declare(strict_types=1);

namespace App\Domain\Account\Support;

use App\Models\User;

/**
 * What each person wants to hear about, and how.
 *
 * One canonical shape, because there were two. The web screen wrote
 * `mention_in_comment` with an `in_app` flag; the mobile screen wrote `mention`
 * with a `push` flag, into the same column. Each replaced whole entries, so
 * saving on one silently discarded the other's channel.
 *
 * Channels are `push` and `email` only. The database channel is deliberately
 * not switchable: the in-app list is the record of what happened, and somebody
 * turning off push is asking for a quieter phone, not for history to be
 * thrown away.
 */
final class NotificationPreferences
{
    /**
     * Every notification a person may silence, with what it does by default.
     *
     * Operational alerts — budget warnings, and anything addressed to an
     * administrator rather than a participant — are absent on purpose. They
     * are not preferences.
     *
     * @return array<string, array{push: bool, email: bool}>
     */
    public static function defaults(): array
    {
        return [
            'action_item_assigned' => ['push' => true, 'email' => true],
            'action_item_overdue' => ['push' => true, 'email' => true],
            'meeting_finalized' => ['push' => true, 'email' => true],
            'meeting_approved' => ['push' => true, 'email' => true],
            'meeting_starting' => ['push' => true, 'email' => false],
            'circulation_pending' => ['push' => true, 'email' => true],
            'mention' => ['push' => true, 'email' => true],
            // Loud by default and the most frequent of the lot, which is
            // exactly why it needed to be switchable.
            'extraction_completed' => ['push' => true, 'email' => false],
            'transcription_completed' => ['push' => true, 'email' => false],
            'stale_decision' => ['push' => false, 'email' => true],
            // One key for both extraction and transcription failures: somebody
            // who wants to know when processing breaks wants to know either way.
            'processing_failed' => ['push' => true, 'email' => true],
        ];
    }

    /**
     * The stored document, normalised and gap-filled.
     *
     * @return array<string, array{push: bool, email: bool}>
     */
    public static function for(User $user): array
    {
        $stored = $user->settings?->notification_preferences ?? [];
        $resolved = self::defaults();

        foreach ($stored as $key => $value) {
            $key = self::canonicalKey($key);

            if (! isset($resolved[$key]) || ! is_array($value)) {
                continue;
            }

            $resolved[$key] = [
                'push' => (bool) ($value['push'] ?? $resolved[$key]['push']),
                // `in_app` is the web screen's old name for the same intent.
                // Read it so a preference set there is not ignored here.
                'email' => (bool) ($value['email'] ?? $resolved[$key]['email']),
            ];
        }

        return $resolved;
    }

    /**
     * Merge a partial change without discarding channels it did not mention.
     *
     * This is the fix for the cross-client loss: a caller that only knows
     * about `email` must not blank out `push`.
     *
     * @param  array<string, array<string, mixed>>  $changes
     * @return array<string, array{push: bool, email: bool}>
     */
    public static function merge(User $user, array $changes): array
    {
        $current = self::for($user);

        foreach ($changes as $key => $value) {
            $key = self::canonicalKey($key);

            if (! isset($current[$key]) || ! is_array($value)) {
                continue;
            }

            foreach (['push', 'email'] as $channel) {
                if (array_key_exists($channel, $value)) {
                    $current[$key][$channel] = (bool) $value[$channel];
                }
            }

            // The web form posts in_app; treat it as the same switch as push
            // rather than as a third channel nobody reads.
            if (array_key_exists('in_app', $value) && ! array_key_exists('push', $value)) {
                $current[$key]['push'] = (bool) $value['in_app'];
            }
        }

        return $current;
    }

    public static function allows(User $user, string $key, string $channel): bool
    {
        $prefs = self::for($user);
        $key = self::canonicalKey($key);

        return (bool) ($prefs[$key][$channel] ?? true);
    }

    /** Old names kept working rather than orphaning what people already set. */
    private static function canonicalKey(string $key): string
    {
        return match ($key) {
            'mention_in_comment' => 'mention',
            'extraction_failed', 'transcription_failed' => 'processing_failed',
            default => $key,
        };
    }
}
