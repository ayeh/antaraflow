import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';

/// The mark a record carries once it is settled.
///
/// Not confetti. A minute being approved is the most consequential thing this
/// app does, and celebrating it like a game would undercut the one quality the
/// product is selling. What happens instead is what happens on paper: a stamp
/// lands, slightly off square, and stays.
///
/// [animate] plays the landing once. A stamp already on a record when the
/// screen opens is simply there.
class Stamp extends StatefulWidget {
  const Stamp({
    super.key,
    required this.label,
    required this.caption,
    this.animate = false,
  });

  final String label;
  final String caption;
  final bool animate;

  @override
  State<Stamp> createState() => _StampState();
}

class _StampState extends State<Stamp> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 420),
    value: widget.animate ? 0 : 1,
  );

  @override
  void initState() {
    super.initState();
    if (widget.animate) _controller.forward();
  }

  @override
  void didUpdateWidget(Stamp old) {
    super.didUpdateWidget(old);
    if (widget.animate && !old.animate) {
      _controller
        ..value = 0
        ..forward();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reduced = MediaQuery.disableAnimationsOf(context);

    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final t = reduced ? 1.0 : AppTheme.easeOut.transform(_controller.value);

        return Opacity(
          opacity: t,
          child: Transform.rotate(
            // Comes in askew and settles a little off square, which is where
            // a hand-held stamp actually lands.
            angle: (-0.16 + 0.12 * t) * math.pi / 2,
            child: Transform.scale(
              // Larger than final, pressing down onto the page.
              scale: 1 + (1 - t) * 0.35,
              child: child,
            ),
          ),
        );
      },
      child: _Face(label: widget.label, caption: widget.caption),
    );
  }
}

class _Face extends StatelessWidget {
  const _Face({required this.label, required this.caption});

  final String label;
  final String caption;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 9, 16, 9),
      decoration: BoxDecoration(
        border: Border.all(color: AppColors.primaryInk, width: 2),
        borderRadius: BorderRadius.circular(AppTheme.radiusS),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            label.toUpperCase(),
            style: AppTheme.eyebrow(
              colour: AppColors.primaryInk,
            ).copyWith(fontSize: 13, letterSpacing: 2.2),
          ),
          const SizedBox(height: 3),
          Text(
            caption.toUpperCase(),
            style: AppTheme.mono(
              size: 9.5,
              colour: AppColors.primaryInk,
              tracking: 1,
            ),
          ),
        ],
      ),
    );
  }
}
