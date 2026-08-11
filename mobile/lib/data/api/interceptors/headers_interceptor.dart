import 'dart:io';

import 'package:dio/dio.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:uuid/uuid.dart';

import '../../local/secure_store.dart';

/// Attaches the headers every mobile endpoint expects.
///
/// `Idempotency-Key` is set on every POST rather than only where duplicates are
/// obviously harmful. The write queue retries after a lost response, and at that
/// point the app cannot know whether the first attempt was applied — a key on
/// every create is what makes the retry safe.
class HeadersInterceptor extends Interceptor {
  HeadersInterceptor({required SecureStore store, String? locale})
    : _store = store,
      _locale = locale;

  final SecureStore _store;
  final String? _locale;

  String? _clientVersion;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    options.headers['Accept'] = 'application/json';
    options.headers['X-Client-Version'] = await _resolveClientVersion();

    if (_locale != null) {
      options.headers['Accept-Language'] = _locale;
    }

    final token = await _store.readToken();
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }

    final organizationId = await _store.readOrganizationId();
    if (organizationId != null) {
      options.headers['X-Organization-Id'] = organizationId.toString();
    }

    // A caller may set its own key so a retry from the outbox reuses the key
    // the original attempt carried; only fill in when it has not.
    if (options.method.toUpperCase() == 'POST' &&
        !options.headers.containsKey('Idempotency-Key')) {
      options.headers['Idempotency-Key'] = const Uuid().v4();
    }

    handler.next(options);
  }

  Future<String> _resolveClientVersion() async {
    if (_clientVersion != null) {
      return _clientVersion!;
    }

    final info = await PackageInfo.fromPlatform();
    final platform = Platform.isIOS ? 'ios' : 'android';

    return _clientVersion =
        '$platform/${info.version} (build ${info.buildNumber})';
  }
}
