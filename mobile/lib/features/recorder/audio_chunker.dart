import 'dart:async';
import 'dart:io';
import 'dart:math' as math;
import 'dart:typed_data';

import 'package:flutter/foundation.dart';
import 'package:path/path.dart' as p;
import 'package:record/record.dart';

/// One slice of audio, already on disk and ready to post.
class AudioChunk {
  const AudioChunk({
    required this.number,
    required this.file,
    required this.start,
    required this.end,
  });

  final int number;
  final File file;
  final Duration start;
  final Duration end;
}

/// Turns the microphone into a sequence of fixed-length WAV files.
///
/// Recording to a file and restarting the recorder every fifteen seconds is the
/// obvious approach and it is wrong: each restart drops audio, and fifteen
/// seconds later it drops more, so a two-hour sitting loses minutes of speech
/// in slivers nobody can point at. Instead the recorder streams raw PCM
/// continuously and this class cuts the stream — the cut is arithmetic on a
/// sample count, so no audio is lost and chunk boundaries line up exactly with
/// the times reported to the server.
///
/// The WAV header is written per chunk because the server transcribes chunks
/// independently; raw PCM would arrive as a file no decoder recognises.
class AudioChunker {
  AudioChunker({this.chunkSeconds = 15});

  /// 16 kHz mono is what speech recognition resamples to anyway, and it is a
  /// third of the bytes of 48 kHz over a phone connection during a meeting.
  static const sampleRate = 16000;
  static const _channels = 1;
  static const _bytesPerSample = 2;

  final int chunkSeconds;

  final _recorder = AudioRecorder();
  final _chunks = StreamController<AudioChunk>.broadcast();

  /// Exposed as a listenable rather than through state so the waveform can
  /// repaint at audio rate without rebuilding the screen around it.
  final level = ValueNotifier<double>(0);

  final _buffer = BytesBuilder(copy: false);
  StreamSubscription<Uint8List>? _subscription;
  Directory? _scratch;

  /// Chunk writes are chained rather than fired in parallel, so chunks reach
  /// the outbox in the order they were cut.
  Future<void> _writes = Future<void>.value();

  int _samplesWritten = 0;
  int _chunkStartSample = 0;
  int _chunkNumber = 0;
  bool _stopped = false;

  /// Audio already on the server when this run began. Everything this class
  /// reports — the clock, and the start and end of every chunk — is measured
  /// from here, so a resumed sitting continues the recording rather than
  /// overwriting the beginning of it.
  Duration _offset = Duration.zero;

  Stream<AudioChunk> get chunks => _chunks.stream;

  int get _bytesPerChunk =>
      sampleRate * _channels * _bytesPerSample * chunkSeconds;

  /// How much audio has actually been captured. Derived from samples, not from
  /// a wall clock, so a pause or a slow frame cannot make the timer disagree
  /// with the recording it is timing.
  Duration get position => _samplesAt(_samplesWritten);

  Future<bool> hasPermission() => _recorder.hasPermission();

  /// [fromChunk] and [alreadyRecorded] come from the server when an existing
  /// session is rejoined; both are zero for a new one.
  Future<void> start(
    Directory scratch, {
    int fromChunk = 0,
    Duration alreadyRecorded = Duration.zero,
  }) async {
    _scratch = scratch;
    _chunkNumber = fromChunk;
    _offset = alreadyRecorded;

    final stream = await _recorder.startStream(
      const RecordConfig(
        encoder: AudioEncoder.pcm16bits,
        sampleRate: sampleRate,
        numChannels: _channels,
        // Deliberately off, matching the browser recorder: these are tuned for
        // one person close to a headset. In a meeting room noise suppression
        // gates distant speech out, and auto gain pumps between a loud chair
        // and a quiet member.
        echoCancel: false,
        noiseSuppress: false,
        autoGain: false,
      ),
    );

    _subscription = stream.listen(_onData, onError: (Object _) {});
  }

  Future<void> pause() async {
    await _recorder.pause();
    level.value = 0;
  }

  Future<void> resume() => _recorder.resume();

  /// Stops the microphone and emits whatever is still buffered, so the last
  /// few seconds of a meeting are not the ones that go missing.
  Future<void> stop() async {
    if (_stopped) return;
    _stopped = true;

    await _recorder.stop();
    await _subscription?.cancel();
    await _flush();

    level.value = 0;
  }

  Future<void> dispose() async {
    await stop();
    await _chunks.close();
    await _recorder.dispose();
    level.dispose();
  }

  void _onData(Uint8List data) {
    if (_stopped) return;

    _buffer.add(data);
    _samplesWritten += data.lengthInBytes ~/ _bytesPerSample;
    level.value = _levelOf(data);

    while (_buffer.length >= _bytesPerChunk) {
      _cut(_bytesPerChunk);
    }
  }

  Future<void> _flush() async {
    if (_buffer.length > 0) {
      _cut(_buffer.length);
    }

    await _writes;
  }

  void _cut(int bytes) {
    final scratch = _scratch;
    if (scratch == null) return;

    final all = _buffer.takeBytes();
    final payload = Uint8List.sublistView(all, 0, bytes);

    // Anything past the cut belongs to the next chunk.
    if (all.lengthInBytes > bytes) {
      _buffer.add(Uint8List.sublistView(all, bytes));
    }

    final number = _chunkNumber++;
    final start = _samplesAt(_chunkStartSample);
    _chunkStartSample += bytes ~/ _bytesPerSample;
    final end = _samplesAt(_chunkStartSample);

    final file = File(p.join(scratch.path, 'chunk-$number.wav'));
    final wav = wrapAsWav(payload);

    _writes = _writes.then((_) async {
      await file.writeAsBytes(wav, flush: true);

      if (!_chunks.isClosed) {
        _chunks.add(
          AudioChunk(number: number, file: file, start: start, end: end),
        );
      }
    });
  }

  Duration _samplesAt(int samples) {
    return _offset + Duration(milliseconds: (samples * 1000) ~/ sampleRate);
  }

  /// Peak, not RMS. A meter driven by RMS barely moves for speech across a
  /// table, which is exactly the case somebody is checking when they glance at
  /// the phone to see whether it is still hearing the room.
  double _levelOf(Uint8List data) {
    // Read through ByteData rather than an Int16List view: the platform hands
    // back buffers at arbitrary offsets, and a typed-list view over an odd
    // offset throws.
    final view = ByteData.sublistView(data);
    final samples = view.lengthInBytes ~/ 2;
    if (samples == 0) return 0;

    var peak = 0;
    // Every fourth sample: at 16 kHz that is still 4,000 readings a second,
    // and the meter cannot show more than the screen refreshes anyway.
    for (var i = 0; i < samples; i += 4) {
      final value = view.getInt16(i * 2, Endian.little).abs();
      if (value > peak) peak = value;
    }

    // Compressed, because linear amplitude spends most of its range on volumes
    // nobody in a meeting produces.
    return math.pow(peak / 32768, 0.55).toDouble().clamp(0, 1);
  }

  /// Wraps raw PCM in the 44-byte canonical WAV header.
  ///
  /// Every field here is one the server's decoder reads, and getting the byte
  /// rate or the data length wrong produces a file that opens and plays as
  /// silence rather than one that fails loudly — so this is tested directly.
  @visibleForTesting
  static Uint8List wrapAsWav(Uint8List pcm) {
    const headerBytes = 44;
    final byteRate = sampleRate * _channels * _bytesPerSample;
    final out = Uint8List(headerBytes + pcm.lengthInBytes);
    final view = ByteData.view(out.buffer);

    void ascii(int offset, String tag) {
      for (var i = 0; i < tag.length; i++) {
        out[offset + i] = tag.codeUnitAt(i);
      }
    }

    ascii(0, 'RIFF');
    view.setUint32(4, 36 + pcm.lengthInBytes, Endian.little);
    ascii(8, 'WAVE');
    ascii(12, 'fmt ');
    view.setUint32(16, 16, Endian.little);
    view.setUint16(20, 1, Endian.little); // PCM
    view.setUint16(22, _channels, Endian.little);
    view.setUint32(24, sampleRate, Endian.little);
    view.setUint32(28, byteRate, Endian.little);
    view.setUint16(32, _channels * _bytesPerSample, Endian.little);
    view.setUint16(34, _bytesPerSample * 8, Endian.little);
    ascii(36, 'data');
    view.setUint32(40, pcm.lengthInBytes, Endian.little);
    out.setRange(headerBytes, out.length, pcm);

    return out;
  }
}
