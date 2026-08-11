import 'package:flutter/material.dart';

import 'app_colors.dart';

/// Theme for a record, not a feed.
///
/// The interface antaraNote produces is a minute book: what was decided, who
/// moved it, when it was carried. So the type is set tight and structural, the
/// figures are monospaced and column-aligned, and lists are ruled rather than
/// boxed — a card around every row turns a record into a stream of unrelated
/// objects.
///
/// Both faces are bundled as variable fonts, so weight is set on the `wght`
/// axis. The fallbacks below still matter — they cover the frame or two before
/// the asset is resolved, and anything the font has no glyph for.
abstract final class AppTheme {
  static const headingFont = 'Nunito';
  static const bodyFont = 'Nunito';

  /// Everything that counts or ticks: reference numbers, timers, tallies,
  /// due dates. Figures that shift width as they change are unreadable in a
  /// column, which is most of this app.
  static const monoFont = 'JetBrains Mono';

  static const roundedFallback = <String>[
    '.SF Pro Rounded',
    'SF Pro Rounded',
    'Varela Round',
  ];

  static const monoFallback = <String>['SF Mono', 'Menlo', 'monospace'];

  /// Width of the reference gutter that every list aligns to.
  static const gutter = 62.0;

  /// One radius system: small for tags and badges, medium for anything
  /// interactive. Nothing else.
  static const radiusS = 3.0;
  static const radiusM = 6.0;

  /// A strong ease-out. The built-in curves are too weak to read as deliberate.
  static const easeOut = Cubic(0.23, 1, 0.32, 1);
  static const enter = Duration(milliseconds: 280);

  static const scale = _TypeScale();

  /// The weight axis of a variable font.
  ///
  /// On a variable face Flutter picks the instance from `fontVariations`, not
  /// from `fontWeight`. Set only `fontWeight` and every string renders at the
  /// default 400 and the platform fakes the bold ones by smearing the strokes,
  /// which on a rounded face fills in the counters and looks like a rendering
  /// fault. Both are given: the axis draws the weight, `fontWeight` keeps the
  /// style honest for anything that inspects it.
  static List<FontVariation> axis(FontWeight weight) => [
    FontVariation('wght', weight.value.toDouble()),
  ];

  /// Applies a weight to an existing style, on both the axis and the property.
  ///
  /// Use this instead of `copyWith(fontWeight:)` anywhere a style's weight is
  /// changed after the fact — a bare copyWith leaves the axis behind.
  static TextStyle at(TextStyle style, FontWeight weight) =>
      style.copyWith(fontWeight: weight, fontVariations: axis(weight));

  static ThemeData get light {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      primary: AppColors.primary,
      secondary: AppColors.navy,
      tertiary: AppColors.lime,
      error: AppColors.danger,
      surface: AppColors.paperRaised,
    );

    return _base(scheme).copyWith(
      scaffoldBackgroundColor: AppColors.paper,
      dividerColor: AppColors.rule,
    );
  }

  static ThemeData get dark {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      brightness: Brightness.dark,
      // Navy disappears against a dark ground, so the lime takes the accent
      // and the green stays for actions. Inverting the light theme naively
      // would leave half the interface invisible.
      primary: AppColors.lime,
      secondary: const Color(0xFF9DB4FF),
      tertiary: AppColors.primary,
      error: const Color(0xFFF87171),
    );

    return _base(scheme);
  }

  static ThemeData _base(ColorScheme scheme) {
    final isLight = scheme.brightness == Brightness.light;
    final ink = isLight ? AppColors.ink : Colors.white;
    final soft = isLight ? AppColors.inkSoft : const Color(0xFFA6A9BE);

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      fontFamily: bodyFont,
      fontFamilyFallback: roundedFallback,
      textTheme: _textTheme(ink, soft),
      dividerTheme: DividerThemeData(
        color: isLight ? AppColors.rule : const Color(0xFF262A44),
        thickness: 1,
        space: 1,
      ),
      // Cards are reserved for things that genuinely are discrete objects.
      // Lists use rules.
      cardTheme: CardThemeData(
        elevation: 0,
        color: isLight ? AppColors.paperRaised : scheme.surfaceContainerHighest,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusM),
          side: BorderSide(
            color: isLight ? AppColors.rule : Colors.transparent,
          ),
        ),
        margin: EdgeInsets.zero,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          // Held one-handed, standing up, in a meeting room.
          minimumSize: const Size.fromHeight(54),
          // Square-ish. A pill would soften the whole page toward "app"; this
          // is meant to read as a stamped action.
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radiusM),
          ),
          backgroundColor: AppColors.primary,
          // Navy on the brand green is 4.75:1 and passes body contrast, so the
          // green needs no darkening. White on it is 2.94:1 and fails even the
          // large-text floor.
          foregroundColor: AppColors.navy,
          disabledBackgroundColor: AppColors.rule,
          disabledForegroundColor: AppColors.inkFaint,
          textStyle: TextStyle(
            fontFamily: bodyFont,
            fontFamilyFallback: roundedFallback,
            fontSize: 16,
            fontWeight: FontWeight.w800,
            fontVariations: axis(FontWeight.w800),
            letterSpacing: 0.2,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(54),
          side: const BorderSide(color: AppColors.ruleStrong),
          foregroundColor: ink,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radiusM),
          ),
          textStyle: TextStyle(
            fontFamily: bodyFont,
            fontFamilyFallback: roundedFallback,
            fontSize: 15,
            fontWeight: FontWeight.w700,
            fontVariations: axis(FontWeight.w700),
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isLight
            ? AppColors.paperRaised
            : scheme.surfaceContainerHighest,
        contentPadding: const EdgeInsets.fromLTRB(16, 18, 16, 18),
        border: _fieldBorder(AppColors.fieldEdge),
        enabledBorder: _fieldBorder(AppColors.fieldEdge),
        // primaryInk, not the brand green: green on white is 2.94:1 and fails
        // the 3:1 floor for a non-text component, and a focus ring nobody can
        // see is the one failure that locks keyboard users out entirely.
        focusedBorder: _fieldBorder(AppColors.primaryInk, width: 2),
        errorBorder: _fieldBorder(AppColors.danger),
        focusedErrorBorder: _fieldBorder(AppColors.danger, width: 2),
        labelStyle: TextStyle(
          color: soft,
          fontWeight: FontWeight.w600,
          fontVariations: axis(FontWeight.w600),
        ),
        floatingLabelStyle: TextStyle(
          color: scheme.primary,
          fontWeight: FontWeight.w700,
          fontVariations: axis(FontWeight.w700),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: AppColors.navyDeep,
        contentTextStyle: TextStyle(
          fontFamily: bodyFont,
          fontFamilyFallback: roundedFallback,
          color: Colors.white,
          fontWeight: FontWeight.w600,
          fontVariations: axis(FontWeight.w600),
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusM),
        ),
      ),
    );
  }

  static OutlineInputBorder _fieldBorder(Color colour, {double width = 1}) {
    return OutlineInputBorder(
      borderRadius: BorderRadius.circular(radiusM),
      borderSide: BorderSide(color: colour, width: width),
    );
  }

  static TextTheme _textTheme(Color ink, Color soft) {
    // Nunito is a rounded humanist. Set loose it reads soft; set tight and
    // heavy it reads certain, which is what a governance record needs.
    //
    // Tracking is expressed relative to size. A flat -0.6px is -0.018em at 34
    // but -0.035em at 17, so small headings were being tracked twice as tight
    // as large ones — and titleMedium at 17 is the size this app uses most.
    TextStyle h(double size, {FontWeight weight = FontWeight.w800}) =>
        TextStyle(
          fontFamily: headingFont,
          fontFamilyFallback: roundedFallback,
          fontSize: size,
          fontWeight: weight,
          fontVariations: axis(weight),
          height: 1.15,
          letterSpacing: size * (size >= 27 ? -0.018 : -0.010),
          color: ink,
        );

    TextStyle b(double size, {FontWeight weight = FontWeight.w400}) =>
        TextStyle(
          fontFamily: bodyFont,
          fontFamilyFallback: roundedFallback,
          fontSize: size,
          fontWeight: weight,
          fontVariations: axis(weight),
          height: 1.55,
          color: ink,
        );

    return TextTheme(
      displaySmall: h(scale.display),
      headlineMedium: h(scale.h1),
      headlineSmall: h(scale.h2),
      titleMedium: h(scale.h3, weight: FontWeight.w700),
      titleSmall: b(14, weight: FontWeight.w700),
      bodyLarge: b(16),
      bodyMedium: b(scale.body),
      bodySmall: b(scale.small).copyWith(color: soft, height: 1.45),
      labelLarge: b(scale.body, weight: FontWeight.w700),
    );
  }

  /// Monospaced figures. Tabular so columns do not jitter as values change.
  static TextStyle mono({
    double size = 12,
    FontWeight weight = FontWeight.w500,
    Color? colour,
    double tracking = 0.2,
  }) {
    return TextStyle(
      fontFamily: monoFont,
      fontFamilyFallback: monoFallback,
      fontSize: size,
      fontWeight: weight,
      fontVariations: axis(weight),
      letterSpacing: tracking,
      height: 1.3,
      color: colour,
      fontFeatures: const [FontFeature.tabularFigures()],
    );
  }

  /// Small structural labels. Uppercase with wide tracking, used to name a
  /// region without competing with its content.
  static TextStyle eyebrow({Color? colour}) {
    return TextStyle(
      fontFamily: bodyFont,
      fontFamilyFallback: roundedFallback,
      fontSize: 10.5,
      fontWeight: FontWeight.w800,
      fontVariations: axis(FontWeight.w800),
      letterSpacing: 1.3,
      height: 1.2,
      color: colour ?? AppColors.inkFaint,
    );
  }
}

/// A 1.25 scale from the 14pt body up.
///
/// The previous steps were 1.21 and 1.24 at the two sizes nearest body text,
/// which is why a row title never quite separated from its subtitle.
class _TypeScale {
  const _TypeScale();

  final double display = 34.5;
  final double h1 = 27.5;
  final double h2 = 22;
  final double h3 = 17.5;
  final double body = 14;

  /// Nothing in this app goes below 12. Board members skew older than the
  /// average phone user.
  final double small = 12;
}
