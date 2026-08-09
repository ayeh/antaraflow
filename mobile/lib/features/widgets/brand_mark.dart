import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';

/// The antaraNote mark.
///
/// Renders the real logo file when it is present. The script "n" in the mark is
/// a custom letterform, so the drawn fallback below is an approximation and is
/// only there to keep the app building before the asset lands — it must not
/// reach a release.
///
/// Drop these into mobile/assets/images/:
///   logo-mark.png     the lime tile alone, square, ideally 512×512
///   logo-lockup.png   tile plus wordmark, transparent, ideally 1024 wide
class BrandMark extends StatelessWidget {
  const BrandMark({
    super.key,
    this.size = 44,
    this.showWordmark = true,
    this.onDark = false,
  });

  final double size;
  final bool showWordmark;

  /// Only affects the drawn wordmark fallback. The supplied lockup art is used
  /// as-is; a reverse version should be a separate asset if one is needed.
  final bool onDark;

  static const _markAsset = 'assets/images/logo-mark.png';
  static const _lockupAsset = 'assets/images/logo-lockup.png';

  @override
  Widget build(BuildContext context) {
    if (!showWordmark) {
      return Image.asset(
        _markAsset,
        width: size,
        height: size,
        filterQuality: FilterQuality.medium,
        errorBuilder: (context, _, _) => _DrawnTile(size: size),
      );
    }

    return Image.asset(
      _lockupAsset,
      height: size,
      filterQuality: FilterQuality.medium,
      errorBuilder: (context, _, _) => _DrawnLockup(size: size, onDark: onDark),
    );
  }
}

/// Stand-in for the tile. Approximate by definition — the real glyph is drawn,
/// not typeset.
class _DrawnTile extends StatelessWidget {
  const _DrawnTile({required this.size});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: AppColors.lime,
        borderRadius: BorderRadius.circular(size * 0.28),
      ),
      alignment: Alignment.center,
      child: Text(
        'n',
        style: TextStyle(
          fontSize: size * 0.62,
          height: 1.05,
          fontWeight: FontWeight.w800,
          fontStyle: FontStyle.italic,
          letterSpacing: -0.5,
          color: AppColors.navy,
        ),
      ),
    );
  }
}

class _DrawnLockup extends StatelessWidget {
  const _DrawnLockup({required this.size, required this.onDark});

  final double size;
  final bool onDark;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        _DrawnTile(size: size),
        SizedBox(width: size * 0.32),
        Text.rich(
          const TextSpan(
            children: [
              TextSpan(
                text: 'antara',
                style: TextStyle(fontWeight: FontWeight.w400),
              ),
              TextSpan(
                text: 'Note',
                style: TextStyle(fontWeight: FontWeight.w800),
              ),
            ],
          ),
          style: TextStyle(
            fontSize: size * 0.62,
            height: 1.1,
            letterSpacing: -0.4,
            color: onDark ? Colors.white : AppColors.navy,
          ),
        ),
      ],
    );
  }
}
