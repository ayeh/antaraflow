import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../../core/haptics.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/brand_mark.dart';

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

  static const _cards = <_Card>[
    _Card(
      eyebrow: 'The red button',
      title: 'It records the room, not a call.',
      body:
          'Put the phone face up on the table and press record. antaraNote '
          'listens through the microphone — there is no bot to invite and '
          'nothing for anyone else to install.',
    ),
    _Card(
      eyebrow: 'While it runs',
      title: 'Mark a decision the moment it is carried.',
      body:
          'One tap on “Mark this” stamps the second you heard it. Those marks '
          'become the skeleton of the minutes, so you are not reconstructing '
          'an hour from memory afterwards.',
      accent: true,
    ),
    _Card(
      eyebrow: 'Afterwards',
      title: 'Minutes, numbered and circulated.',
      body:
          'The recording becomes a transcript, the transcript becomes a draft, '
          'and the draft goes out for confirmation. Everyone present should be '
          'told they are being recorded — that part is still yours.',
    ),
  ];

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  bool get _isLast => _index == _cards.length - 1;

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
    return Scaffold(
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
                    child: const Text('Skip'),
                  ),
                ],
              ),
            ),
            Expanded(
              child: PageView.builder(
                controller: _pages,
                itemCount: _cards.length,
                onPageChanged: (i) => setState(() => _index = i),
                itemBuilder: (context, i) => _cards[i],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 0, 24, 22),
              child: Column(
                children: [
                  _Progress(count: _cards.length, index: _index),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: _next,
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.lime,
                        foregroundColor: AppColors.navyDeep,
                      ),
                      child: Text(_isLast ? 'Start' : 'Next'),
                    ),
                  ),
                ],
              ),
            ),
          ],
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
