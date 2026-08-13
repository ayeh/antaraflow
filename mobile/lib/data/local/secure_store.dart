import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

/// Credentials and device identity, held in the platform keychain.
///
/// The device id has to survive app restarts but must not survive a reinstall
/// being treated as a different phone, so it is generated once and kept here
/// rather than derived from hardware identifiers, which the stores restrict.
class SecureStore {
  SecureStore({FlutterSecureStorage? storage})
    : _storage =
          storage ??
          const FlutterSecureStorage(
            // Android defaults to AES-GCM with RSA-OAEP key wrapping in this
            // version, so nothing needs configuring there.
            //
            // first_unlock_this_device on iOS: background uploads have to keep
            // working while the phone is locked mid-meeting, but the token must
            // not travel to a restored backup on another device.
            iOptions: IOSOptions(
              accessibility: KeychainAccessibility.first_unlock_this_device,
            ),
          );

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'auth_token';
  static const _tokenExpiryKey = 'auth_token_expires_at';
  static const _organizationKey = 'current_organization_id';
  static const _deviceIdKey = 'device_id';
  static const _localeKey = 'locale';

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<DateTime?> readTokenExpiry() async {
    final raw = await _storage.read(key: _tokenExpiryKey);
    return raw == null ? null : DateTime.tryParse(raw);
  }

  Future<void> writeSession({required String token, DateTime? expiresAt}) async {
    await _storage.write(key: _tokenKey, value: token);
    if (expiresAt == null) {
      await _storage.delete(key: _tokenExpiryKey);
    } else {
      await _storage.write(
        key: _tokenExpiryKey,
        value: expiresAt.toIso8601String(),
      );
    }
  }

  /// Clears credentials but keeps the device id: the same phone signing in
  /// again should register as the same device, not accumulate stale rows.
  Future<void> clearSession() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _tokenExpiryKey);
    await _storage.delete(key: _organizationKey);
  }

  Future<int?> readOrganizationId() async {
    final raw = await _storage.read(key: _organizationKey);
    return raw == null ? null : int.tryParse(raw);
  }

  Future<void> writeOrganizationId(int? id) async {
    if (id == null) {
      await _storage.delete(key: _organizationKey);
      return;
    }
    await _storage.write(key: _organizationKey, value: id.toString());
  }

  /// The chosen interface language, or null to follow the phone.
  ///
  /// Kept beside the session rather than in shared preferences so there is one
  /// place holding what this install remembers — but deliberately not cleared
  /// on sign-out: somebody who set the app to Malay wants the login screen in
  /// Malay too.
  Future<String?> readLocale() => _storage.read(key: _localeKey);

  Future<void> writeLocale(String? tag) async {
    if (tag == null) {
      await _storage.delete(key: _localeKey);

      return;
    }

    await _storage.write(key: _localeKey, value: tag);
  }

  Future<String> deviceId() async {
    final existing = await _storage.read(key: _deviceIdKey);
    if (existing != null && existing.isNotEmpty) {
      return existing;
    }

    final generated = const Uuid().v4();
    await _storage.write(key: _deviceIdKey, value: generated);
    return generated;
  }
}
