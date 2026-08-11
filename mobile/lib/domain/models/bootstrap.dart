import 'organization.dart';
import 'user.dart';

/// Everything the app needs to draw its first screen.
class BootstrapData {
  const BootstrapData({
    required this.user,
    required this.organization,
    required this.capabilities,
    required this.unread,
    this.brandColor,
    this.planSlug,
  });

  factory BootstrapData.fromJson(Map<String, dynamic> json) {
    final organization = json['organization'] as Map<String, dynamic>? ?? {};
    final subscription = json['subscription'] as Map<String, dynamic>? ?? {};

    return BootstrapData(
      user: AppUser.fromJson(json['user'] as Map<String, dynamic>),
      organization: Organization.fromJson(organization),
      brandColor: organization['brand_color'] as String?,
      planSlug: subscription['plan'] as String?,
      capabilities: Capabilities.fromJson(
        json['capabilities'] as Map<String, dynamic>? ?? const {},
      ),
      unread: UnreadCounts.fromJson(
        json['unread'] as Map<String, dynamic>? ?? const {},
      ),
    );
  }

  final AppUser user;
  final Organization organization;
  final String? brandColor;
  final String? planSlug;
  final Capabilities capabilities;
  final UnreadCounts unread;
}

/// Server-side feature flags.
///
/// Read from here rather than inferred from the plan, so a feature can be
/// turned off for an organisation without shipping a new build to the stores.
class Capabilities {
  const Capabilities({
    this.transcription = false,
    this.aiSummaries = false,
    this.export = false,
    this.liveExtraction = false,
    this.aiEnabled = false,
    this.voting = false,
    this.annotations = false,
  });

  factory Capabilities.fromJson(Map<String, dynamic> json) => Capabilities(
    transcription: json['transcription'] as bool? ?? false,
    aiSummaries: json['ai_summaries'] as bool? ?? false,
    export: json['export'] as bool? ?? false,
    liveExtraction: json['live_extraction'] as bool? ?? false,
    aiEnabled: json['ai_enabled'] as bool? ?? false,
    voting: json['voting'] as bool? ?? false,
    annotations: json['annotations'] as bool? ?? false,
  );

  final bool transcription;
  final bool aiSummaries;
  final bool export;
  final bool liveExtraction;
  final bool aiEnabled;
  final bool voting;
  final bool annotations;
}

class UnreadCounts {
  const UnreadCounts({
    this.notifications = 0,
    this.actionItemsDue = 0,
    this.pendingApprovals = 0,
  });

  factory UnreadCounts.fromJson(Map<String, dynamic> json) => UnreadCounts(
    notifications: json['notifications'] as int? ?? 0,
    actionItemsDue: json['action_items_due'] as int? ?? 0,
    pendingApprovals: json['pending_approvals'] as int? ?? 0,
  );

  final int notifications;
  final int actionItemsDue;
  final int pendingApprovals;

  int get total => notifications + actionItemsDue + pendingApprovals;
}
