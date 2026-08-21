import 'dart:convert';
import 'dart:io';

import 'package:antaranote/core/error/api_exception.dart';
import 'package:antaranote/data/api/api_client.dart';
import 'package:antaranote/data/local/secure_store.dart';
import 'package:antaranote/data/repositories/live_repository.dart';
import 'package:antaranote/features/recorder/recorder_role.dart';
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

  test('start sends the device label so the web app can name the phone', () async {
    final adapter = _Canned(
      status: 201,
      body: {
        'session': {'id': 7, 'meeting_id': 12, 'status': 'active'},
      },
    );

    await _repositoryReturning(adapter).start(
      12,
      chunkSeconds: 15,
      clientId: 'abc',
      deviceId: 'phone-uuid',
      deviceLabel: 'iPhone 15 Pro',
    );

    final body = adapter.seen?.data as Map<String, dynamic>;
    expect(body['device_label'], 'iPhone 15 Pro');
    expect(body['device_id'], 'phone-uuid');
  });

  test('start omits the device label when there is none', () async {
    final adapter = _Canned(
      status: 201,
      body: {
        'session': {'id': 7, 'meeting_id': 12, 'status': 'active'},
      },
    );

    await _repositoryReturning(
      adapter,
    ).start(12, chunkSeconds: 15, clientId: 'abc');

    final body = adapter.seen?.data as Map<String, dynamic>;
    expect(body.keys, isNot(contains('device_label')));
  });

  group('uploading a chunk from more than one device', () {
    late Directory scratch;
    late File audio;

    setUp(() {
      scratch = Directory.systemTemp.createTempSync('live-repo');
      audio = File('${scratch.path}/chunk-4.wav')
        ..writeAsBytesSync([0, 1, 2, 3]);
    });

    tearDown(() {
      if (scratch.existsSync()) scratch.deleteSync(recursive: true);
    });

    Map<String, String> fieldsOf(RequestOptions? seen) {
      final form = seen?.data as FormData?;

      return {
        for (final field in form?.fields ?? const []) field.key: field.value,
      };
    }

    Future<_Canned> upload({
      String deviceId = '',
      String deviceLabel = '',
      RecorderRole role = RecorderRole.primary,
    }) async {
      final adapter = _Canned(status: 201, body: {'chunk': {}});

      await _repositoryReturning(adapter).uploadChunk(
        9,
        file: audio,
        chunkNumber: 4,
        startTime: 60,
        endTime: 75,
        deviceId: deviceId,
        deviceLabel: deviceLabel,
        role: role,
      );

      return adapter;
    }

    // Invisible from the outside, which is why it is pinned here. The server
    // scopes its idempotency cache by user, so two people are already safe —
    // but one person recording on both their own phone and their own tablet
    // would have the satellite's chunk answered from the primary's cached
    // response. The audio never reaches the server and every response still
    // reads as a success.
    test(
      'two devices in one sitting do not share an idempotency key',
      () async {
        final primary = await upload(deviceId: 'laptop-at-the-head');
        final satellite = await upload(
          deviceId: 'phone-at-the-far-end',
          role: RecorderRole.satellite,
        );

        expect(
          primary.seen?.headers['Idempotency-Key'],
          isNot(satellite.seen?.headers['Idempotency-Key']),
        );
      },
    );

    // The other half of that: a retry after a timeout is the same chunk and
    // must still be recognised as one, or it is stored and billed twice.
    test('the same chunk retried from one device keeps its key', () async {
      final first = await upload(deviceId: 'phone-at-the-far-end');
      final again = await upload(deviceId: 'phone-at-the-far-end');

      expect(
        first.seen?.headers['Idempotency-Key'],
        again.seen?.headers['Idempotency-Key'],
      );
    });

    test('a satellite says which device it is on every chunk', () async {
      final adapter = await upload(
        deviceId: 'phone-at-the-far-end',
        role: RecorderRole.satellite,
      );

      expect(fieldsOf(adapter.seen), containsPair('role', 'satellite'));
      expect(
        fieldsOf(adapter.seen),
        containsPair('device_id', 'phone-at-the-far-end'),
      );
    });

    test('a satellite carries its device label so it can be named too', () async {
      final adapter = await upload(
        deviceId: 'phone-at-the-far-end',
        deviceLabel: 'Pixel 7',
        role: RecorderRole.satellite,
      );

      expect(fieldsOf(adapter.seen), containsPair('device_label', 'Pixel 7'));
    });

    // The browser recorder sends no device, and neither does an app build that
    // predates satellites. The server reads a missing device as the session's
    // one primary, which is exactly what those recordings are.
    test('a recorder with no device sends neither field', () async {
      final adapter = await upload();

      expect(fieldsOf(adapter.seen).keys, isNot(contains('device_id')));
      expect(fieldsOf(adapter.seen).keys, isNot(contains('role')));
    });
  });
}
