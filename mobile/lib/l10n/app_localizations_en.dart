// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class LEn extends L {
  LEn([String locale = 'en']) : super(locale);

  @override
  String get notifications => 'Notifications';

  @override
  String get back => 'Back';

  @override
  String get goodMorning => 'Good morning';

  @override
  String get goodAfternoon => 'Good afternoon';

  @override
  String get goodEvening => 'Good evening';

  @override
  String get homeDateLine => 'EEEE d MMMM · HH:mm';

  @override
  String get standingClearPrefix => 'You have ';

  @override
  String get standingClearMarked => 'nothing due';

  @override
  String get standingClearSuffix => '.';

  @override
  String get standingDuePrefix => 'You have ';

  @override
  String get standingDueMarkedTail => ' due today';

  @override
  String get standingDueSuffix => '.';

  @override
  String get standingClearDetail =>
      'Nothing is overdue and nothing is waiting on your approval.';

  @override
  String get standingDueDetail =>
      'Everything else can wait until these are cleared.';

  @override
  String get upNext => 'Up next';

  @override
  String get nothingScheduled => 'Nothing scheduled';

  @override
  String get nothingScheduledDetail =>
      'Meetings you are invited to appear here.';

  @override
  String get diaryUnavailable => 'Could not load the diary';

  @override
  String get pullToRetry => 'Pull down to try again.';

  @override
  String get waitingOnYou => 'Waiting on you';

  @override
  String get nothingWaiting => 'Nothing waiting';

  @override
  String get nothingWaitingDetail => 'Approvals and overdue items land here.';

  @override
  String get actionItems => 'Action items';

  @override
  String get actionItemsDetail => 'Due today or earlier';

  @override
  String get minutesToApprove => 'Minutes to approve';

  @override
  String get minutesToApproveDetail => 'Circulated to you, not yet answered';

  @override
  String get gutterToday => 'today';

  @override
  String get gutterUndated => 'undated';

  @override
  String get gutterNil => 'nil';

  @override
  String get gutterNow => 'now';

  @override
  String get gutterDue => 'due';

  @override
  String get gutterOpen => 'open';
}
