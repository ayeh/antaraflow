import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:uuid/uuid.dart';

import '../../core/error/api_exception.dart';
import '../../core/haptics.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../meetings/create_meeting_sheet.dart';
import '../meetings/meeting_detail_controller.dart';
import '../meetings/meetings_screen.dart';
import 'recorder_controller.dart';
import 'recorder_screen.dart';

/// Asks the one question that has to be answered before recording: which
/// meeting is this?
///
/// It is a sheet rather than a screen because the answer is usually obvious —
/// the sitting happening now — and anything longer than one tap between walking
/// into a room and recording is time the transcript does not have.
Future<void> startRecording(BuildContext context, WidgetRef ref) async {
  Haptics.tick();

  final choice = await showModalBottomSheet<_Choice>(
    context: context,
    backgroundColor: AppColors.paperRaised,
    isScrollControlled: true,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(
        top: Radius.circular(AppTheme.radiusM),
      ),
    ),
    builder: (context) => const _Sheet(),
  );

  if (choice == null || !context.mounted) return;

  // Hands off to the full form — title, type, date, place — rather than
  // filing something named after the clock. Somebody planning ahead has more
  // to say than somebody who just walked into a room.
  if (choice.scheduleOnly) {
    await showCreateMeeting(context, ref);

    return;
  }

  final target = choice.meetingId != null
      ? (id: choice.meetingId!, title: choice.title)
      : await _createMeeting(context, ref, choice.title);

  if (target == null || !context.mounted) return;

  await startRecordingFor(
    context,
    ref,
    meetingId: target.id,
    title: target.title,
  );
}

/// Opens the recorder straight onto a known sitting, skipping the question.
///
/// Used from a meeting's own screen, where the answer is already on screen and
/// asking it again would be a step for nothing.
Future<void> startRecordingFor(
  BuildContext context,
  WidgetRef ref, {
  required int meetingId,
  required String title,
}) async {
  await Navigator.of(
    context,
  ).push(recorderRoute(meetingId: meetingId, title: title));

  if (!context.mounted) return;

  // Both are stale the moment a recording ends: a new sitting was filed, or an
  // existing one grew a transcript and a live session.
  ref
    ..invalidate(meetingsProvider)
    ..invalidate(meetingDetailProvider(meetingId));
}

Future<({int id, String title})?> _createMeeting(
  BuildContext context,
  WidgetRef ref,
  String title,
) async {
  try {
    return await ref
        .read(liveRepositoryProvider)
        .createMeeting(
          title: title,
          date: DateTime.now(),
          clientId: const Uuid().v4(),
        );
  } on ApiException catch (e) {
    if (context.mounted) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.message)));
    }

    return null;
  }
}

class _Choice {
  const _Choice({required this.title, this.meetingId})
    : scheduleOnly = false;

  /// File a sitting and stop there — no recorder, no session.
  const _Choice.schedule() : title = '', meetingId = null, scheduleOnly = true;

  final String title;
  final int? meetingId;
  final bool scheduleOnly;
}

class _Sheet extends ConsumerWidget {
  const _Sheet();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final meetings = ref.watch(meetingsProvider);
    final now = DateTime.now();

    return SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
            child: Text('RECORD INTO', style: AppTheme.eyebrow()),
          ),
          ..._candidates(meetings.valueOrNull ?? const [], now).map(
            (meeting) => _Row(
              reference: meeting.date == null
                  ? 'nil'
                  : DateFormat('d MMM').format(meeting.date!),
              caption: meeting.reference,
              title: meeting.title,
              onTap: () => Navigator.of(
                context,
              ).pop(_Choice(meetingId: meeting.id, title: meeting.title)),
            ),
          ),
          _Row(
            reference: DateFormat('d MMM').format(now),
            caption: 'new',
            title: 'A sitting not on the list',
            accent: true,
            onTap: () =>
                Navigator.of(context).pop(_Choice(title: _defaultTitle(now))),
          ),
          // Below the rule because it is the one row here that does not start
          // a recording. Somebody reaching for this button is usually in the
          // room already — but this is the button their thumb knows, and the
          // alternative was the far corner of another screen.
          Container(
            margin: const EdgeInsets.only(top: 10),
            padding: const EdgeInsets.fromLTRB(20, 14, 20, 4),
            decoration: const BoxDecoration(
              border: Border(top: BorderSide(color: AppColors.ruleStrong)),
            ),
            child: Text('OR FILE IT FOR LATER', style: AppTheme.eyebrow()),
          ),
          _Row(
            reference: '—',
            caption: 'plan',
            title: 'New meeting, without recording',
            onTap: () => Navigator.of(context).pop(const _Choice.schedule()),
          ),
          const SizedBox(height: 12),
        ],
      ),
    );
  }

  /// Draft sittings within a day either side of now.
  ///
  /// Anything finalised is closed, and a meeting three weeks old is not the one
  /// somebody standing in a room is about to record.
  List<MeetingSummary> _candidates(List<MeetingSummary> all, DateTime now) {
    final window = all.where((meeting) {
      final date = meeting.date;
      if (date == null || meeting.status != 'draft') return false;

      return date.difference(now).abs() < const Duration(days: 1);
    }).toList();

    return window.take(4).toList();
  }

  String _defaultTitle(DateTime now) {
    return 'Recording, ${DateFormat('d MMMM').format(now)} at '
        '${DateFormat('HH:mm').format(now)}';
  }
}

class _Row extends StatelessWidget {
  const _Row({
    required this.reference,
    required this.caption,
    required this.title,
    required this.onTap,
    this.accent = false,
  });

  final String reference;
  final String caption;
  final String title;
  final VoidCallback onTap;
  final bool accent;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Container(
        decoration: const BoxDecoration(
          border: Border(top: BorderSide(color: AppColors.rule)),
        ),
        padding: const EdgeInsets.fromLTRB(20, 15, 20, 15),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: AppTheme.gutter,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    reference,
                    style: AppTheme.mono(
                      size: 12,
                      weight: FontWeight.w700,
                      colour: accent ? AppColors.primaryInk : AppColors.inkSoft,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    caption,
                    style: AppTheme.mono(
                      size: 10.5,
                      colour: AppColors.inkFaint,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Text(
                title,
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
