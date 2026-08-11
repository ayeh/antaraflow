import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';
import 'package:uuid/uuid.dart';

import '../../core/error/api_exception.dart';
import '../../core/providers.dart';
import '../../data/repositories/live_repository.dart';
import '../../domain/models/live_session.dart';
import 'audio_chunker.dart';
import 'chunk_outbox.dart';
import 'live_activity.dart';
import 'recording_service.dart';

final liveRepositoryProvider = Provider<LiveRepository>((ref) {
  return LiveRepository(client: ref.watch(apiClientProvider));
});

/// Scoped to the recorder route, so leaving the screen tears the session
/// machinery down rather than leaving a microphone open behind a list.
final recorderControllerProvider =
    StateNotifierProvider.autoDispose<RecorderController, RecorderState>((ref) {
      final controller = RecorderController(ref.watch(liveRepositoryProvider));
      ref.onDispose(controller.disposeAsync);

      return controller;
    });

enum RecorderPhase {
  /// Asking for the microphone and opening the session.
  preparing,

  /// The microphone was refused. Nothing else can happen until that changes.
  denied,

  recording,
  paused,

  /// Draining the queue before the session can be closed.
  finishing,
  finished,
  failed,
}

@immutable
class RecorderState {
  const RecorderState({
    this.phase = RecorderPhase.preparing,
    this.meetingTitle = '',
    this.sessionId,
    this.elapsed = Duration.zero,
    this.bookmarks = const [],
    this.chunksDropped = 0,
    this.error,
  });

  final RecorderPhase phase;
  final String meetingTitle;
  final int? sessionId;
  final Duration elapsed;

  /// Newest first: the mark someone just made is the one they want to see.
  final List<Bookmark> bookmarks;

  final int chunksDropped;
  final String? error;

  bool get isLive => phase == RecorderPhase.recording;
  bool get canMark =>
      phase == RecorderPhase.recording || phase == RecorderPhase.paused;

  String get clock {
    final h = elapsed.inHours.toString().padLeft(2, '0');
    final m = elapsed.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = elapsed.inSeconds.remainder(60).toString().padLeft(2, '0');

    return '$h:$m:$s';
  }

  RecorderState copyWith({
    RecorderPhase? phase,
    String? meetingTitle,
    int? sessionId,
    Duration? elapsed,
    List<Bookmark>? bookmarks,
    int? chunksDropped,
    String? error,
    bool clearError = false,
  }) {
    return RecorderState(
      phase: phase ?? this.phase,
      meetingTitle: meetingTitle ?? this.meetingTitle,
      sessionId: sessionId ?? this.sessionId,
      elapsed: elapsed ?? this.elapsed,
      bookmarks: bookmarks ?? this.bookmarks,
      chunksDropped: chunksDropped ?? this.chunksDropped,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

/// Runs one recording from the microphone to the server.
class RecorderController extends StateNotifier<RecorderState> {
  RecorderController(this._repository) : super(const RecorderState());

  final LiveRepository _repository;
  final _uuid = const Uuid();

  final _chunker = AudioChunker();
  final _activity = LiveActivity();
  final _service = RecordingService();
  ChunkOutbox? _outbox;
  StreamSubscription<AudioChunk>? _chunkSub;
  Timer? _ticker;
  Timer? _activityTicker;

  /// Repaints the meter without rebuilding the screen.
  ValueListenable<double> get level => _chunker.level;

  ValueListenable<OutboxStatus>? get outbox => _outbox?.status;

  /// Opens the session and the microphone.
  ///
  /// The order matters: the microphone comes first, so somebody who refuses
  /// permission has not silently created an empty session on the record.
  Future<void> begin({required int meetingId, required String title}) async {
    state = state.copyWith(meetingTitle: title, clearError: true);

    if (!await _chunker.hasPermission()) {
      state = state.copyWith(phase: RecorderPhase.denied);
      return;
    }

    try {
      final session = await _repository.start(
        meetingId,
        chunkSeconds: _chunker.chunkSeconds,
        clientId: _uuid.v4(),
      );

      _outbox = ChunkOutbox(repository: _repository, sessionId: session.id);
      _chunkSub = _chunker.chunks.listen((chunk) => _outbox?.add(chunk));

      // Cache, not documents: these files exist only until they are uploaded,
      // and the OS is welcome to reclaim them if the app dies holding them.
      final recorded = session.recordedAt(_chunker.chunkSeconds);

      final scratch = await getTemporaryDirectory();
      await _chunker.start(
        scratch,
        fromChunk: session.nextChunkNumber,
        alreadyRecorded: recorded,
      );

      unawaited(_activity.start(title: title, elapsed: recorded));
      unawaited(_service.start(title));

      _ticker = Timer.periodic(const Duration(milliseconds: 500), (_) {
        if (mounted) state = state.copyWith(elapsed: _chunker.position);
      });

      // The card runs its own clock, so it only needs telling when something
      // it cannot work out for itself changes. Once every fifteen seconds is
      // roughly per chunk, and well inside what the system will budget.
      _activityTicker = Timer.periodic(
        const Duration(seconds: 15),
        (_) => _pushActivity(),
      );

      state = state.copyWith(
        phase: RecorderPhase.recording,
        sessionId: session.id,
        // A rejoined sitting has been running for a while; starting the clock
        // at zero would tell the room the meeting just began.
        elapsed: recorded,
      );
    } on ApiException catch (e) {
      state = state.copyWith(phase: RecorderPhase.failed, error: e.message);
    }
  }

  /// Marks the moment.
  ///
  /// The timestamp is taken here, on the tap, and the mark is shown before the
  /// server is told. Somebody marking a decision is watching the room, not the
  /// screen, and the mark has to belong to the second they heard it rather
  /// than the second the request came back.
  Future<void> mark({
    BookmarkKind kind = BookmarkKind.general,
    String? label,
  }) async {
    final sessionId = state.sessionId;
    if (sessionId == null || !state.canMark) return;

    final bookmark = Bookmark(
      clientId: _uuid.v4(),
      at: _chunker.position,
      kind: kind,
      label: label,
    );

    state = state.copyWith(bookmarks: [bookmark, ...state.bookmarks]);
    _pushActivity();

    try {
      final saved = await _repository.addBookmark(
        sessionId,
        bookmark: bookmark,
      );
      _replace(saved);
    } on ApiException {
      // Kept exactly as it is, unsynced. The mark is the user's; losing it
      // because the network blinked is not an acceptable outcome, and the flag
      // is what lets the screen say so honestly.
    }
  }

  Future<void> togglePause() async {
    final sessionId = state.sessionId;
    if (sessionId == null) return;

    try {
      if (state.phase == RecorderPhase.recording) {
        await _chunker.pause();
        await _repository.pause(sessionId);
        state = state.copyWith(phase: RecorderPhase.paused);
        _pushActivity();
      } else if (state.phase == RecorderPhase.paused) {
        await _repository.resume(sessionId);
        await _chunker.resume();
        state = state.copyWith(phase: RecorderPhase.recording);
        _pushActivity();
      }
    } on ApiException catch (e) {
      state = state.copyWith(error: e.message);
    }
  }

  /// Stops recording and waits for the queue before closing the session.
  ///
  /// Ending the session while chunks are still in flight is how transcripts
  /// lose their last few minutes, so the wait is the point of this method.
  Future<void> end() async {
    final sessionId = state.sessionId;
    if (sessionId == null) return;

    state = state.copyWith(phase: RecorderPhase.finishing);

    _ticker?.cancel();
    _activityTicker?.cancel();
    unawaited(_activity.end());
    unawaited(_service.stop());
    await _chunker.stop();

    final undelivered = await _outbox?.drain() ?? 0;

    try {
      // The server also reports chunks it has not finished with, but at this
      // exact moment that number is mostly transcription still queued — it
      // counts down over the next minute or two. Reporting it as loss would
      // tell somebody their recording has holes in it after every single
      // meeting. Only audio that never left the phone is a real gap.
      await _repository.end(sessionId);

      state = state.copyWith(
        phase: RecorderPhase.finished,
        chunksDropped: undelivered,
      );
    } on ApiException catch (e) {
      state = state.copyWith(phase: RecorderPhase.failed, error: e.message);
    }
  }

  void _pushActivity() {
    unawaited(
      _activity.update(
        elapsed: _chunker.position,
        marks: state.bookmarks.length,
        queued: _outbox?.status.value.queued ?? 0,
        paused: state.phase == RecorderPhase.paused,
      ),
    );
  }

  void _replace(Bookmark saved) {
    if (!mounted) return;

    state = state.copyWith(
      bookmarks: [
        for (final existing in state.bookmarks)
          existing.clientId == saved.clientId ? saved : existing,
      ],
    );
  }

  Future<void> disposeAsync() async {
    _ticker?.cancel();
    _activityTicker?.cancel();
    // Leaving the screen while a session runs must not leave a card on the
    // lock screen, or a notification, with nothing behind it.
    unawaited(_activity.end());
    unawaited(_service.stop());
    await _chunkSub?.cancel();
    await _chunker.dispose();
    await _outbox?.close();
  }
}
