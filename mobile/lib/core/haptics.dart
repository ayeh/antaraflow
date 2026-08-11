import 'package:flutter/services.dart';

/// The whole app's vocabulary of touch, in one place.
///
/// Three weights, each tied to a class of event rather than to a widget. Named
/// this way because the mapping is the decision — scattering
/// `HapticFeedback.lightImpact()` through the screens is how an app ends up
/// buzzing at the same weight for a tab change and for signing a minute.
///
/// Fired before the network call, never after. A confirmation that waits on a
/// server reads as lag, not as feedback.
abstract final class Haptics {
  /// Moving between things: tabs, filters, a bookmark landing in the list.
  static void select() => HapticFeedback.selectionClick();

  /// A small commitment that can be undone: ticking a task, pulling to refresh.
  static void tick() => HapticFeedback.lightImpact();

  /// Entering or leaving a mode. Recording starting is the main one.
  static void shift() => HapticFeedback.mediumImpact();

  /// Something is now on the record: minutes confirmed, session ended.
  static void commit() => HapticFeedback.heavyImpact();
}
