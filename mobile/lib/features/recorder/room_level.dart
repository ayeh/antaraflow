import 'dart:math' as math;

import 'package:flutter/foundation.dart';

/// What the room sounds like from wherever the phone happens to be lying.
enum RoomVerdict {
  /// Nobody has said enough for this to be worth an opinion. Not a problem —
  /// most of a meeting is one person talking and everybody else not.
  listening,

  /// Voices are arriving at a level transcription can work with.
  clear,

  /// Somebody is speaking and they are too far away to be transcribed well.
  faint,

  /// Not room tone, not distant speech — nothing. Either the microphone is
  /// covered or it is handing back zeroes, which Android does silently to a
  /// backgrounded app that has lost its foreground service.
  silent,
}

/// What one stretch of audio measured, in dBFS.
///
/// Sent to the server with every chunk. On its own a single reading says
/// whether the phone was placed well; across thousands of chunks, joined
/// against the confidence each one transcribed at, it answers the question the
/// capture-quality plan cannot currently answer with anything but a guess —
/// whether poor transcripts are a capture problem or a processing one.
@immutable
class LevelReading {
  const LevelReading({
    required this.peak,
    required this.speech,
    required this.noise,
    required this.frames,
  });

  /// Loudest sample seen, as dBFS. Detects clipping and digital silence, both
  /// of which the averages hide.
  final double peak;

  /// The 95th percentile of frame loudness — in practice, how loud it is when
  /// somebody talks. A mean would be dominated by the silence between
  /// sentences and would report every meeting as too quiet.
  final double speech;

  /// The 10th percentile: the room with nobody speaking into it.
  final double noise;

  final int frames;

  /// How far speech rises above the room it is spoken in. The number that
  /// decides whether a bad transcript is a level problem or a noise problem.
  double get headroom => speech - noise;

  Map<String, double> toJson() => {
    'peak_dbfs': _round(peak),
    'speech_dbfs': _round(speech),
    'noise_dbfs': _round(noise),
  };

  static double _round(double value) => (value * 10).roundToDouble() / 10;
}

/// Measures the room continuously, from the same PCM the recorder is writing.
///
/// Three consumers, one pass over the samples: the level meter, a per-chunk
/// reading for the server, and the verdicts behind the placement check and the
/// too-quiet warning. They are deliberately not three separate measurements —
/// a warning that disagreed with the meter on screen would be worse than no
/// warning.
///
/// Everything here works on frames rather than on buffers. The platform hands
/// back buffers of whatever size it feels like, so buffer-sized statistics
/// would mean something different on every device.
class RoomLevel {
  RoomLevel({
    this.sampleRate = 16000,
    this.frameMillis = 100,
    this.windowSeconds = 90,
    this.checkSeconds = 45,
  });

  /// Below this, speech is arriving but too faint to transcribe reliably.
  ///
  /// Provisional, and knowingly so: it is set from where distant speech tends
  /// to land rather than from measurement, exactly like the browser recorder's
  /// gain constants. The readings this class ships with every chunk are how it
  /// gets replaced with a number that came from real meetings.
  static const faintBelow = -45.0;

  /// Below this there is no room at all. A live microphone in an empty room
  /// still returns around -60 dBFS of tone; only a dead one returns this.
  static const deadBelow = -70.0;

  /// Below this, nothing audible happened.
  ///
  /// The honest boundary of what a level meter can tell you: an empty room and
  /// a room where somebody is speaking from impossibly far away produce the
  /// same trace, and warning about the second in every quiet moment of the
  /// first would train people to ignore the warning. A microphone that has
  /// genuinely stopped working is still caught, by [deadBelow].
  static const inaudibleBelow = -60.0;

  /// How far above the noise floor a frame has to be to count as somebody
  /// speaking rather than as the air conditioning.
  ///
  /// Deliberately low. The obvious value is around ten decibels, and it is
  /// wrong: a voice from the far end of a table clears the room it is spoken
  /// in by very little, so a wide gate rules out exactly the case worth
  /// warning about and reports it as nobody having spoken.
  static const speechOverNoise = 6.0;

  /// dBFS reported for digital silence, instead of negative infinity.
  static const floor = -100.0;

  final int sampleRate;
  final int frameMillis;
  final int windowSeconds;
  final int checkSeconds;

  /// Drives the waveform. Peak rather than RMS, and per buffer rather than per
  /// frame, because a meter is the thing somebody glances at to see whether the
  /// phone is still hearing the room — it has to move when they speak, not a
  /// tenth of a second later.
  final level = ValueNotifier<double>(0);

  /// Settles once, early, on how well the phone has been placed.
  final check = ValueNotifier<RoomVerdict>(RoomVerdict.listening);

  /// Keeps its opinion for the whole sitting, over a rolling window.
  final verdict = ValueNotifier<RoomVerdict>(RoomVerdict.listening);

  int get _frameSamples => sampleRate * frameMillis ~/ 1000;
  int get _windowFrames => windowSeconds * 1000 ~/ frameMillis;
  int get _checkFrames => checkSeconds * 1000 ~/ frameMillis;

  /// At least this many frames before any verdict at all, so a verdict is
  /// never formed from the first fraction of a second of a recording.
  int get _minFrames => 3000 ~/ frameMillis;

  double _sumSquares = 0;
  int _framePeak = 0;
  int _frameFilled = 0;

  final _windowLoudness = <double>[];
  final _windowPeaks = <double>[];

  final _chunkLoudness = <double>[];
  final _chunkPeaks = <double>[];

  int _checkFramesSeen = 0;
  bool _checkClosed = false;

  /// Frames since the rolling verdict was last worked out. Percentiles need a
  /// sort, and re-sorting nine hundred frames ten times a second would be the
  /// most expensive thing in the recorder for no gain — the verdict behind a
  /// ninety-second window cannot meaningfully change in under a second.
  int _sinceVerdict = 0;

  /// Feeds one buffer of signed 16-bit little-endian PCM.
  void add(Uint8List data) {
    // Through ByteData rather than an Int16List view: the platform hands back
    // buffers at arbitrary offsets, and a typed-list view over an odd offset
    // throws.
    final view = ByteData.sublistView(data);
    final samples = view.lengthInBytes ~/ 2;
    if (samples == 0) return;

    var bufferPeak = 0;

    for (var i = 0; i < samples; i++) {
      final value = view.getInt16(i * 2, Endian.little);
      final magnitude = value.abs();

      if (magnitude > bufferPeak) bufferPeak = magnitude;
      if (magnitude > _framePeak) _framePeak = magnitude;

      final scaled = value / 32768;
      _sumSquares += scaled * scaled;

      if (++_frameFilled >= _frameSamples) _closeFrame();
    }

    level.value = _meter(bufferPeak);
  }

  /// The reading for everything measured since the last call, and a fresh
  /// start for the next chunk.
  LevelReading takeChunk() {
    final reading = _read(_chunkLoudness, _chunkPeaks);

    _chunkLoudness.clear();
    _chunkPeaks.clear();

    return reading;
  }

  /// Drops the meter to nothing without forgetting the room.
  ///
  /// For pausing and stopping: the needle has to fall, but a sitting resumed
  /// two minutes later is the same room and should not have to earn its verdict
  /// again from scratch.
  void mute() => level.value = 0;

  void dispose() {
    level.dispose();
    check.dispose();
    verdict.dispose();
  }

  void _closeFrame() {
    final loudness = _dbfs(math.sqrt(_sumSquares / _frameFilled));
    final peak = _dbfs(_framePeak / 32768);

    _sumSquares = 0;
    _framePeak = 0;
    _frameFilled = 0;

    _chunkLoudness.add(loudness);
    _chunkPeaks.add(peak);

    _windowLoudness.add(loudness);
    _windowPeaks.add(peak);

    if (_windowLoudness.length > _windowFrames) {
      _windowLoudness.removeAt(0);
      _windowPeaks.removeAt(0);
    }

    _advanceCheck();

    if (++_sinceVerdict >= 10) {
      _sinceVerdict = 0;
      verdict.value = _verdictOf(_windowLoudness, _windowPeaks);
    }
  }

  /// The placement check runs on the opening of the recording and then stops.
  ///
  /// It closes as soon as it has an opinion, rather than after a fixed time,
  /// because the useful moment is whenever somebody first speaks — which in a
  /// room settling down might be ten seconds in or might be forty.
  ///
  /// It reads the rolling window rather than keeping its own: the check closes
  /// well before the window is long enough to have forgotten anything, so
  /// while it is open the two are the same frames.
  void _advanceCheck() {
    if (_checkClosed) return;

    _checkFramesSeen++;

    final reached = _verdictOf(_windowLoudness, _windowPeaks);

    if (reached != RoomVerdict.listening) {
      check.value = reached;
      _checkClosed = true;

      return;
    }

    if (_checkFramesSeen >= _checkFrames) _checkClosed = true;
  }

  RoomVerdict _verdictOf(List<double> loudness, List<double> peaks) {
    if (loudness.length < _minFrames) return RoomVerdict.listening;

    final reading = _read(loudness, peaks);

    // Checked before anything else, and on peak rather than on loudness: a
    // microphone handing back zeroes is not a quiet room, and telling somebody
    // to move the phone closer would be advice for a problem they do not have.
    if (reading.peak < deadBelow) return RoomVerdict.silent;

    if (reading.speech < inaudibleBelow) return RoomVerdict.listening;

    final threshold = reading.noise + speechOverNoise;
    final spoken = loudness.where((frame) => frame > threshold).length;

    // Nobody is talking. Common, and not something to warn about — the whole
    // reason the level is measured on a percentile is that silence between
    // sentences is normal. Held to the same minimum as everything else so that
    // a chair scraping cannot pass for somebody speaking.
    if (spoken < _minFrames) return RoomVerdict.listening;

    return reading.speech < faintBelow ? RoomVerdict.faint : RoomVerdict.clear;
  }

  LevelReading _read(List<double> loudness, List<double> peaks) {
    if (loudness.isEmpty) {
      return const LevelReading(
        peak: floor,
        speech: floor,
        noise: floor,
        frames: 0,
      );
    }

    return LevelReading(
      peak: peaks.reduce(math.max),
      speech: _percentile(loudness, 0.95),
      noise: _percentile(loudness, 0.10),
      frames: loudness.length,
    );
  }

  static double _percentile(List<double> values, double fraction) {
    final sorted = [...values]..sort();
    final index = ((sorted.length - 1) * fraction).round();

    return sorted[index];
  }

  static double _dbfs(double amplitude) {
    if (amplitude <= 0) return floor;

    return math.max(floor, 20 * math.log(amplitude) / math.ln10);
  }

  /// Compressed, because linear amplitude spends most of its range on volumes
  /// nobody in a meeting produces.
  static double _meter(int peak) =>
      math.pow(peak / 32768, 0.55).toDouble().clamp(0, 1);
}
