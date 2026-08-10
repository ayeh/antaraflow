import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../domain/models/bootstrap.dart';
import '../home/home_screen.dart';
import '../me/me_screen.dart';
import '../meetings/meetings_screen.dart';
import '../tasks/tasks_screen.dart';

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

class _AppShellState extends ConsumerState<AppShell> {
  int _index = 0;

  static const _tabs = <Widget>[
    HomeScreen(),
    MeetingsScreen(),
    TasksScreen(),
    MeScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    final unread = ref.watch(bootstrapProvider).valueOrNull?.unread;

    return Scaffold(
      backgroundColor: AppColors.paper,
      body: IndexedStack(index: _index, children: _tabs),
      bottomNavigationBar: _TabBar(
        index: _index,
        dueCount: unread?.actionItemsDue ?? 0,
        onSelect: (i) => setState(() => _index = i),
        onRecord: () => _startRecording(context),
      ),
    );
  }

  void _startRecording(BuildContext context) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Recording is not wired up yet.')),
    );
  }
}

/// Hand-built rather than a NavigationBar, so the record button can sit inside
/// the bar as a hard-edged block instead of a floating circle hovering over the
/// content. Nothing else in the interface is round; the record button should
/// not be the exception.
class _TabBar extends StatelessWidget {
  const _TabBar({
    required this.index,
    required this.dueCount,
    required this.onSelect,
    required this.onRecord,
  });

  final int index;
  final int dueCount;
  final ValueChanged<int> onSelect;
  final VoidCallback onRecord;

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.paddingOf(context).bottom;

    return Container(
      decoration: const BoxDecoration(
        color: AppColors.paperRaised,
        border: Border(top: BorderSide(color: AppColors.ruleStrong)),
      ),
      padding: EdgeInsets.only(bottom: bottom),
      child: SizedBox(
        height: 64,
        child: Row(
          children: [
            _Tab(
              label: 'Home',
              icon: Icons.subject_rounded,
              selected: index == 0,
              onTap: () => onSelect(0),
            ),
            _Tab(
              label: 'Meetings',
              icon: Icons.article_outlined,
              selected: index == 1,
              onTap: () => onSelect(1),
            ),
            _RecordButton(onTap: onRecord),
            _Tab(
              label: 'Tasks',
              icon: Icons.task_alt_rounded,
              selected: index == 2,
              badge: dueCount,
              onTap: () => onSelect(2),
            ),
            _Tab(
              label: 'Me',
              icon: Icons.person_outline_rounded,
              selected: index == 3,
              onTap: () => onSelect(3),
            ),
          ],
        ),
      ),
    );
  }
}

class _Tab extends StatelessWidget {
  const _Tab({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
    this.badge = 0,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;
  final int badge;

  @override
  Widget build(BuildContext context) {
    final colour = selected ? AppColors.navyDeep : AppColors.inkFaint;

    return Expanded(
      child: InkWell(
        onTap: onTap,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // A rule above the selected tab, echoing the section rules on the
            // page. No pill, no floating indicator.
            SizedBox(
              height: 3,
              child: selected
                  ? Container(width: 26, color: AppColors.primary)
                  : null,
            ),
            const SizedBox(height: 9),
            Stack(
              clipBehavior: Clip.none,
              children: [
                Icon(icon, size: 21, color: colour),
                if (badge > 0)
                  Positioned(
                    right: -7,
                    top: -4,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 4,
                        vertical: 1,
                      ),
                      decoration: BoxDecoration(
                        color: AppColors.danger,
                        borderRadius: BorderRadius.circular(AppTheme.radiusS),
                      ),
                      child: Text(
                        '$badge',
                        style: AppTheme.mono(
                          size: 9.5,
                          weight: FontWeight.w700,
                          colour: Colors.white,
                          tracking: 0,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 5),
            Text(
              label,
              style: TextStyle(
                fontSize: 10.5,
                fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                color: colour,
                letterSpacing: 0.1,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Present on every tab. Someone walking into a meeting should never have to
/// navigate before they can start capturing it.
class _RecordButton extends StatelessWidget {
  const _RecordButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 76,
      child: Center(
        child: Material(
          color: AppColors.recording,
          borderRadius: BorderRadius.circular(AppTheme.radiusM),
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(AppTheme.radiusM),
            child: const SizedBox(
              width: 58,
              height: 46,
              child: Icon(Icons.fiber_manual_record, color: Colors.white, size: 20),
            ),
          ),
        ),
      ),
    );
  }
}
