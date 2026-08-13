import 'dart:math' as math;
import 'dart:typed_data';

/// Audio at levels the tests know exactly.
///
/// A tone rather than noise because the level of a tone is exactly known, and
/// these tests exist to prove that a stated level comes back as that level. A
/// generator that only approximately knew its own loudness could not tell a
/// threshold bug from itself.
const rate = 16000;

/// [seconds] of a 300 Hz tone whose RMS is [dbfs], as signed 16-bit
/// little-endian PCM — the format the recorder streams.
Uint8List tone(double seconds, {required double dbfs}) {
  final samples = (seconds * rate).round();
  final out = Uint8List(samples * 2);
  final view = ByteData.sublistView(out);

  // A sine's RMS is its amplitude over root two, and the levels here are
  // measured on RMS, so the peak has to be scaled up to land the RMS where
  // asked for.
  final amplitude = math.pow(10, dbfs / 20) * math.sqrt2 * 32767;

  for (var i = 0; i < samples; i++) {
    final value = amplitude * math.sin(2 * math.pi * 300 * i / rate);
    view.setInt16(i * 2, value.round().clamp(-32768, 32767), Endian.little);
  }

  return out;
}

/// Digital silence — what a microphone Android has quietly revoked hands back.
Uint8List zeroes(double seconds) => Uint8List((seconds * rate).round() * 2);

/// Room tone with nobody in it.
Uint8List roomTone(double seconds, {double dbfs = -62}) =>
    tone(seconds, dbfs: dbfs);

/// Somebody talking: bursts with the room showing between them.
///
/// Unbroken sound at one exact level is not speech, and the verdict rules are
/// built to know the difference — so a fixture meant to stand for a person
/// talking has to have the gaps in it that a person leaves.
Uint8List talking(double seconds, {required double dbfs}) {
  final out = BytesBuilder();

  for (var spent = 0.0; spent < seconds; spent += 2) {
    out
      ..add(tone(1.5, dbfs: dbfs))
      ..add(roomTone(0.5));
  }

  return out.takeBytes();
}
