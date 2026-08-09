import 'package:dio/dio.dart';

import '../../core/config/app_config.dart';
import '../../core/error/api_exception.dart';
import '../local/secure_store.dart';
import 'interceptors/error_interceptor.dart';
import 'interceptors/headers_interceptor.dart';

/// Thin wrapper over Dio that always throws [ApiException].
class ApiClient {
  ApiClient({
    required SecureStore store,
    Dio? dio,
    void Function()? onUnauthenticated,
    void Function(String? minimumVersion, String? storeUrl)? onUpgradeRequired,
  }) : _dio = dio ?? Dio() {
    _dio.options
      ..baseUrl = AppConfig.apiRoot
      ..connectTimeout = AppConfig.connectTimeout
      ..receiveTimeout = AppConfig.receiveTimeout
      // 4xx bodies carry the error envelope, so they must reach the
      // interceptor rather than being swallowed as transport failures.
      ..validateStatus = (status) => status != null && status < 400;

    _dio.interceptors.addAll([
      HeadersInterceptor(store: store),
      ErrorInterceptor(
        onUnauthenticated: onUnauthenticated,
        onUpgradeRequired: onUpgradeRequired,
      ),
    ]);
  }

  final Dio _dio;

  Dio get raw => _dio;

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, dynamic>? query,
  }) async {
    return _asMap(await _send(() => _dio.get(path, queryParameters: query)));
  }

  Future<List<dynamic>> getList(
    String path, {
    Map<String, dynamic>? query,
  }) async {
    final body = await get(path, query: query);
    final data = body['data'];

    return data is List ? data : const [];
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Object? body,
    String? idempotencyKey,
  }) async {
    return _asMap(
      await _send(
        () => _dio.post(
          path,
          data: body,
          options: idempotencyKey == null
              ? null
              : Options(headers: {'Idempotency-Key': idempotencyKey}),
        ),
      ),
    );
  }

  Future<Map<String, dynamic>> patch(String path, {Object? body}) async {
    return _asMap(await _send(() => _dio.patch(path, data: body)));
  }

  Future<void> delete(String path) async {
    await _send(() => _dio.delete(path));
  }

  /// Multipart upload with a longer ceiling than an ordinary request.
  Future<Map<String, dynamic>> upload(
    String path, {
    required FormData form,
    String? idempotencyKey,
    void Function(int sent, int total)? onProgress,
  }) async {
    return _asMap(
      await _send(
        () => _dio.post(
          path,
          data: form,
          onSendProgress: onProgress,
          options: Options(
            sendTimeout: AppConfig.uploadTimeout,
            receiveTimeout: AppConfig.uploadTimeout,
            headers: idempotencyKey == null
                ? null
                : {'Idempotency-Key': idempotencyKey},
          ),
        ),
      ),
    );
  }

  Future<Response<dynamic>> _send(
    Future<Response<dynamic>> Function() request,
  ) async {
    try {
      return await request();
    } on DioException catch (e) {
      final error = e.error;
      if (error is ApiException) {
        throw error;
      }

      throw ApiException(
        message: 'Something went wrong. Please try again.',
        code: ApiErrorCode.serverError,
        statusCode: e.response?.statusCode,
      );
    }
  }

  Map<String, dynamic> _asMap(Response<dynamic> response) {
    final data = response.data;

    if (data is Map<String, dynamic>) {
      return data;
    }

    // 204 and other empty successes are legitimate; callers that expect a body
    // will simply find the keys absent.
    return const {};
  }
}
