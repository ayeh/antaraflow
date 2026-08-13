import 'package:flutter/material.dart';

import '../../core/error/api_exception.dart';
import '../../core/theme/app_colors.dart';
import '../../l10n/app_localizations.dart';

/// Failure state that says what happened and offers the way out.
///
/// The server already returns a translated, human-readable message, so it is
/// shown as-is rather than replaced with a generic apology.
class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.error, this.onRetry});

  final Object error;
  final VoidCallback? onRetry;

  /// Server messages arrive already translated, so they win. The two the app
  /// writes itself never reached a translator — they are minted in the network
  /// layer, which has no BuildContext — so they are resolved from the code
  /// here instead.
  String _message(BuildContext context, ApiException? api) =>
      switch (api?.code) {
        ApiErrorCode.networkUnavailable => L.of(context).offline,
        ApiErrorCode.serverError => L.of(context).somethingWentWrong,
        _ => api?.message ?? L.of(context).somethingWentWrong,
      };

  @override
  Widget build(BuildContext context) {
    final api = error is ApiException ? error as ApiException : null;
    final offline = api?.isOffline ?? false;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              offline ? Icons.cloud_off_outlined : Icons.error_outline,
              size: 40,
              color: offline ? AppColors.n500 : AppColors.danger,
            ),
            const SizedBox(height: 16),
            Text(
              _message(context, api),
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyLarge,
            ),
            if (onRetry != null) ...[
              const SizedBox(height: 24),
              OutlinedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: Text(L.of(context).tryAgain),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
