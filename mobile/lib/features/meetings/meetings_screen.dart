import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/error_view.dart';
import '../widgets/gutter_row.dart';
import '../widgets/ledger_scaffold.dart';

/// The list of recorded meetings.
///
/// The sitting date lives in the gutter so the whole list reads down one
/// column, the way somebody flicks through a bound volume looking for a date.
/// Month breaks give the eye somewhere to rest and are the only element on the
/// screen that is not shaped like a row.
final meetingsProvider = FutureProvider<List<MeetingSummary>>((ref) async {
  final rows = await ref.watch(apiClientProvider).getList('/meetings');

  return rows
      .cast<Map<String, dynamic>>()
      .map(MeetingSummary.fromJson)
      .toList();
});

class MeetingSummary {
  const MeetingSummary({
    required this.id,
    required this.title,
    required this.status,
    this.momNumber,
    this.date,
    this.location,
    this.attendeeCount,
  });

  factory MeetingSummary.fromJson(Map<String, dynamic> json) => MeetingSummary(
    id: json['id'] as int,
    title: json['title'] as String? ?? 'Untitled',
    status: json['status'] as String? ?? 'draft',
    momNumber: json['mom_number'] as String?,
    date: DateTime.tryParse(json['meeting_date'] as String? ?? ''),
    location: json['location'] as String?,
    attendeeCount: json['attendee_count'] as int?,
  );

  final int id;
  final String title;
  final String status;
  final String? momNumber;
  final DateTime? date;
  final String? location;
  final int? attendeeCount;

  /// Only the exceptional states are tagged.
  ///
  /// Draft and finalised are what most rows are, and a bordered tag on every
  /// row is furniture that dilutes the two tags that carry meaning.
  bool get isExceptional =>
      status == 'approved' || status == 'pending_confirmation';

  Color? get severity => switch (status) {
    'approved' => AppColors.primaryInk,
    'pending_confirmation' => AppColors.warning,
    _ => null,
  };

  String? get statusLabel =>
      isExceptional ? status.replaceAll('_', ' ') : null;

  /// The sequence number alone.
  ///
  /// Stored references look like MOM-2026-000003 and are far too wide for the
  /// gutter; they wrap to two lines and destroy the column the layout is built
  /// around.
  String get reference {
    final number = momNumber;
    if (number == null || number.isEmpty) return '$id';

    final tail = number.split(RegExp(r'[-/]')).last;
    final digits = int.tryParse(tail);

    return digits?.toString().padLeft(3, '0') ?? tail;
  }
}

class MeetingsScreen extends ConsumerWidget {
  const MeetingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final meetings = ref.watch(meetingsProvider);
    final count = meetings.valueOrNull?.length;

    return LedgerScaffold(
      title: 'Meetings',
      meta: count == null
          ? null
          : (count == 1 ? '1 MEETING' : '$count MEETINGS'),
      // Search is deliberately absent until it works. A visible affordance
      // that silently does nothing costs more trust than a missing one.
      onRefresh: () async => ref.invalidate(meetingsProvider),
      child: meetings.when(
        loading: () => const _Loading(),
        error: (error, _) => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(meetingsProvider),
        ),
        data: (rows) => rows.isEmpty
            ? const _NoMeetings()
            : StaggeredList(
                padding: const EdgeInsets.only(bottom: 110),
                children: _entries(context, rows),
              ),
      ),
    );
  }

  /// Interleaves month breaks with rows.
  ///
  /// The API returns meetings ordered by sitting date, so a change of month is
  /// a real boundary in the data rather than a decorative divider.
  List<Widget> _entries(BuildContext context, List<MeetingSummary> rows) {
    final entries = <Widget>[];
    String? currentMonth;

    for (final meeting in rows) {
      final month = meeting.date == null
          ? 'Undated'
          : DateFormat('MMMM yyyy').format(meeting.date!);

      if (month != currentMonth) {
        currentMonth = month;
        entries.add(_MonthBreak(label: month));
      }

      entries.add(
        GutterRow(
          gutter: meeting.date == null
              ? 'nil'
              : DateFormat('d MMM').format(meeting.date!),
          gutterCaption: meeting.reference,
          title: meeting.title,
          subtitle: _detail(meeting),
          status: meeting.statusLabel,
          severity: meeting.severity,
          onTap: () => ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Opening minutes is not built yet.')),
          ),
        ),
      );
    }

    return entries;
  }

  String? _detail(MeetingSummary meeting) {
    final parts = <String>[
      if (meeting.attendeeCount != null) '${meeting.attendeeCount} present',
      // Only the first line of an address. A wrapped second line pushes every
      // row past the fold.
      if (meeting.location != null && meeting.location!.isNotEmpty)
        meeting.location!.split(RegExp(r'[,\n]')).first.trim(),
    ];

    return parts.isEmpty ? null : parts.join(' · ');
  }
}

/// The one element on the screen that is not a row.
///
/// It carries no gutter, so the column visibly breaks and restarts, which is
/// what makes a long list feel navigable rather than endless.
class _MonthBreak extends StatelessWidget {
  const _MonthBreak({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: AppColors.ruleStrong)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 28, 20, 9),
      child: Text(label.toUpperCase(), style: AppTheme.eyebrow()),
    );
  }
}

class _Loading extends StatelessWidget {
  const _Loading();

  @override
  Widget build(BuildContext context) {
    // Varying widths so the placeholder reads as text rather than as a bar
    // chart. Six is roughly one screen.
    const widths = [0.72, 0.54, 0.83, 0.61, 0.77, 0.48];

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        const _MonthBreak(label: 'Loading'),
        for (final width in widths) GutterRowSkeleton(titleFraction: width),
      ],
    );
  }
}

class _NoMeetings extends StatelessWidget {
  const _NoMeetings();

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        const SizedBox(height: 64),
        Center(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 40),
            child: Column(
              children: [
                Text('NOTHING RECORDED', style: AppTheme.eyebrow()),
                const SizedBox(height: 14),
                Text(
                  'No meetings yet',
                  style: Theme.of(context).textTheme.headlineSmall,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Text(
                  'Record a meeting and its minutes will be filed here.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
