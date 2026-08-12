import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';

/// The level meter, and the only proof the room has that the phone is
/// listening.
///
/// It reads the microphone directly rather than being driven by the recorder's
/// state, so it keeps moving even while an upload is stuck — which is the
/// moment somebody is most likely to look at it and wonder whether recording
/// has stopped.
class Waveform extends StatefulWidget {
  const Waveform({
    super.key,
    required this.level,
    required this.live,
    this.height = 84,
  });

  final ValueListenable<double> level;

  /// Paused draws the history it has and stops advancing, rather than
  /// flatlining — a flat line reads as a fault.
  final bool live;

  final double height;

  @override
  State<Waveform> createState() => _WaveformState();
}

class _WaveformState extends State<Waveform> {
  static const _bars = 64;
  static const _cadence = Duration(milliseconds: 80);

  final _history = List<double>.filled(_bars, 0, growable: false);
  final _repaint = ValueNotifier(0);
  Timer? _sampler;

  @override
  void initState() {
    super.initState();
    _sampler = Timer.periodic(_cadence, (_) => _advance());
  }

  @override
  void dispose() {
    _sampler?.cancel();
    _repaint.dispose();
    super.dispose();
  }

  void _advance() {
    if (!widget.live) return;

    for (var i = 0; i < _bars - 1; i++) {
      _history[i] = _history[i + 1];
    }
    _history[_bars - 1] = widget.level.value;

    _repaint.value++;
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: widget.height,
      width: double.infinity,
      child: RepaintBoundary(
        child: CustomPaint(
          painter: _WavePainter(
            history: _history,
            repaint: _repaint,
            live: widget.live,
          ),
        ),
      ),
    );
  }
}

class _WavePainter extends CustomPainter {
  _WavePainter({
    required this.history,
    required this.live,
    required Listenable repaint,
  }) : super(repaint: repaint);

  final List<double> history;
  final bool live;

  @override
  void paint(Canvas canvas, Size size) {
    final count = history.length;
    final slot = size.width / count;
    final width = (slot * 0.55).clamp(2.0, 5.0);
    final centre = size.height / 2;
    final paint = Paint()..style = PaintingStyle.fill;

    for (var i = 0; i < count; i++) {
      final value = history[i];
      final height = (value * size.height * 0.9).clamp(2.0, size.height);
      final x = i * slot + (slot - width) / 2;

      // The newest bars carry the accent and it falls away behind them, so the
      // eye reads direction without an arrow telling it to.
      //
      // Ramped across the last twelve rather than switched on at the eighth: a
      // hard edge between full lime and a faded tail does not read as one wave
      // moving, it reads as a bright block parked at the end of a dim one.
      const ramp = 12;
      final recency = ((i - (count - ramp)) / ramp).clamp(0.0, 1.0);
      final alpha =
          (0.18 + value * 0.34) + (1 - (0.18 + value * 0.34)) * recency;

      paint.color = live
          ? AppColors.lime.withValues(alpha: alpha)
          : const Color(0xFF3D5698);

      canvas.drawRRect(
        RRect.fromRectAndRadius(
          Rect.fromLTWH(x, centre - height / 2, width, height),
          const Radius.circular(1.5),
        ),
        paint,
      );
    }
  }

  @override
  bool shouldRepaint(_WavePainter old) => old.live != live;
}
