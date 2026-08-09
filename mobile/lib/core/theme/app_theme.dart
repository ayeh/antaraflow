import 'package:flutter/material.dart';

import 'app_colors.dart';

/// Theme built from the brand book's colour and type scales.
///
/// Font families are named but the files are not bundled yet — drop the
/// licensed Plus Jakarta Sans and Inter files into assets/fonts/ and declare
/// them in pubspec.yaml, and every text style here picks them up. Until then
/// Flutter falls back to the platform font, which is legible but off-brand.
abstract final class AppTheme {
  static const headingFont = 'Plus Jakarta Sans';
  static const bodyFont = 'Inter';
  static const monoFont = 'JetBrains Mono';

  /// Board members skew older than the average phone user; nothing in the app
  /// goes below 12sp and body text sits at 14.
  static const _scale = _TypeScale();

  static ThemeData get light {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      primary: AppColors.primary,
      secondary: AppColors.secondary,
      tertiary: AppColors.accent,
      error: AppColors.danger,
      surface: Colors.white,
    );

    return _base(scheme).copyWith(
      scaffoldBackgroundColor: AppColors.neutral50,
      dividerColor: AppColors.neutral200,
    );
  }

  static ThemeData get dark {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      brightness: Brightness.dark,
      primary: const Color(0xFF2DD4BF),
      tertiary: const Color(0xFFF59E0B),
      error: const Color(0xFFF87171),
    );

    return _base(scheme);
  }

  static ThemeData _base(ColorScheme scheme) {
    final isLight = scheme.brightness == Brightness.light;

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      fontFamily: bodyFont,
      textTheme: _textTheme(
        isLight ? AppColors.neutral900 : Colors.white,
        isLight ? AppColors.neutral700 : const Color(0xFFCBD5E1),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: scheme.surface,
        foregroundColor: isLight ? AppColors.neutral900 : Colors.white,
        elevation: 0,
        scrolledUnderElevation: 1,
        centerTitle: false,
        titleTextStyle: TextStyle(
          fontFamily: headingFont,
          fontSize: _scale.h2,
          fontWeight: FontWeight.w700,
          color: isLight ? AppColors.neutral900 : Colors.white,
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: isLight ? Colors.white : scheme.surfaceContainerHighest,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(
            color: isLight ? AppColors.neutral200 : Colors.transparent,
          ),
        ),
        margin: EdgeInsets.zero,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          // 52 rather than the Material default 40: this is a one-handed app
          // used standing up in a meeting room.
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          textStyle: const TextStyle(
            fontFamily: bodyFont,
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isLight ? Colors.white : scheme.surfaceContainerHighest,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 16,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.neutral200),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.neutral200),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: scheme.primary, width: 2),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 68,
        elevation: 0,
        backgroundColor: scheme.surface,
        indicatorColor: AppColors.primaryLight,
        labelTextStyle: WidgetStateProperty.all(
          const TextStyle(fontSize: 12, fontWeight: FontWeight.w500),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
        ),
      ),
    );
  }

  static TextTheme _textTheme(Color heading, Color body) {
    TextStyle h(double size, {FontWeight weight = FontWeight.w700}) =>
        TextStyle(
          fontFamily: headingFont,
          fontSize: size,
          fontWeight: weight,
          height: 1.3,
          color: heading,
        );

    TextStyle b(double size, {FontWeight weight = FontWeight.w400}) =>
        TextStyle(
          fontFamily: bodyFont,
          fontSize: size,
          fontWeight: weight,
          height: 1.6,
          color: body,
        );

    return TextTheme(
      displaySmall: h(_scale.display),
      headlineMedium: h(_scale.h1),
      headlineSmall: h(_scale.h2),
      titleMedium: h(_scale.h3, weight: FontWeight.w600),
      bodyLarge: b(16),
      bodyMedium: b(_scale.body),
      bodySmall: b(_scale.small),
      labelLarge: b(_scale.body, weight: FontWeight.w600),
    );
  }
}

class _TypeScale {
  const _TypeScale();

  final double display = 36;
  final double h1 = 28;
  final double h2 = 22;
  final double h3 = 18;
  final double body = 14;
  final double small = 12;
}
