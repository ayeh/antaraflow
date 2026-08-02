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

        clear() {
            count = 0;
            head = 0;
        },
    };
}
