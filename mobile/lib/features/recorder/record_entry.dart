import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// Requests to start recording that did not come from inside the app.
///
/// The home-screen widget, the Action button and Siri all arrive here. Two
/// paths, because a widget tap either wakes a running app or cold-launches it:
/// a live call on the channel for the first, and a pending flag drained on
/// startup for the second. Without the second, the most common case — phone
/// in pocket, app long since evicted — silently does nothing.
class RecordEntry {
  RecordEntry({MethodChannel? channel})
    : _channel = channel ?? const MethodChannel(_name);

  static const _name = 'cloud.antara.note/record_entry';

  final MethodChannel _channel;

  static bool get isSupported => !kIsWeb && Platform.isIOS;

  /// Starts listening, and picks up a request that arrived before Flutter was
  /// running. Returns true when there was one waiting.
  Future<bool> listen(VoidCallback onRequested) async {
    if (!isSupported) return false;

    _channel.setMethodCallHandler((call) async {
      if (call.method == 'startRecording') onRequested();
    });

    try {
      return await _channel.invokeMethod<bool>('consumePending') ?? false;
    } on MissingPluginException {
      return false;
    } on PlatformException {
      return false;
    }
  }

  void dispose() {
    if (isSupported) _channel.setMethodCallHandler(null);
  }
}
