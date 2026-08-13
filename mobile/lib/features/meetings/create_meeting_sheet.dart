import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:uuid/uuid.dart';

import '../../core/error/api_exception.dart';
import '../../core/haptics.dart';
import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import 'meeting_detail_screen.dart';
import 'meetings_screen.dart';
import '../../l10n/app_localizations.dart';

enum _MeetingType {
  general('general'),
  boardMeeting('board_meeting'),
  standUp('standup'),
  clientCall('client_call'),
  oneOnOne('one_on_one'),
  workshop('workshop'),
  retrospective('retrospective');

  const _MeetingType(this.wire);

  /// What the API expects. Protocol, so never translated.
  final String wire;

  String label(BuildContext context) => switch (this) {
    _MeetingType.general => L.of(context).typeGeneral,
    _MeetingType.boardMeeting => L.of(context).typeBoard,
    _MeetingType.standUp => L.of(context).typeStandUp,
    _MeetingType.clientCall => L.of(context).typeClientCall,
    _MeetingType.oneOnOne => L.of(context).typeOneOnOne,
    _MeetingType.workshop => L.of(context).typeWorkshop,
    _MeetingType.retrospective => L.of(context).typeRetrospective,
  };
}

/// Opens the create-meeting sheet and, on success, pushes to the detail screen.
Future<void> showCreateMeeting(BuildContext context, WidgetRef ref) async {
  Haptics.tick();

  final result = await showModalBottomSheet<({int id, String title})>(
    context: context,
    backgroundColor: AppColors.paperRaised,
    isScrollControlled: true,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(
        top: Radius.circular(AppTheme.radiusM),
      ),
    ),
    builder: (_) => const _Sheet(),
  );

  if (result == null || !context.mounted) return;

  ref.invalidate(meetingsProvider);

  await Navigator.of(context).push(
    MaterialPageRoute<void>(
      builder: (_) => MeetingDetailScreen(id: result.id, title: result.title),
    ),
  );

  if (context.mounted) ref.invalidate(meetingsProvider);
}

class _Sheet extends ConsumerStatefulWidget {
  const _Sheet();

  @override
  ConsumerState<_Sheet> createState() => _SheetState();
}

class _SheetState extends ConsumerState<_Sheet> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _locationController = TextEditingController();

  _MeetingType? _type;
  DateTime? _date;
  bool _loading = false;

  @override
  void dispose() {
    _titleController.dispose();
    _locationController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final insets = MediaQuery.viewInsetsOf(context);

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(bottom: insets.bottom),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
                child: Text(
                  L.of(context).newMeetingEyebrow,
                  style: AppTheme.eyebrow(),
                ),
              ),
              const SizedBox(height: 14),
              Container(
                decoration: const BoxDecoration(
                  border: Border(
                    top: BorderSide(color: AppColors.rule),
                    bottom: BorderSide(color: AppColors.rule),
                  ),
                ),
                padding: const EdgeInsets.fromLTRB(20, 15, 20, 15),
                child: TextFormField(
                  controller: _titleController,
                  autofocus: true,
                  textCapitalization: TextCapitalization.sentences,
                  textInputAction: TextInputAction.done,
                  style: Theme.of(context).textTheme.titleMedium,
                  decoration: InputDecoration(
                    hintText: L.of(context).meetingTitle,
                    isDense: true,
                    filled: false,
                    border: InputBorder.none,
                    enabledBorder: InputBorder.none,
                    focusedBorder: InputBorder.none,
                    contentPadding: EdgeInsets.zero,
                  ),
                  validator: (v) => v == null || v.trim().isEmpty
                      ? L.of(context).titleRequired
                      : null,
                ),
              ),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 4),
                child: Row(
                  children: [
                    for (final type in _MeetingType.values)
                      Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: _TypeChip(
                          label: type.label(context),
                          selected: _type == type,
                          onTap: () => setState(() {
                            _type = _type == type ? null : type;
                            Haptics.select();
                          }),
                        ),
                      ),
                  ],
                ),
              ),
              // Date row — follows the gutter ledger pattern
              InkWell(
                onTap: _pickDate,
                child: Container(
                  decoration: const BoxDecoration(
                    border: Border(top: BorderSide(color: AppColors.rule)),
                  ),
                  padding: const EdgeInsets.fromLTRB(20, 15, 20, 15),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        width: AppTheme.gutter,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _date == null
                                  ? L.of(context).gutterDate
                                  : DateFormat('d MMM').format(_date!),
                              style: AppTheme.mono(
                                size: 12,
                                weight: FontWeight.w700,
                                colour: _date == null
                                    ? AppColors.inkFaint
                                    : AppColors.primaryInk,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 14),
                      Text(
                        _date == null
                            ? L.of(context).noDateSet
                            : DateFormat('EEEE, d MMMM yyyy').format(_date!),
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: _date == null
                              ? AppColors.inkFaint
                              : AppColors.ink,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              // Location row
              Container(
                decoration: const BoxDecoration(
                  border: Border(
                    top: BorderSide(color: AppColors.rule),
                    bottom: BorderSide(color: AppColors.rule),
                  ),
                ),
                padding: const EdgeInsets.fromLTRB(20, 15, 20, 15),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    SizedBox(
                      width: AppTheme.gutter,
                      child: Text(
                        L.of(context).gutterPlace,
                        style: AppTheme.mono(
                          size: 12,
                          colour: AppColors.inkFaint,
                        ),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: TextField(
                        controller: _locationController,
                        textCapitalization: TextCapitalization.words,
                        textInputAction: TextInputAction.done,
                        style: Theme.of(context).textTheme.bodyMedium,
                        decoration: InputDecoration(
                          hintText: L.of(context).locationOptional,
                          isDense: true,
                          filled: false,
                          border: InputBorder.none,
                          enabledBorder: InputBorder.none,
                          focusedBorder: InputBorder.none,
                          contentPadding: EdgeInsets.zero,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 0, 20, 4),
                child: FilledButton(
                  onPressed: _loading ? null : _submit,
                  child: _loading
                      ? const SizedBox(
                          height: 18,
                          width: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation<Color>(
                              AppColors.navy,
                            ),
                          ),
                        )
                      : Text(L.of(context).createMeeting),
                ),
              ),
              const SizedBox(height: 8),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _pickDate() async {
    Haptics.select();

    final picked = await showDatePicker(
      context: context,
      initialDate: _date ?? DateTime.now(),
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 365 * 2)),
    );

    if (picked != null) setState(() => _date = picked);
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() => _loading = true);
    Haptics.tick();

    try {
      final clientId = const Uuid().v4();

      final body = await ref
          .read(apiClientProvider)
          .post(
            '/meetings',
            body: {
              'title': _titleController.text.trim(),
              if (_type != null) 'meeting_type': _type!.wire,
              if (_date != null) 'meeting_date': _date!.toIso8601String(),
              if (_locationController.text.trim().isNotEmpty)
                'location': _locationController.text.trim(),
              'client_id': clientId,
            },
            idempotencyKey: 'meeting-$clientId',
          );

      if (!mounted) return;

      final data = body['data'] is Map ? body['data'] as Map : body;

      Navigator.of(context).pop((
        id: data['id'] as int,
        title: data['title'] as String? ?? _titleController.text.trim(),
      ));
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _loading = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }
}

class _TypeChip extends StatelessWidget {
  const _TypeChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        curve: AppTheme.easeOut,
        padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
        decoration: BoxDecoration(
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
    );
  }
}
