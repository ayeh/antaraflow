import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/error_view.dart';
import '../widgets/gutter_row.dart';
import '../widgets/ledger_scaffold.dart';

/// Action items assigned to the person holding the phone.
///
/// Grouped by when, not by which committee: the question being asked is "what
/// is late", never "what belongs to the audit committee". Overdue sits first
/// and carries the only red on the screen.
final tasksProvider = FutureProvider<List<TaskItem>>((ref) async {
  final rows = await ref
      .watch(apiClientProvider)
      .getList('/action-items', query: {'assigned_to_me': 1, 'per_page': 100});

  return rows.cast<Map<String, dynamic>>().map(TaskItem.fromJson).toList();
});

class TaskItem {
  const TaskItem({
    required this.id,
    required this.title,
    required this.status,
    required this.isOverdue,
    this.dueDate,
    this.meetingTitle,
  });

  factory TaskItem.fromJson(Map<String, dynamic> json) => TaskItem(
    id: json['id'] as int,
    title: json['title'] as String? ?? 'Untitled',
    status: json['status'] as String? ?? 'open',
    isOverdue: json['is_overdue'] as bool? ?? false,
    dueDate: DateTime.tryParse(json['due_date'] as String? ?? ''),
    meetingTitle: (json['meeting'] as Map<String, dynamic>?)?['title'] as String?,
  );

  final int id;
  final String title;
  final String status;
  final bool isOverdue;
  final DateTime? dueDate;
  final String? meetingTitle;

  bool get isDone => status == 'completed' || status == 'cancelled';

  bool get isDueToday {
    final due = dueDate;
    if (due == null || isOverdue) return false;

    final now = DateTime.now();
    return due.year == now.year && due.month == now.month && due.day == now.day;
  }
}

class TasksScreen extends ConsumerWidget {
  const TasksScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tasks = ref.watch(tasksProvider);

    return LedgerScaffold(
      title: 'Tasks',
      meta: tasks.valueOrNull == null
          ? null
          : '${tasks.value!.where((t) => !t.isDone).length} OPEN · ASSIGNED TO YOU',
      onRefresh: () async => ref.invalidate(tasksProvider),
      child: tasks.when(
        loading: () => const Center(
          child: SizedBox.square(
            dimension: 22,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
        ),
        error: (error, _) => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(tasksProvider),
        ),
        data: (rows) => _Grouped(tasks: rows),
      ),
    );
  }
}

class _Grouped extends StatelessWidget {
  const _Grouped({required this.tasks});

  final List<TaskItem> tasks;

  @override
  Widget build(BuildContext context) {
    final open = tasks.where((t) => !t.isDone).toList();
    final overdue = open.where((t) => t.isOverdue).toList();
    final today = open.where((t) => t.isDueToday).toList();
    final later = open
        .where((t) => !t.isOverdue && !t.isDueToday)
        .toList();
    final done = tasks.where((t) => t.isDone).toList();

    if (tasks.isEmpty) return const _NoTasks();

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.only(bottom: 110),
      children: [
        if (overdue.isNotEmpty) ...[
          SectionRule(label: 'Overdue', trailing: '${overdue.length}'),
          ..._rows(overdue, AppColors.danger),
        ],
        if (today.isNotEmpty) ...[
          SectionRule(label: 'Due today', trailing: '${today.length}'),
          ..._rows(today, AppColors.warning),
        ],
        if (later.isNotEmpty) ...[
          SectionRule(label: 'Later', trailing: '${later.length}'),
          ..._rows(later, null),
        ],
        if (done.isNotEmpty) ...[
          SectionRule(label: 'Closed', trailing: '${done.length}'),
          ..._rows(done, null, dimmed: true),
        ],
      ],
    );
  }

  List<Widget> _rows(List<TaskItem> items, Color? severity, {bool dimmed = false}) {
    return [
      for (final task in items)
        GutterRow(
          gutter: task.dueDate == null
              ? '—'
              : DateFormat('d MMM').format(task.dueDate!),
          gutterCaption: task.dueDate == null
              ? 'no date'
              : DateFormat('EEE').format(task.dueDate!).toLowerCase(),
          title: task.title,
          subtitle: task.meetingTitle,
          severity: severity,
          dimmed: dimmed,
          onTap: () {},
          trailing: _Check(done: task.isDone),
        ),
    ];
  }
}

/// A ruled square rather than a checkbox widget, so it matches the tags and
/// the badges instead of importing a second visual language.
class _Check extends StatelessWidget {
  const _Check({required this.done});

  final bool done;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 22,
      height: 22,
      decoration: BoxDecoration(
        color: done ? AppColors.primary : Colors.transparent,
        border: Border.all(
          color: done ? AppColors.primary : AppColors.ruleStrong,
          width: 1.5,
        ),
        borderRadius: BorderRadius.circular(AppTheme.radiusS),
      ),
      child: done
          ? const Icon(Icons.check, size: 15, color: AppColors.navy)
          : null,
    );
  }
}

class _NoTasks extends StatelessWidget {
  const _NoTasks();

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
                Text('NOTHING ASSIGNED', style: AppTheme.eyebrow()),
                const SizedBox(height: 14),
                Text(
                  'You are clear',
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 8),
                Text(
                  'Action items assigned to you in a meeting will appear here.',
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
