import '../../features/recorder/recorder_role.dart';

/// What the server calls a bookmark: a moment somebody marked while the
/// meeting was still running.
enum BookmarkKind {
  decision('decision', 'Decision'),
  action('action', 'Action'),
  question('question', 'Question'),
  general('general', 'Mark');

  const BookmarkKind(this.wire, this.label);

  final String wire;
  final String label;

  static BookmarkKind fromWire(String? value) {
    return BookmarkKind.values.firstWhere(
      (kind) => kind.wire == value,
      orElse: () => BookmarkKind.general,
    );
  }
}

class LiveSession {
  const LiveSession({
    required this.id,
    required this.meetingId,
    required this.status,
    this.startedAt,
    this.totalDurationSeconds,
    this.nextChunkNumber = 0,
    this.role = RecorderRole.primary,
    this.secondsIntoChunk = 0,
  });

  factory LiveSession.fromJson(
    Map<String, dynamic> json, {
    int nextChunkNumber = 0,
    RecorderRole role = RecorderRole.primary,
    double secondsIntoChunk = 0,
  }) => LiveSession(
    id: json['id'] as int,
    meetingId: json['meeting_id'] as int,
    status: json['status'] as String? ?? 'active',
    startedAt: DateTime.tryParse(json['started_at'] as String? ?? ''),
    totalDurationSeconds: (json['total_duration_seconds'] as num?)?.toInt(),
    nextChunkNumber: nextChunkNumber,
    role: role,
    secondsIntoChunk: secondsIntoChunk,
  );

  final int id;
  final int meetingId;
  final String status;
  final DateTime? startedAt;
  final int? totalDurationSeconds;

  /// What this device should be in this sitting.
  ///
  /// Decided by the server, not guessed here: it is the only side that can see
  /// whether the device already has audio in this sitting — an app coming back
  /// from being killed — or whether somebody else is already recording and
  /// this is a second phone arriving to help.
  final RecorderRole role;

  /// How far past the last chunk boundary the sitting already is.
  ///
  /// A device joining in the middle waits this long before it starts cutting,
  /// so its chunks line up with the ones already being recorded rather than
  /// straddling two of them.
  final double secondsIntoChunk;

  /// Where a resumed recording has to pick up numbering.
  ///
  /// Restarting at zero is not a cosmetic error: the server answers a chunk
  /// number it already holds with CHUNK_DUPLICATE, the client correctly drops
  /// it from the queue, and every second recorded after the resume is thrown
  /// away without anything reporting a failure.
  final int nextChunkNumber;

  /// Where new audio joins the session's timeline.
  ///
  /// `total_duration_seconds` is only computed when a session is closed, so on
  /// a rejoin of a session that is still running it is null. The chunk count is
  /// what is reliable there, and it is exact: chunks are cut at a fixed length
  /// and an app that was killed never wrote a short final one.
  ///
  /// Getting this wrong does not fail loudly — the audio uploads, the numbers
  /// are unique — it just files the second half of the meeting over the top of
  /// the first, and the transcript comes back interleaved.
  Duration recordedAt(int chunkSeconds) {
    final total = totalDurationSeconds;
    if (total != null && total > 0) return Duration(seconds: total);

    return Duration(seconds: nextChunkNumber * chunkSeconds);
  }

  /// What a joining device has to throw away before it is in step.
  ///
  /// The server says how far past the boundary the sitting is; the remainder
  /// of that window is audio this device missed the start of, and sending a
  /// short chunk for it would cover only its tail while claiming the whole
  /// number. Zero for a device that is not joining anything.
  Duration alignmentGap(int chunkSeconds) {
    final into = Duration(milliseconds: (secondsIntoChunk * 1000).round());

    if (into <= Duration.zero) return Duration.zero;

    return Duration(seconds: chunkSeconds) - into;
  }

  bool get isActive => status == 'active';
  bool get isPaused => status == 'paused';
}

/// A mark on the timeline.
///
/// [id] is null until the server has acknowledged it. The mark is shown either
/// way — losing somebody's mark because the lift had no signal is the one
/// failure this feature cannot have.
class Bookmark {
  const Bookmark({
    required this.clientId,
    required this.at,
    required this.kind,
    this.id,
    this.label,
    this.synced = false,
  });

  final String clientId;
  final Duration at;
  final BookmarkKind kind;
  final int? id;
  final String? label;
  final bool synced;

  Bookmark copyWith({int? id, bool? synced}) => Bookmark(
    clientId: clientId,
    at: at,
    kind: kind,
    id: id ?? this.id,
    label: label,
    synced: synced ?? this.synced,
  );

  String get stamp {
    final h = at.inHours;
    final m = at.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = at.inSeconds.remainder(60).toString().padLeft(2, '0');

    return h > 0 ? '$h:$m:$s' : '$m:$s';
  }
}
