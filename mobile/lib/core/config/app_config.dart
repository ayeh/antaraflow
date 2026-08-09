/// Build-time configuration.
///
/// Values come from `--dart-define` so a debug build can point at Herd while a
/// release build points at production, without a code change deciding it.
class AppConfig {
  const AppConfig._();

  /// Base URL of the API, without a trailing slash.
  ///
  /// The Android emulator reaches the host machine at 10.0.2.2, never at
  /// localhost, so the default here is the one that works on a simulator rather
  /// than the one that reads more naturally.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://antara-flow.test',
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
