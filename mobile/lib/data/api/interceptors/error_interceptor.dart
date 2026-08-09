import 'package:dio/dio.dart';

import '../../../core/error/api_exception.dart';

/// Turns every failure into an [ApiException] carrying the server's own code.
///
/// Nothing above this layer should have to know Dio exists, and no screen should
/// ever branch on a raw status code.
class ErrorInterceptor extends Interceptor {
  ErrorInterceptor({this.onUnauthenticated, this.onUpgradeRequired});

  /// Called when the token is gone or rejected, so the session can be dropped
  /// once centrally instead of on every screen that happens to notice.
  final void Function()? onUnauthenticated;

  final void Function(String? minimumVersion, String? storeUrl)?
  onUpgradeRequired;

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    final exception = _toApiException(err);

    if (exception.isUnauthenticated) {
      onUnauthenticated?.call();
    }

    if (exception.isUpgradeRequired) {
      onUpgradeRequired?.call(
        exception.extra['minimum_version'] as String?,
        exception.extra['store_url'] as String?,
      );
    }

    handler.reject(
      DioException(
        requestOptions: err.requestOptions,
        response: err.response,
        type: err.type,
        error: exception,
      ),
    );
  }

  ApiException _toApiException(DioException err) {
    switch (err.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.connectionError:
        return const ApiException(
          message: 'No connection. This will be retried automatically.',
          code: ApiErrorCode.networkUnavailable,
        );
      case DioExceptionType.cancel:
        return const ApiException(
          message: 'Request cancelled.',
          code: 'CANCELLED',
        );
      default:
        break;
    }

    final response = err.response;
    final body = response?.data;

    if (body is! Map<String, dynamic>) {
      return ApiException(
        message: 'Something went wrong. Please try again.',
        code: ApiErrorCode.serverError,
        statusCode: response?.statusCode,
      );
    }

    return ApiException(
      message:
          body['message'] as String? ??
          'Something went wrong. Please try again.',
      code: body['code'] as String? ?? ApiErrorCode.serverError,
      statusCode: response?.statusCode,
      errors: _parseErrors(body['errors']),
      extra: Map<String, dynamic>.from(body)
        ..remove('message')
        ..remove('code')
        ..remove('errors'),
    );
  }

  Map<String, List<String>> _parseErrors(dynamic raw) {
    if (raw is! Map) {
      return const {};
    }

    return raw.map(
      (key, value) => MapEntry(
        key.toString(),
        (value is List ? value : [value]).map((e) => e.toString()).toList(),
      ),
    );
  }
}
