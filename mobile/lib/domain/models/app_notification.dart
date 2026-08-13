import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';

/// One entry in the notification list.
///
/// `type` arrives as a dotted key the server derived from its own class names
/// — `action.item.assigned`, `meeting.finalized` — so the app never has to
/// know PHP namespaces. It is matched loosely: an unknown key still renders,
/// with the neutral mark, rather than disappearing because the server grew a
/// kind this build has not heard of.
class AppNotification {
  const AppNotification({
    required this.id,
    required this.type,
    this.title,
    this.body,
    this.meetingId,
    this.readAt,
    this.createdAt,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) =>
      AppNotification(
        id: json['id'] as String,
        type: json['type'] as String? ?? '',
        title: json['title'] as String?,
        body: json['body'] as String?,
        meetingId: (json['meeting_id'] as num?)?.toInt(),
        readAt: DateTime.tryParse(json['read_at'] as String? ?? ''),
        createdAt: DateTime.tryParse(json['created_at'] as String? ?? ''),
      );

  final String id;
  final String type;
  final String? title;
  final String? body;
  final int? meetingId;
  final DateTime? readAt;
  final DateTime? createdAt;

  bool get isUnread => readAt == null;

  AppNotification markRead(DateTime at) => AppNotification(
    id: id,
    type: type,
    title: title,
    body: body,
    meetingId: meetingId,
    readAt: at,
    createdAt: createdAt,
  );

  /// The short word that sits in the gutter.
  ///
  /// Kept to one word so the column holds; the full sentence is already in the
  /// title the server wrote. The keys are the eight the server actually sends,
  /// read off the notifications table rather than guessed from the class
  /// names — an earlier guess put every row under "note".
  String get mark => switch (type) {
    'action.item.assigned' => 'task',
    'action.item.overdue' => 'late',
    'calendar.meeting.starting' => 'soon',
    // Not "minutes": the cell above it holds 2m, 16h, 1d, and a kind that
    // reads as a duration in a column of durations is a kind nobody reads.
    'extraction.completed' => 'drafted',
    'transcription.completed' => 'audio',
    'stale.decision' => 'stale',
    _ when type.endsWith('.failed') => 'failed',
    // Not a fallback for the eight above — a shape for the ninth, whenever the
    // server grows one.
    _ => 'note',
  };

  /// Colour is spent only where the entry asks something of the reader, or
  /// tells them something did not happen.
  Color? get severity => switch (type) {
    _ when type.endsWith('.failed') => AppColors.danger,
    'action.item.overdue' => AppColors.danger,
    'calendar.meeting.starting' => AppColors.warning,
    'stale.decision' => AppColors.warning,
    _ => null,
  };
}
