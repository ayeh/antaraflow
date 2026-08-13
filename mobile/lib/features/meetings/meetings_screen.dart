import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/haptics.dart';
import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../domain/models/meeting_detail.dart';
import '../widgets/error_view.dart';
import '../widgets/gutter_row.dart';
import '../widgets/ledger_scaffold.dart';
import 'create_meeting_sheet.dart';
import 'meeting_detail_screen.dart';

/// The list of recorded meetings.
///
/// The sitting date lives in the gutter so the whole list reads down one
/// column, the way somebody flicks through a bound volume looking for a date.
/// Month breaks give the eye somewhere to rest and are the only element on the
/// screen that is not shaped like a row.
final meetingsProvider = FutureProvider<List<MeetingSummary>>((ref) async {
  final rows = await ref.watch(apiClientProvider).getList('/meetings');

  return rows
      .cast<Map<String, dynamic>>()
      .map(MeetingSummary.fromJson)
      .toList();
});

/// What the list is narrowed to. Held outside the screen so it survives a tab
/// change — somebody filtering to drafts and stepping away to a task should
/// come back to the list they left.
final meetingFilterProvider = StateProvider<MeetingFilter>(
  (ref) => const MeetingFilter(),
);

@immutable
class MeetingFilter {
  const MeetingFilter({this.query = '', this.status});

  final String query;
  final MeetingStatus? status;

  bool get isNarrowed => query.trim().isNotEmpty || status != null;

  MeetingFilter copyWith({
    String? query,
    MeetingStatus? status,
    bool clearStatus = false,
  }) => MeetingFilter(
    query: query ?? this.query,
    status: clearStatus ? null : (status ?? this.status),
  );

  /// Matched on the phone rather than the server.
  ///
  /// The API supports `q`, but a request per keystroke over a mobile
  /// connection is slower than filtering a list that is already in hand, and
  /// the list is one page. When it stops being one page, this moves to the
  /// server and gains a debounce.
  bool matches(MeetingSummary meeting) {
    if (status != null && meeting.status != status!.wire) return false;

    final term = query.trim().toLowerCase();
    if (term.isEmpty) return true;

    return meeting.title.toLowerCase().contains(term) ||
        (meeting.momNumber?.toLowerCase().contains(term) ?? false) ||
        (meeting.location?.toLowerCase().contains(term) ?? false);
  }
}

class MeetingSummary {
  const MeetingSummary({
    required this.id,
    required this.title,
    required this.status,
    this.momNumber,
    this.date,
    this.location,
    this.attendeeCount,
  });

  factory MeetingSummary.fromJson(Map<String, dynamic> json) => MeetingSummary(
    id: json['id'] as int,
    title: json['title'] as String? ?? 'Untitled',
    status: json['status'] as String? ?? 'draft',
    momNumber: json['mom_number'] as String?,
    date: DateTime.tryParse(json['meeting_date'] as String? ?? ''),
    location: json['location'] as String?,
    attendeeCount: json['attendee_count'] as int?,
  );

  final int id;
  final String title;
  final String status;
  final String? momNumber;
  final DateTime? date;
  final String? location;
  final int? attendeeCount;

  /// Only the exceptional states are tagged.
  ///
  /// Draft and finalised are what most rows are, and a bordered tag on every
  /// row is furniture that dilutes the two tags that carry meaning.
  bool get isExceptional =>
      status == 'approved' || status == 'pending_confirmation';

  Color? get severity => switch (status) {
    'approved' => AppColors.primaryInk,
    'pending_confirmation' => AppColors.warning,
    _ => null,
  };

  String? get statusLabel => isExceptional ? status.replaceAll('_', ' ') : null;

  /// The sequence number alone.
  ///
  /// Stored references look like MOM-2026-000003 and are far too wide for the
  /// gutter; they wrap to two lines and destroy the column the layout is built
  /// around.
  String get reference {
    final number = momNumber;
    if (number == null || number.isEmpty) return '$id';

    final tail = number.split(RegExp(r'[-/]')).last;
    final digits = int.tryParse(tail);

    return digits?.toString().padLeft(3, '0') ?? tail;
  }
}

class MeetingsScreen extends ConsumerStatefulWidget {
  const MeetingsScreen({super.key});

  @override
  ConsumerState<MeetingsScreen> createState() => _MeetingsScreenState();
}

class _MeetingsScreenState extends ConsumerState<MeetingsScreen> {
  bool _searching = false;

  @override
  Widget build(BuildContext context) {
    final meetings = ref.watch(meetingsProvider);
    final filter = ref.watch(meetingFilterProvider);
    final all = meetings.valueOrNull;
    final rows = all?.where(filter.matches).toList();

    return LedgerScaffold(
      title: 'Meetings',
      meta: _meta(all, rows, filter),
      actions: [
        MastheadAction(
          icon: _searching ? Icons.close_rounded : Icons.search_rounded,
          tooltip: _searching ? 'Close search' : 'Search',
          onPressed: _toggleSearch,
        ),
        // Last, so the solid one sits at the edge where the eye stops.
        MastheadAction(
          icon: Icons.add_rounded,
          tooltip: 'New meeting',
          filled: true,
          onPressed: () => showCreateMeeting(context, ref),
        ),
      ],
      onRefresh: () async => ref.refresh(meetingsProvider.future),
      child: Column(
        children: [
          if (_searching)
            _SearchBar(
              filter: filter,
              onChanged: (next) =>
                  ref.read(meetingFilterProvider.notifier).state = next,
            ),
          Expanded(
            child: meetings.when(
              loading: () => const _Loading(),
              error: (error, _) => ErrorView(
                error: error,
                onRetry: () => ref.invalidate(meetingsProvider),
              ),
              data: (_) => rows!.isEmpty
                  ? (filter.isNarrowed
                        ? _NoMatches(filter: filter, onClear: _clear)
                        : const _NoMeetings())
                  : _Ledger(rows: rows),
            ),
          ),
        ],
      ),
    );
  }

  String? _meta(
    List<MeetingSummary>? all,
    List<MeetingSummary>? rows,
    MeetingFilter filter,
  ) {
    if (all == null || rows == null) return null;

    if (filter.isNarrowed) {
      return '${rows.length} OF ${all.length}';
    }

    return all.length == 1 ? '1 MEETING' : '${all.length} MEETINGS';
  }

  void _toggleSearch() {
    Haptics.select();
    setState(() => _searching = !_searching);

    if (!_searching) _clear();
  }

  void _clear() {
    ref.read(meetingFilterProvider.notifier).state = const MeetingFilter();
  }
}

/// Sits under the masthead rather than floating over the list, so the ruled
/// column below it is never partly covered.
class _SearchBar extends StatefulWidget {
  const _SearchBar({required this.filter, required this.onChanged});

  final MeetingFilter filter;
  final ValueChanged<MeetingFilter> onChanged;

  @override
  State<_SearchBar> createState() => _SearchBarState();
}

class _SearchBarState extends State<_SearchBar> {
  late final _controller = TextEditingController(text: widget.filter.query);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.paperRaised,
        border: Border(bottom: BorderSide(color: AppColors.ruleStrong)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 14, 20, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            controller: _controller,
            autofocus: true,
            textInputAction: TextInputAction.search,
            style: Theme.of(context).textTheme.bodyLarge,
            decoration: const InputDecoration(
              isDense: true,
              hintText: 'Title, number or place',
              prefixIcon: Icon(Icons.search_rounded, size: 20),
            ),
            onChanged: (value) =>
                widget.onChanged(widget.filter.copyWith(query: value)),
          ),
          const SizedBox(height: 12),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                _Chip(
                  label: 'All',
                  selected: widget.filter.status == null,
                  onTap: () => widget.onChanged(
                    widget.filter.copyWith(clearStatus: true),
                  ),
                ),
                for (final status in MeetingStatus.values)
                  _Chip(
                    label: status.label,
                    selected: widget.filter.status == status,
                    onTap: () => widget.onChanged(
                      widget.filter.copyWith(status: status),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: GestureDetector(
        onTap: () {
          Haptics.select();
          onTap();
        },
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          curve: AppTheme.easeOut,
          padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
          decoration: BoxDecoration(
            // Square, like the tags. A pill here would be the only rounded
            // thing on the screen.
            borderRadius: BorderRadius.circular(AppTheme.radiusS),
            color: selected ? AppColors.primarySoft : Colors.transparent,
            border: Border.all(
              color: selected ? AppColors.primaryInk : AppColors.ruleStrong,
            ),
          ),
          child: Text(
            label.toUpperCase(),
            style: AppTheme.eyebrow(
              colour: selected ? AppColors.primaryInk : AppColors.inkSoft,
            ),
          ),
        ),
      ),
    );
  }
}

/// The ruled column, with month breaks that stay put while their rows scroll.
class _Ledger extends StatelessWidget {
  const _Ledger({required this.rows});

  final List<MeetingSummary> rows;

  @override
  Widget build(BuildContext context) {
    final months = <String, List<MeetingSummary>>{};

    for (final meeting in rows) {
      final month = meeting.date == null
          ? 'Undated'
          : DateFormat('MMMM yyyy').format(meeting.date!);

      months.putIfAbsent(month, () => []).add(meeting);
    }

    return CustomScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      slivers: [
        for (final entry in months.entries)
          SliverMainAxisGroup(
            slivers: [
              // Pinned: somebody scrolling a year of minutes is looking for a
              // date, and losing which month they are in is losing their place.
              SliverPersistentHeader(
                pinned: true,
                delegate: _MonthHeader(label: entry.key),
              ),
              SliverList.builder(
                itemCount: entry.value.length,
                itemBuilder: (context, index) =>
                    _MeetingRow(meeting: entry.value[index]),
              ),
            ],
          ),
        const SliverToBoxAdapter(child: SizedBox(height: 110)),
      ],
    );
  }
}

class _MeetingRow extends StatelessWidget {
  const _MeetingRow({required this.meeting});

  final MeetingSummary meeting;

  @override
  Widget build(BuildContext context) {
    final gutter = meeting.date == null
        ? 'nil'
        : DateFormat('d MMM').format(meeting.date!);

    return GutterRow(
      gutter: gutter,
      gutterCaption: meeting.reference,
      title: meeting.title,
      subtitle: _detail(meeting),
      status: meeting.statusLabel,
      severity: meeting.severity,
      heroTag: 'meeting-${meeting.id}-$gutter',
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => MeetingDetailScreen(
            id: meeting.id,
            title: meeting.title,
            reference: gutter,
          ),
        ),
      ),
    );
  }

  String? _detail(MeetingSummary meeting) {
    final parts = <String>[
      if (meeting.attendeeCount != null) '${meeting.attendeeCount} present',
      // Only the first line of an address. A wrapped second line pushes every
      // row past the fold.
      if (meeting.location != null && meeting.location!.isNotEmpty)
        meeting.location!.split(RegExp(r'[,\n]')).first.trim(),
    ];

    return parts.isEmpty ? null : parts.join(' · ');
  }
}

/// The one element on the screen that is not a row.
///
/// It carries no gutter, so the column visibly breaks and restarts, which is
/// what makes a long list feel navigable rather than endless.
class _MonthHeader extends SliverPersistentHeaderDelegate {
  const _MonthHeader({required this.label});

  final String label;

  @override
  double get minExtent => 37;

  @override
  double get maxExtent => 37;

  @override
  Widget build(BuildContext context, double shrinkOffset, bool overlaps) {
    return Container(
      width: double.infinity,
      // Opaque, not transparent: it is pinned, and the rows scroll under it.
      decoration: const BoxDecoration(
        color: AppColors.paper,
        border: Border(bottom: BorderSide(color: AppColors.ruleStrong)),
      ),
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 9),
      child: Text(label.toUpperCase(), style: AppTheme.eyebrow()),
    );
  }

  @override
  bool shouldRebuild(_MonthHeader old) => old.label != label;
}

class _Loading extends StatelessWidget {
  const _Loading();

  @override
  Widget build(BuildContext context) {
    // Varying widths so the placeholder reads as text rather than as a bar
    // chart. Six is roughly one screen.
    const widths = [0.72, 0.54, 0.83, 0.61, 0.77, 0.48];

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        Container(
          width: double.infinity,
          decoration: const BoxDecoration(
            border: Border(bottom: BorderSide(color: AppColors.ruleStrong)),
          ),
          padding: const EdgeInsets.fromLTRB(20, 18, 20, 9),
          child: Text('LOADING', style: AppTheme.eyebrow()),
        ),
        for (final width in widths) GutterRowSkeleton(titleFraction: width),
      ],
    );
  }
}

class _NoMatches extends StatelessWidget {
  const _NoMatches({required this.filter, required this.onClear});

  final MeetingFilter filter;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final term = filter.query.trim();

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        const SizedBox(height: 54),
        Center(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 40),
            child: Column(
              children: [
                Text('NOTHING MATCHES', style: AppTheme.eyebrow()),
                const SizedBox(height: 14),
                Text(
                  term.isEmpty ? 'No meetings in that state' : 'No “$term”',
                  style: Theme.of(context).textTheme.headlineSmall,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Text(
                  'Search covers the title, the reference number and the place.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 20),
                OutlinedButton(
                  onPressed: onClear,
                  child: const Text('Clear the filter'),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

/// An empty ledger has to offer the pen.
///
/// This screen used to describe what would eventually appear here and leave it
/// at that, which put the only way forward behind an icon in the masthead.
class _NoMeetings extends ConsumerWidget {
  const _NoMeetings();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        const SizedBox(height: 64),
        Center(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 40),
            child: Column(
              children: [
                Text('NOTHING RECORDED', style: AppTheme.eyebrow()),
                const SizedBox(height: 14),
                Text(
                  'No meetings yet',
                  style: Theme.of(context).textTheme.headlineSmall,
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 8),
                Text(
                  'File a sitting ahead of time, or record one and its minutes '
                  'will be filed here.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: () => showCreateMeeting(context, ref),
                  child: const Text('New meeting'),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
