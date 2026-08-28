import 'dart:async';
import 'dart:io';

import 'package:app_links/app_links.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

import '../../core/config/app_config.dart';

/// Links that open the app straight onto a sitting to help record.
///
/// The shape is `antaranote://live/join/<token>`: a primary shares it from a
/// running recording, and tapping it in a chat is meant to land the colleague
/// on the offer to add their microphone — the same offer a second phone gets
/// by opening the meeting itself, reached without them having to find it.
class DeepLinkEntry {
  DeepLinkEntry({AppLinks? links, MethodChannel? native})
    : _links = links ?? AppLinks(),
      // iOS routes custom-scheme URLs through the scene delegate, never to the
      // app_links plugin, so on iOS the token comes over this native channel
      // instead. Android keeps app_links, and the channel stays null there.
      _native =
          native ??
          (!kIsWeb && Platform.isIOS
              ? const MethodChannel('cloud.antara.note/deep_link')
              : null);

  final AppLinks _links;

  final MethodChannel? _native;

  StreamSubscription<Uri>? _sub;

  /// Starts listening, and drains a link the app was cold-launched with.
  ///
  /// [onToken] is called with the invite token each time one arrives, live
  /// stream and cold-launch alike, so the caller has one path to handle rather
  /// than two.
  Future<void> listen(void Function(String token) onToken) async {
    _sub = _links.uriLinkStream.listen((uri) {
      final token = tokenFrom(uri);
      if (token != null) onToken(token);
    });

    // The link a cold launch carried is not on the stream — it arrived before
    // anyone was listening — so it has to be asked for once, separately.
    final initial = await _links.getInitialLink();
    if (initial != null) {
      final token = tokenFrom(initial);
      if (token != null) onToken(token);
    }

    await _listenNative(onToken);
  }

  /// The iOS native channel: live links come in as `link` calls, and a token
  /// that cold-launched the app is drained once, the same handshake the
  /// recorder entry uses so a request arriving before Dart is lost to nobody.
  Future<void> _listenNative(void Function(String token) onToken) async {
    final native = _native;
    if (native == null) return;

    native.setMethodCallHandler((call) async {
      if (call.method == 'link' && call.arguments is String) {
        onToken(call.arguments as String);
      }
    });

    try {
      final pending = await native.invokeMethod<String>('consumePending');
      if (pending != null) onToken(pending);
    } on MissingPluginException {
      // No native bridge — nothing to drain.
    } on PlatformException {
      // A malformed answer is not worth crashing a cold launch over.
    }
  }

  /// The invite token in `antaranote://live/join/<token>`, or null for any
  /// other link: the scheme is ours but nothing promises it will only ever
  /// carry this one shape.
  static String? tokenFrom(Uri uri) {
    if (uri.scheme != AppConfig.deepLinkScheme) return null;

    // The first segment of a custom-scheme URI parses as the host, not the
    // path, so it has to be folded back in before the path makes sense.
    final segments = [
      uri.host,
      ...uri.pathSegments,
    ].where((segment) => segment.isNotEmpty).toList();

    if (segments.length == 3 &&
        segments[0] == 'live' &&
        segments[1] == 'join') {
      return segments[2];
    }

    return null;
  }

  /// The link a primary shares for [token] — the inverse of [tokenFrom].
  static String linkFor(String token) =>
      '${AppConfig.deepLinkScheme}://live/join/$token';

  void dispose() {
    _sub?.cancel();
    _sub = null;
    _native?.setMethodCallHandler(null);
  }
}
