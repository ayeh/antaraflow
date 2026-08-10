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
  /// Very light — around 1.4:1 against white — so it can hold the mark or act
  /// as a highlighter behind text, but it must never carry text on a light
  /// ground.
  static const lime = Color(0xFF87FF51);

  /// Headings, navigation, and body text. Carries the wordmark.
  static const navy = Color(0xFF01266E);

  static const danger = Color(0xFFEF4444);
  static const success = Color(0xFF22C55E);
  static const warning = Color(0xFFF59E0B);
  static const info = Color(0xFF0284C7);

  /// Green dark enough to carry text and icons on paper: 5.70:1. The brand
  /// green itself is 2.82:1 and fails at any size, and one step down
  /// (#2B8A00) still only reaches 4.25:1 — short of the 4.5 floor.
  static const primaryInk = Color(0xFF24730A);

  /// Retained for surfaces needing a darker green fill, not for text.
  static const primaryDeep = Color(0xFF2B8A00);

  /// Tint for selected rows and quiet emphasis.
  static const primarySoft = Color(0xFFEFFBE7);

  /// Deeper than the brand navy, for the masthead slab. The brand navy is
  /// already dark; going one step further gives the masthead authority without
  /// introducing a colour the palette does not contain.
  static const navyDeep = Color(0xFF011A4D);

  /// Reading ground.
  ///
  /// Very slightly warm, against a cool navy. Neutrals are not specified in the
  /// branding settings, which is the room this uses: a warm page under cool
  /// structure reads as a document rather than as default application chrome.
  static const paper = Color(0xFFFBFAF7);
  static const paperRaised = Color(0xFFFFFFFF);

  /// Hairlines. This interface rules its lists rather than boxing them.
  static const rule = Color(0xFFE4E2DC);
  static const ruleStrong = Color(0xFFCFCCC4);

  static const ink = Color(0xFF11142B);

  /// 7.47:1 on paper.
  static const inkSoft = Color(0xFF4E5166);

  /// The quietest text allowed anywhere: 4.59:1. Small labels and gutter
  /// captions sit here. This audience skews older; nothing goes fainter.
  static const inkFaint = Color(0xFF6E7188);

  // Retained names used across the app.
  static const n50 = paper;
  static const n100 = Color(0xFFF3F1EC);
  static const n200 = rule;
  static const n300 = ruleStrong;
  static const n500 = inkSoft;
  static const n700 = Color(0xFF2F3247);
  static const n900 = ink;
  static const secondaryLight = inkSoft;

  /// Recording, and nothing else in the app.
  static const recording = danger;
}
