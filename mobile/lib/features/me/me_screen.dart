import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/haptics.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../domain/models/organization.dart';
import '../auth/auth_controller.dart';
import '../meetings/meetings_screen.dart';
import '../shell/app_shell.dart';
import '../tasks/tasks_screen.dart';
import '../widgets/ledger_scaffold.dart';
import '../../l10n/app_localizations.dart';
import '../notifications/notification_settings_screen.dart';
import 'language_screen.dart';

/// Who you are signed in as, and the three switches that belong to a phone.
///
/// Everything else — users, billing, templates, webhooks — is web work and
/// stays there. This screen exists so that the identity the rest of the app is
/// acting under is never a mystery.
class MeScreen extends ConsumerWidget {
  const MeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(authControllerProvider).session;
    final user = session?.user;
    final organizations = session?.organizations ?? const <Organization>[];
    final currentMatches = organizations.where(
      (organization) => organization.isCurrent,
    );
    final current = currentMatches.isEmpty ? null : currentMatches.first;

    return LedgerScaffold(
      title: L.of(context).tabMe,
      meta: current?.name.toUpperCase(),
      child: ListView(
        padding: const EdgeInsets.only(bottom: 110),
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          if (user != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 24, 20, 22),
              child: Row(
                children: [
                  _Initials(initials: user.initials, avatarUrl: user.avatarUrl),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          user.name,
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                        const SizedBox(height: 3),
                        Text(
                          user.email,
                          style: AppTheme.mono(
                            size: 11.5,
                            colour: AppColors.inkFaint,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          if (organizations.length > 1) ...[
            SectionRule(label: L.of(context).organisation),
            for (final organization in organizations)
              _OrganizationRow(
                organization: organization,
                onSwitch: () => _switch(context, ref, organization),
              ),
          ],
          SectionRule(label: L.of(context).settings),
          _SettingRow(
            label: L.of(context).notifications,
            detail: L.of(context).settingsNotificationsDetail,
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => const NotificationSettingsScreen(),
              ),
            ),
          ),
          _SettingRow(
            label: L.of(context).language,
            detail: L.of(context).languageDetail,
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(builder: (_) => const LanguageScreen()),
            ),
          ),
          SectionRule(label: L.of(context).session),
          _SettingRow(
            label: L.of(context).signOut,
            detail: L.of(context).signOutDetail,
            danger: true,
            onTap: () => _signOut(context, ref),
          ),
        ],
      ),
    );
  }

  /// Switching tenant changes what every other screen is showing, so it is
  /// confirmed rather than done on a tap in a list.
  Future<void> _switch(
    BuildContext context,
    WidgetRef ref,
    Organization organization,
  ) async {
    final confirmed = await _confirm(
      context,
      title: L.of(context).switchToOrg(organization.name),
      detail: L.of(context).switchToOrgDetail,
      action: L.of(context).switchAction,
    );

    if (!confirmed) return;

    Haptics.shift();

    final switched = await ref
        .read(authControllerProvider.notifier)
        .switchOrganization(organization.id);

    // The return value used to be discarded. A failed switch still invalidated
    // every list, which refetched them under the tenant the person had just
    // tried to leave — so the app appeared to change organisation, showed the
    // wrong data, and said nothing. Whatever went wrong is on the auth state;
    // it had nowhere to be seen.
    if (!switched) {
      if (!context.mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            ref.read(authControllerProvider).errorMessage ??
                L.of(context).organisationSwitchFailed,
          ),
        ),
      );

      return;
    }

    // Every list on every other tab belongs to the old tenant now.
    ref
      ..invalidate(bootstrapProvider)
      ..invalidate(meetingsProvider)
      ..invalidate(tasksProvider)
      // A status filter chosen in the old tenant means nothing in the new one,
      // and survives a tab change by design — so it would have quietly hidden
      // rows that are perfectly fine.
      ..read(meetingFilterProvider.notifier).state = const MeetingFilter();
  }

  Future<void> _signOut(BuildContext context, WidgetRef ref) async {
    final confirmed = await _confirm(
      context,
      title: L.of(context).signOutConfirm,
      detail: L.of(context).signOutConfirmDetail,
      action: L.of(context).signOut,
      destructive: true,
    );

    if (!confirmed) return;

    await ref.read(authControllerProvider.notifier).logout();
  }

  Future<bool> _confirm(
    BuildContext context, {
    required String title,
    required String detail,
    required String action,
    bool destructive = false,
  }) async {
    final answer = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: AppColors.paperRaised,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppTheme.radiusM),
        ),
        title: Text(title, style: Theme.of(context).textTheme.titleMedium),
        content: Text(detail, style: Theme.of(context).textTheme.bodyMedium),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(
              L.of(context).cancel,
              style: TextStyle(color: AppColors.inkSoft),
            ),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(
              action,
              style: TextStyle(
                color: destructive ? AppColors.danger : AppColors.primaryInk,
                fontWeight: FontWeight.w700,
                fontVariations: AppTheme.axis(FontWeight.w700),
              ),
            ),
          ),
        ],
      ),
    );

    return answer ?? false;
  }
}

class _Initials extends StatelessWidget {
  const _Initials({required this.initials, this.avatarUrl});

  final String initials;
  final String? avatarUrl;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 54,
      height: 54,
      decoration: BoxDecoration(
        color: AppColors.primarySoft,
        borderRadius: BorderRadius.circular(AppTheme.radiusM),
        border: Border.all(color: AppColors.rule),
        image: avatarUrl == null
            ? null
            : DecorationImage(
                image: NetworkImage(avatarUrl!),
                fit: BoxFit.cover,
              ),
      ),
      alignment: Alignment.center,
      child: avatarUrl != null
          ? null
          : Text(
              initials,
              style: AppTheme.mono(
                size: 17,
                weight: FontWeight.w700,
                colour: AppColors.primaryInk,
              ),
            ),
    );
  }
}

class _OrganizationRow extends StatelessWidget {
  const _OrganizationRow({required this.organization, required this.onSwitch});

  final Organization organization;
  final VoidCallback onSwitch;

  @override
  Widget build(BuildContext context) {
    final current = organization.isCurrent;

    return _Row(
      onTap: current ? null : onSwitch,
      gutter: current
          ? L.of(context).gutterCurrent
          : L.of(context).gutterSwitch,
      accent: current,
      title: organization.name,
      detail: organization.role,
      trailing: current
          ? const Icon(
              Icons.check_rounded,
              size: 18,
              color: AppColors.primaryInk,
            )
          : null,
    );
  }
}

class _SettingRow extends StatelessWidget {
  const _SettingRow({
    required this.label,
    required this.detail,
    required this.onTap,
    this.danger = false,
  });

  final String label;
  final String detail;
  final VoidCallback onTap;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    return _Row(
      onTap: onTap,
      gutter: danger ? 'end' : 'open',
      title: label,
      detail: detail,
      danger: danger,
      trailing: Icon(
        Icons.chevron_right_rounded,
        size: 20,
        color: danger ? AppColors.danger : AppColors.inkFaint,
      ),
    );
  }
}

/// The same ruled row the rest of the app uses, with a word in the gutter
/// where the lists put a date.
class _Row extends StatelessWidget {
  const _Row({
    required this.gutter,
    required this.title,
    required this.onTap,
    this.detail,
    this.trailing,
    this.accent = false,
    this.danger = false,
  });

  final String gutter;
  final String title;
  final String? detail;
  final VoidCallback? onTap;
  final Widget? trailing;
  final bool accent;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final ink = danger ? AppColors.danger : AppColors.ink;

    return InkWell(
      onTap: onTap,
      child: Container(
        decoration: const BoxDecoration(
          border: Border(bottom: BorderSide(color: AppColors.rule)),
        ),
        padding: const EdgeInsets.fromLTRB(20, 15, 18, 15),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: AppTheme.gutter,
              child: Text(
                gutter,
                style: AppTheme.mono(
                  size: 11,
                  weight: FontWeight.w700,
                  colour: danger
                      ? AppColors.danger
                      : (accent ? AppColors.primaryInk : AppColors.inkFaint),
                ),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: Theme.of(
                      context,
                    ).textTheme.titleMedium?.copyWith(color: ink, height: 1.3),
                  ),
                  if (detail != null) ...[
                    const SizedBox(height: 4),
                    Text(detail!, style: Theme.of(context).textTheme.bodySmall),
                  ],
                ],
              ),
            ),
            if (trailing != null) ...[const SizedBox(width: 10), trailing!],
          ],
        ),
      ),
    );
  }
}
