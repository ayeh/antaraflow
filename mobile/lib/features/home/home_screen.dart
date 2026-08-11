import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../domain/models/bootstrap.dart';
import '../shell/app_shell.dart';
import '../widgets/error_view.dart';
import '../widgets/gutter_row.dart';
import '../widgets/ledger_scaffold.dart';
import '../widgets/marker.dart';
import '../widgets/rolling_count.dart';

/// What needs deciding, not a dashboard of charts.
///
/// Charts belong on the web. Someone opening this on a phone is asking three
/// things: what is next, what is late, and what is waiting on me — so the page
/// answers those in that order and stops.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bootstrap = ref.watch(bootstrapProvider);
    final now = DateTime.now();

    return LedgerScaffold(
      title: _greeting(now),
      meta: DateFormat('EEEE d MMMM · HH:mm').format(now).toUpperCase(),
      actions: [
        MastheadAction(
          icon: Icons.notifications_none_rounded,
          tooltip: 'Notifications',
          badge: bootstrap.valueOrNull?.unread.notifications ?? 0,
          onPressed: () {},
        ),
      ],
      onRefresh: () async {
        ref.invalidate(bootstrapProvider);
        ref.invalidate(upcomingProvider);
      },
      child: bootstrap.when(
        loading: () => const _Loading(),
        error: (error, _) => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(bootstrapProvider),
        ),
        data: (data) => _Body(data: data),
      ),
    );
  }

  String _greeting(DateTime now) {
    if (now.hour < 12) return 'Good morning';
    if (now.hour < 18) return 'Good afternoon';
    return 'Good evening';
  }
}

/// The sittings that have not happened yet.
///
/// Read from the calendar endpoint rather than the meetings list, which is
/// ordered newest-first and would need the whole archive pulled down to find
/// the three that matter.
final upcomingProvider = FutureProvider<List<Upcoming>>((ref) async {
  final now = DateTime.now();
  final today = DateTime(now.year, now.month, now.day);

  final rows = await ref
      .watch(apiClientProvider)
      .getList(
        '/meetings/calendar',
        query: {
          'from': today.toIso8601String(),
          'to': today.add(const Duration(days: 14)).toIso8601String(),
        },
      );

  // A sitting that started twenty minutes ago is still the next one — someone
  // walking in late needs it at the top, not filtered out.
  final grace = now.subtract(const Duration(hours: 1));

  return rows
      .cast<Map<String, dynamic>>()
      .map(Upcoming.fromJson)
      .where((meeting) => meeting.date != null && meeting.date!.isAfter(grace))
      .take(3)
      .toList();
});

class Upcoming {
  const Upcoming({
    required this.id,
    required this.title,
    this.date,
    this.location,
  });

  factory Upcoming.fromJson(Map<String, dynamic> json) => Upcoming(
    id: json['id'] as int,
    title: json['title'] as String? ?? 'Untitled',
    date: DateTime.tryParse(json['meeting_date'] as String? ?? ''),
    location: json['location'] as String?,
  );

  final int id;
  final String title;
  final DateTime? date;
  final String? location;

  bool get isToday {
    final at = date;
    if (at == null) return false;

    final now = DateTime.now();
    return at.year == now.year && at.month == now.month && at.day == now.day;
  }

  bool get isSoon {
    final at = date;
    if (at == null) return false;

    final until = at.difference(DateTime.now());

    return until < const Duration(minutes: 30) &&
        until > const Duration(hours: -1);
  }
}

class _Body extends ConsumerWidget {
  const _Body({required this.data});

  final BootstrapData data;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final upcoming = ref.watch(upcomingProvider);

    return ListView(
      padding: const EdgeInsets.only(bottom: 120),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        _Standing(data: data),
        const SectionRule(label: 'Up next'),
        ...switch (upcoming) {
          AsyncData(:final value) when value.isEmpty => const [
            _Empty(
              line: 'Nothing scheduled',
              detail: 'Meetings you are invited to appear here.',
            ),
          ],
          AsyncData(:final value) => [
            for (final meeting in value) _UpcomingRow(meeting: meeting),
          ],
          AsyncError() => const [
            _Empty(
              line: 'Could not load the diary',
              detail: 'Pull down to try again.',
            ),
          ],
          _ => const [GutterRowSkeleton(titleFraction: 0.62)],
        },
        const SectionRule(label: 'Waiting on you'),
        _Waiting(unread: data.unread),
      ],
    );
  }
}

class _UpcomingRow extends StatelessWidget {
  const _UpcomingRow({required this.meeting});

  final Upcoming meeting;

  @override
  Widget build(BuildContext context) {
    final at = meeting.date;

    return GutterRow(
      gutter: at == null ? 'nil' : DateFormat('HH:mm').format(at),
      gutterCaption: at == null
          ? 'undated'
          : (meeting.isToday
                ? 'today'
                : DateFormat('EEE d').format(at).toLowerCase()),
      title: meeting.title,
      subtitle: meeting.location,
      // The only row on this screen that ever turns amber, and only in the
      // half hour where somebody should be walking towards the room.
      severity: meeting.isSoon ? AppColors.warning : null,
      status: meeting.isSoon ? 'now' : null,
      onTap: () {},
    );
  }
}

/// The one sentence worth reading on this screen, with the highlighter on the
/// number that decides whether anything else matters today.
class _Standing extends StatelessWidget {
  const _Standing({required this.data});

  final BootstrapData data;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final style = theme.textTheme.headlineSmall!;
    final due = data.unread.actionItemsDue;
    final clear = due == 0;

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 26, 20, 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          DefaultTextStyle.merge(
            style: style.copyWith(height: 1.35),
            child: Wrap(
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                const Text('You have '),
                Marker(
                  child: clear
                      ? Text('nothing due', style: style)
                      : Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            // Rolls when the count changes, so clearing a task
                            // is visible here rather than silently different.
                            RollingCount(value: due, style: style),
                            Text(' due today', style: style),
                          ],
                        ),
                ),
                const Text('.'),
              ],
            ),
          ),
          const SizedBox(height: 10),
          Text(
            clear
                ? 'Nothing is overdue and nothing is waiting on your approval.'
                : 'Everything else can wait until these are cleared.',
            style: theme.textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

class _Waiting extends StatelessWidget {
  const _Waiting({required this.unread});

  final UnreadCounts unread;

  @override
  Widget build(BuildContext context) {
    // Only what is actually waiting. Listing a zero next to "nothing is due"
    // under a heading that already says "waiting on you" tells someone the same
    // thing three times, and buries the one row that matters on the days when
    // there is one.
    final rows = <Widget>[
      if (unread.actionItemsDue > 0)
        GutterRow(
          gutter: '${unread.actionItemsDue}',
          gutterCaption: 'due',
          title: 'Action items',
          subtitle: 'Due today or earlier',
          severity: AppColors.danger,
          onTap: () {},
        ),
      if (unread.pendingApprovals > 0)
        GutterRow(
          gutter: '${unread.pendingApprovals}',
          gutterCaption: 'open',
          title: 'Minutes to approve',
          subtitle: 'Circulated to you, not yet answered',
          severity: AppColors.warning,
          onTap: () {},
        ),
    ];

    if (rows.isEmpty) {
      return const _Empty(
        line: 'Nothing waiting',
        detail: 'Approvals and overdue items land here.',
      );
    }

    return Column(children: rows);
  }
}

class _Loading extends StatelessWidget {
  const _Loading();

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: const [
        Padding(
          padding: EdgeInsets.fromLTRB(20, 30, 20, 22),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _Bar(height: 22, width: 250),
              SizedBox(height: 14),
              _Bar(height: 11, width: 190),
            ],
          ),
        ),
        SectionRule(label: 'Up next'),
        GutterRowSkeleton(titleFraction: 0.62),
        SectionRule(label: 'Waiting on you'),
        GutterRowSkeleton(titleFraction: 0.44),
      ],
    );
  }
}

class _Bar extends StatelessWidget {
  const _Bar({required this.height, required this.width});

  final double height;
  final double width;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: AppColors.rule,
        borderRadius: BorderRadius.circular(2),
      ),
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty({required this.line, required this.detail});

  final String line;
  final String detail;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        border: Border(
          top: BorderSide(color: AppColors.rule),
          bottom: BorderSide(color: AppColors.rule),
        ),
      ),
      padding: const EdgeInsets.fromLTRB(20, 26, 20, 26),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: AppTheme.gutter,
            child: Text(
              'nil',
              style: AppTheme.mono(size: 13, colour: AppColors.inkFaint),
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(line, style: Theme.of(context).textTheme.titleSmall),
                const SizedBox(height: 4),
                Text(detail, style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
