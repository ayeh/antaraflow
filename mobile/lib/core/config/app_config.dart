/// Build-time configuration.
///
/// Values come from `--dart-define` so a debug build can point at Herd while a
/// release build points at production, without a code change deciding it.
class AppConfig {
  const AppConfig._();

  /// Base URL of the API, without a trailing slash.
  ///
  /// Herd parks this project at antaraflow.test — the folder name as-is, not a
  /// kebab-cased version of it. The Android emulator cannot reach the host by
  /// name at all and needs `--dart-define=API_BASE_URL=http://10.0.2.2:8000`
  /// against `php artisan serve`.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://antaraflow.test',
  );

  static const String apiPrefix = '/api/mobile/v1';

  static String get apiRoot => '$apiBaseUrl$apiPrefix';

  static const Duration connectTimeout = Duration(seconds: 15);

  /// Generous, because AI answers and export rendering are slow by nature.
  static const Duration receiveTimeout = Duration(seconds: 60);

  /// A single audio chunk can be several megabytes on a poor connection.
  static const Duration uploadTimeout = Duration(minutes: 3);

  static const String deepLinkScheme = 'antaranote';
}
