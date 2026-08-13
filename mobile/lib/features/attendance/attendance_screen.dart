import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/error/api_exception.dart';
import '../../core/haptics.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../domain/models/attendance.dart';
import '../meetings/meeting_detail_controller.dart';
import '../widgets/error_view.dart';
import '../widgets/ledger_scaffold.dart';
import '../widgets/rolling_count.dart';
import 'attendance_controller.dart';
import '../../l10n/app_localizations.dart';

/// The attendance desk.
///
/// The host opens a link, shares it to whatever screen the room can see, and
/// then watches names arrive. The phone is not the display — the lobby is —
/// but the host should not have to turn around to know it is working, so the
/// same names land here as they land there.
class AttendanceScreen extends ConsumerWidget {
  const AttendanceScreen({
    super.key,
    required this.meetingId,
    required this.title,
  });

  final int meetingId;
  final String title;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final desk = ref.watch(attendanceProvider(meetingId));
    final loaded = desk.valueOrNull;

    return LedgerScaffold(
      title: L.of(context).attendance,
      meta: _meta(context, loaded),
      leading: IconButton(
        icon: const Icon(Icons.arrow_back_rounded),
        color: const Color(0xFFC3D0F0),
        tooltip: L.of(context).back,
        onPressed: () => Navigator.of(context).maybePop(),
      ),
      child: switch (desk) {
        AsyncError(:final error) when loaded == null => ErrorView(
          error: error,
          onRetry: () => ref.invalidate(attendanceProvider(meetingId)),
        ),
        AsyncLoading() when loaded == null => const _Waiting(),
        _ =>
          loaded!.isOpen
              ? _Open(desk: loaded, meetingId: meetingId, title: title)
              : _Closed(meetingId: meetingId),
      },
    );
  }

  String? _meta(BuildContext context, AttendanceDesk? desk) {
    if (desk == null) return null;

    final l = L.of(context);
    if (!desk.isOpen) return l.deskClosed;

    final code = desk.token!.joinCode;

    return code == null ? l.deskOpen('') : l.deskOpen(code);
  }
}

/// Before a link exists. One button, and an honest description of what it does.
class _Closed extends ConsumerStatefulWidget {
  const _Closed({required this.meetingId});

  final int meetingId;

  @override
  ConsumerState<_Closed> createState() => _ClosedState();
}

class _ClosedState extends ConsumerState<_Closed> {
  bool _opening = false;

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 40, 20, 20),
      children: [
        Text(L.of(context).noDeskOpen, style: AppTheme.eyebrow()),
        const SizedBox(height: 14),
        Text(
          L.of(context).nobodyCanSignIn,
          style: Theme.of(context).textTheme.headlineSmall,
        ),
        const SizedBox(height: 10),
        Text(
          L.of(context).deskExplain,
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 26),
        FilledButton(
          onPressed: _opening ? null : _open,
          child: _opening
              ? const SizedBox(
                  height: 18,
                  width: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    valueColor: AlwaysStoppedAnimation<Color>(AppColors.navy),
                  ),
                )
              : Text(L.of(context).openTheDesk),
        ),
      ],
    );
  }

  Future<void> _open() async {
    setState(() => _opening = true);
    Haptics.shift();

    try {
      await ref.read(attendanceDeskProvider).open(widget.meetingId);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _opening = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }
}

class _Open extends ConsumerWidget {
  const _Open({
    required this.desk,
    required this.meetingId,
    required this.title,
  });

  final AttendanceDesk desk;
  final int meetingId;
  final String title;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final token = desk.token!;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        _Tally(count: desk.registered.length, token: token),
        _Share(token: token, title: title),
        _Arrivals(registered: desk.registered),
        const SizedBox(height: 28),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 30),
          child: OutlinedButton(
            onPressed: () => _confirmClose(context, ref),
            style: OutlinedButton.styleFrom(
              foregroundColor: AppColors.danger,
              side: const BorderSide(color: AppColors.ruleStrong),
            ),
            child: Text(L.of(context).closeTheDesk),
          ),
        ),
      ],
    );
  }

  Future<void> _confirmClose(BuildContext context, WidgetRef ref) async {
    Haptics.tick();

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(L.of(context).closeTheDeskConfirm),
        content: Text(L.of(context).closeTheDeskDetail),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(L.of(context).keepItOpen),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: TextButton.styleFrom(foregroundColor: AppColors.danger),
            child: Text(L.of(context).close),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    Haptics.commit();

    try {
      await ref.read(attendanceDeskProvider).close(meetingId);
      // The headline count on the record changes the moment the desk shuts.
      ref.invalidate(meetingDetailProvider(meetingId));
    } on ApiException catch (e) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }
}

/// The count, at the size the count deserves.
class _Tally extends StatelessWidget {
  const _Tally({required this.count, required this.token});

  final int count;
  final AttendanceToken token;

  @override
  Widget build(BuildContext context) {
    final style = Theme.of(context).textTheme.displaySmall!;

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              RollingCount(value: count, style: style),
              const SizedBox(width: 10),
              Text(
                L.of(context).signedInCount,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
            ],
          ),
          if (token.expiresAt != null) ...[
            const SizedBox(height: 8),
            Text(
              L
                  .of(context)
                  .linkCloses(
                    DateFormat(
                      'EEEE, HH:mm',
                      Localizations.localeOf(context).toLanguageTag(),
                    ).format(token.expiresAt!.toLocal()),
                  ),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ],
      ),
    );
  }
}

/// The lobby link, and the two ways it leaves the phone.
class _Share extends StatelessWidget {
  const _Share({required this.token, required this.title});

  final AttendanceToken token;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 26, 20, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(L.of(context).theScreenToShare, style: AppTheme.eyebrow()),
          const SizedBox(height: 12),
          Container(
            decoration: BoxDecoration(
              border: Border.all(color: AppColors.rule),
              borderRadius: BorderRadius.circular(AppTheme.radiusM),
              color: AppColors.paperRaised,
            ),
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
            child: Text(
              token.lobbyUrl,
              style: AppTheme.mono(size: 11.5, colour: AppColors.inkSoft),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: FilledButton.icon(
                  onPressed: () => _share(context),
                  icon: const Icon(Icons.ios_share_rounded, size: 18),
                  label: Text(L.of(context).share),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _copy(context),
                  icon: const Icon(Icons.copy_rounded, size: 17),
                  label: Text(L.of(context).copy),
                ),
              ),
            ],
          ),
          if (token.joinCode != null) ...[
            const SizedBox(height: 18),
            Row(
              children: [
                SizedBox(
                  width: AppTheme.gutter,
                  child: Text(
                    L.of(context).gutterCode,
                    style: AppTheme.mono(size: 11, colour: AppColors.inkFaint),
                  ),
                ),
                Text(
                  token.joinCode!,
                  style: AppTheme.mono(
                    size: 19,
                    weight: FontWeight.w700,
                    colour: AppColors.primaryInk,
                    tracking: 3,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _share(BuildContext context) async {
    Haptics.tick();

    final box = context.findRenderObject() as RenderBox?;

    await SharePlus.instance.share(
      ShareParams(
        uri: Uri.parse(token.lobbyUrl),
        subject: L.of(context).signInTo(title),
        // iPad anchors the share sheet to whatever was tapped; without an
        // origin it throws rather than guessing.
        sharePositionOrigin: box == null
            ? null
            : box.localToGlobal(Offset.zero) & box.size,
      ),
    );
  }

  Future<void> _copy(BuildContext context) async {
    await Clipboard.setData(ClipboardData(text: token.lobbyUrl));

    if (!context.mounted) return;

    Haptics.tick();
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(L.of(context).linkCopied)));
  }
}

/// Names, newest first, each one stamping itself onto the page as it lands.
class _Arrivals extends StatefulWidget {
  const _Arrivals({required this.registered});

  final List<RegisteredAttendee> registered;

  @override
  State<_Arrivals> createState() => _ArrivalsState();
}

class _ArrivalsState extends State<_Arrivals> {
  /// Who was already on screen last build.
  ///
  /// Without this every name re-animates on every three-second poll, which
  /// turns a calm list into a flicker and makes a genuine arrival invisible.
  final _seen = <int>{};
  var _primed = false;

  @override
  void didUpdateWidget(_Arrivals old) {
    super.didUpdateWidget(old);

    final arrived = widget.registered.where((a) => !_seen.contains(a.id));

    // The first load is not an arrival — twelve people who signed in before
    // the screen opened should not each buzz the phone.
    if (_primed && arrived.isNotEmpty) Haptics.select();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.registered.isEmpty) {
      _primed = true;

      return Padding(
        padding: const EdgeInsets.fromLTRB(20, 34, 20, 0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(L.of(context).nobodyYet, style: AppTheme.eyebrow()),
            const SizedBox(height: 10),
            Text(
              L.of(context).nobodyYetDetail,
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      );
    }

    final rows = <Widget>[];

    for (final attendee in widget.registered) {
      final isNew = _primed && !_seen.contains(attendee.id);
      rows.add(_Arrival(attendee: attendee, animate: isNew));
      _seen.add(attendee.id);
    }

    _primed = true;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 32, 20, 10),
          child: Text(L.of(context).signedInSection, style: AppTheme.eyebrow()),
        ),
        ...rows,
      ],
    );
  }
}

class _Arrival extends StatelessWidget {
  const _Arrival({required this.attendee, required this.animate});

  final RegisteredAttendee attendee;
  final bool animate;

  @override
  Widget build(BuildContext context) {
    final row = Container(
      decoration: const BoxDecoration(
        border: Border(top: BorderSide(color: AppColors.rule)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 13, 20, 13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: AppTheme.gutter,
            child: Text(
              attendee.isExternal ? L.of(context).guest : L.of(context).member,
              style: AppTheme.mono(
                size: 10.5,
                colour: attendee.isExternal
                    ? AppColors.inkFaint
                    : AppColors.primaryInk,
              ),
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  attendee.name,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                if (attendee.detail != null) ...[
                  const SizedBox(height: 3),
                  Text(
                    attendee.detail!,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );

    if (!animate || MediaQuery.disableAnimationsOf(context)) return row;

    return _Stamped(child: row);
  }
}

/// The arrival itself: down a few pixels, a shade oversized, settling.
///
/// The same gesture the recorder uses when a bookmark lands — this app has one
/// way of saying *that just happened*, and it should not learn a second.
class _Stamped extends StatefulWidget {
  const _Stamped({required this.child});

  final Widget child;

  @override
  State<_Stamped> createState() => _StampedState();
}

class _StampedState extends State<_Stamped>
    with SingleTickerProviderStateMixin {
  late final _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 340),
  )..forward();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final eased = CurvedAnimation(parent: _controller, curve: AppTheme.easeOut);

    return AnimatedBuilder(
      animation: eased,
      builder: (context, child) {
        final t = eased.value;

        return Opacity(
          opacity: t,
          child: Transform.translate(
            offset: Offset(0, -7 * (1 - t)),
            child: Transform.scale(
              scale: 1 + 0.035 * (1 - t),
              alignment: Alignment.centerLeft,
              child: child,
            ),
          ),
        );
      },
      child: widget.child,
    );
  }
}

class _Waiting extends StatelessWidget {
  const _Waiting();

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 26, 20, 0),
      children: [Text(L.of(context).readingTheDesk, style: AppTheme.eyebrow())],
    );
  }
}
