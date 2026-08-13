import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/haptics.dart';
import '../../core/theme/app_theme.dart';
import '../../domain/models/app_notification.dart';
import '../meetings/meeting_detail_screen.dart';
import '../widgets/error_view.dart';
import '../widgets/gutter_row.dart';
import '../widgets/ledger_scaffold.dart';
import 'notifications_controller.dart';
import '../../l10n/app_localizations.dart';

/// What the app has been trying to tell you.
///
/// Ruled rows like everything else, with the kind in the gutter rather than an
/// icon per line — forty notifications with forty coloured circles is the
/// cluttered feed this app exists not to be.
class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(notificationsProvider);
    final controller = ref.read(notificationsProvider.notifier);

    return LedgerScaffold(
      title: L.of(context).notifications,
      meta: _meta(context, state),
      leading: IconButton(
        icon: const Icon(Icons.arrow_back_rounded),
        color: const Color(0xFFC3D0F0),
        tooltip: L.of(context).back,
        onPressed: () => Navigator.of(context).maybePop(),
      ),
      actions: [
        if (state.unread > 0)
          MastheadAction(
            icon: Icons.done_all_rounded,
            tooltip: L.of(context).markAllRead,
            onPressed: () {
              Haptics.tick();
              controller.markAllRead();
            },
          ),
      ],
      onRefresh: controller.load,
      child: switch (state) {
        NotificationsState(error: final error?) => ErrorView(
          error: error,
          onRetry: controller.load,
        ),
        NotificationsState(loading: true, items: []) => const _Loading(),
        NotificationsState(items: []) => const _Empty(),
        _ => _List(state: state, controller: controller),
      },
    );
  }

  String? _meta(BuildContext context, NotificationsState state) {
    if (state.loading && state.items.isEmpty) return null;
    final l = L.of(context);
    if (state.items.isEmpty) return l.nothingWaiting;

    return state.unread == 0 ? l.allRead : l.unreadCount(state.unread);
  }
}

class _List extends StatefulWidget {
  const _List({required this.state, required this.controller});

  final NotificationsState state;
  final NotificationsController controller;

  @override
  State<_List> createState() => _ListState();
}

class _ListState extends State<_List> {
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_maybeLoadMore);
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  void _maybeLoadMore() {
    if (!_scroll.hasClients) return;

    // A screen's warning, so the next page is already in hand by the time the
    // reader reaches the bottom rather than arriving after a stall.
    final remaining =
        _scroll.position.maxScrollExtent - _scroll.position.pixels;
    if (remaining < 600) widget.controller.loadMore();
  }

  @override
  Widget build(BuildContext context) {
    final items = widget.state.items;

    return ListView.builder(
      controller: _scroll,
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.only(bottom: 110),
      itemCount: items.length + (widget.state.hasMore ? 1 : 0),
      itemBuilder: (context, index) {
        if (index >= items.length) return const _More();

        return _Row(
          notification: items[index],
          onTap: () => _open(context, items[index]),
        );
      },
    );
  }

  Future<void> _open(BuildContext context, AppNotification notification) async {
    Haptics.select();
    await widget.controller.markRead(notification);

    final meetingId = notification.meetingId;
    if (meetingId == null || !context.mounted) return;

    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => MeetingDetailScreen(
          id: meetingId,
          title: notification.title ?? L.of(context).meeting,
        ),
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.notification, required this.onTap});

  final AppNotification notification;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final when = notification.createdAt;

    return Opacity(
      // Read entries stay legible but stop competing. Removing them would
      // lose the only record of what was already said.
      opacity: notification.isUnread ? 1 : 0.55,
      child: GutterRow(
        gutter: when == null ? L.of(context).gutterNil : _ago(context, when),
        gutterCaption: notification.mark(context),
        title: notification.title ?? L.of(context).notification,
        subtitle: notification.body,
        severity: notification.severity,
        onTap: onTap,
      ),
    );
  }

  /// Short enough for the gutter: minutes, then hours, then the date.
  String _ago(BuildContext context, DateTime at) {
    final l = L.of(context);
    final gap = DateTime.now().difference(at);

    if (gap.inMinutes < 1) return l.agoNow;
    if (gap.inMinutes < 60) return l.agoMinutes(gap.inMinutes);
    if (gap.inHours < 24) return l.agoHours(gap.inHours);
    if (gap.inDays < 7) return l.agoDays(gap.inDays);

    return DateFormat(
      'd MMM',
      Localizations.localeOf(context).toLanguageTag(),
    ).format(at);
  }
}

class _More extends StatelessWidget {
  const _More();

  @override
  Widget build(BuildContext context) =>
      const GutterRowSkeleton(titleFraction: 0.62);
}

class _Loading extends StatelessWidget {
  const _Loading();

  @override
  Widget build(BuildContext context) {
    const widths = [0.68, 0.54, 0.79, 0.6, 0.72];

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        for (final width in widths) GutterRowSkeleton(titleFraction: width),
      ],
    );
  }
}

class _Empty extends StatelessWidget {
  const _Empty();

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
                Text(L.of(context).nothingWaiting, style: AppTheme.eyebrow()),
                const SizedBox(height: 14),
                Text(
                  L.of(context).upToDate,
                  style: Theme.of(context).textTheme.headlineSmall,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Text(
                  L.of(context).nothingWaitingNotifications,
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
