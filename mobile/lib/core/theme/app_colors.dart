import 'package:flutter/material.dart';

/// The palette from docs/brand/BRAND-REFERENCE.md.
///
/// Note that public/design-system.html still carries an older purple palette;
/// the brand book is the authority and is what these values follow.
abstract final class AppColors {
  /// Nusantara Teal — the brand's primary.
  static const primary = Color(0xFF0D7377);
  static const primaryDark = Color(0xFF095153);
  static const primaryLight = Color(0xFFE6F4F4);

  static const secondary = Color(0xFF1E293B); // Slate Navy
  static const secondaryLight = Color(0xFF64748B); // Cool Gray

  /// Amber Gold. Reserved for primary calls to action, per the brand book —
  /// not for decoration, or it stops reading as "the thing to press".
  static const accent = Color(0xFFD97706);

  static const success = Color(0xFF059669);
  static const warning = Color(0xFFF59E0B);
  static const danger = Color(0xFFDC2626);
  static const info = Color(0xFF0284C7);

  static const neutral50 = Color(0xFFF8FAFC);
  static const neutral100 = Color(0xFFF1F5F9);
  static const neutral200 = Color(0xFFE2E8F0);
  static const neutral300 = Color(0xFFCBD5E1);
  static const neutral700 = Color(0xFF334155);
  static const neutral900 = Color(0xFF0F172A);

  /// Recording is destructive to get wrong, so it uses the one colour nothing
  /// else in the app is allowed to use.
  static const recording = Color(0xFFDC2626);
}
