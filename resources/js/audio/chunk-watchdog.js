/** How long each chunk records before it is flushed as a complete file. */
export const CHUNK_LENGTH_MS = 30000;

/**
 * How long the chunk cycle may go without flushing before it counts as stalled.
 * Two chunk-lengths: enough slack that a throttled-but-alive background tab
 * running a chunk long does not trip a false restart, short enough that a real
 * stall is caught within a minute rather than after the whole meeting.
 */
export const CHUNK_STALL_LIMIT_MS = CHUNK_LENGTH_MS * 2;

/**
 * Tracks whether the chunk-recording cycle has gone silent.
 *
 * The cycle keeps itself alive by chaining off each chunk recorder's `onstop`.
 * When a backgrounded tab swallows an `onstop` — or throttles the stop timer so
 * it never fires — the chain ends with no error and recording quietly stops
 * while the session stays open. The recorder's one-second tick asks this
 * whether too long has passed since the last flush and, if so, restarts.
 *
 * State, not a bare predicate, so the caller has one place that owns "when did a
 * chunk last flush" rather than threading a timestamp through every path.
 *
 * @return {{flushed: (nowMs: number) => void, reset: (nowMs: number) => void, isStalled: (nowMs: number) => boolean}}
 */
export function createChunkWatchdog({ stallLimitMs = CHUNK_STALL_LIMIT_MS } = {}) {
    let lastFlushedAt = null;

    return {
        /** Record that a chunk boundary was reached — the chain is advancing. */
        flushed(nowMs) {
            lastFlushedAt = nowMs;
        },

        /** Reseed the clock without counting a flush (start, resume, restart). */
        reset(nowMs) {
            lastFlushedAt = nowMs;
        },

        /**
         * Whether the cycle has gone longer than its limit without a flush.
         * False until the first reset/flush, so a watchdog that was never armed
         * never asks for a restart.
         */
        isStalled(nowMs) {
            if (lastFlushedAt === null) {
                return false;
            }

            return nowMs - lastFlushedAt > stallLimitMs;
        },
    };
}
