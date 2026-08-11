import 'dart:io';

import 'package:package_info_plus/package_info_plus.dart';

import '../../domain/models/session.dart';
import '../api/api_client.dart';
import '../local/secure_store.dart';

class AuthRepository {
  AuthRepository({required ApiClient client, required SecureStore store})
    : _client = client,
      _store = store;

  final ApiClient _client;
  final SecureStore _store;

  Future<AuthSession> login({
    required String email,
    required String password,
    String? pushToken,
  }) async {
    final body = await _client.post(
      '/auth/login',
      body: {
        'email': email,
        'password': password,
        'device_name': await _deviceName(),
        'device_id': await _store.deviceId(),
        'platform': Platform.isIOS ? 'ios' : 'android',
        'push_token': ?pushToken,
      },
    );

    return _persist(AuthSession.fromJson(body));
  }

  Future<AuthSession> loginWithProvider({
    required String provider,
    required String accessToken,
    String? pushToken,
  }) async {
    final body = await _client.post(
      '/auth/social',
      body: {
        'provider': provider,
        'access_token': accessToken,
        'device_name': await _deviceName(),
        'device_id': await _store.deviceId(),
        'platform': Platform.isIOS ? 'ios' : 'android',
        'push_token': ?pushToken,
      },
    );

    return _persist(AuthSession.fromJson(body));
  }

  /// Confirms the stored token still works and returns who it belongs to.
  Future<AuthSession> me() async {
    final body = await _client.get('/auth/me');
    final session = AuthSession.fromJson(body);

    await _store.writeOrganizationId(session.currentOrganization?.id);

    return session;
  }

  Future<void> refresh() async {
    final body = await _client.post('/auth/refresh');

    final token = body['token'] as String?;
    if (token == null) return;

    await _store.writeSession(
      token: token,
      expiresAt: DateTime.tryParse(body['expires_at'] as String? ?? ''),
    );
  }

  /// Clears local credentials even when the call fails: a person tapping sign
  /// out on a train must not stay signed in because the network was down.
  Future<void> logout() async {
    try {
      await _client.post('/auth/logout');
    } catch (_) {
      // Intentionally ignored.
    } finally {
      await _store.clearSession();
    }
  }

  Future<AuthSession> switchOrganization(int organizationId) async {
    final body = await _client.post(
      '/auth/organization',
      body: {'organization_id': organizationId},
    );

    final session = AuthSession.fromJson(body);
    await _store.writeOrganizationId(session.currentOrganization?.id);

    return session;
  }

  Future<void> sendPasswordReset(String email) =>
      _client.post('/auth/password/forgot', body: {'email': email});

  Future<AuthSession> _persist(AuthSession session) async {
    final token = session.token;

    if (token != null) {
      await _store.writeSession(token: token, expiresAt: session.expiresAt);
    }

    await _store.writeOrganizationId(session.currentOrganization?.id);

    return session;
  }

  Future<String> _deviceName() async {
    final info = await PackageInfo.fromPlatform();

    return '${info.appName} ${Platform.operatingSystemVersion}';
  }
}
