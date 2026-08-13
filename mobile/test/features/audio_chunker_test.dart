import 'dart:io';

import 'package:antaranote/features/recorder/audio_chunker.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:path/path.dart' as p;

import '../support/pcm.dart';

/// One second of 16 kHz mono 16-bit audio.
Uint8List seconds(int n) =>
    Uint8List(AudioChunker.sampleRate * 2 * n)..fillRange(0, 8, 7);

void main() {
  // AudioChunker builds an AudioRecorder eagerly, which reaches for a platform
  // channel the moment it is constructed. Nothing here ever opens a
  // microphone — the cutter is what is under test — so the channel only has to
  // answer rather than do anything.
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
          const MethodChannel('com.llfbandit.record/messages'),
          (_) async => null,
        );
  });

  late Directory scratch;

  setUp(() {
    scratch = Directory.systemTemp.createTempSync('chunker');
  });

  tearDown(() {
    if (scratch.existsSync()) scratch.deleteSync(recursive: true);
  });

  test(
    'cuts a chunk once fifteen seconds have arrived, and not before',
    () async {
      final chunker = AudioChunker();
      final cut = <AudioChunk>[];
      chunker.chunks.listen(cut.add);
      chunker.prepare(scratch);

      chunker.receive(seconds(14));
      await chunker.settled;
      await pumpEventQueue();
      expect(cut, isEmpty, reason: 'a partial chunk is not a chunk');

      chunker.receive(seconds(1));
      await chunker.settled;
      await pumpEventQueue();

      expect(cut, hasLength(1));
      expect(cut.single.number, 0);
      expect(cut.single.start, Duration.zero);
      expect(cut.single.end, const Duration(seconds: 15));
    },
  );

  test('keeps cutting for the length of a sitting', () async {
    final chunker = AudioChunker();
    final cut = <AudioChunk>[];
    chunker.chunks.listen(cut.add);
    chunker.prepare(scratch);

    for (var i = 0; i < 5; i++) {
      chunker.receive(seconds(15));
      await chunker.settled;
      await pumpEventQueue();
    }

    expect(cut.map((c) => c.number), [0, 1, 2, 3, 4]);
    expect(cut.last.end, const Duration(seconds: 75));
  });

  // The failure that hides itself. The recorder screen keeps counting and the
  // level meter keeps moving off the live microphone, so every visible sign
  // says the meeting is being recorded while nothing is reaching the server.
  test(
    'a chunk that cannot be written does not stop the ones after it',
    () async {
      final chunker = AudioChunker();
      final cut = <AudioChunk>[];
      chunker.chunks.listen(cut.add);
      chunker.prepare(scratch);

      // Whatever takes the scratch directory away mid-meeting — the OS
      // reclaiming the cache under pressure is the realistic one.
      scratch.deleteSync(recursive: true);
      chunker.receive(seconds(15));
      await chunker.settled;
      await pumpEventQueue();

      expect(cut, isEmpty, reason: 'this chunk genuinely could not be written');
      expect(chunker.writeFailures.value, 1, reason: 'and it must be reported');

      scratch.createSync(recursive: true);
      chunker.receive(seconds(15));
      await chunker.settled;
      await pumpEventQueue();

      expect(
        cut.map((c) => c.number),
        [1],
        reason: 'recording continues; one failed write is not the end of it',
      );
    },
  );

  test('a failed write leaves no half-file behind to be uploaded', () async {
    final chunker = AudioChunker();
    chunker.chunks.listen((_) {});
    chunker.prepare(scratch);

    scratch.deleteSync(recursive: true);
    chunker.receive(seconds(15));
    await chunker.settled;
    await pumpEventQueue();

    scratch.createSync(recursive: true);
    expect(File(p.join(scratch.path, 'chunk-0.wav')).existsSync(), isFalse);
  });

  test('each chunk carries how loud it was, measured as it was cut', () async {
    final chunker = AudioChunker();
    final cut = <AudioChunk>[];
    chunker.chunks.listen(cut.add);
    chunker.prepare(scratch);

    chunker.receive(talking(15, dbfs: -30));
    chunker.receive(talking(15, dbfs: -50));
    await chunker.settled;
    await pumpEventQueue();

    expect(
      cut.map((c) => c.reading.speech),
      [closeTo(-30, 1), closeTo(-50, 1)],
      reason: 'each chunk is measured on its own audio, not the sitting so far',
    );
  });

  test(
    'a resumed sitting numbers and times chunks from where it left off',
    () async {
      final chunker = AudioChunker();
      final cut = <AudioChunk>[];
      chunker.chunks.listen(cut.add);
      chunker.prepare(
        scratch,
        fromChunk: 4,
        alreadyRecorded: const Duration(seconds: 60),
      );

      chunker.receive(seconds(15));
      await chunker.settled;
      await pumpEventQueue();

      expect(cut.single.number, 4);
      expect(cut.single.start, const Duration(seconds: 60));
      expect(cut.single.end, const Duration(seconds: 75));
    },
  );

  // A device joining a sitting already in progress lands mid-window. It waits
  // for the primary's next boundary rather than sending a short chunk, because a
  // short chunk numbered N covers only the tail of the window the primary's
  // chunk N covers — and if selection preferred it, the opening of that window
  // would vanish from the transcript.
  test('a satellite throws away the part of a window it missed', () async {
    final chunker = AudioChunker();
    final cut = <AudioChunk>[];
    chunker.chunks.listen(cut.add);

    // The sitting is seven seconds into the window that becomes chunk 2, so the
    // next clean boundary is eight seconds away and belongs to chunk 3.
    chunker.prepare(
      scratch,
      fromChunk: 3,
      alreadyRecorded: const Duration(seconds: 45),
      discardFirst: const Duration(seconds: 8),
    );

    chunker.receive(seconds(8));
    await chunker.settled;
    await pumpEventQueue();

    expect(
      cut,
      isEmpty,
      reason: 'this audio belongs to a window already half gone',
    );
    expect(
      chunker.position,
      Duration.zero + const Duration(seconds: 45),
      reason: 'discarded audio must not move the clock',
    );

    chunker.receive(seconds(15));
    await chunker.settled;
    await pumpEventQueue();

    expect(cut.single.number, 3);
    expect(cut.single.start, const Duration(seconds: 45));
    expect(cut.single.end, const Duration(seconds: 60));
  });

  test('the gap can span more buffers than it takes to fill one', () async {
    final chunker = AudioChunker();
    final cut = <AudioChunk>[];
    chunker.chunks.listen(cut.add);
    chunker.prepare(scratch, discardFirst: const Duration(seconds: 5));

    for (var i = 0; i < 5; i++) {
      chunker.receive(seconds(1));
    }
    await chunker.settled;
    await pumpEventQueue();

    expect(chunker.position, Duration.zero);

    chunker.receive(seconds(15));
    await chunker.settled;
    await pumpEventQueue();

    expect(cut.single.start, Duration.zero);
  });

  test('a primary discards nothing at all', () async {
    final chunker = AudioChunker();
    final cut = <AudioChunk>[];
    chunker.chunks.listen(cut.add);
    chunker.prepare(scratch);

    chunker.receive(seconds(15));
    await chunker.settled;
    await pumpEventQueue();

    expect(cut.single.start, Duration.zero);
    expect(cut.single.end, const Duration(seconds: 15));
  });
}
