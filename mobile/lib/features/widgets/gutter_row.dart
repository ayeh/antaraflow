import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';

/// A row in the record.
///
/// The fixed left gutter is the point: every reference number, time and due
/// date sits in the same monospaced column down the whole screen, so the eye
/// scans one axis instead of hunting for the date inside each row. It is how a
/// minute book is set, and it is the reason this list reads faster than a stack
/// of cards.
class GutterRow extends StatefulWidget {
  const GutterRow({
    super.key,
    required this.gutter,
    required this.title,
    this.subtitle,
    this.gutterCaption,
    this.status,
    this.severity,
    this.onTap,
    this.trailing,
    this.dimmed = false,
  });

  /// The monospaced reference. Kept short — this column is narrow by design.
  final String gutter;

  /// A second, quieter line under the reference. A weekday, a duration.
  final String? gutterCaption;

  final String title;
  final String? subtitle;

  /// Short state word, rendered as a flat tag rather than a coloured pill.
  final String? status;

  /// Tints the reference and the tag. Absent means this row is unremarkable,
  /// which most rows are — colour here has to stay rare to mean anything.
  final Color? severity;

  final VoidCallback? onTap;
  final Widget? trailing;
  final bool dimmed;

  @override
  State<GutterRow> createState() => _GutterRowState();
}

class _GutterRowState extends State<GutterRow> {
  bool _pressed = false;

  void _setPressed(bool value) {
    if (_pressed == value) return;
    setState(() => _pressed = value);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final accent = widget.severity ?? AppColors.inkFaint;

    final content = Container(
      decoration: BoxDecoration(
        color: _pressed ? AppColors.primarySoft : Colors.transparent,
        border: const Border(bottom: BorderSide(color: AppColors.rule)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 15, 18, 15),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: AppTheme.gutter,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.gutter,
                  style: AppTheme.mono(
                    size: 12,
                    weight: FontWeight.w700,
                    colour: widget.severity ?? AppColors.inkSoft,
                  ),
                ),
                if (widget.gutterCaption != null) ...[
                  const SizedBox(height: 3),
                  Text(
                    widget.gutterCaption!,
                    style: AppTheme.mono(size: 10.5, colour: AppColors.inkFaint),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.title,
                  style: theme.textTheme.titleMedium?.copyWith(height: 1.3),
                ),
                // Subtitle and tag share one line. Given each its own, a list
                // of twenty sittings runs to three screens and the gutter
                // column stops being scannable, which was the point of it.
                if (widget.subtitle != null || widget.status != null) ...[
                  const SizedBox(height: 5),
                  Row(
                    children: [
                      if (widget.status != null) ...[
                        _Tag(label: widget.status!, colour: accent),
                        const SizedBox(width: 8),
                      ],
                      if (widget.subtitle != null)
                        Expanded(
                          child: Text(
                            widget.subtitle!,
                            style: theme.textTheme.bodySmall,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                    ],
                  ),
                ],
              ],
            ),
          ),
          if (widget.trailing != null) ...[
            const SizedBox(width: 10),
            widget.trailing!,
          ],
        ],
      ),
    );

    // A ripple spreading across a ledger row reads as an application; a quiet
    // press reads as paper. The scale is small on purpose — at full width,
    // anything deeper than this looks like the row is buckling.
    final pressable = GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: widget.onTap,
      onTapDown: widget.onTap == null ? null : (_) => _setPressed(true),
      onTapUp: widget.onTap == null ? null : (_) => _setPressed(false),
      onTapCancel: widget.onTap == null ? null : () => _setPressed(false),
      child: AnimatedScale(
        scale: _pressed ? 0.985 : 1,
        duration: const Duration(milliseconds: 120),
        curve: AppTheme.easeOut,
        child: content,
      ),
    );

    return Semantics(
      // Title first. Read in layout order a screen reader announces the date
      // and reference before the thing they belong to, which is backwards.
      label: _semanticLabel,
      button: widget.onTap != null,
      excludeSemantics: true,
      child: Opacity(opacity: widget.dimmed ? 0.45 : 1, child: pressable),
    );
  }

  String get _semanticLabel {
    final parts = <String>[
      widget.title,
      widget.gutter,
      if (widget.gutterCaption != null) widget.gutterCaption!,
      if (widget.status != null) widget.status!,
      if (widget.subtitle != null) widget.subtitle!,
    ];

    return parts.join('. ');
  }
}

/// Flat, ruled tag. No fill, no pill — a filled pill on every row would put
/// more colour on the page than the content it describes.
class _Tag extends StatelessWidget {
  const _Tag({required this.label, required this.colour});

  final String label;
  final Color colour;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(6, 3, 6, 3),
      decoration: BoxDecoration(
        border: Border.all(color: colour.withValues(alpha: 0.45)),
        borderRadius: BorderRadius.circular(AppTheme.radiusS),
      ),
      child: Text(
        label.toUpperCase(),
        style: AppTheme.eyebrow(colour: colour).copyWith(fontSize: 9.5),
      ),
    );
  }
}

/// Placeholder at the real row height, on the real gutter rhythm.
///
/// A centred spinner throws the layout away and snaps it back when the data
/// lands. Holding the shape is most of what makes a list feel dependable.
class GutterRowSkeleton extends StatelessWidget {
  const GutterRowSkeleton({super.key, this.titleFraction = 0.7});

  final double titleFraction;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: AppColors.rule)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 15, 18, 15),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(
            width: AppTheme.gutter,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _Bar(width: 40, height: 11),
                SizedBox(height: 6),
                _Bar(width: 26, height: 9),
              ],
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                FractionallySizedBox(
                  alignment: Alignment.centerLeft,
                  widthFactor: titleFraction,
                  child: const _Bar(height: 13),
                ),
                const SizedBox(height: 9),
                const FractionallySizedBox(
                  alignment: Alignment.centerLeft,
                  widthFactor: 0.45,
                  child: _Bar(height: 10),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Bar extends StatelessWidget {
  const _Bar({this.width, required this.height});

  final double? width;
  final double height;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: AppColors.rule,
        borderRadius: BorderRadius.circular(2),
      ),
    );
  }
}

/// Reveals list children in sequence on first paint.
///
/// One orchestrated entrance, not a micro-interaction on every element. It also
/// does real work: the stagger walks the eye down the gutter column, which is
/// the axis the layout wants you to read.
class StaggeredList extends StatefulWidget {
  const StaggeredList({
    super.key,
    required this.children,
    this.padding = EdgeInsets.zero,
  });

  final List<Widget> children;
  final EdgeInsets padding;

  @override
  State<StaggeredList> createState() => _StaggeredListState();
}

class _StaggeredListState extends State<StaggeredList> {
  /// Indices that have already played their entrance.
  ///
  /// ListView.builder recycles, so without this an item animates again every
  /// time it scrolls back into view. Rows that keep re-appearing read as
  /// restless, which is the opposite of what a record should feel like.
  final _played = <int>{};

  @override
  Widget build(BuildContext context) {
    final reduced = MediaQuery.disableAnimationsOf(context);

    return ListView.builder(
      padding: widget.padding,
      physics: const AlwaysScrollableScrollPhysics(),
      itemCount: widget.children.length,
      itemBuilder: (context, index) {
        final child = widget.children[index];

        if (reduced || _played.contains(index)) return child;

        _played.add(index);

        return _Rise(
          delay: Duration(milliseconds: (index * 45).clamp(0, 400)),
          child: child,
        );
      },
    );
  }
}

class _Rise extends StatefulWidget {
  const _Rise({required this.delay, required this.child});

  final Duration delay;
  final Widget child;

  @override
  State<_Rise> createState() => _RiseState();
}

class _RiseState extends State<_Rise> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: AppTheme.enter,
  );

  @override
  void initState() {
    super.initState();
    Future<void>.delayed(widget.delay, () {
      if (mounted) _controller.forward();
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final curve = CurvedAnimation(parent: _controller, curve: AppTheme.easeOut);

    return FadeTransition(
      opacity: curve,
      child: SlideTransition(
        position: Tween(
          begin: const Offset(0, 0.06),
          end: Offset.zero,
        ).animate(curve),
        child: widget.child,
      ),
    );
  }
}
