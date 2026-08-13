import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../core/haptics.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/brand_mark.dart';
import '../widgets/ledger_scaffold.dart';
import '../../l10n/app_localizations.dart';

/// Whether the three cards have been seen on this device.
///
/// Kept beside the token rather than in shared preferences: it is device
/// state, it is small, and the app already has exactly one place for that.
final firstRunProvider = FutureProvider<bool>((ref) async {
  const storage = FlutterSecureStorage();
  final seen = await storage.read(key: _seenKey);

  return seen != 'yes';
});

const _seenKey = 'first_run_seen';

Future<void> markFirstRunSeen(WidgetRef ref) async {
  const storage = FlutterSecureStorage();
  await storage.write(key: _seenKey, value: 'yes');
  ref.invalidate(firstRunProvider);
}

/// Three cards, shown once.
///
/// Not a tour of the interface — a board member does not need to be told what
/// a list is. It answers the three questions somebody actually has before they
/// dare press a red button in a room full of people: what does this record,
/// who knows it is recording, and where does what I say end up.
class FirstRun extends ConsumerStatefulWidget {
  const FirstRun({super.key, required this.onDone});

  final VoidCallback onDone;

  @override
  ConsumerState<FirstRun> createState() => _FirstRunState();
}

class _FirstRunState extends ConsumerState<FirstRun> {
  final _pages = PageController();
  int _index = 0;

  /// Built per-frame rather than held as a const list: these are three
  /// paragraphs of prose and prose has to come from the translator.
  List<_Card> _cards(BuildContext context) {
    final l = L.of(context);

    return [
      _Card(
        eyebrow: l.firstRunOneEyebrow,
        title: l.firstRunOneLine,
        body: l.firstRunOneBody,
      ),
      _Card(
        eyebrow: l.firstRunTwoEyebrow,
        title: l.firstRunTwoLine,
        body: l.firstRunTwoBody,
        accent: true,
      ),
      _Card(
        eyebrow: l.firstRunThreeEyebrow,
        title: l.firstRunThreeLine,
        body: l.firstRunThreeBody,
      ),
    ];
  }

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  /// Three, and the count is structural rather than derived: _cards needs a
  /// context and this is asked for outside build.
  static const _cardCount = 3;

  bool get _isLast => _index == _cardCount - 1;

  void _next() {
    Haptics.select();

    if (_isLast) {
      markFirstRunSeen(ref);
      widget.onDone();
      return;
    }

    _pages.nextPage(
      duration: const Duration(milliseconds: 340),
      curve: AppTheme.easeOut,
    );
  }

  void _skip() {
    markFirstRunSeen(ref);
    widget.onDone();
  }

  @override
  Widget build(BuildContext context) {
    return LightStatusBar(
      child: Scaffold(
        backgroundColor: AppColors.navyDeep,
        body: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(24, 18, 12, 0),
                child: Row(
                  children: [
                    const BrandMark(size: 30, onDark: true),
                    const Spacer(),
                    TextButton(
                      onPressed: _skip,
                      style: TextButton.styleFrom(
                        foregroundColor: const Color(0xFF8FA2D6),
                      ),
                      child: Text(L.of(context).skip),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: PageView.builder(
                  controller: _pages,
                  itemCount: _cardCount,
                  onPageChanged: (i) => setState(() => _index = i),
                  itemBuilder: (context, i) => _cards(context)[i],
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(24, 0, 24, 22),
                child: Column(
                  children: [
                    _Progress(count: _cardCount, index: _index),
                    const SizedBox(height: 20),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        onPressed: _next,
                        style: FilledButton.styleFrom(
                          backgroundColor: AppColors.lime,
                          foregroundColor: AppColors.navyDeep,
                        ),
                        child: Text(
                          _isLast ? L.of(context).start : L.of(context).next,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Card extends StatelessWidget {
  const _Card({
    required this.eyebrow,
    required this.title,
    required this.body,
    this.accent = false,
  });

  final String eyebrow;
  final String title;
  final String body;

  /// The middle card carries the highlighter, because the mark is the one
  /// thing on these three screens somebody has to remember.
  final bool accent;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 40, 24, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            eyebrow.toUpperCase(),
            style: AppTheme.eyebrow(
              colour: accent ? AppColors.lime : const Color(0xFF8FA2D6),
            ),
          ),
          const SizedBox(height: 18),
          Text(
            title,
            style: Theme.of(context).textTheme.displaySmall?.copyWith(
              color: Colors.white,
              height: 1.14,
            ),
          ),
          const SizedBox(height: 20),
          Text(
            body,
            style: Theme.of(context).textTheme.bodyLarge?.copyWith(
              color: const Color(0xFFC3D0F0),
              height: 1.65,
            ),
          ),
        ],
      ),
    );
  }
}

/// Rules, not dots. The rest of the app marks position with a rule.
class _Progress extends StatelessWidget {
  const _Progress({required this.count, required this.index});

  final int count;
  final int index;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (var i = 0; i < count; i++)
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(right: i == count - 1 ? 0 : 8),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 280),
                curve: AppTheme.easeOut,
                height: 3,
                color: i <= index ? AppColors.lime : const Color(0xFF1C3568),
              ),
            ),
          ),
      ],
    );
  }
}
