import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';
import '../../l10n/app_localizations.dart';

/// Where a sitting is in its life.
///
/// The order matters — it is the order the record moves through, and the
/// detail screen shows the next step rather than a menu of every state.
enum MeetingStatus {
  draft('draft'),
  inProgress('in_progress'),
  finalized('finalized'),
  pendingConfirmation('pending_confirmation'),
  approved('approved');

  const MeetingStatus(this.wire);

  /// What the server calls it. Never shown, never translated.
  final String wire;

  /// A method rather than a field: the label is prose, and prose needs a
  /// locale. The wire value stays const because it is protocol, not language.
  String label(BuildContext context) => switch (this) {
    MeetingStatus.draft => L.of(context).statusDraft,
    MeetingStatus.inProgress => L.of(context).statusInProgress,
    MeetingStatus.finalized => L.of(context).statusFinalized,
    MeetingStatus.pendingConfirmation =>
      L.of(context).statusPendingConfirmation,
    MeetingStatus.approved => L.of(context).statusApproved,
  };

  static MeetingStatus fromWire(String? value) => MeetingStatus.values
      .firstWhere((s) => s.wire == value, orElse: () => MeetingStatus.draft);

  /// Colour is spent only on the states that need an answer. Draft and
  /// finalised are what most records are and carry none.
  Color? get severity => switch (this) {
    MeetingStatus.approved => AppColors.primaryInk,
    MeetingStatus.pendingConfirmation => AppColors.warning,
    MeetingStatus.inProgress => AppColors.danger,
    _ => null,
  };

  bool get isClosed => this == MeetingStatus.approved;
}

class MeetingDetail {
  const MeetingDetail({
    required this.id,
    required this.title,
    required this.status,
    required this.permissions,
    required this.attendees,
    required this.actionItems,
    required this.resolutions,
    this.momNumber,
    this.summary,
    this.content,
    this.date,
    this.location,
    this.durationMinutes,
    this.createdBy,
    this.documentCount = 0,
    this.hasTranscript = false,
  });

  factory MeetingDetail.fromJson(Map<String, dynamic> json) {
    List<Map<String, dynamic>> rows(String key) =>
        (json[key] as List?)?.cast<Map<String, dynamic>>() ?? const [];

    return MeetingDetail(
      id: json['id'] as int,
      title: json['title'] as String? ?? 'Untitled',
      status: MeetingStatus.fromWire(json['status'] as String?),
      permissions: MeetingPermissions.fromJson(
        json['permissions'] as Map<String, dynamic>? ?? const {},
      ),
      momNumber: json['mom_number'] as String?,
      summary: json['summary'] as String?,
      content: json['content'] as String?,
      date: DateTime.tryParse(json['meeting_date'] as String? ?? ''),
      location: json['location'] as String?,
      durationMinutes: (json['duration_minutes'] as num?)?.toInt(),
      createdBy:
          (json['created_by'] as Map<String, dynamic>?)?['name'] as String?,
      attendees: rows('attendees').map(Attendee.fromJson).toList(),
      actionItems: rows('action_items').map(MeetingAction.fromJson).toList(),
      resolutions: rows('resolutions').map(Resolution.fromJson).toList(),
      documentCount: rows('documents').length,
      hasTranscript: rows('transcriptions').isNotEmpty,
    );
  }

  final int id;
  final String title;
  final MeetingStatus status;
  final MeetingPermissions permissions;
  final String? momNumber;
  final String? summary;
  final String? content;
  final DateTime? date;
  final String? location;
  final int? durationMinutes;
  final String? createdBy;
  final List<Attendee> attendees;
  final List<MeetingAction> actionItems;
  final List<Resolution> resolutions;
  final int documentCount;
  final bool hasTranscript;

  int get presentCount => attendees.where((a) => a.isPresent).length;

  /// The one action the record is waiting for, if any.
  ///
  /// Showing both Finalise and Approve at once asks somebody to know the
  /// workflow. A record only ever has one next step.
  MeetingStep? get nextStep {
    if (status == MeetingStatus.approved) return null;

    if (status == MeetingStatus.draft && permissions.canFinalize) {
      return MeetingStep.finalize;
    }

    if (status != MeetingStatus.draft && permissions.canApprove) {
      return MeetingStep.approve;
    }

    return null;
  }
}

/// The one action a record is waiting for.
///
/// An enum rather than a bag of strings: `path` is the endpoint and belongs to
/// the protocol, while the label, the explanation and the button are prose and
/// belong to whoever is reading the screen.
enum MeetingStep {
  finalize('finalize'),
  approve('approve');

  const MeetingStep(this.path);

  final String path;

  String label(BuildContext context) => switch (this) {
    MeetingStep.finalize => L.of(context).stepFinalizeLabel,
    MeetingStep.approve => L.of(context).stepApproveLabel,
  };

  String detail(BuildContext context) => switch (this) {
    MeetingStep.finalize => L.of(context).stepFinalizeDetail,
    MeetingStep.approve => L.of(context).stepApproveDetail,
  };

  String confirm(BuildContext context) => switch (this) {
    MeetingStep.finalize => L.of(context).stepFinalizeConfirm,
    MeetingStep.approve => L.of(context).stepApproveConfirm,
  };
}

class MeetingPermissions {
  const MeetingPermissions({
    this.canUpdate = false,
    this.canFinalize = false,
    this.canApprove = false,
    this.canStartLive = false,
  });

  factory MeetingPermissions.fromJson(Map<String, dynamic> json) =>
      MeetingPermissions(
        canUpdate: json['can_update'] as bool? ?? false,
        canFinalize: json['can_finalize'] as bool? ?? false,
        canApprove: json['can_approve'] as bool? ?? false,
        canStartLive: json['can_start_live'] as bool? ?? false,
      );

  final bool canUpdate;
  final bool canFinalize;
  final bool canApprove;
  final bool canStartLive;
}

class Attendee {
  const Attendee({
    required this.name,
    required this.isPresent,
    this.role,
    this.position,
  });

  factory Attendee.fromJson(Map<String, dynamic> json) => Attendee(
    name: json['name'] as String? ?? 'Unnamed',
    isPresent: json['is_present'] as bool? ?? false,
    role: json['role'] as String?,
    position: json['position'] as String?,
  );

  final String name;
  final bool isPresent;
  final String? role;
  final String? position;
}

class MeetingAction {
  const MeetingAction({
    required this.id,
    required this.title,
    required this.status,
    required this.isOverdue,
    this.assignee,
    this.dueDate,
  });

  factory MeetingAction.fromJson(Map<String, dynamic> json) => MeetingAction(
    id: json['id'] as int,
    title: json['title'] as String? ?? 'Untitled',
    status: json['status'] as String? ?? 'open',
    isOverdue: json['is_overdue'] as bool? ?? false,
    assignee:
        (json['assigned_to'] as Map<String, dynamic>?)?['name'] as String?,
    dueDate: DateTime.tryParse(json['due_date'] as String? ?? ''),
  );

  final int id;
  final String title;
  final String status;
  final bool isOverdue;
  final String? assignee;
  final DateTime? dueDate;

  bool get isDone => status == 'completed' || status == 'cancelled';
}

class Resolution {
  const Resolution({
    required this.id,
    required this.title,
    required this.status,
    this.number,
    this.mover,
    this.seconder,
    this.forVotes = 0,
    this.againstVotes = 0,
    this.abstainVotes = 0,
  });

  factory Resolution.fromJson(Map<String, dynamic> json) {
    final tally = json['tally'] as Map<String, dynamic>? ?? const {};

    int count(String key) => (tally[key] as num?)?.toInt() ?? 0;

    return Resolution(
      id: json['id'] as int,
      title: json['title'] as String? ?? 'Untitled',
      status: json['status'] as String? ?? 'proposed',
      number: json['resolution_number'] as String?,
      mover: (json['mover'] as Map<String, dynamic>?)?['name'] as String?,
      seconder: (json['seconder'] as Map<String, dynamic>?)?['name'] as String?,
      forVotes: count('for'),
      againstVotes: count('against'),
      abstainVotes: count('abstain'),
    );
  }

  final int id;
  final String title;
  final String status;
  final String? number;
  final String? mover;
  final String? seconder;
  final int forVotes;
  final int againstVotes;
  final int abstainVotes;

  bool get wasVoted => forVotes + againstVotes + abstainVotes > 0;

  /// "12–1–2", the way a tally is written in a minute book.
  String get tally => '$forVotes–$againstVotes–$abstainVotes';

  bool get carried => status == 'passed';
  bool get open => status == 'proposed';
}
