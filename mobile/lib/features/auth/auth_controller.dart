import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/error/api_exception.dart';
import '../../core/providers.dart';
import '../../data/local/secure_store.dart';
import 'auth_state.dart';

final authControllerProvider =
    StateNotifierProvider<AuthController, AuthState>((ref) {
      return AuthController(ref);
    });

class AuthController extends StateNotifier<AuthState> {
  AuthController(this._ref) : super(const AuthState());

  final Ref _ref;

  SecureStore get _store => _ref.read(secureStoreProvider);

  /// Decides what the first screen is.
  ///
  /// A stored token is not trusted on its own — it may have been revoked from
  /// another device or expired while the app was closed — so it is confirmed
  /// against the server before the app treats the person as signed in.
  Future<void> restore() async {
    final token = await _store.readToken();

    if (token == null || token.isEmpty) {
      state = state.copyWith(status: AuthStatus.unauthenticated);
      return;
    }

    try {
      final session = await _ref.read(authRepositoryProvider).me();

      state = state.copyWith(
        status: AuthStatus.authenticated,
        session: session,
        clearError: true,
      );

      if (session.needsRefresh) {
        // Best effort: a failure here is not a reason to block the app, the
        // token is still valid for now.
        unawaited(_ref.read(authRepositoryProvider).refresh());
      }
    } on ApiException catch (e) {
      // Offline is not a reason to sign someone out. Their token may be
      // perfectly good; they just cannot reach us right now.
      if (e.isOffline) {
        state = state.copyWith(status: AuthStatus.authenticated);
        return;
      }

      await _store.clearSession();
      state = state.copyWith(status: AuthStatus.unauthenticated);
    }
  }

  Future<bool> login({required String email, required String password}) async {
    state = state.copyWith(isSubmitting: true, clearError: true);

    try {
      final session = await _ref
          .read(authRepositoryProvider)
          .login(email: email, password: password);

      state = state.copyWith(
        status: AuthStatus.authenticated,
        session: session,
        isSubmitting: false,
        clearError: true,
      );

      return true;
    } on ApiException catch (e) {
      state = state.copyWith(
        isSubmitting: false,
        errorMessage: e.message,
        fieldErrors: e.errors,
      );

      return false;
    }
  }

  Future<void> logout() async {
    await _ref.read(authRepositoryProvider).logout();

    state = const AuthState(status: AuthStatus.unauthenticated);
  }

  Future<bool> switchOrganization(int organizationId) async {
    try {
      final session = await _ref
          .read(authRepositoryProvider)
          .switchOrganization(organizationId);

      state = state.copyWith(session: session, clearError: true);

      return true;
    } on ApiException catch (e) {
      state = state.copyWith(errorMessage: e.message);

      return false;
    }
  }

  /// Called by the API layer when the server rejects the token, so a stale
  /// session collapses everywhere at once instead of screen by screen.
  void onTokenRejected() {
    if (state.status == AuthStatus.unauthenticated) {
      return;
    }

    _store.clearSession();
    state = const AuthState(status: AuthStatus.unauthenticated);
  }
}
