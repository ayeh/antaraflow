import 'dart:async';
import 'dart:collection';

import 'package:flutter/foundation.dart';

import '../../data/repositories/live_repository.dart';
import 'audio_chunker.dart';
import 'recorder_role.dart';

/// How the queue is doing, in the terms the recorder screen reports it.
@immutable
class OutboxStatus {
  const OutboxStatus({this.sent = 0, this.queued = 0, this.attempts = 0});

  final int sent;
  final int queued;

  /// Consecutive failures on the chunk at the head of the queue. Two or more
  /// means this is a real problem rather than one dropped packet, which is the
  /// point at which the screen should say so.
  final int attempts;

  bool get isStalled => attempts >= 2;
}

/// Sends chunks, one at a time, forever if it has to.
///
/// Serial rather than parallel on purpose: a phone leaving a building has a
/// narrow, unreliable pipe, and three concurrent uploads on it finish slower
/// than one and fail more often. Nothing is ever dropped for failing — a chunk
/// that will not send stays at the head of the queue and blocks the ones behind
/// it, because a transcript with a hole in the middle is worse than a
/// transcript that arrives late.
class ChunkOutbox {
  ChunkOutbox({
    required LiveRepository repository,
    required this.sessionId,
    this.deviceId = '',
    this.deviceLabel = '',
    this.role = RecorderRole.primary,
  }) : _repository = repository;

  final LiveRepository _repository;
  final int sessionId;

  /// Read once when the session opens rather than per chunk: this comes from
  /// the keychain, and reaching for it every fifteen seconds for the length of
  /// a meeting is work for nothing.
  final String deviceId;

  /// The human device name, sent alongside every chunk so a satellite phone —
  /// which never calls start — still gets labelled on the web app.
  final String deviceLabel;

  final RecorderRole role;

  final _pending = Queue<AudioChunk>();
  final status = ValueNotifier(const OutboxStatus());

  bool _pumping = false;
  bool _closed = false;
  int _sent = 0;
  int _attempts = 0;

  void add(AudioChunk chunk) {
    if (_closed) return;

    _pending.add(chunk);
    _publish();
    unawaited(_pump());
  }

  /// Waits for the queue to empty, or gives up after [limit].
  ///
  /// Returns the number of chunks that never made it. The caller shows that
  /// number rather than claiming a clean finish.
  Future<int> drain({Duration limit = const Duration(seconds: 45)}) async {
    final deadline = DateTime.now().add(limit);

    while (_pending.isNotEmpty && DateTime.now().isBefore(deadline)) {
      await _pump();
      if (_pending.isEmpty) break;
      await Future<void>.delayed(const Duration(milliseconds: 400));
    }

    return _pending.length;
  }

  Future<void> close() async {
    _closed = true;

    for (final chunk in _pending) {
      await _discard(chunk);
    }

    _pending.clear();
    status.dispose();
  }

  Future<void> _pump() async {
    if (_pumping || _pending.isEmpty) return;
    _pumping = true;

    try {
      while (_pending.isNotEmpty && !_closed) {
        final chunk = _pending.first;

        try {
          await _repository.uploadChunk(
            sessionId,
            file: chunk.file,
            chunkNumber: chunk.number,
            startTime: chunk.start.inMilliseconds / 1000,
            endTime: chunk.end.inMilliseconds / 1000,
            reading: chunk.reading,
            deviceId: deviceId,
            deviceLabel: deviceLabel,
            role: role,
          );

          _pending.removeFirst();
          await _discard(chunk);
          _sent++;
          _attempts = 0;
          _publish();
        } catch (_) {
          _attempts++;
          _publish();

          // Backs off to eight seconds and stays there. Longer than that and a
          // queue built up in a lift takes the rest of the meeting to clear
          // once signal returns.
          final wait = Duration(
            seconds: (1 << _attempts.clamp(0, 3)).clamp(1, 8),
          );
          await Future<void>.delayed(wait);

          if (_closed) return;
        }
      }
    } finally {
      _pumping = false;
    }
  }

  Future<void> _discard(AudioChunk chunk) async {
    try {
      if (await chunk.file.exists()) await chunk.file.delete();
    } catch (_) {
      // A leftover file in the cache directory is not worth failing over.
    }
  }

  void _publish() {
    if (_closed) return;

    status.value = OutboxStatus(
      sent: _sent,
      queued: _pending.length,
      attempts: _attempts,
    );
  }
}
