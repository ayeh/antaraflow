import 'package:antaranote/features/recorder/room_level.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/pcm.dart';

void main() {
  group('LevelReading', () {
    test('measures a tone at the level it was generated at', () {
      final room = RoomLevel()..add(tone(2, dbfs: -30));
      final reading = room.takeChunk();

      expect(reading.speech, closeTo(-30, 1));
      expect(
        reading.frames,
        20,
        reason: 'two seconds of hundred-millisecond frames',
      );
    });

    test('reports the floor for silence rather than negative infinity', () {
      final room = RoomLevel()..add(zeroes(1));
      final reading = room.takeChunk();

      expect(reading.peak, RoomLevel.floor);
      expect(reading.speech, RoomLevel.floor);
    });

    // The number that separates "nobody could be heard" from "everybody could
    // be heard, over a fan".
    test('headroom is how far speech rises above the room', () {
      final room = RoomLevel()
        ..add(roomTone(9))
        ..add(tone(3, dbfs: -25));

      expect(room.takeChunk().headroom, closeTo(37, 2));
    });

    test('takes each chunk fresh, so a loud one does not colour the next', () {
      final room = RoomLevel()..add(tone(2, dbfs: -20));
      expect(room.takeChunk().speech, closeTo(-20, 1));

      room.add(tone(2, dbfs: -50));
      expect(room.takeChunk().speech, closeTo(-50, 1));
    });

    test('a chunk too short to measure reports no frames at all', () {
      final room = RoomLevel()..add(tone(0.05, dbfs: -20));

      expect(room.takeChunk().frames, 0, reason: 'and so is never sent');
    });

    test('rounds to a tenth of a decibel on the wire', () {
      final json = RoomLevel().takeChunk().toJson();

      expect(json.keys, ['peak_dbfs', 'speech_dbfs', 'noise_dbfs']);
      expect(json['peak_dbfs'], RoomLevel.floor);
    });
  });

  group('verdict', () {
    test('says nothing at all until it has heard enough', () {
      final room = RoomLevel()..add(tone(1, dbfs: -20));

      expect(room.verdict.value, RoomVerdict.listening);
      expect(room.check.value, RoomVerdict.listening);
    });

    test('clear when voices arrive at a workable level', () {
      final room = RoomLevel()
        ..add(roomTone(5))
        ..add(tone(6, dbfs: -28));

      expect(room.verdict.value, RoomVerdict.clear);
    });

    test('faint when the only speech is well below the threshold', () {
      final room = RoomLevel()
        ..add(roomTone(5, dbfs: -66))
        ..add(tone(6, dbfs: -52));

      expect(room.verdict.value, RoomVerdict.faint);
    });

    // The gate that decides whether anybody spoke has to let this through. A
    // voice from the far end of a table clears the room it is spoken in by
    // very little, and ruling it out as background rules out the one case the
    // warning exists for.
    test('faint even when the voice barely rises above the room', () {
      final room = RoomLevel()
        ..add(roomTone(5, dbfs: -59))
        ..add(tone(6, dbfs: -52));

      expect(room.verdict.value, RoomVerdict.faint);
    });

    // The other side of that trade. Nothing here is loud enough to be speech
    // at any distance, and an empty room looks exactly the same, so there is
    // nothing honest to say.
    test('says nothing when the whole window is below audible', () {
      final room = RoomLevel()
        ..add(roomTone(5, dbfs: -80))
        ..add(tone(6, dbfs: -64));

      expect(room.verdict.value, RoomVerdict.listening);
    });

    // The failure that hides itself: Android revokes the microphone from a
    // backgrounded app without an error, and the recorder carries on writing
    // perfectly timed chunks of nothing.
    test('silent when the microphone is handing back zeroes', () {
      final room = RoomLevel()..add(zeroes(6));

      expect(room.verdict.value, RoomVerdict.silent);
    });

    // Most of a meeting is one person talking and everybody else not. A
    // verdict formed from those gaps would report every sitting as too quiet.
    test('a quiet room with nobody speaking is not a complaint', () {
      final room = RoomLevel()..add(roomTone(20));

      expect(room.verdict.value, RoomVerdict.listening);
    });

    test('a pause between sentences does not undo a good verdict', () {
      final room = RoomLevel()
        ..add(roomTone(3))
        ..add(tone(6, dbfs: -28));

      expect(room.verdict.value, RoomVerdict.clear);

      room.add(roomTone(20));

      expect(
        room.verdict.value,
        RoomVerdict.clear,
        reason: 'silence carries no information about how well the phone hears',
      );
    });

    test('forgets a room it has left behind, once the window has rolled', () {
      final room = RoomLevel(windowSeconds: 10)
        ..add(roomTone(2, dbfs: -66))
        ..add(tone(4, dbfs: -52));

      expect(room.verdict.value, RoomVerdict.faint);

      // Longer than the window, so nothing of the faint stretch survives.
      room.add(talking(12, dbfs: -28));

      expect(room.verdict.value, RoomVerdict.clear);
    });
  });

  group('check', () {
    test('settles once, on the first firm reading, and then holds', () {
      final room = RoomLevel()
        ..add(roomTone(3, dbfs: -66))
        ..add(tone(6, dbfs: -52));

      expect(room.check.value, RoomVerdict.faint);

      // Somebody moved the phone. The rolling verdict follows; the opening
      // check is a record of how the sitting started and does not.
      room.add(tone(20, dbfs: -25));

      expect(room.check.value, RoomVerdict.faint);
      expect(room.verdict.value, RoomVerdict.clear);
    });

    test('gives up asking after its window, without inventing a verdict', () {
      final room = RoomLevel(checkSeconds: 5, windowSeconds: 90)
        ..add(roomTone(8));

      expect(room.check.value, RoomVerdict.listening);

      room.add(tone(6, dbfs: -25));

      expect(
        room.check.value,
        RoomVerdict.listening,
        reason: 'the moment to place the phone well has passed',
      );
    });
  });

  group('meter', () {
    test('rises with the signal and falls back to nothing when muted', () {
      final room = RoomLevel()..add(tone(0.5, dbfs: -20));
      final loud = room.level.value;

      room.add(tone(0.5, dbfs: -50));
      expect(room.level.value, lessThan(loud));

      room.mute();
      expect(room.level.value, 0);
    });

    test('muting keeps what the room has already told us', () {
      final room = RoomLevel()
        ..add(roomTone(3))
        ..add(tone(6, dbfs: -28))
        ..mute();

      expect(room.verdict.value, RoomVerdict.clear);
    });
  });
}
