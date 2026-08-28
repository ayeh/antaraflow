import 'dart:io';

import 'package:dio/dio.dart';

import '../../core/error/api_exception.dart';
import '../../domain/models/live_contributor.dart';
import '../../domain/models/live_session.dart';
import '../../features/recorder/recorder_role.dart';
import '../../features/recorder/room_level.dart';
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
    String deviceId = '',
    String deviceLabel = '',
  }) async {
    try {
      final body = await _client.post(
        '/meetings/$meetingId/live/start',
        body: {
          'chunk_interval': chunkSeconds,
          'live_extraction': true,
          'client_id': clientId,
          if (deviceId.isNotEmpty) 'device_id': deviceId,
          if (deviceLabel.isNotEmpty) 'device_label': deviceLabel,
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
      final role = resume is Map ? resume['role'] : null;
      final into = resume is Map ? resume['seconds_into_chunk'] : null;

      return LiveSession.fromJson(
        Map<String, dynamic>.from(session),
        nextChunkNumber: (next as num?)?.toInt() ?? 0,
        // Defaulting to primary is the safe way round: a server too old to
        // answer this is one where satellites do not exist, and every rejoin
        // there is a recorder coming back to its own sitting.
        role: RecorderRole.fromWire(role as String?),
        secondsIntoChunk: (into as num?)?.toDouble() ?? 0,
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
    LevelReading? reading,
    String deviceId = '',
    String deviceLabel = '',
    RecorderRole role = RecorderRole.primary,
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
      // Omitted rather than sent as nulls when a chunk was too short to
      // measure, so a reading on the server always means a measurement was
      // actually taken.
      if (reading != null && reading.frames > 0) ...reading.toJson(),
      if (deviceId.isNotEmpty) ...{'device_id': deviceId, 'role': role.wire},
      if (deviceLabel.isNotEmpty) 'device_label': deviceLabel,
    });

    final body = await _client.upload(
      '/live/$sessionId/chunks',
      form: form,
      // The same chunk retried after a timeout must not be counted twice.
      //
      // The device is part of the key because two devices record the same
      // chunk numbers for the same sitting. The server scopes its idempotency
      // cache by user, so two people are already safe — but one person
      // recording on both their phone and their tablet is not, and their
      // satellite's chunk would be answered from the primary's cached response
      // without the audio ever reaching the server. Every response would still
      // look like a success.
      idempotencyKey: 'chunk-$sessionId-$deviceId-$chunkNumber',
    );

    return body['code'] != 'CHUNK_DUPLICATE';
  }

  /// Mints, or hands back, the token a primary shares so a colleague in the
  /// room can join this sitting as a satellite.
  ///
  /// Stable by design: sharing the same sitting twice returns the same token,
  /// so a link already sitting in a chat window keeps working.
  Future<({String token, int meetingId, String title})> invite(
    int sessionId,
  ) async {
    final body = await _client.post('/live/$sessionId/invite');
    final meeting = body['meeting'] as Map<String, dynamic>? ?? const {};

    return (
      token: body['token'] as String,
      meetingId: (meeting['id'] as num).toInt(),
      title: meeting['title'] as String? ?? '',
    );
  }

  /// Who is feeding the sitting right now: the recording and every satellite
  /// that has joined, named. Polled by the primary so it can show the room who
  /// has added a microphone.
  Future<List<LiveContributor>> participants(int sessionId) async {
    final body = await _client.get('/live/$sessionId/participants');
    final list = body['participants'];

    if (list is! List) return const [];

    return list
        .whereType<Map<String, dynamic>>()
        .map(LiveContributor.fromJson)
        .toList();
  }

  /// Resolves a shared token to the sitting it points at, so an invited phone
  /// knows which meeting to open and record.
  ///
  /// Throws [ApiException] when the link is no longer good — an ended sitting,
  /// or a token that never existed — which the caller shows as one plain line
  /// rather than opening a recorder onto nothing.
  Future<({int meetingId, String title})> resolveInvite(String token) async {
    final body = await _client.get('/live/join/$token');
    final meeting = body['meeting'] as Map<String, dynamic>? ?? const {};

    return (
      meetingId: (meeting['id'] as num).toInt(),
      title: meeting['title'] as String? ?? '',
    );
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
