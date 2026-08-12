import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import 'tab_bar_styles.dart';

/// The iOS 26 arrangement: a glass pill of destinations, and the one action
/// that is not a destination sitting beside it in its own button.
///
/// Recording is not a place in the app — it is a thing you do, usually while
/// standing up and not looking. Putting it in the middle of the tab row made
/// it a fifth destination with a hole either side of it. Out here it is
/// reachable by thumb, unmistakable, and the four tabs get even spacing back.
class GlassTabBar extends StatelessWidget {
  const GlassTabBar({
    super.key,
    required this.index,
    required this.dueCount,
    required this.onSelect,
    required this.onRecord,
    required this.destinations,
  });

  final int index;
  final int dueCount;
  final ValueChanged<int> onSelect;
  final VoidCallback onRecord;
  final List<GlassDestination> destinations;

  static const height = 64.0;
  static const _gap = 10.0;

  /// Four destinations across roughly 298pt gives a ~74pt slot, so 68 left the
  /// highlight all but touching its neighbours. 58 leaves it breathing.
  static const _highlight = 58.0;

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.paddingOf(context).bottom;

    return Padding(
      padding: EdgeInsets.fromLTRB(14, 0, 14, bottom > 0 ? bottom - 6 : 12),
      child: SizedBox(
        height: height,
        child: Row(
          children: [
            Expanded(child: _pill(context)),
            const SizedBox(width: _gap),
            _RecordAccessory(onTap: onRecord),
          ],
        ),
      ),
    );
  }

  Widget _pill(BuildContext context) {
    return Stack(
      children: [
        const Positioned.fill(child: GlassPane()),
        LayoutBuilder(
          builder: (context, constraints) {
            final slot = constraints.maxWidth / destinations.length;

            return Stack(
              children: [
                AnimatedPositioned(
                  duration: const Duration(milliseconds: 340),
                  curve: AppTheme.easeOut,
                  left: index * slot + (slot - _highlight) / 2,
                  top: 7,
                  width: _highlight,
                  height: height - 14,
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      color: AppColors.lime.withValues(alpha: 0.30),
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
                Row(
                  children: [
                    for (var i = 0; i < destinations.length; i++)
                      Expanded(
                        child: _GlassTab(
                          destination: destinations[i],
                          selected: index == i,
                          badge: destinations[i].badged ? dueCount : 0,
                          onTap: () => onSelect(i),
                        ),
                      ),
                  ],
                ),
              ],
            );
          },
        ),
      ],
    );
  }
}

class GlassDestination {
  const GlassDestination({
    required this.label,
    required this.icon,
    this.badged = false,
  });

  final String label;
  final IconData icon;
  final bool badged;
}

class _GlassTab extends StatelessWidget {
  const _GlassTab({
    required this.destination,
    required this.selected,
    required this.badge,
    required this.onTap,
  });

  final GlassDestination destination;
  final bool selected;
  final int badge;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    // inkFaint is 2.94:1 against the tinted glass and fails outright; it was
    // chosen for a contrast against paper the pill does not provide.
    final colour = selected ? AppColors.primaryInk : AppColors.ink;
    final weight = selected ? FontWeight.w800 : FontWeight.w600;

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              Icon(destination.icon, size: 21, color: colour),
              if (badge > 0)
                Positioned(
                  right: -7,
                  top: -4,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 4,
                      vertical: 1,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.danger,
                      borderRadius: BorderRadius.circular(AppTheme.radiusS),
                    ),
                    child: Text(
                      '$badge',
                      style: AppTheme.mono(
                        size: 9.5,
                        weight: FontWeight.w700,
                        colour: Colors.white,
                        tracking: 0,
                      ),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 5),
          Text(
            destination.label,
            style: TextStyle(
              fontSize: 10.5,
              fontWeight: weight,
              fontVariations: AppTheme.axis(weight),
              color: colour,
              letterSpacing: 0.1,
            ),
          ),
        ],
      ),
    );
  }
}

/// A squircle on the same corner as the pill, not the circle iOS 26 uses for
/// an accessory. The interface has one shape family — pill 22, highlight 14,
/// tags and badges 3 — and a lone true circle would be the exception. Solid
/// red rather than glass: this is the one saturated thing in the app, and the
/// only control whose colour carries a meaning.
class _RecordAccessory extends StatefulWidget {
  const _RecordAccessory({required this.onTap});

  final VoidCallback onTap;

  @override
  State<_RecordAccessory> createState() => _RecordAccessoryState();
}

class _RecordAccessoryState extends State<_RecordAccessory> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: 'Start recording',
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: widget.onTap,
        onTapDown: (_) => setState(() => _pressed = true),
        onTapUp: (_) => setState(() => _pressed = false),
        onTapCancel: () => setState(() => _pressed = false),
        child: AnimatedScale(
          scale: _pressed ? 0.92 : 1,
          duration: const Duration(milliseconds: 140),
          curve: AppTheme.easeOut,
          child: Container(
            width: GlassTabBar.height,
            height: GlassTabBar.height,
            decoration: BoxDecoration(
              color: AppColors.recording,
              borderRadius: BorderRadius.circular(22),
            ),
            child: const Icon(
              Icons.fiber_manual_record,
              color: Colors.white,
              size: 22,
            ),
          ),
        ),
      ),
    );
  }
}
