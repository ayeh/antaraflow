import 'package:antaranote/core/error/api_exception.dart';
import 'package:antaranote/data/api/interceptors/error_interceptor.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

/// The interceptor is the only place that understands the server's error
/// envelope, so these cases are what the whole app's failure handling rests on.
void main() {
  late ErrorInterceptor interceptor;
  late List<String> unauthenticatedCalls;
  late List<String?> upgradeCalls;

  setUp(() {
    unauthenticatedCalls = [];
    upgradeCalls = [];

    interceptor = ErrorInterceptor(
      onUnauthenticated: () => unauthenticatedCalls.add('called'),
      onUpgradeRequired: (minimum, _) => upgradeCalls.add(minimum),
    );
  });

  ApiException capture(DioException error) {
    ApiException? captured;

    interceptor.onError(
      error,
      _CapturingHandler((rejected) {
        captured = rejected.error as ApiException;
      }),
    );

    return captured!;
  }

  DioException responseError(int status, Map<String, dynamic> body) {
    final options = RequestOptions(path: '/meetings');

    return DioException(
      requestOptions: options,
      type: DioExceptionType.badResponse,
      response: Response(
        requestOptions: options,
        statusCode: status,
        data: body,
      ),
    );
  }

  test('carries the server message and code through unchanged', () {
    final exception = capture(
      responseError(422, {
        'message': 'The information provided is not valid.',
        'code': 'VALIDATION_FAILED',
        'errors': {
          'email': ['Enter your email address'],
        },
      }),
    );

    expect(exception.code, ApiErrorCode.validationFailed);
    expect(exception.message, 'The information provided is not valid.');
    expect(exception.firstErrorFor('email'), 'Enter your email address');
    expect(exception.statusCode, 422);
  });

  test('keeps extra fields the endpoint attached', () {
    final exception = capture(
      responseError(409, {
        'message': 'Meeting already has an active live session.',
        'code': 'SESSION_ALREADY_ACTIVE',
        'session': {'id': 314},
      }),
    );

    expect(exception.code, ApiErrorCode.sessionAlreadyActive);
    expect((exception.extra['session'] as Map)['id'], 314);
  });

  test('reports a connection failure as offline rather than a server error', () {
    final exception = capture(
      DioException(
        requestOptions: RequestOptions(path: '/meetings'),
        type: DioExceptionType.connectionError,
      ),
    );

    expect(exception.isOffline, isTrue);
    expect(exception.isRetryable, isTrue);
  });

  test('signals the session is gone exactly once on a 401', () {
    capture(
      responseError(401, {
        'message': 'Please sign in again.',
        'code': 'UNAUTHENTICATED',
      }),
    );

    expect(unauthenticatedCalls, hasLength(1));
  });

  test('surfaces the minimum version on an upgrade demand', () {
    final exception = capture(
      responseError(426, {
        'message': 'Please update antaraNote to continue.',
        'code': 'CLIENT_UPGRADE_REQUIRED',
        'minimum_version': '2.0.0',
        'store_url': 'https://example.test',
      }),
    );

    expect(exception.isUpgradeRequired, isTrue);
    expect(upgradeCalls, ['2.0.0']);
  });

  test('falls back cleanly when the body is not the expected envelope', () {
    final options = RequestOptions(path: '/meetings');

    final exception = capture(
      DioException(
        requestOptions: options,
        type: DioExceptionType.badResponse,
        response: Response(
          requestOptions: options,
          statusCode: 502,
          data: '<html>Bad Gateway</html>',
        ),
      ),
    );

    expect(exception.code, ApiErrorCode.serverError);
    expect(exception.statusCode, 502);
    expect(exception.isRetryable, isTrue);
  });

  test('a 4xx is not retried from the queue', () {
    final exception = capture(
      responseError(403, {
        'message': 'You are not allowed to do that.',
        'code': 'FORBIDDEN',
      }),
    );

    expect(exception.isRetryable, isFalse);
  });
}

class _CapturingHandler extends ErrorInterceptorHandler {
  _CapturingHandler(this.onReject);

  final void Function(DioException) onReject;

  @override
  void reject(DioException error, [bool callFollowingErrorInterceptor = false]) =>
      onReject(error);
}
