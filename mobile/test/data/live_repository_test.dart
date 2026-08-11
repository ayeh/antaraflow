import 'dart:convert';

import 'package:antaranote/core/error/api_exception.dart';
import 'package:antaranote/data/api/api_client.dart';
import 'package:antaranote/data/local/secure_store.dart';
import 'package:antaranote/data/repositories/live_repository.dart';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:package_info_plus/package_info_plus.dart';

/// Answers one canned response, then records what it was asked.
class _Canned implements HttpClientAdapter {
  _Canned({required this.status, required this.body});

  final int status;
  final Map<String, dynamic> body;

  RequestOptions? seen;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<List<int>>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    seen = options;

    return ResponseBody.fromString(
      jsonEncode(body),
      status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

LiveRepository _repositoryReturning(_Canned adapter) {
  final dio = Dio();
  final client = ApiClient(store: SecureStore(), dio: dio);
  dio.httpClientAdapter = adapter;

  return LiveRepository(client: client);
}

void main() {
  // The request goes through the real interceptor stack, because the bug this
  // file exists to catch was in that stack: the client converts every failure
  // to an ApiException, and the repository was still catching DioException.
  // Both plugins the header interceptor reaches for need stubbing for that.
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    FlutterSecureStorage.setMockInitialValues({});
    PackageInfo.setMockInitialValues(
      appName: 'antaraNote',
      packageName: 'cloud.antara.note',
      version: '1.0.0',
      buildNumber: '1',
      buildSignature: '',
    );
  });
  // The single most important failure in the whole recorder: the app is killed
  // mid-sitting, comes back, and must rejoin the session it already started
  // rather than refusing or opening a second one alongside it.
  group('starting a session that is already running', () {
    test('rejoins the session the server hands back with the 409', () async {
      final adapter = _Canned(
        status: 409,
        body: {
          'message': 'Meeting already has an active live session.',
          'code': ApiErrorCode.sessionAlreadyActive,
          'session': {
            'id': 314,
            'meeting_id': 12,
            'status': 'active',
            'total_duration_seconds': 1830,
          },
        },
      );

      final session = await _repositoryReturning(
        adapter,
      ).start(12, chunkSeconds: 15, clientId: 'abc');

      expect(session.id, 314);
      expect(session.meetingId, 12);
      expect(session.isActive, isTrue);
    });

    // Numbering from zero on a rejoin is the quietest way to lose a meeting:
    // the server answers each already-held number with CHUNK_DUPLICATE, the
    // outbox correctly drops it, and the second half of the sitting is thrown
    // away without a single error surfacing.
    test(
      'picks numbering and the clock up where the server left off',
      () async {
        final adapter = _Canned(
          status: 409,
          body: {
            'message': 'Meeting already has an active live session.',
            'code': ApiErrorCode.sessionAlreadyActive,
            'session': {
              'id': 314,
              'meeting_id': 12,
              'status': 'active',
              'total_duration_seconds': 1830,
            },
            'resume': {'next_chunk_number': 122, 'missing_chunks': <int>[]},
          },
        );

        final session = await _repositoryReturning(
          adapter,
        ).start(12, chunkSeconds: 15, clientId: 'abc');

        expect(session.nextChunkNumber, 122);
        expect(
          session.recordedAt(15),
          const Duration(minutes: 30, seconds: 30),
        );
      },
    );

    // A session that is still running has no duration yet — the server only
    // computes it on close — so the offset has to come from the chunk count or
    // the resumed audio is filed over the top of the first half.
    test('times new audio from the chunk count while still running', () async {
      final adapter = _Canned(
        status: 409,
        body: {
          'message': 'Meeting already has an active live session.',
          'code': ApiErrorCode.sessionAlreadyActive,
          'session': {
            'id': 314,
            'meeting_id': 12,
            'status': 'active',
            'total_duration_seconds': null,
          },
          'resume': {'next_chunk_number': 3},
        },
      );

      final session = await _repositoryReturning(
        adapter,
      ).start(12, chunkSeconds: 15, clientId: 'abc');

      expect(session.recordedAt(15), const Duration(seconds: 45));
    });

    test('a rejoin without a resume block starts numbering at zero', () async {
      final adapter = _Canned(
        status: 409,
        body: {
          'message': 'Meeting already has an active live session.',
          'code': ApiErrorCode.sessionAlreadyActive,
          'session': {'id': 314, 'meeting_id': 12, 'status': 'active'},
        },
      );

      final session = await _repositoryReturning(
        adapter,
      ).start(12, chunkSeconds: 15, clientId: 'abc');

      expect(session.nextChunkNumber, 0);
      expect(session.recordedAt(15), Duration.zero);
    });

    test('still fails when the server sends no session to rejoin', () async {
      final adapter = _Canned(
        status: 409,
        body: {
          'message': 'Meeting already has an active live session.',
          'code': ApiErrorCode.sessionAlreadyActive,
        },
      );

      expect(
        () => _repositoryReturning(
          adapter,
        ).start(12, chunkSeconds: 15, clientId: 'abc'),
        throwsA(isA<ApiException>()),
      );
    });

    test('a different conflict is not swallowed as a rejoin', () async {
      final adapter = _Canned(
        status: 409,
        body: {
          'message': 'This meeting has been approved and cannot be changed.',
          'code': ApiErrorCode.meetingApprovedImmutable,
          'session': {'id': 1, 'meeting_id': 12, 'status': 'active'},
        },
      );

      expect(
        () => _repositoryReturning(
          adapter,
        ).start(12, chunkSeconds: 15, clientId: 'abc'),
        throwsA(
          isA<ApiException>().having(
            (e) => e.code,
            'code',
            ApiErrorCode.meetingApprovedImmutable,
          ),
        ),
      );
    });
  });

  test('a fresh session is read straight out of the 201', () async {
    final adapter = _Canned(
      status: 201,
      body: {
        'session': {
          'id': 7,
          'meeting_id': 12,
          'status': 'active',
          'started_at': '2026-08-10T10:00:00+08:00',
        },
      },
    );

    final session = await _repositoryReturning(
      adapter,
    ).start(12, chunkSeconds: 15, clientId: 'abc');

    expect(session.id, 7);
    expect(adapter.seen?.path, '/meetings/12/live/start');
  });
}
