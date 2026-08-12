import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';

/// A pane of real Liquid Glass behind the bar.
///
/// Composited from UIKit underneath Flutter's own drawing, because Flutter
/// paints its own pixels and inherits nothing from the system.
///
/// iOS only. Elsewhere the pill keeps its shape and behaviour on an opaque
/// ground — the same bar, without the material.
class GlassPane extends StatelessWidget {
  const GlassPane({super.key});

  static bool get isAvailable => !kIsWeb && Platform.isIOS;

  @override
  Widget build(BuildContext context) {
    if (!isAvailable) {
      return const ColoredBox(color: AppColors.paperRaised);
    }

    return const UiKitView(viewType: 'cloud.antara.note/glass');
  }
}

/// How far the active list has scrolled, as a fraction of the bar to show.
///
/// The bar gives its height back to the content while somebody reads, and
/// returns the moment they reach for it.
class TabBarVisibility extends ChangeNotifier {
  double _shown = 1;
  double _lastOffset = 0;

  /// Set by [reset]. The next notification is swallowed as a baseline.
  bool _priming = true;

  /// 1 = fully out, 0 = fully tucked away.
  double get shown => _shown;

  void onScroll(ScrollNotification notification) {
    if (notification is! ScrollUpdateNotification) return;

    final offset = notification.metrics.pixels;

    // The first reading after a reset establishes where this list is; it is
    // not a movement. Zeroing `_lastOffset` alone is not enough — a tab that
    // was already scrolled reports its own offset and that difference would
    // read as one enormous downward flick.
    if (_priming) {
      _priming = false;
      _lastOffset = offset;
      return;
    }

    final delta = offset - _lastOffset;
    _lastOffset = offset;

    // Near the top the bar is always out; nobody scrolling back to the start
    // wants to hunt for it.
    if (offset < 24) {
      _set(1);
      return;
    }

    if (delta.abs() < 2) return;

    _set((_shown - delta / 90).clamp(0.0, 1.0));
  }

  /// Called when the tab changes. Each tab keeps its own scroll offset, so the
  /// first notification after a switch carries a delta against the *previous*
  /// tab's position — enough to tuck the bar away the moment somebody moves
  /// between tabs, which is exactly when they are using it.
  void reset() {
    _priming = true;
    _lastOffset = 0;
    _set(1);
  }

  void _set(double value) {
    if ((value - _shown).abs() < 0.01) return;

    _shown = value;
    notifyListeners();
  }
}

/// Wraps the bar so it slides out of the way as the page scrolls.
class CollapsingBar extends StatelessWidget {
  const CollapsingBar({
    super.key,
    required this.visibility,
    required this.height,
    required this.child,
  });

  final TabBarVisibility visibility;

  /// How far to push the bar down when fully tucked away. Used only for the
  /// translation — the bar keeps its own height, because forcing one stretches
  /// the glass pane past the row it is meant to sit behind.
  final double height;

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: visibility,
      builder: (context, child) {
        final shown = visibility.shown;

        // Translated, not clipped. `Align(heightFactor:)` eats the bar from
        // the bottom edge, which on a floating pill reads as the shape being
        // cut in half rather than as it moving out of the way.
        return Transform.translate(
          offset: Offset(0, height * (1 - shown)),
          child: Opacity(
            opacity: Curves.easeOut.transform(shown.clamp(0.0, 1.0)),
            child: child,
          ),
        );
      },
      child: child,
    );
  }
}
