import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../core/haptics.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../domain/models/live_session.dart';
import '../widgets/brand_mark.dart';
import '../widgets/ledger_scaffold.dart';
import 'chunk_outbox.dart';
import 'recorder_controller.dart';
import 'waveform.dart';

/// Ink for the recorder, which is the one screen that lives on navy.
abstract final class _Dark {
  static const ground = Color(0xFF041231);
  static const rule = Color(0xFF0E2350);
  static const ruleStrong = Color(0xFF1C3568);
  static const faint = Color(0xFF6C85C4);
  static const soft = Color(0xFF9FB2E4);
  static const bright = Color(0xFFE9F0FF);
  static const live = Color(0xFFFF5A5A);
}

/// Rises from the bottom rather than sliding in from the side.
///
/// Recording is a mode, not another page, and the whole interface inverting
/// from paper to navy as the sheet comes up is the signal that the phone is
/// now doing something on behalf of the room.
Route<void> recorderRoute({required int meetingId, required String title}) {
  return PageRouteBuilder<void>(
    transitionDuration: const Duration(milliseconds: 340),
    reverseTransitionDuration: const Duration(milliseconds: 240),
    pageBuilder: (_, _, _) =>
        RecorderScreen(meetingId: meetingId, title: title),
    transitionsBuilder: (context, animation, _, child) {
      final curved = CurvedAnimation(
        parent: animation,
        curve: AppTheme.easeOut,
        reverseCurve: Curves.easeIn,
      );

      return SlideTransition(
        position: Tween(
          begin: const Offset(0, 1),
          end: Offset.zero,
        ).animate(curved),
        child: child,
      );
    },
  );
}

class RecorderScreen extends ConsumerStatefulWidget {
  const RecorderScreen({
    super.key,
    required this.meetingId,
    required this.title,
  });

  final int meetingId;
  final String title;

  @override
  ConsumerState<RecorderScreen> createState() => _RecorderScreenState();
}

class _RecorderScreenState extends ConsumerState<RecorderScreen> {
  @override
  void initState() {
    super.initState();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      Haptics.shift();
      ref
          .read(recorderControllerProvider.notifier)
          .begin(meetingId: widget.meetingId, title: widget.title);
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(recorderControllerProvider);

    ref.listen(recorderControllerProvider, (previous, next) {
      if (previous?.phase != next.phase &&
          next.phase == RecorderPhase.finished) {
        Haptics.commit();
      }
    });

    return PopScope(
      // Leaving mid-sitting would abandon a live session on the server and
      // leave the microphone open behind a list of meetings.
      canPop: !state.canMark,
      child: Theme(
        data: Theme.of(context).copyWith(
          textSelectionTheme: const TextSelectionThemeData(
            cursorColor: AppColors.lime,
          ),
        ),
        child: LightStatusBar(
          child: Scaffold(
            backgroundColor: _Dark.ground,
            body: SafeArea(
              child: switch (state.phase) {
                RecorderPhase.preparing => const _Waiting(
                  line: 'Opening the microphone',
                ),
                RecorderPhase.denied => const _Denied(),
                RecorderPhase.failed => _Failed(message: state.error),
                RecorderPhase.finishing => const _Waiting(
                  line: 'Filing the last of the audio',
                ),
                RecorderPhase.finished => _Finished(state: state),
                _ => _Live(state: state),
              },
            ),
          ),
        ),
      ),
    );
  }
}

class _Live extends ConsumerWidget {
  const _Live({required this.state});

  final RecorderState state;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final controller = ref.read(recorderControllerProvider.notifier);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _Header(title: state.meetingTitle),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _StatusPill(live: state.isLive),
              const SizedBox(height: 10),
              Text(
                state.clock,
                style: AppTheme.mono(
                  size: 42,
                  weight: FontWeight.w300,
                  colour: Colors.white,
                  tracking: -0.5,
                ),
              ),
              const SizedBox(height: 10),
              _OutboxLine(status: controller.outbox, lost: controller.lost),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 6, 20, 2),
          child: Waveform(level: controller.level, live: state.isLive),
        ),
        Expanded(child: _Marks(bookmarks: state.bookmarks)),
        _Actions(state: state),
      ],
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(8, 4, 16, 0),
      child: Row(
        children: [
          IconButton(
            // Deliberately not a back arrow: the session keeps running, and an
            // arrow would promise otherwise.
            icon: const Icon(Icons.keyboard_arrow_down_rounded),
            color: _Dark.soft,
            tooltip: 'Minimise',
            onPressed: () => Navigator.of(context).maybePop(),
          ),
          Expanded(
            child: Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: AppTheme.eyebrow(colour: _Dark.faint),
            ),
          ),
          // The one screen other people in the room look at. A phone lies face
          // up on the table for an hour with this on it, so the mark earns its
          // place here in a way it does not on a working list.
          const Padding(
            padding: EdgeInsets.only(left: 10),
            child: BrandMark(size: 22, showWordmark: false),
          ),
        ],
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.live});

  final bool live;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        _Beacon(live: live),
        const SizedBox(width: 8),
        Text(
          live ? 'RECORDING' : 'PAUSED',
          style: AppTheme.eyebrow(
            colour: live ? _Dark.live : AppColors.warning,
          ),
        ),
      ],
    );
  }
}

/// The one thing on the screen that never stops moving while recording runs.
class _Beacon extends StatefulWidget {
  const _Beacon({required this.live});

  final bool live;

  @override
  State<_Beacon> createState() => _BeaconState();
}

class _BeaconState extends State<_Beacon> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1800),
  );

  /// Not initState: reading MediaQuery there is reading an inherited widget
  /// before the element is allowed to depend on one.
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _sync();
  }

  @override
  void didUpdateWidget(_Beacon old) {
    super.didUpdateWidget(old);
    _sync();
  }

  void _sync() {
    if (widget.live && !MediaQuery.disableAnimationsOf(context)) {
      _controller.repeat(reverse: true);
    } else {
      _controller.stop();
      _controller.value = 1;
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: Tween(begin: 0.3, end: 1.0).animate(_controller),
      child: Container(
        width: 9,
        height: 9,
        decoration: BoxDecoration(
          color: widget.live ? _Dark.live : AppColors.warning,
          shape: BoxShape.circle,
        ),
      ),
    );
  }
}

/// Says out loud how much audio has actually reached the server.
///
/// Hiding this is the tempting choice and the wrong one: the failure people
/// fear is that the phone was not really recording, and a silent app gives
/// them no way to tell the difference until the transcript is short.
class _OutboxLine extends StatelessWidget {
  const _OutboxLine({required this.status, required this.lost});

  final ValueListenable<OutboxStatus>? status;

  /// Chunks that never reached disk. Distinct from a queue that is behind:
  /// nothing is coming for these, so the line has to stop saying AUTOSAVING as
  /// though everything were being kept.
  final ValueListenable<int> lost;

  @override
  Widget build(BuildContext context) {
    final listenable = status;

    if (listenable == null) {
      return Text(
        'STARTING',
        style: AppTheme.mono(size: 11, colour: _Dark.faint, tracking: 0.6),
      );
    }

    return ValueListenableBuilder(
      valueListenable: listenable,
      builder: (context, value, _) {
        return ValueListenableBuilder(
          valueListenable: lost,
          builder: (context, dropped, _) {
            final stalled = value.isStalled;
            final parts = <String>[
              '${value.sent} SENT',
              if (value.queued > 0) '${value.queued} QUEUED',
              if (dropped > 0) '$dropped LOST',
              if (stalled) 'RETRYING' else if (dropped == 0) 'AUTOSAVING',
            ];

            return Text(
              parts.join(' · '),
              style: AppTheme.mono(
                size: 11,
                colour: dropped > 0
                    ? AppColors.danger
                    : stalled
                    ? AppColors.warning
                    : _Dark.faint,
                tracking: 0.6,
              ),
            );
          },
        );
      },
    );
  }
}

class _Marks extends StatelessWidget {
  const _Marks({required this.bookmarks});

  final List<Bookmark> bookmarks;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 10, 20, 6),
          child: Row(
            children: [
              Text('MARKS', style: AppTheme.eyebrow(colour: _Dark.faint)),
              const SizedBox(width: 12),
              const Expanded(child: Divider(color: _Dark.rule)),
              if (bookmarks.isNotEmpty) ...[
                const SizedBox(width: 12),
                Text(
                  '${bookmarks.length}',
                  style: AppTheme.mono(size: 11, colour: _Dark.faint),
                ),
              ],
            ],
          ),
        ),
        Expanded(
          child: bookmarks.isEmpty
              ? const _NoMarks()
              : ListView.builder(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  itemCount: bookmarks.length,
                  itemBuilder: (context, index) {
                    final bookmark = bookmarks[index];

                    return _MarkRow(
                      key: ValueKey(bookmark.clientId),
                      bookmark: bookmark,
                    );
                  },
                ),
        ),
      ],
    );
  }
}

class _NoMarks extends StatelessWidget {
  const _NoMarks();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 0),
      child: Text(
        'Mark a decision as it is carried and it lands here, timed to the '
        'second. The marks become the skeleton of the minutes.',
        style: Theme.of(
          context,
        ).textTheme.bodySmall?.copyWith(color: _Dark.faint, height: 1.5),
      ),
    );
  }
}

/// Stamps in from above on first build, then never moves again.
class _MarkRow extends StatefulWidget {
  const _MarkRow({super.key, required this.bookmark});

  final Bookmark bookmark;

  @override
  State<_MarkRow> createState() => _MarkRowState();
}

class _MarkRowState extends State<_MarkRow>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 340),
  );

  @override
  void initState() {
    super.initState();
    _controller.forward();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bookmark = widget.bookmark;
    final curved = CurvedAnimation(
      parent: _controller,
      curve: AppTheme.easeOut,
    );
    final reduced = MediaQuery.disableAnimationsOf(context);

    final row = Container(
      decoration: const BoxDecoration(
        border: Border(bottom: BorderSide(color: _Dark.rule)),
      ),
      padding: const EdgeInsets.symmetric(vertical: 11),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 54,
            child: Text(
              bookmark.stamp,
              style: AppTheme.mono(
                size: 12,
                weight: FontWeight.w700,
                colour: AppColors.lime,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  bookmark.label?.isNotEmpty == true
                      ? bookmark.label!
                      : bookmark.kind.label,
                  style: TextStyle(
                    color: _Dark.bright,
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    fontVariations: AppTheme.axis(FontWeight.w600),
                    height: 1.3,
                  ),
                ),
                if (bookmark.label?.isNotEmpty == true) ...[
                  const SizedBox(height: 3),
                  Text(
                    bookmark.kind.label.toUpperCase(),
                    style: AppTheme.eyebrow(colour: _Dark.faint),
                  ),
                ],
              ],
            ),
          ),
          if (!bookmark.synced)
            Padding(
              padding: const EdgeInsets.only(left: 8, top: 2),
              child: Icon(
                Icons.cloud_queue_rounded,
                size: 15,
                color: _Dark.faint,
                semanticLabel: 'Not yet sent',
              ),
            ),
        ],
      ),
    );

    if (reduced) return row;

    return FadeTransition(
      opacity: curved,
      child: SizeTransition(sizeFactor: curved, child: row),
    );
  }
}

class _Actions extends ConsumerWidget {
  const _Actions({required this.state});

  final RecorderState state;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final controller = ref.read(recorderControllerProvider.notifier);

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 18),
      child: Column(
        children: [
          _MarkButton(
            onMark: () {
              // Before the request, always. Feedback that waits on a server
              // reads as lag rather than as confirmation.
              Haptics.select();
              controller.mark();
            },
            onChoose: () => _chooseKind(context, controller),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _GhostButton(
                  label: state.isLive ? 'Pause' : 'Resume',
                  icon: state.isLive
                      ? Icons.pause_rounded
                      : Icons.play_arrow_rounded,
                  onPressed: () {
                    Haptics.tick();
                    controller.togglePause();
                  },
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _GhostButton(
                  label: 'End',
                  icon: Icons.stop_rounded,
                  onPressed: () => _confirmEnd(context, controller),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _chooseKind(
    BuildContext context,
    RecorderController controller,
  ) async {
    Haptics.tick();

    final kind = await showModalBottomSheet<BookmarkKind>(
      context: context,
      backgroundColor: _Dark.ground,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(
          top: Radius.circular(AppTheme.radiusM),
        ),
      ),
      builder: (context) => const _KindSheet(),
    );

    if (kind != null) {
      Haptics.select();
      await controller.mark(kind: kind);
    }
  }

  Future<void> _confirmEnd(
    BuildContext context,
    RecorderController controller,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: _Dark.ground,
        title: const Text(
          'End the recording?',
          style: TextStyle(color: Colors.white),
        ),
        content: const Text(
          'The audio still in the queue is sent first, then the minutes are '
          'drafted from the transcript.',
          style: TextStyle(color: _Dark.soft),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text(
              'Keep recording',
              style: TextStyle(color: _Dark.soft),
            ),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('End', style: TextStyle(color: AppColors.lime)),
          ),
        ],
      ),
    );

    if (confirmed ?? false) {
      Haptics.commit();
      await controller.end();
    }
  }
}

/// Tap marks the moment; hold names it.
///
/// A dialog on every mark would make this unusable — the whole value is that
/// it costs one thumb and no attention.
class _MarkButton extends StatefulWidget {
  const _MarkButton({required this.onMark, required this.onChoose});

  final VoidCallback onMark;
  final VoidCallback onChoose;

  @override
  State<_MarkButton> createState() => _MarkButtonState();
}

class _MarkButtonState extends State<_MarkButton> {
  bool _pressed = false;
  bool _flashing = false;

  void _fire() {
    widget.onMark();

    setState(() => _flashing = true);
    Future<void>.delayed(const Duration(milliseconds: 220), () {
      if (mounted) setState(() => _flashing = false);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: 'Mark this moment. Hold to choose a kind.',
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: _fire,
        onLongPress: widget.onChoose,
        onTapDown: (_) => setState(() => _pressed = true),
        onTapUp: (_) => setState(() => _pressed = false),
        onTapCancel: () => setState(() => _pressed = false),
        child: AnimatedScale(
          scale: _pressed ? 0.965 : 1,
          duration: const Duration(milliseconds: 140),
          curve: AppTheme.easeOut,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 220),
            curve: AppTheme.easeOut,
            height: 58,
            decoration: BoxDecoration(
              color: _flashing ? Colors.white : AppColors.lime,
              borderRadius: BorderRadius.circular(AppTheme.radiusM),
            ),
            alignment: Alignment.center,
            child: Text(
              'Mark this',
              style: TextStyle(
                color: AppColors.navyDeep,
                fontSize: 16.5,
                fontWeight: FontWeight.w800,
                fontVariations: AppTheme.axis(FontWeight.w800),
                letterSpacing: 0.2,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _GhostButton extends StatelessWidget {
  const _GhostButton({
    required this.label,
    required this.icon,
    required this.onPressed,
  });

  final String label;
  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton.icon(
      onPressed: onPressed,
      icon: Icon(icon, size: 19),
      label: Text(label),
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(50),
        foregroundColor: _Dark.bright,
        side: const BorderSide(color: _Dark.ruleStrong),
      ),
    );
  }
}

class _KindSheet extends StatelessWidget {
  const _KindSheet();

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 8),
            child: Text(
              'WHAT WAS IT',
              style: AppTheme.eyebrow(colour: _Dark.faint),
            ),
          ),
          for (final kind in BookmarkKind.values)
            ListTile(
              title: Text(
                kind.label,
                style: TextStyle(
                  color: _Dark.bright,
                  fontWeight: FontWeight.w600,
                  fontVariations: AppTheme.axis(FontWeight.w600),
                ),
              ),
              onTap: () => Navigator.of(context).pop(kind),
            ),
          const SizedBox(height: 8),
        ],
      ),
    );
  }
}

class _Waiting extends StatelessWidget {
  const _Waiting({required this.line});

  final String line;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SizedBox.square(
            dimension: 22,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: AppColors.lime,
            ),
          ),
          const SizedBox(height: 20),
          Text(line, style: AppTheme.eyebrow(colour: _Dark.faint)),
        ],
      ),
    );
  }
}

class _Denied extends StatelessWidget {
  const _Denied();

  @override
  Widget build(BuildContext context) {
    return _Notice(
      eyebrow: 'MICROPHONE REFUSED',
      title: 'antaraNote cannot hear the room',
      detail:
          'Recording needs the microphone. Grant it in Settings and come back '
          'to this screen.',
      action: 'Open settings',
      onAction: openAppSettings,
    );
  }
}

class _Failed extends StatelessWidget {
  const _Failed({this.message});

  final String? message;

  @override
  Widget build(BuildContext context) {
    return _Notice(
      eyebrow: 'NOT STARTED',
      title: 'The session did not open',
      detail: message ?? 'Something went wrong reaching the server.',
      action: 'Close',
      onAction: () => Navigator.of(context).maybePop(),
    );
  }
}

class _Finished extends StatelessWidget {
  const _Finished({required this.state});

  final RecorderState state;

  @override
  Widget build(BuildContext context) {
    final marks = state.bookmarks.length;

    return _Notice(
      eyebrow: 'FILED',
      title: state.chunksDropped > 0
          ? 'Recorded, with gaps'
          : 'Recorded and filed',
      detail: [
        '${state.clock} of audio',
        if (marks > 0) '$marks ${marks == 1 ? 'mark' : 'marks'}',
        if (state.chunksDropped > 0)
          '${state.chunksDropped} pieces never reached the server, so the '
              'transcript will be short in places',
      ].join(' · '),
      action: 'Done',
      onAction: () => Navigator.of(context).maybePop(),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({
    required this.eyebrow,
    required this.title,
    required this.detail,
    required this.action,
    required this.onAction,
  });

  final String eyebrow;
  final String title;
  final String detail;
  final String action;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 24, 24, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(eyebrow, style: AppTheme.eyebrow(colour: _Dark.faint)),
          const SizedBox(height: 14),
          Text(
            title,
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(color: Colors.white),
          ),
          const SizedBox(height: 10),
          Text(
            detail,
            style: Theme.of(
              context,
            ).textTheme.bodyMedium?.copyWith(color: _Dark.soft, height: 1.5),
          ),
          const SizedBox(height: 28),
          FilledButton(
            onPressed: onAction,
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.lime,
              foregroundColor: AppColors.navyDeep,
            ),
            child: Text(action),
          ),
        ],
      ),
    );
  }
}
