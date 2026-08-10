import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../domain/models/bootstrap.dart';
import '../shell/app_shell.dart';
import '../widgets/error_view.dart';
import '../widgets/gutter_row.dart';
import '../widgets/ledger_scaffold.dart';
import '../widgets/marker.dart';

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
      onRefresh: () async => ref.invalidate(bootstrapProvider),
      child: bootstrap.when(
        loading: () => const Center(
          child: SizedBox.square(
            dimension: 22,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
        ),
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

class _Body extends StatelessWidget {
  const _Body({required this.data});

  final BootstrapData data;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.only(bottom: 120),
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        _Standing(data: data),
        const SectionRule(label: 'Up next'),
        const _Empty(
          line: 'Nothing scheduled',
          detail: 'Meetings you are invited to appear here.',
        ),
        const SectionRule(label: 'Waiting on you'),
        _Waiting(unread: data.unread),
      ],
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
    final due = data.unread.actionItemsDue;
    final clear = due == 0;

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 26, 20, 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          DefaultTextStyle.merge(
            style: theme.textTheme.headlineSmall!.copyWith(height: 1.35),
            child: Wrap(
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                const Text('You have '),
                Marker(
                  child: Text(
                    clear ? 'nothing due' : '$due due today',
                    style: theme.textTheme.headlineSmall,
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
