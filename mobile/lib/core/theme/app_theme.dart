import 'package:flutter/material.dart';

import 'app_colors.dart';

/// Theme built from the live branding settings.
///
/// Nunito is named for both roles but the file is not bundled yet — drop
/// Nunito into assets/fonts/ and declare it in pubspec.yaml and every style
/// here picks it up. The fallback list reaches for the platform's rounded face
/// first, which is far closer to Nunito than the default humanist sans.
abstract final class AppTheme {
  static const headingFont = 'Nunito';
  static const bodyFont = 'Nunito';
  static const monoFont = 'JetBrains Mono';

  /// SF Pro Rounded on Apple platforms; Android falls through to Roboto.
  static const roundedFallback = <String>[
    '.SF Pro Rounded',
    'SF Pro Rounded',
    'Varela Round',
  ];

  /// Board members skew older than the average phone user; nothing in the app
  /// goes below 12sp and body text sits at 14.
  static const _scale = _TypeScale();

  static ThemeData get light {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      primary: AppColors.primary,
      secondary: AppColors.navy,
      tertiary: AppColors.lime,
      error: AppColors.danger,
      surface: Colors.white,
    );

    return _base(scheme).copyWith(
      scaffoldBackgroundColor: AppColors.n50,
      dividerColor: AppColors.n200,
    );
  }

  static ThemeData get dark {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      brightness: Brightness.dark,
      // The brand green is bright enough to hold up on a dark ground; the lime
      // becomes the accent because navy disappears against it.
      primary: AppColors.lime,
      secondary: const Color(0xFF9DB4FF),
      tertiary: AppColors.primary,
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
      fontFamilyFallback: roundedFallback,
      textTheme: _textTheme(
        isLight ? AppColors.navy : Colors.white,
        isLight ? AppColors.n700 : const Color(0xFFC3CAD9),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: scheme.surface,
        foregroundColor: isLight ? AppColors.navy : Colors.white,
        elevation: 0,
        scrolledUnderElevation: 1,
        centerTitle: false,
        titleTextStyle: TextStyle(
          fontFamily: headingFont,
          fontSize: _scale.h2,
          fontWeight: FontWeight.w700,
          color: isLight ? AppColors.navy : Colors.white,
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: isLight ? Colors.white : scheme.surfaceContainerHighest,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(
            color: isLight ? AppColors.n200 : Colors.transparent,
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
            fontFamilyFallback: roundedFallback,
            fontSize: 16,
            fontWeight: FontWeight.w700,
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
          borderSide: const BorderSide(color: AppColors.n200),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.n200),
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
        indicatorColor: AppColors.primarySoft,
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
          fontFamilyFallback: roundedFallback,
          fontSize: size,
          fontWeight: weight,
          height: 1.3,
          color: heading,
        );

    TextStyle b(double size, {FontWeight weight = FontWeight.w400}) =>
        TextStyle(
          fontFamily: bodyFont,
          fontFamilyFallback: roundedFallback,
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
