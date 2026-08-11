import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// The lock-screen card, from the Dart side.
///
/// Every call is best effort. A Live Activity is how the room knows recording
/// is still running; it is not how the recording happens. If the platform says
/// no — an older phone, activities switched off in Settings, or simply
/// Android, which has no equivalent — the recorder must not notice.
class LiveActivity {
  LiveActivity({MethodChannel? channel})
    : _channel = channel ?? const MethodChannel(_name);

  static const _name = 'cloud.antara.note/live_activity';

  final MethodChannel _channel;

  bool _running = false;

  bool get isRunning => _running;

  /// True only where an activity could appear. Checked before the channel, so
  /// a platform with no handler registered is never asked.
  static bool get isSupported => !kIsWeb && Platform.isIOS;

  Future<void> start({required String title, required Duration elapsed}) async {
    if (!isSupported) return;

    _running = await _invoke('start', {
      'title': title,
      'elapsed': elapsed.inSeconds,
      'marks': 0,
      'queued': 0,
      'paused': false,
    });
  }

  Future<void> update({
    required Duration elapsed,
    required int marks,
    required int queued,
    required bool paused,
  }) async {
    if (!_running) return;

    await _invoke('update', {
      'elapsed': elapsed.inSeconds,
      'marks': marks,
      'queued': queued,
      'paused': paused,
    });
  }

  Future<void> end() async {
    if (!_running) return;

    _running = false;
    await _invoke('end', const <String, Object?>{});
  }

  Future<bool> _invoke(String method, Map<String, Object?> arguments) async {
    try {
      return await _channel.invokeMethod<bool>(method, arguments) ?? false;
    } on PlatformException {
      return false;
    } on MissingPluginException {
      // A build that predates the extension. Silence is the right response.
      return false;
    }
  }
}
