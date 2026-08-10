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
class GutterRow extends StatelessWidget {
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
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final accent = severity ?? AppColors.inkFaint;

    return InkWell(
      onTap: onTap,
      splashColor: AppColors.primarySoft,
      highlightColor: AppColors.primarySoft,
      child: Opacity(
        opacity: dimmed ? 0.45 : 1,
        child: Container(
          decoration: const BoxDecoration(
            border: Border(bottom: BorderSide(color: AppColors.rule)),
          ),
          padding: const EdgeInsets.fromLTRB(20, 13, 16, 13),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                width: AppTheme.gutter,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      gutter,
                      style: AppTheme.mono(
                        size: 12,
                        weight: FontWeight.w700,
                        colour: severity ?? AppColors.inkSoft,
                      ),
                    ),
                    if (gutterCaption != null) ...[
                      const SizedBox(height: 3),
                      Text(
                        gutterCaption!,
                        style: AppTheme.mono(
                          size: 10.5,
                          colour: AppColors.inkFaint,
                        ),
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
                      title,
                      style: theme.textTheme.titleSmall?.copyWith(height: 1.35),
                    ),
                    // Subtitle and tag share one line. Given each on its own,
                    // a list of twenty sittings runs to three screens and the
                    // gutter column stops being scannable, which was the whole
                    // point of it.
                    if (subtitle != null || status != null) ...[
                      const SizedBox(height: 5),
                      Row(
                        children: [
                          if (status != null) ...[
                            _Tag(label: status!, colour: accent),
                            const SizedBox(width: 8),
                          ],
                          if (subtitle != null)
                            Expanded(
                              child: Text(
                                subtitle!,
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
              if (trailing != null) ...[
                const SizedBox(width: 10),
                trailing!,
              ],
            ],
          ),
        ),
      ),
    );
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

/// Reveals list children in sequence on first paint.
///
/// One orchestrated entrance, not a micro-interaction on every element. It also
/// does real work: the stagger walks the eye down the gutter column, which is
/// the axis the layout wants you to read.
class StaggeredList extends StatelessWidget {
  const StaggeredList({
    super.key,
    required this.children,
    this.padding = EdgeInsets.zero,
  });

  final List<Widget> children;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    final reduced = MediaQuery.disableAnimationsOf(context);

    return ListView.builder(
      padding: padding,
      physics: const AlwaysScrollableScrollPhysics(),
      itemCount: children.length,
      itemBuilder: (context, index) {
        if (reduced) return children[index];

        return _Rise(
          delay: Duration(milliseconds: (index * 45).clamp(0, 400)),
          child: children[index],
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
    final curve = CurvedAnimation(
      parent: _controller,
      curve: AppTheme.easeOut,
    );

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
