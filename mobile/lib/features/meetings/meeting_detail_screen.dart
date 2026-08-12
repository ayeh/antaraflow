import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/error/api_exception.dart';
import '../../core/haptics.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../domain/models/meeting_detail.dart';
import '../attendance/attendance_screen.dart';
import '../recorder/start_recording_sheet.dart';
import '../widgets/error_view.dart';
import '../widgets/gutter_row.dart';
import '../widgets/ledger_scaffold.dart';
import '../widgets/prose.dart';
import '../widgets/stamp.dart';
import 'meeting_detail_controller.dart';

/// The minute itself.
///
/// Read top to bottom the way the record is written: what it is, what was
/// decided, who has to do something, who was there. The one action the record
/// is waiting for sits at the bottom, where somebody arrives after reading
/// rather than before.
class MeetingDetailScreen extends ConsumerStatefulWidget {
  const MeetingDetailScreen({
    super.key,
    required this.id,
    required this.title,
    this.reference,
  });

  final int id;
  final String title;

  /// The gutter reference this screen was opened from, so it can fly up into
  /// the masthead rather than the page arriving from nowhere.
  final String? reference;

  @override
  ConsumerState<MeetingDetailScreen> createState() =>
      _MeetingDetailScreenState();
}

class _MeetingDetailScreenState extends ConsumerState<MeetingDetailScreen> {
  /// True only for the seconds after the stamp was earned on this screen.
  bool _justSettled = false;

  @override
  Widget build(BuildContext context) {
    final meeting = ref.watch(meetingDetailProvider(widget.id));
    final loaded = meeting.valueOrNull;

    return LedgerScaffold(
      title: loaded?.title ?? widget.title,
      meta: _provenance(loaded),
      leading: IconButton(
        icon: const Icon(Icons.arrow_back_rounded),
        color: const Color(0xFFC3D0F0),
        tooltip: 'Back',
        onPressed: () => Navigator.of(context).maybePop(),
      ),
      reference: widget.reference ?? loaded?.momNumber,
      heroTag: widget.reference == null
          ? null
          : 'meeting-${widget.id}-${widget.reference}',
      onRefresh: () async =>
          ref.refresh(meetingDetailProvider(widget.id).future),
      child: meeting.when(
        loading: () => const _Loading(),
        error: (error, _) => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(meetingDetailProvider(widget.id)),
        ),
        data: (data) => _Body(
          meeting: data,
          justSettled: _justSettled,
          onStep: (step) => _take(data, step),
        ),
      ),
    );
  }

  String? _provenance(MeetingDetail? meeting) {
    if (meeting == null) return null;

    final parts = <String>[
      // The number is set above the title as the reference, not repeated here.
      if (meeting.date != null)
        DateFormat('d MMM yyyy').format(meeting.date!).toUpperCase(),
      meeting.status.label.toUpperCase(),
    ];

    return parts.join(' · ');
  }

  Future<void> _take(MeetingDetail meeting, MeetingStep step) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: AppColors.paperRaised,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppTheme.radiusM),
        ),
        title: Text(step.label, style: Theme.of(context).textTheme.titleMedium),
        content: Text(
          step.detail,
          style: Theme.of(context).textTheme.bodyMedium,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text(
              'Not yet',
              style: TextStyle(color: AppColors.inkSoft),
            ),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(
              step.confirm,
              style: const TextStyle(
                color: AppColors.primaryInk,
                fontWeight: FontWeight.w700,
                fontVariations: [FontVariation('wght', 700)],
              ),
            ),
          ),
        ],
      ),
    );

    if (!(confirmed ?? false)) return;

    try {
      await ref.read(meetingDetailProvider(widget.id).notifier).take(step);

      // The weight is spent here and nowhere else in the app: this is the
      // moment a record stops being a draft.
      Haptics.commit();
      if (mounted) setState(() => _justSettled = true);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }
}

class _Body extends ConsumerWidget {
  const _Body({
    required this.meeting,
    required this.justSettled,
    required this.onStep,
  });

  final MeetingDetail meeting;
  final bool justSettled;
  final ValueChanged<MeetingStep> onStep;

  /// A sign-in desk belongs to a sitting that is still happening. Once the
  /// minutes are on the record, attendance is history, not a door.
  bool _canRunDesk(MeetingDetail meeting) =>
      meeting.permissions.canUpdate && !meeting.status.isClosed;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final step = meeting.nextStep;

    return ListView(
      padding: const EdgeInsets.only(bottom: 120),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        _Facts(meeting: meeting),
        if (meeting.status.isClosed) _Settled(animate: justSettled),
        if (meeting.summary?.trim().isNotEmpty ?? false) ...[
          const SectionRule(label: 'Summary'),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 0),
            child: Text(
              meeting.summary!.trim(),
              style: Theme.of(
                context,
              ).textTheme.bodyLarge?.copyWith(height: 1.6),
            ),
          ),
        ],
        if (meeting.content?.trim().isNotEmpty ?? false) ...[
          const SectionRule(label: 'Minutes'),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 0),
            child: Prose(source: meeting.content!),
          ),
        ],
        if (meeting.resolutions.isNotEmpty) ...[
          SectionRule(
            label: 'Resolutions',
            trailing: '${meeting.resolutions.length}',
          ),
          for (final resolution in meeting.resolutions)
            _ResolutionRow(resolution: resolution),
        ],
        if (meeting.actionItems.isNotEmpty) ...[
          SectionRule(
            label: 'Action items',
            trailing: '${meeting.actionItems.where((a) => !a.isDone).length}',
          ),
          for (final action in meeting.actionItems) _ActionRow(action: action),
        ],
        if (meeting.attendees.isNotEmpty || _canRunDesk(meeting)) ...[
          SectionRule(
            label: 'Attendance',
            trailing: meeting.attendees.isEmpty
                ? null
                : '${meeting.presentCount}/${meeting.attendees.length}',
          ),
          if (meeting.attendees.isNotEmpty)
            _Attendance(attendees: meeting.attendees),
          if (_canRunDesk(meeting))
            Padding(
              padding: EdgeInsets.fromLTRB(
                20,
                meeting.attendees.isEmpty ? 6 : 16,
                20,
                0,
              ),
              child: OutlinedButton.icon(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => AttendanceScreen(
                      meetingId: meeting.id,
                      title: meeting.title,
                    ),
                  ),
                ),
                icon: const Icon(Icons.qr_code_rounded, size: 17),
                label: const Text('Sign-in desk'),
              ),
            ),
        ],
        if (step != null) ...[
          const SectionRule(label: 'Waiting on you'),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 6, 20, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(step.detail, style: Theme.of(context).textTheme.bodySmall),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: () => onStep(step),
                  child: Text(step.label),
                ),
              ],
            ),
          ),
        ],
        if (meeting.permissions.canStartLive && !meeting.status.isClosed) ...[
          const SectionRule(label: 'Record'),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 0),
            child: OutlinedButton.icon(
              onPressed: () => startRecordingFor(
                context,
                ref,
                meetingId: meeting.id,
                title: meeting.title,
              ),
              icon: const Icon(Icons.fiber_manual_record, size: 15),
              label: const Text('Record this sitting'),
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.danger,
                side: const BorderSide(color: AppColors.ruleStrong),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

/// The provenance block: date, place, who kept the record.
class _Facts extends StatelessWidget {
  const _Facts({required this.meeting});

  final MeetingDetail meeting;

  @override
  Widget build(BuildContext context) {
    final rows = <(String, String)>[
      if (meeting.date != null)
        // 'Sat' would have been the verb, but in a lowercase mono column
        // beside 'Thursday' it reads as an abbreviation of Saturday.
        ('Date', DateFormat('EEEE d MMMM yyyy, HH:mm').format(meeting.date!)),
      if (meeting.location?.isNotEmpty ?? false) ('At', meeting.location!),
      if (meeting.durationMinutes != null)
        ('Ran', '${meeting.durationMinutes} minutes'),
      if (meeting.attendees.isNotEmpty)
        ('Present', '${meeting.presentCount} of ${meeting.attendees.length}'),
      if (meeting.createdBy != null) ('Kept by', meeting.createdBy!),
      if (meeting.hasTranscript) ('Audio', 'Transcribed'),
      if (meeting.documentCount > 0)
        ('Papers', '${meeting.documentCount} attached'),
    ];

    if (rows.isEmpty) return const SizedBox(height: 8);

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 22, 20, 2),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (final (label, value) in rows)
            Padding(
              padding: const EdgeInsets.only(bottom: 7),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  SizedBox(
                    width: AppTheme.gutter,
                    child: Text(
                      label.toLowerCase(),
                      style: AppTheme.mono(
                        size: 11,
                        colour: AppColors.inkFaint,
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Text(
                      value,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _Settled extends StatelessWidget {
  const _Settled({required this.animate});

  final bool animate;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 14, 20, 6),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Stamp(
          label: 'Approved',
          caption: 'On the record',
          animate: animate,
        ),
      ),
    );
  }
}

class _ResolutionRow extends StatelessWidget {
  const _ResolutionRow({required this.resolution});

  final Resolution resolution;

  @override
  Widget build(BuildContext context) {
    final parts = <String>[
      if (resolution.mover != null) 'Moved by ${resolution.mover}',
      if (resolution.seconder != null) 'seconded by ${resolution.seconder}',
    ];

    return GutterRow(
      gutter: resolution.number ?? '${resolution.id}',
      gutterCaption: resolution.wasVoted ? resolution.tally : 'no vote',
      title: resolution.title,
      subtitle: parts.isEmpty ? null : parts.join(', '),
      status: resolution.open
          ? 'open'
          : (resolution.carried ? null : resolution.status),
      severity: resolution.carried
          ? AppColors.primaryInk
          : (resolution.open ? AppColors.warning : null),
    );
  }
}

class _ActionRow extends StatelessWidget {
  const _ActionRow({required this.action});

  final MeetingAction action;

  @override
  Widget build(BuildContext context) {
    return GutterRow(
      gutter: action.dueDate == null
          ? 'nil'
          : DateFormat('d MMM').format(action.dueDate!),
      gutterCaption: action.dueDate == null
          ? 'no date'
          : DateFormat('EEE').format(action.dueDate!).toLowerCase(),
      title: action.title,
      subtitle: action.assignee,
      struck: action.isDone,
      dimmed: action.isDone,
      severity: action.isOverdue && !action.isDone ? AppColors.danger : null,
    );
  }
}

/// Names, not rows.
///
/// Twenty attendees as twenty ruled rows is three screens of nothing; as a
/// paragraph of names it is one glance, which is all anybody wants from it.
class _Attendance extends StatelessWidget {
  const _Attendance({required this.attendees});

  final List<Attendee> attendees;

  @override
  Widget build(BuildContext context) {
    final present = attendees.where((a) => a.isPresent).toList();
    final absent = attendees.where((a) => !a.isPresent).toList();

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (present.isNotEmpty)
            _Names(label: 'Present', names: present.map((a) => a.name)),
          if (absent.isNotEmpty) ...[
            if (present.isNotEmpty) const SizedBox(height: 12),
            _Names(label: 'Apologies', names: absent.map((a) => a.name)),
          ],
        ],
      ),
    );
  }
}

class _Names extends StatelessWidget {
  const _Names({required this.label, required this.names});

  final String label;
  final Iterable<String> names;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(), style: AppTheme.eyebrow()),
        const SizedBox(height: 5),
        Text(
          names.join(' · '),
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.6),
        ),
      ],
    );
  }
}

class _Loading extends StatelessWidget {
  const _Loading();

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: const [
        SizedBox(height: 22),
        _Line(width: 240),
        _Line(width: 180),
        _Line(width: 210),
        SectionRule(label: 'Minutes'),
        SizedBox(height: 10),
        _Line(width: double.infinity),
        _Line(width: double.infinity),
        _Line(width: 220),
      ],
    );
  }
}

class _Line extends StatelessWidget {
  const _Line({required this.width});

  final double width;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 10),
      child: Container(
        width: width,
        height: 11,
        decoration: BoxDecoration(
          color: AppColors.rule,
          borderRadius: BorderRadius.circular(2),
        ),
      ),
    );
  }
}
