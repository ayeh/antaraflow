import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:permission_handler/permission_handler.dart';

/// Keeps Android recording once the app leaves the screen.
///
/// Android revokes microphone access from a backgrounded app unless a
/// foreground service with the microphone type is running, and it does it
/// without an error: the audio stream keeps delivering buffers, every sample
/// in them zero. So this is not a nicety — without it a sitting recorded with
/// the phone face down is an hour of silence that nothing reports.
///
/// iOS needs no equivalent; `UIBackgroundModes: audio` covers it there, which
/// is why this does nothing off Android.
class RecordingService {
  RecordingService({MethodChannel? channel})
    : _channel = channel ?? const MethodChannel(_name);

  static const _name = 'cloud.antara.note/recording_service';

  final MethodChannel _channel;

  static bool get isRequired => !kIsWeb && Platform.isAndroid;

  Future<void> start(String title, {required String note}) async {
    if (!isRequired) return;

    // Android 13 and up suppress the service's notification unless this has
    // been granted — the service still runs and the microphone still works,
    // but the one thing telling the room the phone is listening is invisible.
    // Asked here rather than at launch, because this is the moment it means
    // something and the moment somebody will say yes to it.
    if (await Permission.notification.isDenied) {
      await Permission.notification.request();
    }

    await _invoke('start', {'title': title, 'note': note});
  }

  /// Rewrites the line under the title on the ongoing notification.
  ///
  /// This is the only surface the recorder has once the phone is face down on
  /// the table, so it is where a room nobody can be heard in has to be said.
  Future<void> note(String? note) async {
    if (!isRequired || note == null) return;

    await _invoke('note', {'note': note});
  }

  Future<void> stop() => _invoke('stop', const {});

  Future<void> _invoke(String method, Map<String, Object?> arguments) async {
    if (!isRequired) return;

    try {
      await _channel.invokeMethod<bool>(method, arguments);
    } on PlatformException {
      // Nothing to do about it here. The recording itself is unaffected while
      // the app is on screen, and the screen is where it was just started.
    } on MissingPluginException {
      // A build that predates the service.
    }
  }
}
