import 'dart:typed_data';

import 'package:antaranote/domain/models/live_session.dart';
import 'package:antaranote/features/recorder/audio_chunker.dart';
import 'package:antaranote/features/recorder/chunk_outbox.dart';
import 'package:antaranote/features/recorder/recorder_controller.dart';
import 'package:antaranote/features/recorder/recorder_role.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('wrapAsWav', () {
    String tag(Uint8List bytes, int offset) =>
        String.fromCharCodes(bytes.sublist(offset, offset + 4));

    final pcm = Uint8List.fromList(List.generate(320, (i) => i % 256));
    final wav = AudioChunker.wrapAsWav(pcm);
    final view = ByteData.sublistView(wav);

    test('is a RIFF/WAVE container with a fmt and a data chunk', () {
      expect(tag(wav, 0), 'RIFF');
      expect(tag(wav, 8), 'WAVE');
      expect(tag(wav, 12), 'fmt ');
      expect(tag(wav, 36), 'data');
    });

    test('declares uncompressed 16-bit mono at the capture rate', () {
      expect(view.getUint32(16, Endian.little), 16, reason: 'fmt chunk size');
      expect(view.getUint16(20, Endian.little), 1, reason: 'PCM');
      expect(view.getUint16(22, Endian.little), 1, reason: 'mono');
      expect(view.getUint32(24, Endian.little), AudioChunker.sampleRate);
      expect(view.getUint16(34, Endian.little), 16, reason: 'bits per sample');
    });

    test('byte rate and block align agree with the format', () {
      expect(view.getUint32(28, Endian.little), AudioChunker.sampleRate * 2);
      expect(view.getUint16(32, Endian.little), 2);
    });

    // The two length fields are the ones that fail silently: a decoder trusts
    // them over the actual file size and reads a truncated stream as silence.
    test('both length fields describe the payload that is there', () {
      expect(view.getUint32(4, Endian.little), 36 + pcm.length);
      expect(view.getUint32(40, Endian.little), pcm.length);
      expect(wav.length, 44 + pcm.length);
    });

    test('carries the samples through byte for byte', () {
      expect(wav.sublist(44), pcm);
    });

    test('handles an empty tail chunk without producing a broken header', () {
      final empty = AudioChunker.wrapAsWav(Uint8List(0));

      expect(empty.length, 44);
      expect(ByteData.sublistView(empty).getUint32(40, Endian.little), 0);
    });
  });

  group('OutboxStatus', () {
    test('one failure is a blip, not a stall', () {
      expect(const OutboxStatus(attempts: 1).isStalled, isFalse);
    });

    test('two consecutive failures are worth telling the user about', () {
      expect(const OutboxStatus(attempts: 2).isStalled, isTrue);
    });
  });

  group('Bookmark', () {
    Bookmark at(Duration position) =>
        Bookmark(clientId: 'c', at: position, kind: BookmarkKind.decision);

    test('drops the hour while a meeting is still under one', () {
      expect(at(const Duration(minutes: 7, seconds: 4)).stamp, '07:04');
    });

    test('shows the hour once a sitting runs past it', () {
      expect(
        at(const Duration(hours: 2, minutes: 3, seconds: 9)).stamp,
        '2:03:09',
      );
    });

    test('starts unsynced and is marked once the server answers', () {
      final bookmark = at(Duration.zero);

      expect(bookmark.synced, isFalse);
      expect(bookmark.copyWith(id: 12, synced: true).synced, isTrue);
    });
  });

  group('BookmarkKind', () {
    test('reads the wire values the API defines', () {
      expect(BookmarkKind.fromWire('decision'), BookmarkKind.decision);
      expect(BookmarkKind.fromWire('question'), BookmarkKind.question);
    });

    test('falls back to a general mark rather than throwing', () {
      expect(BookmarkKind.fromWire('nonsense'), BookmarkKind.general);
      expect(BookmarkKind.fromWire(null), BookmarkKind.general);
    });
  });

  group('RecorderState', () {
    test('the clock is zero-padded so the column never shifts width', () {
      const state = RecorderState(
        elapsed: Duration(hours: 1, minutes: 2, seconds: 3),
      );

      expect(state.clock, '01:02:03');
    });

    test('marking is allowed while paused, since the meeting is still on', () {
      expect(const RecorderState(phase: RecorderPhase.paused).canMark, isTrue);
      expect(
        const RecorderState(phase: RecorderPhase.finished).canMark,
        isFalse,
      );
    });
  });
  group('RecorderState role', () {
    test('a satellite knows it is not the recording', () {
      const state = RecorderState(role: RecorderRole.satellite);

      expect(state.role.isSatellite, isTrue);
    });

    test('a recorder is the recording unless told otherwise', () {
      expect(const RecorderState().role, RecorderRole.primary);
    });
  });

  group('RecorderRole.fromWire', () {
    test('reads what the server said', () {
      expect(RecorderRole.fromWire('satellite'), RecorderRole.satellite);
      expect(RecorderRole.fromWire('primary'), RecorderRole.primary);
    });

    // A server too old to answer this is one where satellites do not exist,
    // and every rejoin there is a recorder coming back to its own sitting.
    test('falls back to the recording rather than to helping', () {
      expect(RecorderRole.fromWire(null), RecorderRole.primary);
      expect(RecorderRole.fromWire('nonsense'), RecorderRole.primary);
    });
  });

  group('LiveSession.alignmentGap', () {
    test('waits out the rest of the window it joined halfway through', () {
      const session = LiveSession(
        id: 1,
        meetingId: 2,
        status: 'active',
        secondsIntoChunk: 7,
      );

      expect(session.alignmentGap(15), const Duration(seconds: 8));
    });

    test('waits for nothing when the sitting is on a boundary', () {
      const session = LiveSession(id: 1, meetingId: 2, status: 'active');

      expect(session.alignmentGap(15), Duration.zero);
    });
  });
}
