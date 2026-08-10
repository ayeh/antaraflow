import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/error_view.dart';
import '../widgets/gutter_row.dart';
import '../widgets/ledger_scaffold.dart';

/// The minute book.
///
/// Reference number and date live in the gutter so the whole list can be read
/// down one column, the way somebody flicks through a bound volume looking for
/// a sitting. Status is a flat tag, not a coloured pill: on a list this long,
/// a filled badge on every row is more colour than content.
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

  /// Only two statuses earn colour. A record that is merely finalised is the
  /// normal state of things and does not need flagging.
  Color? get severity => switch (status) {
    'approved' => AppColors.primaryInk,
    'pending_confirmation' => AppColors.warning,
    _ => null,
  };

  String get statusLabel => status.replaceAll('_', ' ');

  /// The sequence number alone.
  ///
  /// Stored references look like MOM-2026-000003 and are far too wide for the
  /// gutter — they wrap to two lines and destroy the column the layout is built
  /// around. A minute book is read by sitting number, so that is what shows.
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

    return LedgerScaffold(
      title: 'Minute book',
      meta: meetings.valueOrNull == null
          ? null
          : '${meetings.value!.length} SITTINGS RECORDED',
      actions: [
        MastheadAction(
          icon: Icons.search_rounded,
          tooltip: 'Search minutes',
          onPressed: () {},
        ),
      ],
      onRefresh: () async => ref.invalidate(meetingsProvider),
      child: meetings.when(
        loading: () => const Center(
          child: SizedBox.square(
            dimension: 22,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
        ),
        error: (error, _) => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(meetingsProvider),
        ),
        data: (rows) => rows.isEmpty
            ? const _NoMeetings()
            : StaggeredList(
                padding: const EdgeInsets.only(top: 8, bottom: 110),
                children: [
                  for (final meeting in rows)
                    GutterRow(
                      // The list is ordered by sitting date, so the date is
                      // what the gutter carries. Reference numbers are issued
                      // on creation and do not run in date order, which made
                      // the column read as noise rather than as an axis.
                      gutter: meeting.date == null
                          ? '—'
                          : DateFormat('d MMM').format(meeting.date!),
                      gutterCaption: meeting.reference,
                      title: meeting.title,
                      subtitle: _detail(meeting),
                      status: meeting.statusLabel,
                      severity: meeting.severity,
                      onTap: () {},
                      trailing: const Icon(
                        Icons.chevron_right_rounded,
                        size: 18,
                        color: AppColors.inkFaint,
                      ),
                    ),
                ],
              ),
      ),
    );
  }

  String? _detail(MeetingSummary meeting) {
    final parts = <String>[
      if (meeting.attendeeCount != null) '${meeting.attendeeCount} present',
      // Only the first line of an address. The seeder produces full postal
      // addresses and a wrapped second line pushes every row past the fold.
      if (meeting.location != null && meeting.location!.isNotEmpty)
        meeting.location!.split(RegExp(r'[,\n]')).first.trim(),
    ];

    return parts.isEmpty ? null : parts.join(' · ');
  }
}

class _NoMeetings extends StatelessWidget {
  const _NoMeetings();

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        const SizedBox(height: 60),
        Center(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 40),
            child: Column(
              children: [
                Text('THE BOOK IS EMPTY', style: AppTheme.eyebrow()),
                const SizedBox(height: 14),
                Text(
                  'Nothing recorded yet',
                  style: Theme.of(context).textTheme.headlineSmall,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Text(
                  'Record a meeting and the minutes will be filed here.',
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
