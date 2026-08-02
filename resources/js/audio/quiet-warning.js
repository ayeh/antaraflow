/** Speech below this level will not survive Opus encoding intelligibly. */
export const QUIET_THRESHOLD_DBFS = -40;

/** How long the level must stay low before the user is told. */
export const SUSTAINED_MS = 15_000;

/**
 * Tracks whether the captured level has been too quiet for long enough to be
 * worth interrupting the user. Fires at most once per recording: a warning
 * that repeats gets ignored, and worse, interrupts whoever is running the
 * meeting.
 *
 * @return {{observe: (levelDbfs: number, nowMs: number) => boolean, reset: () => void}}
 */
export function createQuietWarning({
    thresholdDbfs = QUIET_THRESHOLD_DBFS,
    sustainedMs = SUSTAINED_MS,
} = {}) {
    let quietSince = null;
    let alreadyFired = false;

    return {
        observe(levelDbfs, nowMs) {
            if (alreadyFired) {
                return false;
            }

            if (levelDbfs > thresholdDbfs) {
                quietSince = null;

                return false;
            }

            if (quietSince === null) {
                quietSince = nowMs;

                return false;
            }

            if (nowMs - quietSince < sustainedMs) {
                return false;
            }

            alreadyFired = true;

            return true;
        },

        reset() {
            quietSince = null;
            alreadyFired = false;
        },
    };
}
