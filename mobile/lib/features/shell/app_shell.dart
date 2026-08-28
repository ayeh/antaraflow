import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/error/api_exception.dart';
import '../../core/haptics.dart';
import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../domain/models/bootstrap.dart';
import '../home/home_screen.dart';
import '../me/me_screen.dart';
import '../meetings/meetings_screen.dart';
import '../recorder/deep_link.dart';
import '../recorder/record_entry.dart';
import '../recorder/recorder_controller.dart';
import 'glass_tab_bar.dart';
import 'tab_bar_styles.dart';
import '../recorder/start_recording_sheet.dart';
import '../tasks/tasks_screen.dart';
import '../../l10n/app_localizations.dart';

/// Loaded once when the shell mounts; every tab reads from it rather than
/// asking the server again.
final bootstrapProvider = FutureProvider<BootstrapData>((ref) {
  return ref.watch(bootstrapRepositoryProvider).load();
});

/// Four destinations and a record button.
///
/// Four is the ceiling on purpose: a fifth pushes the record action off the
/// primary surface, and recording is the reason this app exists.
class AppShell extends ConsumerStatefulWidget {
  const AppShell({super.key});

  @override
  ConsumerState<AppShell> createState() => _AppShellState();
}

/// Which tab the shell is on.
///
/// A provider rather than State because Home's rows are shortcuts into the
/// other tabs — "3 minutes to approve" has to be able to send somebody to the
/// meetings list, and a callback threaded down four widgets to do it would be
/// worse than one piece of shared state.
final selectedTabProvider = StateProvider<int>((ref) => 0);

/// Tab positions, named so a jump reads as a destination rather than an index.
abstract final class Tabs {
  static const home = 0;
  static const meetings = 1;
  static const tasks = 2;
  static const me = 3;
}

class _AppShellState extends ConsumerState<AppShell> {
  /// The widget, the Action button and Siri all land here.
  final _entry = RecordEntry();

  /// A shared invite link lands here, to open the recorder as a satellite.
  final _link = DeepLinkEntry();

  final _visibility = TabBarVisibility();

  static const _tabs = <Widget>[
    HomeScreen(),
    MeetingsScreen(),
    TasksScreen(),
    MeScreen(),
  ];

  @override
  void initState() {
    super.initState();

    // After the first frame: a cold launch from the widget has a request
    // waiting, and opening the recorder needs a Navigator that exists.
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final waiting = await _entry.listen(_openRecorder);
      if (waiting) unawaited(_openRecorder());
      unawaited(_link.listen(_joinAsSatellite));
    });
  }

  @override
  void dispose() {
    _entry.dispose();
    _link.dispose();
    super.dispose();
  }

  /// True while the sheet or the recorder is up, so a second request — a
  /// cold-launch flag followed by a live call — does not stack another.
  bool _entering = false;

  Future<void> _openRecorder() async {
    if (!mounted || _entering) return;

    _entering = true;
    try {
      await startRecording(context, ref);
    } finally {
      _entering = false;
    }
  }

  /// Opens the recorder onto the sitting a shared link points at.
  ///
  /// The token is resolved before anything opens, so a dead link — an ended
  /// sitting, or one from another tenant — is one quiet line rather than a
  /// recorder staring at nothing. The recorder it lands on asks before it
  /// records: the server answers this phone's start with the satellite role,
  /// and that is the offer screen, not a microphone already open.
  Future<void> _joinAsSatellite(String token) async {
    if (!mounted || _entering) return;

    _entering = true;
    try {
      final invite = await ref.read(liveRepositoryProvider).resolveInvite(token);
      if (!mounted) return;

      await startRecordingFor(
        context,
        ref,
        meetingId: invite.meetingId,
        title: invite.title,
      );
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      _entering = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final unread = ref.watch(bootstrapProvider).valueOrNull?.unread;
    final index = ref.watch(selectedTabProvider);

    return Scaffold(
      backgroundColor: AppColors.paper,
      // Glass over nothing is just a pale rectangle. The bar has to float over
      // the scrolling list for the material to do anything at all — which is
      // itself part of the cost: content now runs underneath the tabs.
      extendBody: true,
      body: NotificationListener<ScrollNotification>(
        onNotification: (notification) {
          _visibility.onScroll(notification);
          return false;
        },
        child: IndexedStack(index: index, children: _tabs),
      ),
      bottomNavigationBar: _bar(index, unread?.actionItemsDue ?? 0),
    );
  }

  List<GlassDestination> _destinations(BuildContext context) {
    final l = L.of(context);

    return [
      GlassDestination(label: l.tabHome, icon: Icons.subject_rounded),
      GlassDestination(label: l.tabMeetings, icon: Icons.article_outlined),
      GlassDestination(
        label: l.tabTasks,
        icon: Icons.task_alt_rounded,
        badged: true,
      ),
      GlassDestination(label: l.tabMe, icon: Icons.person_outline_rounded),
    ];
  }

  Widget _bar(int index, int dueCount) {
    final bottom = MediaQuery.paddingOf(context).bottom;

    return CollapsingBar(
      visibility: _visibility,
      height: GlassTabBar.height + (bottom > 0 ? bottom + 6 : 12),
      child: GlassTabBar(
        index: index,
        dueCount: dueCount,
        onSelect: _select,
        onRecord: () => startRecording(context, ref),
        destinations: _destinations(context),
      ),
    );
  }

  void _select(int index) {
    if (index == ref.read(selectedTabProvider)) return;

    Haptics.select();
    _visibility.reset();
    ref.read(selectedTabProvider.notifier).state = index;
  }
}

/// Hand-built rather than a NavigationBar, so the record button can sit inside
/// the bar as a hard-edged block instead of a floating circle hovering over the
/// content. Nothing else in the interface is round; the record button should
/// not be the exception.
