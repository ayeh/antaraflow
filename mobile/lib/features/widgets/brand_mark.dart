import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';

/// The antaraNote mark: a lime tile carrying a navy "n", with the wordmark
/// set "antara" light and "Note" bold.
///
/// The glyph is drawn as type rather than loaded from an asset, so the real
/// logo file should replace it before release — the script "n" in the brand
/// asset is a custom form no installed face will reproduce exactly.
class BrandMark extends StatelessWidget {
  const BrandMark({
    super.key,
    this.size = 44,
    this.showWordmark = true,
    this.onDark = false,
  });

  final double size;
  final bool showWordmark;

  /// On a dark ground the wordmark flips to white; the tile does not change,
  /// because lime on navy is the mark's own contrast and it survives anywhere.
  final bool onDark;

  @override
  Widget build(BuildContext context) {
    final tile = Container(
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

    if (!showWordmark) {
      return tile;
    }

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        tile,
        SizedBox(width: size * 0.32),
        Text.rich(
          TextSpan(
            children: const [
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
