import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/api/api_client.dart';
import '../data/local/secure_store.dart';
import '../data/repositories/auth_repository.dart';
import '../data/repositories/bootstrap_repository.dart';
import '../features/auth/auth_controller.dart';

/// Wiring for the whole app.
///
/// Riverpod code generation is deliberately not used: riverpod_generator and
/// drift_dev pin incompatible analyzer ranges, and hand-written providers cost
/// a few lines rather than a dependency conflict on every upgrade.
final secureStoreProvider = Provider<SecureStore>((ref) => SecureStore());

/// Raised when the server rejects the stored token, so the session can be torn
/// down in one place rather than by whichever screen noticed first.
final apiClientProvider = Provider<ApiClient>((ref) {
  final client = ApiClient(
    store: ref.watch(secureStoreProvider),
    onUnauthenticated: () {
      ref.read(authControllerProvider.notifier).onTokenRejected();
    },
    onUpgradeRequired: (minimumVersion, storeUrl) {
      ref.read(upgradeRequiredProvider.notifier).state = UpgradeRequirement(
        minimumVersion: minimumVersion,
        storeUrl: storeUrl,
      );
    },
  );

  ref.onDispose(client.raw.close);

  return client;
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    client: ref.watch(apiClientProvider),
    store: ref.watch(secureStoreProvider),
  );
});

final bootstrapRepositoryProvider = Provider<BootstrapRepository>((ref) {
  return BootstrapRepository(client: ref.watch(apiClientProvider));
});

/// Non-null once the server has told us this build is too old to continue.
final upgradeRequiredProvider = StateProvider<UpgradeRequirement?>(
  (ref) => null,
);

class UpgradeRequirement {
  const UpgradeRequirement({this.minimumVersion, this.storeUrl});

  final String? minimumVersion;
  final String? storeUrl;
}
