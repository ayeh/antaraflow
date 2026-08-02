/** Floor for the dBFS scale; anything quieter is reported as this value. */
export const SILENCE_DBFS = -100;

/** Peaks above this are treated as clipping. */
export const CLIPPING_DBFS = -1;

/**
 * Convert a linear amplitude in the range 0..1 to dBFS.
 */
export function toDbfs(amplitude) {
    if (amplitude <= 0) {
        return SILENCE_DBFS;
    }

    return Math.max(SILENCE_DBFS, 20 * Math.log10(amplitude));
}

/**
 * Root-mean-square of an AnalyserNode byte time-domain buffer, returned as a
 * linear amplitude in the range 0..1. Byte time-domain data is centred on 128.
 */
export function rmsFromTimeDomain(buffer) {
    if (buffer.length === 0) {
        return 0;
    }

    let sum = 0;

    for (let i = 0; i < buffer.length; i++) {
        const sample = (buffer[i] - 128) / 128;
        sum += sample * sample;
    }

    return Math.sqrt(sum / buffer.length);
}

/**
 * Whether a peak level is hot enough to be treated as clipping.
 */
export function isClipping(peakDbfs) {
    return peakDbfs > CLIPPING_DBFS;
}
