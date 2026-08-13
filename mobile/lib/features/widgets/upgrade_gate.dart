import 'package:flutter/material.dart';

import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../l10n/app_localizations.dart';

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
                  color: AppColors.primary,
                ),
                const SizedBox(height: 20),
                Text(
                  L.of(context).updateRequired,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 8),
                Text(
                  requirement.minimumVersion == null
                      ? L.of(context).updateGeneric
                      : L
                            .of(context)
                            .updateVersioned(requirement.minimumVersion!),
                  textAlign: TextAlign.center,
                  style: Theme.of(
                    context,
                  ).textTheme.bodyMedium?.copyWith(color: AppColors.n500),
                ),
                if (requirement.storeUrl != null) ...[
                  const SizedBox(height: 28),
                  FilledButton(
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.primary,
                    ),
                    onPressed: () {},
                    child: Text(L.of(context).openTheStore),
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
