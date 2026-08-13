import 'dart:async';
import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/foundation.dart';
import 'package:path/path.dart' as p;
import 'package:record/record.dart';

import 'room_level.dart';

/// One slice of audio, already on disk and ready to post.
class AudioChunk {
  const AudioChunk({
    required this.number,
    required this.file,
    required this.start,
    required this.end,
    required this.reading,
  });

  final int number;
  final File file;
  final Duration start;
  final Duration end;

  /// How loud this slice was, measured before it was written. Travels with the
  /// upload so the server can tell a badly-placed phone from a badly-processed
  /// recording without opening the audio again.
  final LevelReading reading;
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

  /// Everything the recorder knows about how the room sounds.
  final room = RoomLevel(sampleRate: sampleRate);

  /// Exposed as a listenable rather than through state so the waveform can
  /// repaint at audio rate without rebuilding the screen around it.
  ValueListenable<double> get level => room.level;

  /// Chunks that were cut but could not be written to disk.
  ///
  /// Anything above zero means audio from this sitting is gone and no amount
  /// of waiting will bring it back, so it has to reach the screen rather than
  /// sit in a log nobody reads.
  final writeFailures = ValueNotifier<int>(0);

  final _buffer = BytesBuilder(copy: false);
  StreamSubscription<Uint8List>? _subscription;
  Directory? _scratch;

  /// Chunk writes are chained rather than fired in parallel, so chunks reach
  /// the outbox in the order they were cut.
  Future<void> _writes = Future<void>.value();

  /// Audio still owed to the gap between this device joining and the next
  /// chunk boundary of the sitting it is joining.
  int _discardBytes = 0;

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

  /// Everything [start] does apart from opening the microphone.
  ///
  /// Split out so the cutter can be driven from a test without a device: the
  /// arithmetic that decides where chunks begin and end is the part worth
  /// proving, and it does not need a real microphone to be wrong.
  @visibleForTesting
  void prepare(
    Directory scratch, {
    int fromChunk = 0,
    Duration alreadyRecorded = Duration.zero,
    Duration discardFirst = Duration.zero,
  }) {
    _scratch = scratch;
    _chunkNumber = fromChunk;
    _offset = alreadyRecorded;
    _discardBytes =
        discardFirst.inMilliseconds * sampleRate * _bytesPerSample ~/ 1000;
  }

  /// Stands in for the microphone stream.
  @visibleForTesting
  void receive(Uint8List data) => _onData(data);

  /// Completes once every chunk cut so far has been written and emitted.
  @visibleForTesting
  Future<void> get settled => _writes;

  /// [fromChunk] and [alreadyRecorded] come from the server when an existing
  /// session is rejoined; both are zero for a new one.
  Future<void> start(
    Directory scratch, {
    int fromChunk = 0,
    Duration alreadyRecorded = Duration.zero,
    Duration discardFirst = Duration.zero,
  }) async {
    prepare(
      scratch,
      fromChunk: fromChunk,
      alreadyRecorded: alreadyRecorded,
      discardFirst: discardFirst,
    );

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
    room.mute();
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

    room.mute();
  }

  Future<void> dispose() async {
    await stop();
    await _chunks.close();
    await _recorder.dispose();
    room.dispose();
    writeFailures.dispose();
  }

  void _onData(Uint8List data) {
    if (_stopped) return;

    // Fed before the discard, never after. A satellite waiting for the next
    // boundary is already in the room with its microphone open, and the
    // placement check has no reason to sit blind for the first ten seconds of
    // a meeting it is listening to.
    room.add(data);

    final payload = _keep(data);
    if (payload == null) return;

    _buffer.add(payload);
    _samplesWritten += payload.lengthInBytes ~/ _bytesPerSample;

    while (_buffer.length >= _bytesPerChunk) {
      _cut(_bytesPerChunk);
    }
  }

  /// What is left of [data] once any audio owed to the alignment gap is gone.
  ///
  /// A device joining a sitting in progress lands mid-window. It throws that
  /// part away rather than sending a short chunk: a short chunk numbered N
  /// covers only the tail of the window the primary's chunk N covers, and if
  /// selection preferred it the opening seconds of that window would vanish
  /// from the transcript with nothing to show for it. At most fifteen seconds
  /// of satellite audio is the cheaper mistake.
  ///
  /// The discarded bytes must not reach [_samplesWritten] — the clock and
  /// every chunk boundary after it are counted from there.
  Uint8List? _keep(Uint8List data) {
    if (_discardBytes <= 0) return data;

    final dropped = _discardBytes < data.lengthInBytes
        ? _discardBytes
        : data.lengthInBytes;
    _discardBytes -= dropped;

    // The gap has just closed, so the room reading starts fresh here. Left
    // alone, the first chunk this device sends would be measured partly on
    // audio nobody is going to hear.
    if (_discardBytes <= 0) room.takeChunk();

    return dropped == data.lengthInBytes
        ? null
        : Uint8List.sublistView(data, dropped);
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

    // Measured against the buffers that arrived rather than against the bytes
    // being cut here, so a buffer straddling the boundary lands a few
    // milliseconds of the next chunk in this one's reading. Left that way on
    // purpose: at a fiftieth of a percent it changes no decision, and the
    // alternative is a second clock running alongside the sample arithmetic
    // that decides where chunks begin.
    final reading = room.takeChunk();

    final file = File(p.join(scratch.path, 'chunk-$number.wav'));
    final wav = wrapAsWav(payload);

    // The catch has to sit inside the link rather than on the chain. An error
    // escaping here leaves `_writes` permanently completed with that error,
    // every `.then` after it is skipped, and the chunker stops emitting for the
    // rest of the sitting — while the clock keeps counting and the level meter
    // keeps moving off the live microphone, so every visible sign still says
    // the meeting is being recorded.
    _writes = _writes.then((_) async {
      try {
        await file.writeAsBytes(wav, flush: true);
      } catch (_) {
        // This chunk is gone. Losing fifteen seconds is survivable; losing the
        // rest of the meeting because of it is not.
        writeFailures.value++;
        await _delete(file);
        return;
      }

      if (!_chunks.isClosed) {
        _chunks.add(
          AudioChunk(
            number: number,
            file: file,
            start: start,
            end: end,
            reading: reading,
          ),
        );
      }
    });
  }

  /// Clears away a chunk that failed part-way through being written, so a
  /// truncated file is never picked up and posted as if it were whole.
  Future<void> _delete(File file) async {
    try {
      if (await file.exists()) await file.delete();
    } catch (_) {
      // Already gone, or the directory went with it. Either way there is
      // nothing here that can be uploaded by mistake.
    }
  }

  Duration _samplesAt(int samples) {
    return _offset + Duration(milliseconds: (samples * 1000) ~/ sampleRate);
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
