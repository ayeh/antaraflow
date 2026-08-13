import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/error/api_exception.dart';
import '../../core/haptics.dart';
import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/error_view.dart';
import '../widgets/ledger_scaffold.dart';

/// The six things the app can tell you about, and how.
///
/// Named in the app rather than echoed from the server: the server's keys are
/// `action_item_assigned` and the like, which are correct and unreadable. An
/// unknown key from a newer server is skipped rather than shown raw.
const _kinds = <({String key, String label, String detail})>[
  (
    key: 'action_item_assigned',
    label: 'A task lands on you',
    detail: 'Somebody assigns you an action item',
  ),
  (
    key: 'meeting_finalized',
    label: 'Minutes are finalised',
    detail: 'A sitting closes for editing and opens for approval',
  ),
  (
    key: 'meeting_approved',
    label: 'Minutes are approved',
    detail: 'A record is settled and cannot change',
  ),
  (
    key: 'circulation_pending',
    label: 'Something waits for your signature',
    detail: 'Minutes circulated to you for confirmation',
  ),
  (
    key: 'mention',
    label: 'You are mentioned',
    detail: 'In a comment or a note',
  ),
  (
    key: 'transcription_completed',
    label: 'A recording finishes transcribing',
    detail: 'The audio has become text',
  ),
];

final notificationPreferencesProvider =
    FutureProvider.autoDispose<Map<String, ({bool push, bool email})>>((
      ref,
    ) async {
      final body = await ref
          .read(apiClientProvider)
          .get('/settings/notifications');

      final data = body['data'] as Map<String, dynamic>? ?? const {};

      return {
        for (final entry in data.entries)
          entry.key: (
            push: (entry.value as Map?)?['push'] as bool? ?? false,
            email: (entry.value as Map?)?['email'] as bool? ?? false,
          ),
      };
    });

class NotificationSettingsScreen extends ConsumerStatefulWidget {
  const NotificationSettingsScreen({super.key});

  @override
  ConsumerState<NotificationSettingsScreen> createState() =>
      _NotificationSettingsScreenState();
}

class _NotificationSettingsScreenState
    extends ConsumerState<NotificationSettingsScreen> {
  /// Toggled locally and sent afterwards. A switch that waits for a round trip
  /// before it moves feels broken, and this screen is nothing but switches.
  Map<String, ({bool push, bool email})>? _local;

  @override
  Widget build(BuildContext context) {
    final remote = ref.watch(notificationPreferencesProvider);
    final prefs = _local ?? remote.valueOrNull;

    return LedgerScaffold(
      title: 'Notifications',
      meta: prefs == null ? null : 'PUSH · EMAIL',
      leading: IconButton(
        icon: const Icon(Icons.arrow_back_rounded),
        color: const Color(0xFFC3D0F0),
        tooltip: 'Back',
        onPressed: () => Navigator.of(context).maybePop(),
      ),
      child: switch (remote) {
        AsyncError(:final error) when prefs == null => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(notificationPreferencesProvider),
        ),
        AsyncLoading() when prefs == null => const _Loading(),
        _ => _Rows(prefs: prefs!, onChanged: _set),
      },
    );
  }

  Future<void> _set(String key, {bool? push, bool? email}) async {
    final current =
        (_local ?? ref.read(notificationPreferencesProvider).valueOrNull) ??
        const {};

    final existing = current[key] ?? (push: false, email: false);
    final next = (push: push ?? existing.push, email: email ?? existing.email);

    Haptics.select();
    setState(() => _local = {...current, key: next});

    try {
      await ref
          .read(apiClientProvider)
          .patch(
            '/settings/notifications',
            body: {
              'preferences': {
                key: {'push': next.push, 'email': next.email},
              },
            },
          );
    } on ApiException catch (e) {
      if (!mounted) return;

      // Put it back. A preference that looks saved and is not is worse than
      // one that visibly refused.
      setState(() => _local = {...current, key: existing});
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }
}

class _Rows extends StatelessWidget {
  const _Rows({required this.prefs, required this.onChanged});

  final Map<String, ({bool push, bool email})> prefs;
  final void Function(String key, {bool? push, bool? email}) onChanged;

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.only(bottom: 110),
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 22, 20, 0),
          child: Text(
            'Push reaches this phone. Email reaches you wherever you read it.',
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ),
        const SizedBox(height: 18),
        for (final kind in _kinds)
          if (prefs.containsKey(kind.key))
            _Kind(
              label: kind.label,
              detail: kind.detail,
              value: prefs[kind.key]!,
              onChanged: (({bool? push, bool? email}) change) =>
                  onChanged(kind.key, push: change.push, email: change.email),
            ),
      ],
    );
  }
}

class _Kind extends StatelessWidget {
  const _Kind({
    required this.label,
    required this.detail,
    required this.value,
    required this.onChanged,
  });

  final String label;
  final String detail;
  final ({bool push, bool email}) value;
  final void Function(({bool? push, bool? email})) onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        border: Border(top: BorderSide(color: AppColors.rule)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 14, 20, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 3),
          Text(detail, style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 10),
          Row(
            children: [
              _Toggle(
                label: 'Push',
                on: value.push,
                onTap: () => onChanged((push: !value.push, email: null)),
              ),
              const SizedBox(width: 8),
              _Toggle(
                label: 'Email',
                on: value.email,
                onTap: () => onChanged((push: null, email: !value.email)),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// A square chip rather than a Material switch.
///
/// Two switches per row, six rows, is twelve sliding pills on one screen —
/// which is the toy this app is not. The chip is the same shape as the filter
/// chips on the meetings list.
class _Toggle extends StatelessWidget {
  const _Toggle({required this.label, required this.on, required this.onTap});

  final String label;
  final bool on;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      toggled: on,
      button: true,
      label: label,
      child: GestureDetector(
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          curve: AppTheme.easeOut,
          padding: const EdgeInsets.fromLTRB(9, 7, 11, 7),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppTheme.radiusS),
            color: on ? AppColors.primarySoft : Colors.transparent,
            border: Border.all(
              color: on ? AppColors.primaryInk : AppColors.ruleStrong,
            ),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                on ? Icons.check_rounded : Icons.remove_rounded,
                size: 14,
                color: on ? AppColors.primaryInk : AppColors.inkFaint,
              ),
              const SizedBox(width: 6),
              Text(
                label.toUpperCase(),
                style: AppTheme.eyebrow(
                  colour: on ? AppColors.primaryInk : AppColors.inkSoft,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Loading extends StatelessWidget {
  const _Loading();

  @override
  Widget build(BuildContext context) => ListView(
    physics: const AlwaysScrollableScrollPhysics(),
    padding: const EdgeInsets.fromLTRB(20, 26, 20, 0),
    children: [Text('READING YOUR SETTINGS', style: AppTheme.eyebrow())],
  );
}
