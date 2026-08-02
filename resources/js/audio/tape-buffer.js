/**
 * Fixed-capacity ring buffer holding the visible waveform history. A two-hour
 * recording pushes tens of thousands of samples, so the buffer must never grow
 * with the length of the meeting.
 *
 * @return {{push: (value: number) => void, toArray: () => number[], clear: () => void}}
 */
export function createTape(capacity) {
    const values = new Float32Array(capacity);
    let count = 0;
    let head = 0;

    return {
        push(value) {
            values[head] = value;
            head = (head + 1) % capacity;
            count = Math.min(count + 1, capacity);
        },

        toArray() {
            const out = [];
            const start = count < capacity ? 0 : head;

            for (let i = 0; i < count; i++) {
                out.push(values[(start + i) % capacity]);
            }

            return out;
        },

        /**
         * Copy the history into `target`, oldest first and newest last, without
         * allocating. The renderer calls this on every animation frame, where
         * toArray()'s fresh array of boxed numbers would mean tens of thousands
         * of allocations a second for the length of a meeting.
         *
         * Slots with no sample behind them yet are filled with `fill`, so a fresh
         * recording scrolls in from the right instead of starting full. When
         * `target` is shorter than the history it receives the newest samples.
         */
        readInto(target, fill = 0) {
            const size = target.length;
            const taken = Math.min(count, size);
            const start = (count < capacity ? 0 : head) + (count - taken);
            const offset = size - taken;

            for (let i = 0; i < offset; i++) {
                target[i] = fill;
            }

            for (let i = 0; i < taken; i++) {
                target[offset + i] = values[(start + i) % capacity];
            }

            return target;
        },

        clear() {
            count = 0;
            head = 0;
        },
    };
}
