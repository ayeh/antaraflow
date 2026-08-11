import 'dart:io';

import 'package:dio/dio.dart';

import '../../core/error/api_exception.dart';
import '../../domain/models/live_session.dart';
import '../api/api_client.dart';

/// The live recording endpoints.
///
/// Everything here is written assuming the network will fail mid-meeting,
/// because on a phone in a basement boardroom it will. Duplicate chunks are a
/// success, not an error; a bookmark that cannot be posted is kept locally.
class LiveRepository {
  const LiveRepository({required ApiClient client}) : _client = client;

  final ApiClient _client;

  /// Starts a session, or rejoins the one already running.
  ///
  /// The server answers a second start with 409 and hands back the active
  /// session, which is the whole resume story: an app killed mid-meeting comes
  /// back to the same session rather than opening a second one alongside it.
  Future<LiveSession> start(
    int meetingId, {
    required int chunkSeconds,
    required String clientId,
  }) async {
    try {
      final body = await _client.post(
        '/meetings/$meetingId/live/start',
        body: {
          'chunk_interval': chunkSeconds,
          'live_extraction': true,
          'client_id': clientId,
        },
      );

      return LiveSession.fromJson(body['session'] as Map<String, dynamic>);
    } on ApiException catch (e) {
      // Not DioException: the client layer converts every failure to an
      // ApiException before it gets here, and the running session is on
      // `extra` rather than on a raw response body.
      final session = e.extra['session'];

      if (e.code != ApiErrorCode.sessionAlreadyActive || session is! Map) {
        rethrow;
      }

      final resume = e.extra['resume'];
      final next = resume is Map ? resume['next_chunk_number'] : null;

      return LiveSession.fromJson(
        Map<String, dynamic>.from(session),
        nextChunkNumber: (next as num?)?.toInt() ?? 0,
      );
    }
  }

  /// Uploads one chunk.
  ///
  /// Returns false when the server already had this chunk number, which means
  /// drop it from the queue rather than retry it.
  Future<bool> uploadChunk(
    int sessionId, {
    required File file,
    required int chunkNumber,
    required double startTime,
    required double endTime,
  }) async {
    final form = FormData.fromMap({
      // No explicit content type: Laravel's `mimetypes` rule guesses from the
      // bytes, not from what the client claims, so the RIFF header is what
      // actually decides — and a wrong header here would fail validation while
      // looking correct.
      'audio': await MultipartFile.fromFile(
        file.path,
        filename: 'chunk-$chunkNumber.wav',
      ),
      'chunk_number': chunkNumber,
      'start_time': startTime,
      'end_time': endTime,
    });

    final body = await _client.upload(
      '/live/$sessionId/chunks',
      form: form,
      // The same chunk retried after a timeout must not be counted twice.
      idempotencyKey: 'chunk-$sessionId-$chunkNumber',
    );

    return body['code'] != 'CHUNK_DUPLICATE';
  }

  Future<Bookmark> addBookmark(
    int sessionId, {
    required Bookmark bookmark,
  }) async {
    final body = await _client.post(
      '/live/$sessionId/bookmarks',
      body: {
        'at_seconds': bookmark.at.inMilliseconds / 1000,
        'kind': bookmark.kind.wire,
        if (bookmark.label != null && bookmark.label!.isNotEmpty)
          'label': bookmark.label,
        'client_id': bookmark.clientId,
      },
      idempotencyKey: 'bookmark-${bookmark.clientId}',
    );

    final saved = body['bookmark'] as Map<String, dynamic>?;

    return bookmark.copyWith(id: saved?['id'] as int?, synced: true);
  }

  Future<LiveSession> pause(int sessionId) async {
    final body = await _client.post('/live/$sessionId/pause');

    return LiveSession.fromJson(body['session'] as Map<String, dynamic>);
  }

  Future<LiveSession> resume(int sessionId) async {
    final body = await _client.post('/live/$sessionId/resume');

    return LiveSession.fromJson(body['session'] as Map<String, dynamic>);
  }

  /// Ends the session. `chunks_dropped` is surfaced rather than swallowed: a
  /// transcript with holes in it should be admitted, not discovered later.
  Future<int> end(int sessionId) async {
    final body = await _client.post('/live/$sessionId/end');

    return (body['chunks_dropped'] as num?)?.toInt() ?? 0;
  }

  /// Creates the meeting a recording will be filed against.
  Future<({int id, String title})> createMeeting({
    required String title,
    required DateTime date,
    required String clientId,
  }) async {
    final body = await _client.post(
      '/meetings',
      body: {
        'title': title,
        'meeting_date': date.toIso8601String(),
        'client_id': clientId,
      },
      idempotencyKey: 'meeting-$clientId',
    );

    final meeting = body['data'] is Map ? body['data'] as Map : body;

    return (
      id: meeting['id'] as int,
      title: meeting['title'] as String? ?? title,
    );
  }
}
