import 'package:antaranote/features/tasks/tasks_controller.dart';
import 'package:antaranote/features/tasks/tasks_screen.dart';
import 'package:antaranote/features/widgets/gutter_row.dart';
import 'package:antaranote/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// Serves a fixed list instead of the API.
class _FakeTasks extends TasksNotifier {
  _FakeTasks(this.seed);

  final List<TaskItem> seed;

  @override
  Future<List<TaskItem>> build() async => seed;
}

/// Flips the item locally, the way the real ticker does optimistically, but
/// without a server behind it.
class _FakeTicker implements TaskTicker {
  const _FakeTicker(this._ref);

  final Ref _ref;

  @override
  Future<void> setDone(TaskItem task, {required bool done}) async {
    _ref
        .read(tasksProvider.notifier)
        .replace(task.id, task.copyWith(status: done ? 'completed' : 'open'));
  }
}

void main() {
  TaskItem later({String status = 'open'}) => TaskItem(
    id: 1,
    title: 'Circulate the draft minutes',
    status: status,
    isOverdue: false,
    dueDate: DateTime.now().add(const Duration(days: 20)),
  );

  Future<void> pumpTasks(WidgetTester tester, List<TaskItem> tasks) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          tasksProvider.overrideWith(() => _FakeTasks(tasks)),
          taskTickProvider.overrideWith(_FakeTicker.new),
          // The screen asks for these too. Left un-overridden it reaches the
          // network, and the request outlives the test as a pending timer.
          unassignedTasksProvider.overrideWith(
            (ref) async => (items: <TaskItem>[], total: 0),
          ),
        ],
        // The screen reads its strings through L, so the delegates have to be
        // here. Without them L.of returns null and the screen throws before it
        // draws anything — which is what happened when it was localised and
        // this harness was not updated with it.
        child: const MaterialApp(
          localizationsDelegates: L.localizationsDelegates,
          supportedLocales: L.supportedLocales,
          home: TasksScreen(),
        ),
      ),
    );

    await tester.pump();
  }

  Future<void> tick(WidgetTester tester) async {
    final handle = tester.ensureSemantics();
    await tester.tap(find.bySemanticsLabel('Mark complete'));
    await tester.pump();
    handle.dispose();
  }

  group('ticking an action item', () {
    testWidgets('strikes the row through the moment it is tapped', (
      tester,
    ) async {
      await pumpTasks(tester, [later()]);

      expect(tester.widget<GutterRow>(find.byType(GutterRow)).struck, isFalse);

      await tick(tester);

      expect(tester.widget<GutterRow>(find.byType(GutterRow)).struck, isTrue);
    });

    // The reason the hold exists: Closed is folded away, so a row that moves
    // there the instant it is ticked appears to vanish under the thumb, and
    // the strike-through is never seen.
    testWidgets('leaves the row in its own section while it settles', (
      tester,
    ) async {
      await pumpTasks(tester, [later()]);

      expect(find.text('LATER'), findsOneWidget);

      await tick(tester);
      await tester.pump(const Duration(milliseconds: 400));

      expect(find.text('LATER'), findsOneWidget);
      expect(find.text('CLOSED'), findsNothing);
    });

    testWidgets('files it under Closed once it has', (tester) async {
      await pumpTasks(tester, [later()]);
      await tick(tester);

      await tester.pump(const Duration(milliseconds: 2500));

      expect(find.text('CLOSED'), findsOneWidget);
      expect(find.text('LATER'), findsNothing);
    });

    testWidgets('unticking holds it in Closed the same way', (tester) async {
      await pumpTasks(tester, [later(status: 'completed')]);

      // Closed is folded, so open it before the checkbox can be reached.
      await tester.tap(find.text('CLOSED'));
      await tester.pumpAndSettle();

      await tick(tester);
      await tester.pump(const Duration(milliseconds: 400));

      expect(find.text('CLOSED'), findsOneWidget);
      expect(find.text('LATER'), findsNothing);

      await tester.pump(const Duration(milliseconds: 2500));

      expect(find.text('LATER'), findsOneWidget);
    });
  });
}
