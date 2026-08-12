import 'package:antaranote/features/shell/tab_bar_styles.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// Drives the notifier the way a ScrollView would. The notification needs a
/// real context, so one is borrowed from a pumped widget.
late BuildContext _context;

ScrollUpdateNotification scrollTo(double pixels) {
  return ScrollUpdateNotification(
    metrics: FixedScrollMetrics(
      pixels: pixels,
      minScrollExtent: 0,
      maxScrollExtent: 4000,
      viewportDimension: 800,
      axisDirection: AxisDirection.down,
      devicePixelRatio: 3,
    ),
    context: _context,
  );
}

Future<void> _prime(WidgetTester tester) async {
  await tester.pumpWidget(
    Builder(
      builder: (context) {
        _context = context;
        return const SizedBox();
      },
    ),
  );
}

void main() {
  late TabBarVisibility visibility;

  setUp(() => visibility = TabBarVisibility());

  setUpAll(() async {
    TestWidgetsFlutterBinding.ensureInitialized();
  });

  testWidgets('starts fully out', (tester) async {
    await _prime(tester);
    expect(visibility.shown, 1);
  });

  testWidgets('tucks away as the page scrolls down', (tester) async {
    await _prime(tester);
    visibility.onScroll(scrollTo(100));
    visibility.onScroll(scrollTo(300));

    expect(visibility.shown, lessThan(1));
  });

  testWidgets('comes back as the page scrolls up', (tester) async {
    await _prime(tester);
    visibility.onScroll(scrollTo(400));
    visibility.onScroll(scrollTo(800));
    final hidden = visibility.shown;

    visibility.onScroll(scrollTo(600));

    expect(visibility.shown, greaterThan(hidden));
  });

  // Somebody scrolling back to the start should not have to hunt for the bar.
  testWidgets('is always out near the top, whatever the delta says', (
    tester,
  ) async {
    await _prime(tester);
    visibility.onScroll(scrollTo(600));
    visibility.onScroll(scrollTo(1200));
    expect(visibility.shown, lessThan(1));

    visibility.onScroll(scrollTo(10));

    expect(visibility.shown, 1);
  });

  testWidgets('never goes past fully hidden or fully shown', (tester) async {
    await _prime(tester);
    for (var i = 0; i < 40; i++) {
      visibility.onScroll(scrollTo(100.0 + i * 200));
    }
    expect(visibility.shown, 0);

    for (var i = 40; i >= 0; i--) {
      visibility.onScroll(scrollTo(100.0 + i * 200));
    }
    expect(visibility.shown, 1);
  });

  // Each tab keeps its own scroll offset, so the first notification after a
  // switch carries a delta measured against the *previous* tab's position.
  // Without the reset that hid the bar the moment somebody changed tabs —
  // exactly when they are using it.
  testWidgets('a tab change does not tuck the bar away', (tester) async {
    await _prime(tester);
    visibility.onScroll(scrollTo(100));
    visibility.reset();

    // The new tab reports its own, unrelated offset.
    visibility.onScroll(scrollTo(2000));

    expect(
      visibility.shown,
      1,
      reason: 'the first scroll after a reset is a baseline, not a jump',
    );
  });

  testWidgets('notifies only when the value actually moves', (tester) async {
    await _prime(tester);
    var notifications = 0;
    visibility.addListener(() => notifications++);

    visibility.onScroll(scrollTo(200));
    visibility.onScroll(scrollTo(201));

    expect(notifications, lessThanOrEqualTo(1));
  });
}
