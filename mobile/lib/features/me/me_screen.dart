import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_colors.dart';
import '../auth/auth_controller.dart';

class MeScreen extends ConsumerWidget {
  const MeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(authControllerProvider).session;
    final user = session?.user;

    return Scaffold(
      appBar: AppBar(title: const Text('Me')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
        children: [
          if (user != null)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 26,
                      backgroundColor: AppColors.primarySoft,
                      foregroundImage: user.avatarUrl == null
                          ? null
                          : NetworkImage(user.avatarUrl!),
                      child: Text(
                        user.initials,
                        style: const TextStyle(
                          color: AppColors.primaryDeep,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user.name,
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          Text(
                            user.email,
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(color: AppColors.n500),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          const SizedBox(height: 16),
          if ((session?.organizations.length ?? 0) > 1) ...[
            Text(
              'Organisation',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Card(
              child: Column(
                children: [
                  for (final organization in session!.organizations)
                    ListTile(
                      title: Text(organization.name),
                      subtitle: organization.role == null
                          ? null
                          : Text(organization.role!),
                      trailing: organization.isCurrent
                          ? const Icon(
                              Icons.check_circle,
                              color: AppColors.primary,
                            )
                          : null,
                      onTap: organization.isCurrent
                          ? null
                          : () => ref
                                .read(authControllerProvider.notifier)
                                .switchOrganization(organization.id),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 16),
          ],
          Card(
            child: Column(
              children: [
                const ListTile(
                  leading: Icon(Icons.notifications_outlined),
                  title: Text('Notifications'),
                  trailing: Icon(Icons.chevron_right),
                ),
                const Divider(height: 1),
                const ListTile(
                  leading: Icon(Icons.language_outlined),
                  title: Text('Language'),
                  trailing: Icon(Icons.chevron_right),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(
                    Icons.logout,
                    color: AppColors.danger,
                  ),
                  title: const Text(
                    'Sign out',
                    style: TextStyle(color: AppColors.danger),
                  ),
                  onTap: () =>
                      ref.read(authControllerProvider.notifier).logout(),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
