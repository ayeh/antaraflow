import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'providers.dart';

/// The languages the app is written in.
///
/// A closed list rather than everything Flutter can format: a locale the app
/// has no translations for falls back to English silently, which looks like a
/// bug to whoever picked it.
const supportedLocales = <Locale>[Locale('en'), Locale('ms')];

/// Which language the interface is in, and whether that was chosen or
/// inherited.
///
/// Null means follow the phone. That is the honest default — it is also the
/// only value that changes when somebody changes their system language, which
/// is what most people expect and nobody thinks to ask for.
class LocaleController extends StateNotifier<Locale?> {
  LocaleController(this._ref) : super(null) {
    _restore();
  }

  final Ref _ref;

  Future<void> _restore() async {
    final tag = await _ref.read(secureStoreProvider).readLocale();
    if (tag == null || !mounted) return;

    state = Locale(tag);
  }

  /// Pass null to go back to following the phone.
  Future<void> choose(Locale? locale) async {
    state = locale;
    await _ref.read(secureStoreProvider).writeLocale(locale?.languageCode);
  }
}

final localeProvider = StateNotifierProvider<LocaleController, Locale?>(
  LocaleController.new,
);
