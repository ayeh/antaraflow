import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/haptics.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import 'brand_mark.dart';

/// Page furniture for every screen.
///
/// A solid navy slab rather than a floating app bar. It gives each screen a
/// masthead the way a printed record has one — title, and a monospaced line of
/// provenance underneath — and the content sits on paper beneath it. A floating
/// bar would leave every screen starting with nothing but a back arrow.
class LedgerScaffold extends StatelessWidget {
  const LedgerScaffold({
    super.key,
    required this.title,
    required this.child,
    this.meta,
    this.actions,
    this.leading,
    this.onRefresh,
    this.reference,
    this.heroTag,
    this.letterhead = false,
  });

  final String title;

  /// The monospaced provenance line: reference number, date, count.
  final String? meta;

  /// The record's own number, set above the title.
  ///
  /// Separate from [meta] because it is the one part of the provenance that
  /// also exists in the list this screen was opened from, and so is the part
  /// that can travel between them.
  final String? reference;

  /// Ties [reference] to the gutter cell it was opened from, so the number
  /// flies up into the masthead instead of the page arriving from nowhere.
  final Object? heroTag;

  /// Sets the mark above the title, the way a minute book carries a letterhead
  /// on its first page and not on every one after it.
  final bool letterhead;

  final List<Widget>? actions;
  final Widget? leading;
  final Widget child;
  final Future<void> Function()? onRefresh;

  @override
  Widget build(BuildContext context) {
    final body = onRefresh == null
        ? child
        : RefreshIndicator(
            onRefresh: () async {
              // Fired when the gesture commits, not when the data lands: the
              // thumb is still on the glass here, and this is the moment the
              // pull is confirmed as having been far enough.
              Haptics.tick();
              await onRefresh!();
            },
            // Brand green is 2.94:1 on white and fails the 3:1 floor for a
            // non-text component.
            color: AppColors.primaryInk,
            backgroundColor: AppColors.paperRaised,
            child: child,
          );

    return Scaffold(
      backgroundColor: AppColors.paper,
      body: Column(
        children: [
          _Masthead(
            title: title,
            meta: meta,
            actions: actions,
            leading: leading,
            reference: reference,
            heroTag: heroTag,
            letterhead: letterhead,
          ),
          Expanded(child: body),
        ],
      ),
    );
  }
}

/// Puts the clock, signal and battery in light glyphs.
///
/// Every screen in this app that reaches the top of the display reaches it in
/// navy, and the system default paints those glyphs black. Nothing in Flutter
/// infers this from the colour underneath — it has to be declared, and any
/// screen not wrapped in something that declares it gets an unreadable clock.
class LightStatusBar extends StatelessWidget {
  const LightStatusBar({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: child,
    );
  }
}

class _Masthead extends StatelessWidget {
  const _Masthead({
    required this.title,
    this.meta,
    this.actions,
    this.leading,
    this.reference,
    this.heroTag,
    this.letterhead = false,
  });

  final String title;
  final String? meta;
  final List<Widget>? actions;
  final Widget? leading;
  final String? reference;
  final Object? heroTag;
  final bool letterhead;

  @override
  Widget build(BuildContext context) {
    return LightStatusBar(
      child: Container(
        width: double.infinity,
        color: AppColors.navyDeep,
        padding: EdgeInsets.only(
          top: MediaQuery.paddingOf(context).top + 10,
          bottom: 18,
          left: 20,
          right: 12,
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (leading != null) ...[leading!, const SizedBox(width: 6)],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (letterhead) ...[
                    const BrandMark(size: 21, onDark: true),
                    const SizedBox(height: 14),
                  ],
                  if (reference != null) ...[
                    _Reference(text: reference!, heroTag: heroTag),
                    const SizedBox(height: 7),
                  ],
                  Text(
                    title,
                    style: Theme.of(
                      context,
                    ).textTheme.headlineMedium?.copyWith(color: Colors.white),
                  ),
                  if (meta != null) ...[
                    const SizedBox(height: 6),
                    Text(
                      meta!,
                      style: AppTheme.mono(
                        size: 11.5,
                        colour: const Color(0xFF8FA2D6),
                        tracking: 0.6,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            if (actions != null) ...[
              const SizedBox(width: 8),
              Row(mainAxisSize: MainAxisSize.min, children: actions!),
            ],
          ],
        ),
      ),
    );
  }
}

/// The record number, in the masthead and in flight from the list.
class _Reference extends StatelessWidget {
  const _Reference({required this.text, this.heroTag});

  final String text;
  final Object? heroTag;

  @override
  Widget build(BuildContext context) {
    final label = Text(
      text,
      style: AppTheme.mono(
        size: 12,
        weight: FontWeight.w700,
        colour: AppColors.lime,
        tracking: 0.6,
      ),
    );

    if (heroTag == null) return label;

    // Material, because a Hero in flight is outside the tree that was giving
    // the text its ground, and bare text mid-air paints its own debug banner.
    return Hero(
      tag: heroTag!,
      flightShuttleBuilder: (_, _, _, _, _) =>
          Material(type: MaterialType.transparency, child: label),
      child: label,
    );
  }
}

/// Icon button sized for the masthead, on navy.
class MastheadAction extends StatelessWidget {
  const MastheadAction({
    super.key,
    required this.icon,
    required this.tooltip,
    this.onPressed,
    this.badge = 0,
  });

  final IconData icon;
  final String tooltip;
  final VoidCallback? onPressed;
  final int badge;

  @override
  Widget build(BuildContext context) {
    final button = IconButton(
      onPressed: onPressed,
      tooltip: tooltip,
      icon: Icon(icon, size: 22),
      color: const Color(0xFFC3D0F0),
      constraints: const BoxConstraints.tightFor(width: 44, height: 44),
    );

    if (badge <= 0) return button;

    return Stack(
      clipBehavior: Clip.none,
      children: [
        button,
        Positioned(
          right: 6,
          top: 6,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
            decoration: BoxDecoration(
              color: AppColors.lime,
              borderRadius: BorderRadius.circular(3),
            ),
            child: Text(
              badge > 99 ? '99+' : '$badge',
              style: AppTheme.mono(
                size: 10,
                weight: FontWeight.w700,
                colour: AppColors.navyDeep,
                tracking: 0,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

/// Names a region of the page. A rule runs from the label to the right edge, so
/// sections read as entries in a record rather than as floating groups.
class SectionRule extends StatelessWidget {
  const SectionRule({super.key, required this.label, this.trailing});

  final String label;
  final String? trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 26, 20, 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Text(label.toUpperCase(), style: AppTheme.eyebrow()),
          const SizedBox(width: 12),
          const Expanded(child: Divider(color: AppColors.rule)),
          if (trailing != null) ...[
            const SizedBox(width: 12),
            Text(
              trailing!,
              style: AppTheme.mono(size: 11, colour: AppColors.inkFaint),
            ),
          ],
        ],
      ),
    );
  }
}
