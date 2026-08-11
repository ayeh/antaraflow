import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/error/api_exception.dart';
import '../../core/providers.dart';
import '../shell/app_shell.dart';
import 'tasks_screen.dart';

/// Ticking an action item off.
///
/// Optimistic, and deliberately so: the person tapping is standing up, walking
/// out of a room, clearing a list. A checkbox that waits half a second for a
/// server before it fills in gets tapped twice.
final taskTickProvider = Provider<TaskTicker>((ref) => TaskTicker(ref));

class TaskTicker {
  const TaskTicker(this._ref);

  final Ref _ref;

  Future<void> setDone(TaskItem task, {required bool done}) async {
    final previous = _ref.read(tasksProvider).valueOrNull;
    if (previous == null) return;

    final status = done ? 'completed' : 'open';

    _ref
        .read(tasksProvider.notifier)
        .replace(task.id, task.copyWith(status: status));

    try {
      await _ref
          .read(apiClientProvider)
          .patch('/action-items/${task.id}/status', body: {'status': status});

      // The badge on the tab counts what is due; clearing one changes it.
      _ref.invalidate(bootstrapProvider);
    } on ApiException {
      // Put it back. Leaving a tick that did not happen is worse than the
      // half-second of satisfaction it bought.
      _ref.read(tasksProvider.notifier).replace(task.id, task);
      rethrow;
    }
  }
}
