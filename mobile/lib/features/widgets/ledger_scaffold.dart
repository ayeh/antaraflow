import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';

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
  });

  final String title;

  /// The monospaced provenance line: reference number, date, count.
  final String? meta;

  final List<Widget>? actions;
  final Widget? leading;
  final Widget child;
  final Future<void> Function()? onRefresh;

  @override
  Widget build(BuildContext context) {
    final body = onRefresh == null
        ? child
        : RefreshIndicator(
            onRefresh: onRefresh!,
            color: AppColors.primary,
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
          ),
          Expanded(child: body),
        ],
      ),
    );
  }
}

class _Masthead extends StatelessWidget {
  const _Masthead({
    required this.title,
    this.meta,
    this.actions,
    this.leading,
  });

  final String title;
  final String? meta;
  final List<Widget>? actions;
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
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
                  Text(
                    title,
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      color: Colors.white,
                    ),
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
