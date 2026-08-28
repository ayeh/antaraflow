import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path_provider/path_provider.dart';
import 'package:uuid/uuid.dart';

import '../../core/error/api_exception.dart';
import '../../core/providers.dart';
import '../../data/repositories/live_repository.dart';
import '../../domain/models/live_contributor.dart';
import '../../domain/models/live_session.dart';
import '../../core/haptics.dart';
import '../../data/local/device_label.dart';
import '../../data/local/secure_store.dart';
import 'audio_chunker.dart';
import 'chunk_outbox.dart';
import 'live_activity.dart';
import 'recorder_role.dart';
import 'recording_service.dart';
import 'room_level.dart';

final liveRepositoryProvider = Provider<LiveRepository>((ref) {
  return LiveRepository(client: ref.watch(apiClientProvider));
});

/// Scoped to the recorder route, so leaving the screen tears the session
/// machinery down rather than leaving a microphone open behind a list.
final recorderControllerProvider =
    StateNotifierProvider.autoDispose<RecorderController, RecorderState>((ref) {
      final controller = RecorderController(
        ref.watch(liveRepositoryProvider),
        ref.watch(secureStoreProvider),
      );
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

  /// Somebody else is already recording this sitting, and this phone has
  /// been asked whether it wants to help.
  offered,

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
    this.check = RoomVerdict.listening,
    this.room = RoomVerdict.listening,
    this.role = RecorderRole.primary,
    this.contributors = const [],
    this.error,
  });

  final RecorderPhase phase;
  final String meetingTitle;
  final int? sessionId;
  final Duration elapsed;

  /// Who else is feeding this sitting. Polled by the recording so it can show
  /// the room who has added a microphone; empty on a satellite, which does not
  /// ask.
  final List<LiveContributor> contributors;

  /// The satellites among the contributors — the ones worth naming on the
  /// recording's screen, since the recording is already itself.
  List<LiveContributor> get satellites =>
      contributors.where((person) => person.isSatellite).toList();

  /// How the phone was placed, settled once near the start of the sitting.
  final RoomVerdict check;

  /// How the room has sounded lately. Holds the last firm answer rather than
  /// falling back to `listening` every time the room goes quiet, because a
  /// warning that disappeared during the pause between sentences would be
  /// telling somebody the problem had fixed itself.
  final RoomVerdict room;

  /// Whether this phone is the recording or is helping another device record.
  ///
  /// Carried in state because the screen must say so for the whole sitting: a
  /// satellite is not recording the meeting, and somebody who cannot tell the
  /// two apart will stop the wrong device.
  final RecorderRole role;

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
    RoomVerdict? check,
    RoomVerdict? room,
    RecorderRole? role,
    List<LiveContributor>? contributors,
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
      check: check ?? this.check,
      room: room ?? this.room,
      role: role ?? this.role,
      contributors: contributors ?? this.contributors,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

/// The lines the recorder shows outside its own screen.
///
/// Localised where there is a BuildContext and handed down, because the
/// notification and the lock-screen card outlive the screen that started them
/// — the whole point of both is that they are what somebody sees when the app
/// is not in front of them.
@immutable
class RoomNotices {
  const RoomNotices({
    required this.recording,
    required this.faint,
    required this.silent,
  });

  final String recording;
  final String faint;
  final String silent;

  String? of(RoomVerdict verdict) => switch (verdict) {
    RoomVerdict.faint => faint,
    RoomVerdict.silent => silent,
    _ => null,
  };
}

/// Runs one recording from the microphone to the server.
class RecorderController extends StateNotifier<RecorderState> {
  RecorderController(this._repository, this._store)
    : super(const RecorderState());

  final LiveRepository _repository;
  final SecureStore _store;
  final _uuid = const Uuid();

  final _chunker = AudioChunker();
  final _activity = LiveActivity();
  final _service = RecordingService();
  RoomNotices? _notices;
  String _deviceId = '';
  String _deviceLabel = '';

  /// The running sitting this phone has been asked to help with, held while
  /// the question is on screen.
  LiveSession? _offered;

  ChunkOutbox? _outbox;
  StreamSubscription<AudioChunk>? _chunkSub;
  Timer? _ticker;
  Timer? _activityTicker;

  /// Repaints the meter without rebuilding the screen.
  ValueListenable<double> get level => _chunker.level;

  /// The last time the room was reported as hard to hear. Kept so a sitting in
  /// a genuinely difficult room buzzes once and then leaves people alone,
  /// rather than interrupting the meeting it is supposed to be recording.
  Duration? _lastComplaint;

  /// How long the warning holds its tongue after speaking up.
  static const _quietFor = Duration(minutes: 5);

  ValueListenable<OutboxStatus>? get outbox => _outbox?.status;

  /// Chunks that never made it to disk, and so will never reach the server.
  ValueListenable<int> get lost => _chunker.writeFailures;

  /// Opens the session and the microphone.
  ///
  /// The order matters: the microphone comes first, so somebody who refuses
  /// permission has not silently created an empty session on the record.
  Future<void> begin({
    required int meetingId,
    required String title,
    required RoomNotices notices,
    RecorderRole role = RecorderRole.primary,
  }) async {
    _notices = notices;
    state = state.copyWith(meetingTitle: title, role: role, clearError: true);

    if (!await _chunker.hasPermission()) {
      state = state.copyWith(phase: RecorderPhase.denied);
      return;
    }

    try {
      _deviceId = await _store.deviceId();
      _deviceLabel = await DeviceLabel.resolve();

      final session = await _repository.start(
        meetingId,
        chunkSeconds: _chunker.chunkSeconds,
        clientId: _uuid.v4(),
        deviceId: _deviceId,
        deviceLabel: _deviceLabel,
      );

      // Somebody else is recording this sitting. Opening the microphone now
      // and asking afterwards would be the wrong way round — this phone is
      // about to record a room on somebody else's behalf, and that is a
      // question, not a default.
      if (session.role.isSatellite && role != RecorderRole.satellite) {
        _offered = session;
        state = state.copyWith(phase: RecorderPhase.offered);

        return;
      }

      await _open(session, role: session.role);
    } on ApiException catch (e) {
      state = state.copyWith(phase: RecorderPhase.failed, error: e.message);
    }
  }

  /// Takes up the offer to be a second microphone.
  Future<void> helpRecord() async {
    final session = _offered;
    if (session == null) return;

    _offered = null;
    state = state.copyWith(phase: RecorderPhase.preparing);

    try {
      await _open(session, role: RecorderRole.satellite);
    } on ApiException catch (e) {
      state = state.copyWith(phase: RecorderPhase.failed, error: e.message);
    }
  }

  /// Opens the microphone and starts feeding the session.
  Future<void> _open(LiveSession session, {required RecorderRole role}) async {
    _outbox = ChunkOutbox(
      repository: _repository,
      sessionId: session.id,
      deviceId: _deviceId,
      deviceLabel: _deviceLabel,
      role: role,
    );
    _chunkSub = _chunker.chunks.listen((chunk) => _outbox?.add(chunk));

    // Cache, not documents: these files exist only until they are uploaded,
    // and the OS is welcome to reclaim them if the app dies holding them.
    final recorded = session.recordedAt(_chunker.chunkSeconds);

    final scratch = await getTemporaryDirectory();
    await _chunker.start(
      scratch,
      fromChunk: session.nextChunkNumber,
      alreadyRecorded: recorded,
      // A device joining mid-sitting waits for the next boundary of the
      // recording it is joining, rather than cutting on its own clock.
      discardFirst: role.isSatellite
          ? session.alignmentGap(_chunker.chunkSeconds)
          : Duration.zero,
    );

    final title = state.meetingTitle;
    unawaited(_activity.start(title: title, elapsed: recorded));
    unawaited(_service.start(title, note: _notices?.recording ?? ''));

    _chunker.room.check.addListener(_onCheck);
    _chunker.room.verdict.addListener(_onRoom);

    _ticker = Timer.periodic(const Duration(milliseconds: 500), (_) {
      if (mounted) state = state.copyWith(elapsed: _chunker.position);
    });

    // The card runs its own clock, so it only needs telling when something
    // it cannot work out for itself changes. Once every fifteen seconds is
    // roughly per chunk, and well inside what the system will budget.
    _activityTicker = Timer.periodic(const Duration(seconds: 15), (_) {
      _pushActivity();
      unawaited(_pollParticipants());
    });

    // Once now, so a satellite that joined before this device opened is named
    // without waiting out the first fifteen-second tick.
    unawaited(_pollParticipants());

    state = state.copyWith(
      phase: RecorderPhase.recording,
      role: role,
      sessionId: session.id,
      // A rejoined sitting has been running for a while; starting the clock
      // at zero would tell the room the meeting just began.
      elapsed: recorded,
    );
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

    // A satellite closes itself and nothing else. The session belongs to the
    // device that opened it, and ending it from here would stop the meeting
    // being recorded — and merge the transcript — because somebody put their
    // own phone down.
    if (state.role.isSatellite) {
      state = state.copyWith(
        phase: RecorderPhase.finished,
        chunksDropped: undelivered,
      );

      return;
    }

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

  void _onCheck() {
    if (!mounted) return;

    state = state.copyWith(check: _chunker.room.check.value);
  }

  /// Acts on the rolling verdict, once it changes to something firm.
  ///
  /// `listening` is skipped rather than stored: it means nobody has spoken
  /// lately, which is the normal state of a meeting and says nothing about
  /// whether the phone can hear.
  void _onRoom() {
    final verdict = _chunker.room.verdict.value;

    if (!mounted ||
        verdict == RoomVerdict.listening ||
        verdict == state.room ||
        state.phase != RecorderPhase.recording) {
      return;
    }

    state = state.copyWith(room: verdict);

    // Always kept true, and not rate-limited: a notification still saying the
    // room is hard to hear ten minutes after it stopped being hard to hear is
    // how somebody learns to ignore it.
    unawaited(_service.note(_notices?.of(verdict) ?? _notices?.recording));
    _pushActivity();

    if (verdict == RoomVerdict.clear) return;

    final now = _chunker.position;
    final last = _lastComplaint;
    if (last != null && now - last < _quietFor) return;

    _lastComplaint = now;

    // The one part somebody feels without looking, which is the point: the
    // phone is face down on the table and nobody is watching the screen.
    Haptics.commit();
  }

  /// Asks the server who else is in the room feeding this sitting.
  ///
  /// Only the recording asks: a satellite is helping one device, not running a
  /// roll call. A missed poll is nothing — the screen keeps whoever it last
  /// heard about, and the next tick is fifteen seconds away.
  Future<void> _pollParticipants() async {
    final sessionId = state.sessionId;
    if (sessionId == null || state.role.isSatellite) return;

    try {
      final people = await _repository.participants(sessionId);
      if (mounted) state = state.copyWith(contributors: people);
    } on ApiException {
      // Left for the next tick.
    }
  }

  void _pushActivity() {
    unawaited(
      _activity.update(
        elapsed: _chunker.position,
        marks: state.bookmarks.length,
        queued: _outbox?.status.value.queued ?? 0,
        paused: state.phase == RecorderPhase.paused,
        quiet:
            state.room == RoomVerdict.faint || state.room == RoomVerdict.silent,
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
    // Before the chunker goes: these hang off notifiers it owns and is about
    // to dispose.
    _chunker.room.check.removeListener(_onCheck);
    _chunker.room.verdict.removeListener(_onRoom);
    // Leaving the screen while a session runs must not leave a card on the
    // lock screen, or a notification, with nothing behind it.
    unawaited(_activity.end());
    unawaited(_service.stop());
    await _chunkSub?.cancel();
    await _chunker.dispose();
    await _outbox?.close();
  }
}
