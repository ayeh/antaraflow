import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/haptics.dart';
import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../l10n/app_localizations.dart';
import '../meetings/meeting_detail_screen.dart';
import '../shell/app_shell.dart';
import '../widgets/error_view.dart';
import '../widgets/gutter_row.dart';
import '../widgets/ledger_scaffold.dart';

/// Minutes circulated to you that you have not answered.
///
/// Home counts these and used to send people to the meetings list filtered by
/// `pending_confirmation`, which is a different set — a sitting can be
/// awaiting somebody else's answer, and a circulation can be waiting on you
/// while the record sits in another state. The count said three and the list
/// said none.
class ApprovalsScreen extends ConsumerWidget {
  const ApprovalsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final pending = ref.watch(pendingApprovalsProvider);
    final l = L.of(context);

    return LedgerScaffold(
      title: l.minutesToApprove,
      meta: switch (pending) {
        AsyncData(:final value) =>
          value.isEmpty
              ? l.nothingWaiting.toUpperCase()
              : l.unreadCount(value.length),
        _ => null,
      },
      leading: IconButton(
        icon: const Icon(Icons.arrow_back_rounded),
        color: const Color(0xFFC3D0F0),
        tooltip: l.back,
        onPressed: () => Navigator.of(context).maybePop(),
      ),
      onRefresh: () async => ref.refresh(pendingApprovalsProvider.future),
      child: switch (pending) {
        AsyncError(:final error) => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(pendingApprovalsProvider),
        ),
        AsyncData(:final value) when value.isEmpty => const _Empty(),
        AsyncData(:final value) => ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.only(bottom: 110),
          children: [for (final item in value) _Row(circulation: item)],
        ),
        _ => const _Loading(),
      },
    );
  }
}

final pendingApprovalsProvider =
    FutureProvider.autoDispose<List<PendingCirculation>>((ref) async {
      final rows = await ref
          .read(apiClientProvider)
          .getList('/circulations/pending');

      return rows
          .cast<Map<String, dynamic>>()
          .map(PendingCirculation.fromJson)
          .toList();
    });

class PendingCirculation {
  const PendingCirculation({
    required this.id,
    this.subject,
    this.round,
    this.deadlineAt,
    this.meetingId,
    this.meetingTitle,
    this.momNumber,
  });

  factory PendingCirculation.fromJson(Map<String, dynamic> json) {
    final meeting = json['meeting'] as Map<String, dynamic>?;

    return PendingCirculation(
      id: json['id'] as int,
      subject: json['subject'] as String?,
      round: (json['round'] as num?)?.toInt(),
      deadlineAt: DateTime.tryParse(json['deadline_at'] as String? ?? ''),
      meetingId: (meeting?['id'] as num?)?.toInt(),
      meetingTitle: meeting?['title'] as String?,
      momNumber: meeting?['mom_number'] as String?,
    );
  }

  final int id;
  final String? subject;
  final int? round;
  final DateTime? deadlineAt;
  final int? meetingId;
  final String? meetingTitle;
  final String? momNumber;

  bool get isOverdue =>
      deadlineAt != null && deadlineAt!.isBefore(DateTime.now());

  /// The sequence number alone, like every other gutter in the app.
  String get reference {
    final number = momNumber;
    if (number == null || number.isEmpty) return '$id';

    final tail = number.split(RegExp(r'[-/]')).last;

    return int.tryParse(tail)?.toString().padLeft(3, '0') ?? tail;
  }
}

class _Row extends ConsumerWidget {
  const _Row({required this.circulation});

  final PendingCirculation circulation;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = L.of(context);
    final deadline = circulation.deadlineAt;

    return GutterRow(
      gutter: deadline == null
          ? l.gutterNil
          : DateFormat(
              'd MMM',
              Localizations.localeOf(context).toLanguageTag(),
            ).format(deadline),
      gutterCaption: deadline == null ? l.noDate : l.gutterDue,
      title: circulation.meetingTitle ?? circulation.subject ?? l.meeting,
      subtitle: _detail(l),
      severity: circulation.isOverdue ? AppColors.danger : AppColors.warning,
      onTap: circulation.meetingId == null
          ? null
          : () async {
              Haptics.select();

              await Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => MeetingDetailScreen(
                    id: circulation.meetingId!,
                    title: circulation.meetingTitle ?? l.meeting,
                  ),
                ),
              );

              // Answering it there removes it from here, and from the badge.
              ref
                ..invalidate(pendingApprovalsProvider)
                ..invalidate(bootstrapProvider);
            },
    );
  }

  String? _detail(L l) {
    final parts = <String>[
      if (circulation.round != null) l.circulationRound(circulation.round!),
      if (circulation.subject != null && circulation.meetingTitle != null)
        circulation.subject!,
    ];

    return parts.isEmpty ? null : parts.join(' · ');
  }
}

class _Empty extends StatelessWidget {
  const _Empty();

  @override
  Widget build(BuildContext context) {
    final l = L.of(context);

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        const SizedBox(height: 64),
        Center(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 40),
            child: Column(
              children: [
                Text(l.nothingWaiting.toUpperCase(), style: AppTheme.eyebrow()),
                const SizedBox(height: 14),
                Text(
                  l.upToDate,
                  style: Theme.of(context).textTheme.headlineSmall,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Text(
                  l.approvalsEmptyDetail,
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

class _Loading extends StatelessWidget {
  const _Loading();

  @override
  Widget build(BuildContext context) => ListView(
    physics: const AlwaysScrollableScrollPhysics(),
    children: const [
      GutterRowSkeleton(titleFraction: 0.7),
      GutterRowSkeleton(titleFraction: 0.55),
    ],
  );
}
