import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/error/api_exception.dart';
import '../../core/haptics.dart';
import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/error_view.dart';
import '../widgets/gutter_row.dart';
import '../widgets/ledger_scaffold.dart';
import 'tasks_controller.dart';
import '../meetings/meeting_detail_screen.dart';
import '../../l10n/app_localizations.dart';

/// Action items assigned to the person holding the phone.
///
/// Grouped by when, not by which committee: the question being asked is "what
/// is late", never "what belongs to the audit committee". Overdue sits first
/// and carries the only red on the screen.
final tasksProvider = AsyncNotifierProvider<TasksNotifier, List<TaskItem>>(
  TasksNotifier.new,
);

class TasksNotifier extends AsyncNotifier<List<TaskItem>> {
  @override
  Future<List<TaskItem>> build() async {
    final rows = await ref
        .read(apiClientProvider)
        .getList(
          '/action-items',
          query: {'assigned_to_me': 1, 'per_page': 100},
        );

    return rows.cast<Map<String, dynamic>>().map(TaskItem.fromJson).toList();
  }

  /// Swaps one item in place without refetching the list.
  ///
  /// A tick must not reload the list under the thumb that made it. Where the
  /// row is *shown* while it settles is the screen's business — see
  /// `_GroupedState` — but the data changes here immediately.
  void replace(int id, TaskItem updated) {
    final current = state.valueOrNull;
    if (current == null) return;

    state = AsyncData([
      for (final task in current) task.id == id ? updated : task,
    ]);
  }
}

class TaskItem {
  const TaskItem({
    required this.id,
    required this.title,
    required this.status,
    required this.isOverdue,
    this.dueDate,
    this.meetingTitle,
    this.meetingId,
  });

  factory TaskItem.fromJson(Map<String, dynamic> json) => TaskItem(
    id: json['id'] as int,
    title: json['title'] as String? ?? 'Untitled',
    status: json['status'] as String? ?? 'open',
    isOverdue: json['is_overdue'] as bool? ?? false,
    dueDate: DateTime.tryParse(json['due_date'] as String? ?? ''),
    meetingTitle:
        (json['meeting'] as Map<String, dynamic>?)?['title'] as String?,
    // The server has always sent it; nothing here read it, so a task had no
    // way back to the sitting that produced it.
    meetingId: ((json['meeting'] as Map<String, dynamic>?)?['id'] as num?)
        ?.toInt(),
  );

  final int id;
  final String title;
  final String status;
  final bool isOverdue;
  final DateTime? dueDate;
  final String? meetingTitle;
  final int? meetingId;

  TaskItem copyWith({String? status}) => TaskItem(
    id: id,
    title: title,
    status: status ?? this.status,
    isOverdue: isOverdue,
    dueDate: dueDate,
    meetingTitle: meetingTitle,
    meetingId: meetingId,
  );

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
      title: L.of(context).tasks,
      meta: tasks.valueOrNull == null
          ? null
          : L
                .of(context)
                .tasksMeta(tasks.value!.where((t) => !t.isDone).length),
      onRefresh: () async => ref.refresh(tasksProvider.future),
      child: tasks.when(
        // The list holds its shape while it loads rather than throwing the
        // layout away for a spinner and snapping it back.
        loading: () => const _Loading(),
        error: (error, _) => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(tasksProvider),
        ),
        data: (rows) => _Grouped(tasks: rows),
      ),
    );
  }
}

enum _Group { overdue, today, later, closed }

class _Grouped extends ConsumerStatefulWidget {
  const _Grouped({required this.tasks});

  final List<TaskItem> tasks;

  @override
  ConsumerState<_Grouped> createState() => _GroupedState();
}

class _GroupedState extends ConsumerState<_Grouped> {
  /// Rows whose group changed, held where they were for a moment longer.
  ///
  /// Without this the tick is pointless: the instant an item is completed it
  /// belongs to Closed, so it leaves the section under the thumb that ticked
  /// it and the strike-through is never seen. Worse, Closed is folded, so the
  /// row appears to vanish. Holding it in place lets the line finish drawing,
  /// and lets somebody who ticked the wrong row untick it without going
  /// looking for it.
  static const _settle = Duration(milliseconds: 2400);

  final _held = <int, _Group>{};
  final _timers = <int, Timer>{};

  @override
  void didUpdateWidget(_Grouped old) {
    super.didUpdateWidget(old);

    final before = {for (final task in old.tasks) task.id: _naturalGroup(task)};

    for (final task in widget.tasks) {
      final was = before[task.id];
      if (was == null || was == _naturalGroup(task)) continue;
      if (_held.containsKey(task.id)) continue;

      _held[task.id] = was;
      _timers[task.id] = Timer(_settle, () {
        if (mounted) setState(() => _held.remove(task.id));
      });
    }
  }

  @override
  void dispose() {
    for (final timer in _timers.values) {
      timer.cancel();
    }
    super.dispose();
  }

  _Group _naturalGroup(TaskItem task) {
    if (task.isDone) return _Group.closed;
    if (task.isOverdue) return _Group.overdue;
    if (task.isDueToday) return _Group.today;

    return _Group.later;
  }

  _Group _groupOf(TaskItem task) => _held[task.id] ?? _naturalGroup(task);

  @override
  Widget build(BuildContext context) {
    if (widget.tasks.isEmpty) return const _NoTasks();

    List<TaskItem> inGroup(_Group group) =>
        widget.tasks.where((task) => _groupOf(task) == group).toList();

    final overdue = inGroup(_Group.overdue);
    final today = inGroup(_Group.today);
    final later = inGroup(_Group.later);
    final done = inGroup(_Group.closed);

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.only(bottom: 110),
      children: [
        if (overdue.isNotEmpty) ...[
          SectionRule(
            label: L.of(context).overdue,
            trailing: '${overdue.length}',
          ),
          ..._rows(context, ref, overdue, AppColors.danger),
        ],
        if (today.isNotEmpty) ...[
          SectionRule(
            label: L.of(context).dueToday,
            trailing: '${today.length}',
          ),
          ..._rows(context, ref, today, AppColors.warning),
        ],
        if (later.isNotEmpty) ...[
          SectionRule(label: L.of(context).later, trailing: '${later.length}'),
          ..._rows(context, ref, later, null),
        ],
        // Folded away. Closed items are the largest group on any list that has
        // been used for a while, and none of them need an answer.
        if (done.isNotEmpty)
          _Closed(rows: _rows(context, ref, done, null, dimmed: true)),
      ],
    );
  }

  List<Widget> _rows(
    BuildContext context,
    WidgetRef ref,
    List<TaskItem> items,
    Color? severity, {
    bool dimmed = false,
  }) {
    return [
      for (final task in items)
        GutterRow(
          key: ValueKey(task.id),
          gutter: task.dueDate == null
              ? L.of(context).gutterNil
              : DateFormat(
                  'd MMM',
                  Localizations.localeOf(context).toLanguageTag(),
                ).format(task.dueDate!),
          gutterCaption: task.dueDate == null
              ? L.of(context).noDate
              : DateFormat(
                  'EEE',
                  Localizations.localeOf(context).toLanguageTag(),
                ).format(task.dueDate!).toLowerCase(),
          title: task.title,
          subtitle: task.meetingTitle,
          severity: severity,
          dimmed: dimmed,
          struck: task.isDone,
          onTap: task.meetingId == null
              ? null
              : () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => MeetingDetailScreen(
                      id: task.meetingId!,
                      title: task.meetingTitle ?? task.title,
                    ),
                  ),
                ),
          trailing: _Check(
            done: task.isDone,
            onChanged: (value) => _tick(context, ref, task, value),
          ),
        ),
    ];
  }

  Future<void> _tick(
    BuildContext context,
    WidgetRef ref,
    TaskItem task,
    bool done,
  ) async {
    // Before the request. The tick is the feedback; waiting on a server to
    // give it is how a checkbox ends up tapped twice.
    Haptics.tick();

    try {
      await ref.read(taskTickProvider).setDone(task, done: done);
    } on ApiException catch (e) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }
}

class _Closed extends StatefulWidget {
  const _Closed({required this.rows});

  final List<Widget> rows;

  @override
  State<_Closed> createState() => _ClosedState();
}

class _ClosedState extends State<_Closed> {
  bool _open = false;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        InkWell(
          onTap: () {
            Haptics.select();
            setState(() => _open = !_open);
          },
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 26, 20, 10),
            child: Row(
              children: [
                Text(L.of(context).closed, style: AppTheme.eyebrow()),
                const SizedBox(width: 12),
                const Expanded(child: Divider(color: AppColors.rule)),
                const SizedBox(width: 12),
                Text(
                  '${widget.rows.length}',
                  style: AppTheme.mono(size: 11, colour: AppColors.inkFaint),
                ),
                AnimatedRotation(
                  turns: _open ? 0.5 : 0,
                  duration: const Duration(milliseconds: 220),
                  curve: AppTheme.easeOut,
                  child: const Icon(
                    Icons.keyboard_arrow_down_rounded,
                    size: 20,
                    color: AppColors.inkFaint,
                  ),
                ),
              ],
            ),
          ),
        ),
        AnimatedCrossFade(
          firstChild: const SizedBox(width: double.infinity),
          secondChild: Column(children: widget.rows),
          crossFadeState: _open
              ? CrossFadeState.showSecond
              : CrossFadeState.showFirst,
          duration: const Duration(milliseconds: 260),
          sizeCurve: AppTheme.easeOut,
        ),
      ],
    );
  }
}

/// A ruled square rather than a checkbox widget, so it matches the tags and
/// the badges instead of importing a second visual language.
///
/// The tick is drawn rather than revealed: a checkmark that fades in reads as
/// state arriving from somewhere else, one that draws reads as this tap.
class _Check extends StatelessWidget {
  const _Check({required this.done, required this.onChanged});

  final bool done;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      checked: done,
      label: L.of(context).markComplete,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () => onChanged(!done),
        child: Padding(
          // The square is 22pt; the target around it is 44.
          padding: const EdgeInsets.all(11),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            curve: AppTheme.easeOut,
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
            child: TweenAnimationBuilder<double>(
              tween: Tween(end: done ? 1.0 : 0.0),
              duration: Duration(
                milliseconds: MediaQuery.disableAnimationsOf(context) ? 0 : 240,
              ),
              curve: AppTheme.easeOut,
              builder: (context, extent, _) =>
                  CustomPaint(painter: _TickPainter(extent: extent)),
            ),
          ),
        ),
      ),
    );
  }
}

class _TickPainter extends CustomPainter {
  _TickPainter({required this.extent});

  final double extent;

  @override
  void paint(Canvas canvas, Size size) {
    if (extent <= 0) return;

    final start = Offset(size.width * 0.22, size.height * 0.52);
    final elbow = Offset(size.width * 0.42, size.height * 0.72);
    final end = Offset(size.width * 0.78, size.height * 0.30);

    final paint = Paint()
      ..color = AppColors.navy
      ..strokeWidth = 2.2
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round
      ..style = PaintingStyle.stroke;

    // The short leg is drawn first and takes the first third of the time, the
    // way a tick is actually made.
    const pivot = 0.34;

    if (extent <= pivot) {
      canvas.drawLine(start, Offset.lerp(start, elbow, extent / pivot)!, paint);
      return;
    }

    final path = Path()
      ..moveTo(start.dx, start.dy)
      ..lineTo(elbow.dx, elbow.dy);

    final along = (extent - pivot) / (1 - pivot);
    final tip = Offset.lerp(elbow, end, along)!;
    path.lineTo(tip.dx, tip.dy);

    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(_TickPainter old) => old.extent != extent;
}

class _Loading extends StatelessWidget {
  const _Loading();

  @override
  Widget build(BuildContext context) {
    const widths = [0.66, 0.81, 0.52, 0.74, 0.6];

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        SectionRule(label: L.of(context).loadingSection),
        for (final width in widths) GutterRowSkeleton(titleFraction: width),
      ],
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
                Text(L.of(context).nothingAssigned, style: AppTheme.eyebrow()),
                const SizedBox(height: 14),
                Text(
                  L.of(context).youAreClear,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 8),
                Text(
                  L.of(context).nothingAssignedDetail,
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
