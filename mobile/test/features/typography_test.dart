import 'dart:io';

import 'package:antaranote/core/theme/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

double? wght(TextStyle style) {
  final variations = style.fontVariations;
  if (variations == null) return null;

  for (final variation in variations) {
    if (variation.axis == 'wght') return variation.value;
  }

  return null;
}

void main() {
  // Both faces are variable, so weight is carried on the wght axis. A style
  // that sets only fontWeight renders at 400 with a faked bold — legible
  // enough that it survives a glance, which is exactly why it needs a test.
  group('every text style carries the weight axis', () {
    final text = AppTheme.light.textTheme;

    test('headings', () {
      expect(wght(text.headlineMedium!), 800);
      expect(wght(text.headlineSmall!), 800);
      expect(wght(text.titleMedium!), 700);
    });

    test('body', () {
      expect(wght(text.bodyMedium!), 400);
      expect(wght(text.titleSmall!), 700);
      expect(wght(text.labelLarge!), 700);
    });

    test('figures', () {
      expect(wght(AppTheme.mono()), 500);
      expect(wght(AppTheme.mono(weight: FontWeight.w700)), 700);
    });

    test('eyebrows', () {
      expect(wght(AppTheme.eyebrow()), 800);
    });

    test('button labels', () {
      final filled = AppTheme.light.filledButtonTheme.style!.textStyle!.resolve(
        {},
      );
      final outlined = AppTheme.light.outlinedButtonTheme.style!.textStyle!
          .resolve({});

      expect(wght(filled!), 800);
      expect(wght(outlined!), 700);
    });

    test('AppTheme.at moves both the axis and the property', () {
      final restyled = AppTheme.at(text.bodyMedium!, FontWeight.w600);

      expect(wght(restyled), 600);
      expect(restyled.fontWeight, FontWeight.w600);
    });
  });

  // A renamed family in one place and not the other fails silently: Flutter
  // falls back to the platform face and the app merely looks slightly wrong.
  test('the families the theme asks for are the ones pubspec declares', () {
    final pubspec = File('pubspec.yaml').readAsStringSync();

    expect(pubspec, contains('family: ${AppTheme.bodyFont}'));
    expect(pubspec, contains('family: ${AppTheme.monoFont}'));
    expect(File('assets/fonts/Nunito.ttf').existsSync(), isTrue);
    expect(File('assets/fonts/JetBrainsMono.ttf').existsSync(), isTrue);
  });
}
