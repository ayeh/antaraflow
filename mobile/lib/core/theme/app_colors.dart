import 'package:flutter/material.dart';

/// The live brand, as configured in the platform branding settings.
///
/// Note that docs/brand/BRAND-REFERENCE.md and docs/brand/logos/ are stale —
/// they still describe the teal and gold three-dot mark. The admin settings are
/// what actually renders, so they are the authority here.
abstract final class AppColors {
  /// Primary actions and the active state of anything selectable.
  static const primary = Color(0xFF37AD00);

  /// The logo tile and highlight surfaces.
  ///
  /// Very light — around 1.4:1 against white — so it can hold the mark or fill a
  /// chip, but it must never carry text on a light ground.
  static const lime = Color(0xFF87FF51);

  /// Headings, navigation, and body text. Carries the wordmark.
  static const navy = Color(0xFF01266E);

  static const danger = Color(0xFFEF4444);
  static const success = Color(0xFF22C55E);
  static const warning = Color(0xFFF59E0B);
  static const info = Color(0xFF0284C7);

  /// Darker than `primary` so it can carry small text and icons where the
  /// brand green would fall below the contrast floor.
  static const primaryDeep = Color(0xFF2B8A00);

  /// Tint for selected rows and quiet emphasis.
  static const primarySoft = Color(0xFFEFFBE7);

  // Neutrals are not specified in the branding settings. These are pulled a few
  // degrees toward the navy accent so greys read as part of the palette rather
  // than as an unconsidered default.
  static const n50 = Color(0xFFF7F9FC);
  static const n100 = Color(0xFFEFF2F8);
  static const n200 = Color(0xFFDFE4EE);
  static const n300 = Color(0xFFC3CAD9);
  static const n500 = Color(0xFF6B7590);
  static const n700 = Color(0xFF313A52);
  static const n900 = Color(0xFF0B1330);

  static const secondaryLight = n500;

  /// Recording, and nothing else in the app.
  static const recording = danger;
}
