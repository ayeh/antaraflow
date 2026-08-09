import 'package:flutter/material.dart';

import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';

/// Shown when the API answers 426.
///
/// Deliberately a dead end with one action: a build the server refuses to talk
/// to cannot do anything useful, and letting someone wander the app collecting
/// failures is worse than saying so once.
class UpgradeGate extends StatelessWidget {
  const UpgradeGate({super.key, required this.requirement});

  final UpgradeRequirement requirement;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.system_update_outlined,
                  size: 48,
                  color: AppColors.accent,
                ),
                const SizedBox(height: 20),
                Text(
                  'Update required',
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 8),
                Text(
                  requirement.minimumVersion == null
                      ? 'Please update antaraNote to continue.'
                      : 'antaraNote ${requirement.minimumVersion} or later is '
                            'needed to continue.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: AppColors.secondaryLight,
                  ),
                ),
                if (requirement.storeUrl != null) ...[
                  const SizedBox(height: 28),
                  FilledButton(
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.accent,
                    ),
                    onPressed: () {},
                    child: const Text('Open the store'),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
