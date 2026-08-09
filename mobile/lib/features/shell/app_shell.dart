import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
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

/// Four tabs and a record button, per the UX proposal.
///
/// Four is the ceiling on purpose: a fifth destination pushes the record action
/// off the primary surface, and recording is the reason this app exists.
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
    final bootstrap = ref.watch(bootstrapProvider);
    final unread = bootstrap.valueOrNull?.unread;

    return Scaffold(
      body: IndexedStack(index: _index, children: _tabs),
      floatingActionButton: _RecordButton(
        onPressed: () => _startRecording(context),
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerDocked,
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (index) => setState(() => _index = index),
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: 'Home',
          ),
          const NavigationDestination(
            icon: Icon(Icons.event_note_outlined),
            selectedIcon: Icon(Icons.event_note),
            label: 'Meetings',
          ),
          NavigationDestination(
            icon: _Badged(
              count: unread?.actionItemsDue ?? 0,
              child: const Icon(Icons.checklist_outlined),
            ),
            selectedIcon: const Icon(Icons.checklist),
            label: 'Tasks',
          ),
          NavigationDestination(
            icon: _Badged(
              count: unread?.notifications ?? 0,
              child: const Icon(Icons.person_outline),
            ),
            selectedIcon: const Icon(Icons.person),
            label: 'Me',
          ),
        ],
      ),
    );
  }

  void _startRecording(BuildContext context) {
    // The recording screen lands next; this keeps the affordance honest in the
    // meantime rather than silently doing nothing.
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Recording is not wired up yet.')),
    );
  }
}

/// Always visible, on every tab. Someone walking into a meeting should never
/// have to navigate before they can start capturing it.
class _RecordButton extends StatelessWidget {
  const _RecordButton({required this.onPressed});

  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 64,
      height: 64,
      child: FloatingActionButton(
        onPressed: onPressed,
        backgroundColor: AppColors.recording,
        foregroundColor: Colors.white,
        elevation: 3,
        shape: const CircleBorder(),
        tooltip: 'Record a meeting',
        child: const Icon(Icons.mic, size: 30),
      ),
    );
  }
}

class _Badged extends StatelessWidget {
  const _Badged({required this.count, required this.child});

  final int count;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (count <= 0) {
      return child;
    }

    return Badge.count(count: count, child: child);
  }
}
