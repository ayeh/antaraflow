import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';

/// A highlighter swipe behind text.
///
/// The lime is the brightest thing in the palette and cannot carry text, so it
/// gets the one job it is actually suited to: marking the single most important
/// phrase on a screen, the way somebody would run a highlighter across a
/// printed minute. Used more than once per screen it stops meaning anything.
class Marker extends StatelessWidget {
  const Marker({super.key, required this.child, this.animate = true});

  final Widget child;
  final bool animate;

  @override
  Widget build(BuildContext context) {
    final reduced = MediaQuery.disableAnimationsOf(context);

    return _Swipe(animate: animate && !reduced, child: child);
  }
}

class _Swipe extends StatefulWidget {
  const _Swipe({required this.animate, required this.child});

  final bool animate;
  final Widget child;

  @override
  State<_Swipe> createState() => _SwipeState();
}

class _SwipeState extends State<_Swipe> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: AppTheme.enter,
    value: widget.animate ? 0 : 1,
  );

  @override
  void initState() {
    super.initState();
    if (widget.animate) {
      Future<void>.delayed(const Duration(milliseconds: 260), () {
        if (mounted) _controller.forward();
      });
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final t = AppTheme.easeOut.transform(_controller.value);

        return CustomPaint(
          painter: _MarkerPainter(progress: t),
          child: child,
        );
      },
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 3),
        child: widget.child,
      ),
    );
  }
}

class _MarkerPainter extends CustomPainter {
  _MarkerPainter({required this.progress});

  final double progress;

  @override
  void paint(Canvas canvas, Size size) {
    if (progress <= 0) return;

    final paint = Paint()..color = AppColors.lime.withValues(alpha: 0.85);

    // Sits low and slightly short of full height, the way a marker lands on a
    // line of text rather than boxing it.
    final top = size.height * 0.24;
    final rect = Rect.fromLTWH(
      0,
      top,
      size.width * progress,
      size.height - top - 1,
    );

    canvas.drawRect(rect, paint);
  }

  @override
  bool shouldRepaint(_MarkerPainter old) => old.progress != progress;
}
