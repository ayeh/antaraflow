import 'dart:io';

import 'package:device_info_plus/device_info_plus.dart';

/// The human device name the web app shows against a recording, e.g.
/// "iPhone 15 Pro" or "Google Pixel 7". The server appends the app tag, so a
/// meeting's Inputs list can tell a phone recording from a browser one.
///
/// Deliberately a marketing-ish model, never the user's chosen device name
/// ("Ariff's iPhone") — that is personal, and the point here is the hardware,
/// not who owns it. Resolved once and cached; it cannot change while running.
class DeviceLabel {
  DeviceLabel._();

  static String? _cached;

  static Future<String> resolve() async {
    final cached = _cached;
    if (cached != null) {
      return cached;
    }

    final label = await _read();
    _cached = label;
    return label;
  }

  static Future<String> _read() async {
    final info = DeviceInfoPlugin();

    try {
      if (Platform.isIOS) {
        final ios = await info.iosInfo;
        return _clean(ios.modelName.isNotEmpty ? ios.modelName : ios.model);
      }

      if (Platform.isAndroid) {
        final android = await info.androidInfo;
        final maker = _titleCase(android.manufacturer);
        final model = android.model;
        final name = model.toLowerCase().startsWith(maker.toLowerCase())
            ? model
            : '$maker $model';
        return _clean(name);
      }
    } catch (_) {
      // A device that will not describe itself is not worth failing a
      // recording over; the web app falls back to its plain source badge.
    }

    return '';
  }

  static String _clean(String value) {
    final trimmed = value.trim();
    return trimmed.length > 100 ? trimmed.substring(0, 100) : trimmed;
  }

  static String _titleCase(String value) {
    if (value.isEmpty) {
      return value;
    }
    return value[0].toUpperCase() + value.substring(1);
  }
}
