/// A failure the API described in its own terms.
///
/// The server always answers `{message, code, errors?}`, where `message` is
/// already translated and safe to show, and `code` is the stable string the app
/// branches on. Branching on HTTP status alone is not enough: 409 means five
/// different things depending on the endpoint.
class ApiException implements Exception {
  const ApiException({
    required this.message,
    required this.code,
    this.statusCode,
    this.errors = const {},
    this.extra = const {},
  });

  final String message;
  final String code;
  final int? statusCode;

  /// Field name to list of messages, present on validation failures.
  final Map<String, List<String>> errors;

  /// Anything else the endpoint attached — `minimum_version`, `server_state`,
  /// the running `session` on a duplicate start.
  final Map<String, dynamic> extra;

  bool get isUnauthenticated => code == ApiErrorCode.unauthenticated;

  bool get isUpgradeRequired => code == ApiErrorCode.clientUpgradeRequired;

  bool get isOffline => code == ApiErrorCode.networkUnavailable;

  /// Worth retrying later from a queue; a 4xx generally is not.
  bool get isRetryable =>
      isOffline ||
      code == ApiErrorCode.serverError ||
      code == ApiErrorCode.rateLimited ||
      (statusCode != null && statusCode! >= 500);

  String? firstErrorFor(String field) => errors[field]?.firstOrNull;

  @override
  String toString() => 'ApiException($code, $statusCode): $message';
}

/// Codes the app reacts to. Mirrors §3.3 of the API specification.
abstract final class ApiErrorCode {
  static const unauthenticated = 'UNAUTHENTICATED';
  static const invalidCredentials = 'INVALID_CREDENTIALS';
  static const forbidden = 'FORBIDDEN';
  static const organizationForbidden = 'ORGANIZATION_FORBIDDEN';
  static const noOrganizationContext = 'NO_ORGANIZATION_CONTEXT';
  static const organizationSuspended = 'ORGANIZATION_SUSPENDED';
  static const notFound = 'NOT_FOUND';
  static const validationFailed = 'VALIDATION_FAILED';
  static const meetingApprovedImmutable = 'MEETING_APPROVED_IMMUTABLE';
  static const sessionNotActive = 'SESSION_NOT_ACTIVE';
  static const sessionNotPaused = 'SESSION_NOT_PAUSED';
  static const sessionAlreadyActive = 'SESSION_ALREADY_ACTIVE';
  static const chunkDuplicate = 'CHUNK_DUPLICATE';
  static const votingClosed = 'VOTING_CLOSED';
  static const notAnAttendee = 'NOT_AN_ATTENDEE';
  static const quotaExceeded = 'QUOTA_EXCEEDED';
  static const rateLimited = 'RATE_LIMITED';
  static const clientUpgradeRequired = 'CLIENT_UPGRADE_REQUIRED';
  static const serverError = 'SERVER_ERROR';

  /// Client-side only: the request never reached the server.
  static const networkUnavailable = 'NETWORK_UNAVAILABLE';
}
