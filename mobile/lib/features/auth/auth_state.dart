import '../../domain/models/session.dart';

enum AuthStatus {
  /// Before the stored token has been checked. The app shows a splash here
  /// rather than the sign-in screen, so a returning user never sees a login
  /// form flash past on every cold start.
  unknown,
  authenticated,
  unauthenticated,
}

class AuthState {
  const AuthState({
    this.status = AuthStatus.unknown,
    this.session,
    this.isSubmitting = false,
    this.errorMessage,
    this.fieldErrors = const {},
  });

  final AuthStatus status;
  final AuthSession? session;
  final bool isSubmitting;
  final String? errorMessage;
  final Map<String, List<String>> fieldErrors;

  bool get isAuthenticated => status == AuthStatus.authenticated;

  AuthState copyWith({
    AuthStatus? status,
    AuthSession? session,
    bool? isSubmitting,
    String? errorMessage,
    Map<String, List<String>>? fieldErrors,
    bool clearError = false,
    bool clearSession = false,
  }) {
    return AuthState(
      status: status ?? this.status,
      session: clearSession ? null : (session ?? this.session),
      isSubmitting: isSubmitting ?? this.isSubmitting,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      fieldErrors: clearError ? const {} : (fieldErrors ?? this.fieldErrors),
    );
  }
}
