import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_colors.dart';
import '../../domain/models/bootstrap.dart';
import '../shell/app_shell.dart';
import '../widgets/error_view.dart';

/// A feed of what needs deciding, not a dashboard of charts.
///
/// Charts belong on the web. What a person opens their phone for is: what is
/// next, what is late, and what is waiting on me.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bootstrap = ref.watch(bootstrapProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Today'),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            tooltip: 'Notifications',
            onPressed: () {},
          ),
        ],
      ),
      body: bootstrap.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(bootstrapProvider),
        ),
        data: (data) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(bootstrapProvider),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
            children: [
              _Greeting(name: data.user.name, organization: data.organization.name),
              const SizedBox(height: 20),
              _AttentionGrid(unread: data.unread),
              const SizedBox(height: 24),
              Text(
                'Up next',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 12),
              const _EmptyState(
                icon: Icons.event_available_outlined,
                title: 'Nothing scheduled',
                body: 'Meetings you are invited to will appear here.',
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Greeting extends StatelessWidget {
  const _Greeting({required this.name, required this.organization});

  final String name;
  final String organization;

  @override
  Widget build(BuildContext context) {
    final firstName = name.split(' ').first;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Hello, $firstName',
          style: Theme.of(context).textTheme.headlineMedium,
        ),
        const SizedBox(height: 4),
        Text(
          organization,
          style: Theme.of(
            context,
          ).textTheme.bodyMedium?.copyWith(color: AppColors.secondaryLight),
        ),
      ],
    );
  }
}

class _AttentionGrid extends StatelessWidget {
  const _AttentionGrid({required this.unread});

  final UnreadCounts unread;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _AttentionCard(
            label: 'Due today',
            count: unread.actionItemsDue,
            colour: unread.actionItemsDue > 0
                ? AppColors.danger
                : AppColors.success,
            icon: Icons.flag_outlined,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: _AttentionCard(
            label: 'To approve',
            count: unread.pendingApprovals,
            colour: unread.pendingApprovals > 0
                ? AppColors.accent
                : AppColors.secondaryLight,
            icon: Icons.approval_outlined,
          ),
        ),
      ],
    );
  }
}

class _AttentionCard extends StatelessWidget {
  const _AttentionCard({
    required this.label,
    required this.count,
    required this.colour,
    required this.icon,
  });

  final String label;
  final int count;
  final Color colour;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: colour, size: 22),
            const SizedBox(height: 12),
            Text(
              '$count',
              style: Theme.of(
                context,
              ).textTheme.headlineMedium?.copyWith(color: colour),
            ),
            const SizedBox(height: 2),
            Text(label, style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 32, horizontal: 20),
        child: Column(
          children: [
            Icon(icon, size: 36, color: AppColors.neutral300),
            const SizedBox(height: 12),
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 4),
            Text(
              body,
              textAlign: TextAlign.center,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: AppColors.secondaryLight),
            ),
          ],
        ),
      ),
    );
  }
}
