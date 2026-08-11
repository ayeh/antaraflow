import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/haptics.dart';
import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../domain/models/bootstrap.dart';
import '../home/home_screen.dart';
import '../me/me_screen.dart';
import '../meetings/meetings_screen.dart';
import '../recorder/start_recording_sheet.dart';
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
        onSelect: _select,
        onRecord: () => startRecording(context, ref),
      ),
    );
  }

  void _select(int index) {
    if (index == _index) return;

    Haptics.select();
    setState(() => _index = index);
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

  /// Width of the sliding rule above the selected tab.
  static const _markerWidth = 26.0;
  static const _recordWidth = 76.0;

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
        child: LayoutBuilder(
          builder: (context, constraints) {
            return Stack(
              children: [
                Row(
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
                    const SizedBox(width: _recordWidth),
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
                // A single rule that travels, rather than one appearing under
                // the new tab as another vanishes. Where it came from is the
                // information — the eye follows it and knows what moved.
                AnimatedPositioned(
                  duration: const Duration(milliseconds: 340),
                  curve: AppTheme.easeOut,
                  left: _markerLeft(constraints.maxWidth),
                  top: 0,
                  width: _markerWidth,
                  height: 3,
                  child: const ColoredBox(color: AppColors.primary),
                ),
                Positioned(
                  left: (constraints.maxWidth - _recordWidth) / 2,
                  top: 0,
                  bottom: 0,
                  width: _recordWidth,
                  child: _RecordButton(onTap: onRecord),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  /// The record block sits in the middle of the row, so the four tabs are not
  /// evenly spaced across the bar and the marker cannot be placed by index
  /// alone.
  double _markerLeft(double width) {
    final tab = (width - _recordWidth) / 4;
    final slot = index < 2 ? index : index + 1;
    final offset = slot < 2
        ? slot * tab
        : (width + _recordWidth) / 2 + (slot - 3) * tab;

    return offset + (tab - _markerWidth) / 2;
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
    final weight = selected ? FontWeight.w800 : FontWeight.w600;

    return Expanded(
      child: InkWell(
        onTap: onTap,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // The selected rule is drawn once by the bar and slid between
            // tabs; the space it occupies is reserved here.
            const SizedBox(height: 12),
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
                fontWeight: weight,
                fontVariations: AppTheme.axis(weight),
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
class _RecordButton extends StatefulWidget {
  const _RecordButton({required this.onTap});

  final VoidCallback onTap;

  @override
  State<_RecordButton> createState() => _RecordButtonState();
}

class _RecordButtonState extends State<_RecordButton> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Semantics(
        button: true,
        label: 'Start recording',
        child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: widget.onTap,
          onTapDown: (_) => setState(() => _pressed = true),
          onTapUp: (_) => setState(() => _pressed = false),
          onTapCancel: () => setState(() => _pressed = false),
          // Presses in rather than rippling out. The recorder itself then
          // rises from the bottom edge, so the two read as one gesture.
          child: AnimatedScale(
            scale: _pressed ? 0.93 : 1,
            duration: const Duration(milliseconds: 140),
            curve: AppTheme.easeOut,
            child: Container(
              width: 58,
              height: 46,
              decoration: BoxDecoration(
                color: AppColors.recording,
                borderRadius: BorderRadius.circular(AppTheme.radiusM),
              ),
              child: const Icon(
                Icons.fiber_manual_record,
                color: Colors.white,
                size: 20,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
