/**
 * Test-only entrypoint. Exposes the pure audio helpers on window so browser
 * tests can exercise them with assertScript() without a real microphone.
 */
import * as chunkWatchdog from './audio/chunk-watchdog.js';
import * as level from './audio/level.js';
import * as quietWarning from './audio/quiet-warning.js';
import * as tapeBuffer from './audio/tape-buffer.js';

window.audioHarness = { chunkWatchdog, level, quietWarning, tapeBuffer };
