import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

/// A number that rolls to its new value, one digit column at a time.
///
/// Counts on this app's screens are the whole point of the screen — how many
/// are late, how many are waiting. Swapping the glyph instantly makes a change
/// arriving from the server indistinguishable from a change that was always
/// there. Rolling it says *this moved*, and says which way.
///
/// Only sensible because the figures are already tabular: each column is a
/// fixed width, so the digits stay in their tracks while they turn.
class RollingCount extends StatelessWidget {
  const RollingCount({super.key, required this.value, required this.style});

  final int value;
  final TextStyle style;

  @override
  Widget build(BuildContext context) {
    final digits = value.toString().split('');

    if (MediaQuery.disableAnimationsOf(context)) {
      return Text(value.toString(), style: style);
    }

    return Semantics(
      label: value.toString(),
      excludeSemantics: true,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (var i = 0; i < digits.length; i++)
            _Reel(
              // Keyed from the right: 9 → 10 should roll the units column and
              // introduce a tens column, not renumber every digit on screen.
              key: ValueKey(digits.length - i),
              digit: int.parse(digits[i]),
              style: style,
            ),
        ],
      ),
    );
  }
}

class _Reel extends StatelessWidget {
  const _Reel({super.key, required this.digit, required this.style});

  final int digit;
  final TextStyle style;

  @override
  Widget build(BuildContext context) {
    // One measurement per build, not one per digit per frame. The figures are
    // tabular, so a single '0' gives the width every digit will take.
    final cell = (TextPainter(
      text: TextSpan(text: '0', style: style),
      textDirection: Directionality.of(context),
      textScaler: MediaQuery.textScalerOf(context),
    )..layout()).size;

    return TweenAnimationBuilder<double>(
      tween: Tween(end: digit.toDouble()),
      duration: const Duration(milliseconds: 600),
      curve: AppTheme.easeOut,
      builder: (context, position, _) {
        // Only the two digits bracketing the current position are painted; a
        // full ten-digit strip would be eight wasted layouts every frame.
        final base = position.floor();

        return ClipRect(
          child: SizedBox(
            width: cell.width,
            height: cell.height,
            child: Stack(
              children: [
                for (final candidate in [base, base + 1])
                  Positioned(
                    top: (candidate - position) * cell.height,
                    child: SizedBox(
                      width: cell.width,
                      child: Text(
                        '${candidate % 10}',
                        style: style,
                        textAlign: TextAlign.center,
                      ),
                    ),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }
}
