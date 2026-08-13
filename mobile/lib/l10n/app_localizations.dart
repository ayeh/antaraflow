import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_en.dart';
import 'app_localizations_ms.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of L
/// returned by `L.of(context)`.
///
/// Applications need to include `L.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: L.localizationsDelegates,
///   supportedLocales: L.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the L.supportedLocales
/// property.
abstract class L {
  L(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static L of(BuildContext context) {
    return Localizations.of<L>(context, L)!;
  }

  static const LocalizationsDelegate<L> delegate = _LDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('en'),
    Locale('ms'),
  ];

  /// No description provided for @notifications.
  ///
  /// In en, this message translates to:
  /// **'Notifications'**
  String get notifications;

  /// No description provided for @back.
  ///
  /// In en, this message translates to:
  /// **'Back'**
  String get back;

  /// No description provided for @goodMorning.
  ///
  /// In en, this message translates to:
  /// **'Good morning'**
  String get goodMorning;

  /// No description provided for @goodAfternoon.
  ///
  /// In en, this message translates to:
  /// **'Good afternoon'**
  String get goodAfternoon;

  /// No description provided for @goodEvening.
  ///
  /// In en, this message translates to:
  /// **'Good evening'**
  String get goodEvening;

  /// A DateFormat skeleton, not prose. Translators may reorder the parts — Malay writes the day before the date too — but every token must stay a valid ICU pattern.
  ///
  /// In en, this message translates to:
  /// **'EEEE d MMMM · HH:mm'**
  String get homeDateLine;

  /// The sentence on Home when nothing is owed, split so the highlighter can sit on the middle fragment. Languages that put the time expression last should carry it in the suffix.
  ///
  /// In en, this message translates to:
  /// **'You have '**
  String get standingClearPrefix;

  /// No description provided for @standingClearMarked.
  ///
  /// In en, this message translates to:
  /// **'nothing due'**
  String get standingClearMarked;

  /// No description provided for @standingClearSuffix.
  ///
  /// In en, this message translates to:
  /// **'.'**
  String get standingClearSuffix;

  /// No description provided for @standingDuePrefix.
  ///
  /// In en, this message translates to:
  /// **'You have '**
  String get standingDuePrefix;

  /// Follows the rolling number, inside the highlighter. English reads 'You have [3 due today].'
  ///
  /// In en, this message translates to:
  /// **' due today'**
  String get standingDueMarkedTail;

  /// No description provided for @standingDueSuffix.
  ///
  /// In en, this message translates to:
  /// **'.'**
  String get standingDueSuffix;

  /// No description provided for @standingClearDetail.
  ///
  /// In en, this message translates to:
  /// **'Nothing is overdue and nothing is waiting on your approval.'**
  String get standingClearDetail;

  /// No description provided for @standingDueDetail.
  ///
  /// In en, this message translates to:
  /// **'Everything else can wait until these are cleared.'**
  String get standingDueDetail;

  /// No description provided for @upNext.
  ///
  /// In en, this message translates to:
  /// **'Up next'**
  String get upNext;

  /// No description provided for @nothingScheduled.
  ///
  /// In en, this message translates to:
  /// **'Nothing scheduled'**
  String get nothingScheduled;

  /// No description provided for @nothingScheduledDetail.
  ///
  /// In en, this message translates to:
  /// **'Meetings you are invited to appear here.'**
  String get nothingScheduledDetail;

  /// No description provided for @diaryUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Could not load the diary'**
  String get diaryUnavailable;

  /// No description provided for @pullToRetry.
  ///
  /// In en, this message translates to:
  /// **'Pull down to try again.'**
  String get pullToRetry;

  /// No description provided for @waitingOnYou.
  ///
  /// In en, this message translates to:
  /// **'Waiting on you'**
  String get waitingOnYou;

  /// No description provided for @nothingWaiting.
  ///
  /// In en, this message translates to:
  /// **'Nothing waiting'**
  String get nothingWaiting;

  /// No description provided for @nothingWaitingDetail.
  ///
  /// In en, this message translates to:
  /// **'Approvals and overdue items land here.'**
  String get nothingWaitingDetail;

  /// No description provided for @actionItems.
  ///
  /// In en, this message translates to:
  /// **'Action items'**
  String get actionItems;

  /// No description provided for @actionItemsDetail.
  ///
  /// In en, this message translates to:
  /// **'Due today or earlier'**
  String get actionItemsDetail;

  /// No description provided for @minutesToApprove.
  ///
  /// In en, this message translates to:
  /// **'Minutes to approve'**
  String get minutesToApprove;

  /// No description provided for @minutesToApproveDetail.
  ///
  /// In en, this message translates to:
  /// **'Circulated to you, not yet answered'**
  String get minutesToApproveDetail;

  /// Gutter captions are lowercase and must stay short — the column is 62pt and a wrapped caption breaks the alignment the whole list is built on.
  ///
  /// In en, this message translates to:
  /// **'today'**
  String get gutterToday;

  /// No description provided for @gutterUndated.
  ///
  /// In en, this message translates to:
  /// **'undated'**
  String get gutterUndated;

  /// No description provided for @gutterNil.
  ///
  /// In en, this message translates to:
  /// **'nil'**
  String get gutterNil;

  /// No description provided for @gutterNow.
  ///
  /// In en, this message translates to:
  /// **'now'**
  String get gutterNow;

  /// No description provided for @gutterDue.
  ///
  /// In en, this message translates to:
  /// **'due'**
  String get gutterDue;

  /// No description provided for @gutterOpen.
  ///
  /// In en, this message translates to:
  /// **'open'**
  String get gutterOpen;

  /// No description provided for @appTagline.
  ///
  /// In en, this message translates to:
  /// **'Minutes,\nsigned and settled.'**
  String get appTagline;

  /// No description provided for @signIn.
  ///
  /// In en, this message translates to:
  /// **'SIGN IN'**
  String get signIn;

  /// No description provided for @email.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get email;

  /// No description provided for @emailHint.
  ///
  /// In en, this message translates to:
  /// **'Enter your email address'**
  String get emailHint;

  /// No description provided for @password.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get password;

  /// No description provided for @passwordHint.
  ///
  /// In en, this message translates to:
  /// **'Enter your password'**
  String get passwordHint;

  /// No description provided for @showPassword.
  ///
  /// In en, this message translates to:
  /// **'Show password'**
  String get showPassword;

  /// No description provided for @hidePassword.
  ///
  /// In en, this message translates to:
  /// **'Hide password'**
  String get hidePassword;

  /// No description provided for @signInAction.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get signInAction;

  /// No description provided for @forgotPassword.
  ///
  /// In en, this message translates to:
  /// **'Forgot your password?'**
  String get forgotPassword;

  /// No description provided for @updateRequired.
  ///
  /// In en, this message translates to:
  /// **'Update required'**
  String get updateRequired;

  /// No description provided for @updateGeneric.
  ///
  /// In en, this message translates to:
  /// **'Please update antaraNote to continue.'**
  String get updateGeneric;

  /// No description provided for @updateVersioned.
  ///
  /// In en, this message translates to:
  /// **'antaraNote {version} or later is needed to continue.'**
  String updateVersioned(String version);

  /// No description provided for @openTheStore.
  ///
  /// In en, this message translates to:
  /// **'Open the store'**
  String get openTheStore;

  /// No description provided for @notBuiltYet.
  ///
  /// In en, this message translates to:
  /// **'Not built yet'**
  String get notBuiltYet;

  /// No description provided for @firstRunOneEyebrow.
  ///
  /// In en, this message translates to:
  /// **'The red button'**
  String get firstRunOneEyebrow;

  /// No description provided for @firstRunOneLine.
  ///
  /// In en, this message translates to:
  /// **'It records the room, not a call.'**
  String get firstRunOneLine;

  /// No description provided for @firstRunOneBody.
  ///
  /// In en, this message translates to:
  /// **'Put the phone face up on the table and press record. antaraNote listens through the microphone — there is no bot to invite and nothing for anyone else to install.'**
  String get firstRunOneBody;

  /// No description provided for @firstRunTwoEyebrow.
  ///
  /// In en, this message translates to:
  /// **'While it runs'**
  String get firstRunTwoEyebrow;

  /// No description provided for @firstRunTwoLine.
  ///
  /// In en, this message translates to:
  /// **'Mark a decision the moment it is carried.'**
  String get firstRunTwoLine;

  /// No description provided for @firstRunTwoBody.
  ///
  /// In en, this message translates to:
  /// **'One tap on “Mark this” stamps the second you heard it. Those marks become the skeleton of the minutes, so you are not reconstructing an hour from memory afterwards.'**
  String get firstRunTwoBody;

  /// No description provided for @firstRunThreeEyebrow.
  ///
  /// In en, this message translates to:
  /// **'Afterwards'**
  String get firstRunThreeEyebrow;

  /// No description provided for @firstRunThreeLine.
  ///
  /// In en, this message translates to:
  /// **'Minutes, numbered and circulated.'**
  String get firstRunThreeLine;

  /// No description provided for @firstRunThreeBody.
  ///
  /// In en, this message translates to:
  /// **'The recording becomes a transcript, the transcript becomes a draft, and the draft goes out for confirmation. Everyone present should be told they are being recorded — that part is still yours.'**
  String get firstRunThreeBody;

  /// No description provided for @start.
  ///
  /// In en, this message translates to:
  /// **'Start'**
  String get start;

  /// No description provided for @somethingWentWrong.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong.'**
  String get somethingWentWrong;

  /// No description provided for @tryAgain.
  ///
  /// In en, this message translates to:
  /// **'Try again'**
  String get tryAgain;

  /// No description provided for @offline.
  ///
  /// In en, this message translates to:
  /// **'No connection. This will be retried automatically.'**
  String get offline;

  /// No description provided for @tabHome.
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get tabHome;

  /// No description provided for @tabMeetings.
  ///
  /// In en, this message translates to:
  /// **'Meetings'**
  String get tabMeetings;

  /// No description provided for @tabTasks.
  ///
  /// In en, this message translates to:
  /// **'Tasks'**
  String get tabTasks;

  /// No description provided for @tabMe.
  ///
  /// In en, this message translates to:
  /// **'Me'**
  String get tabMe;

  /// No description provided for @record.
  ///
  /// In en, this message translates to:
  /// **'Record'**
  String get record;

  /// No description provided for @next.
  ///
  /// In en, this message translates to:
  /// **'Next'**
  String get next;

  /// No description provided for @skip.
  ///
  /// In en, this message translates to:
  /// **'Skip'**
  String get skip;

  /// No description provided for @startRecording.
  ///
  /// In en, this message translates to:
  /// **'Start recording'**
  String get startRecording;

  /// No description provided for @meetings.
  ///
  /// In en, this message translates to:
  /// **'Meetings'**
  String get meetings;

  /// No description provided for @searchMeetings.
  ///
  /// In en, this message translates to:
  /// **'Search'**
  String get searchMeetings;

  /// No description provided for @closeSearch.
  ///
  /// In en, this message translates to:
  /// **'Close search'**
  String get closeSearch;

  /// No description provided for @newMeeting.
  ///
  /// In en, this message translates to:
  /// **'New meeting'**
  String get newMeeting;

  /// No description provided for @searchHint.
  ///
  /// In en, this message translates to:
  /// **'Title, number or place'**
  String get searchHint;

  /// No description provided for @filterAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get filterAll;

  /// No description provided for @countOf.
  ///
  /// In en, this message translates to:
  /// **'{shown} OF {total}'**
  String countOf(int shown, int total);

  /// No description provided for @meetingCount.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 MEETING} other{{count} MEETINGS}}'**
  String meetingCount(int count);

  /// No description provided for @loading.
  ///
  /// In en, this message translates to:
  /// **'LOADING'**
  String get loading;

  /// No description provided for @nothingMatches.
  ///
  /// In en, this message translates to:
  /// **'NOTHING MATCHES'**
  String get nothingMatches;

  /// No description provided for @noMeetingsInState.
  ///
  /// In en, this message translates to:
  /// **'No meetings in that state'**
  String get noMeetingsInState;

  /// No description provided for @noMatchFor.
  ///
  /// In en, this message translates to:
  /// **'No “{term}”'**
  String noMatchFor(String term);

  /// No description provided for @searchCovers.
  ///
  /// In en, this message translates to:
  /// **'Search covers the title, the reference number and the place.'**
  String get searchCovers;

  /// No description provided for @clearTheFilter.
  ///
  /// In en, this message translates to:
  /// **'Clear the filter'**
  String get clearTheFilter;

  /// No description provided for @nothingRecorded.
  ///
  /// In en, this message translates to:
  /// **'NOTHING RECORDED'**
  String get nothingRecorded;

  /// No description provided for @noMeetingsYet.
  ///
  /// In en, this message translates to:
  /// **'No meetings yet'**
  String get noMeetingsYet;

  /// No description provided for @noMeetingsYetDetail.
  ///
  /// In en, this message translates to:
  /// **'File a sitting ahead of time, or record one and its minutes will be filed here.'**
  String get noMeetingsYetDetail;

  /// No description provided for @present.
  ///
  /// In en, this message translates to:
  /// **'{count} present'**
  String present(int count);

  /// No description provided for @statusDraft.
  ///
  /// In en, this message translates to:
  /// **'Draft'**
  String get statusDraft;

  /// No description provided for @statusInProgress.
  ///
  /// In en, this message translates to:
  /// **'In progress'**
  String get statusInProgress;

  /// No description provided for @statusFinalized.
  ///
  /// In en, this message translates to:
  /// **'Finalised'**
  String get statusFinalized;

  /// No description provided for @statusPendingConfirmation.
  ///
  /// In en, this message translates to:
  /// **'Awaiting confirmation'**
  String get statusPendingConfirmation;

  /// No description provided for @statusApproved.
  ///
  /// In en, this message translates to:
  /// **'Approved'**
  String get statusApproved;

  /// No description provided for @stepFinalizeLabel.
  ///
  /// In en, this message translates to:
  /// **'Finalise the minutes'**
  String get stepFinalizeLabel;

  /// No description provided for @stepFinalizeDetail.
  ///
  /// In en, this message translates to:
  /// **'Closes them for editing and opens them for approval.'**
  String get stepFinalizeDetail;

  /// No description provided for @stepFinalizeConfirm.
  ///
  /// In en, this message translates to:
  /// **'Finalise'**
  String get stepFinalizeConfirm;

  /// No description provided for @stepApproveLabel.
  ///
  /// In en, this message translates to:
  /// **'Approve the minutes'**
  String get stepApproveLabel;

  /// No description provided for @stepApproveDetail.
  ///
  /// In en, this message translates to:
  /// **'Puts them on the record. This cannot be undone.'**
  String get stepApproveDetail;

  /// No description provided for @stepApproveConfirm.
  ///
  /// In en, this message translates to:
  /// **'Approve'**
  String get stepApproveConfirm;

  /// No description provided for @notYet.
  ///
  /// In en, this message translates to:
  /// **'Not yet'**
  String get notYet;

  /// No description provided for @summary.
  ///
  /// In en, this message translates to:
  /// **'Summary'**
  String get summary;

  /// No description provided for @minutes.
  ///
  /// In en, this message translates to:
  /// **'Minutes'**
  String get minutes;

  /// No description provided for @resolutions.
  ///
  /// In en, this message translates to:
  /// **'Resolutions'**
  String get resolutions;

  /// No description provided for @attendance.
  ///
  /// In en, this message translates to:
  /// **'Attendance'**
  String get attendance;

  /// No description provided for @signInDesk.
  ///
  /// In en, this message translates to:
  /// **'Sign-in desk'**
  String get signInDesk;

  /// No description provided for @recordSection.
  ///
  /// In en, this message translates to:
  /// **'Record'**
  String get recordSection;

  /// No description provided for @recordThisSitting.
  ///
  /// In en, this message translates to:
  /// **'Record this sitting'**
  String get recordThisSitting;

  /// No description provided for @factDate.
  ///
  /// In en, this message translates to:
  /// **'Date'**
  String get factDate;

  /// No description provided for @factAt.
  ///
  /// In en, this message translates to:
  /// **'At'**
  String get factAt;

  /// No description provided for @factRan.
  ///
  /// In en, this message translates to:
  /// **'Ran'**
  String get factRan;

  /// No description provided for @factRanMinutes.
  ///
  /// In en, this message translates to:
  /// **'{count} minutes'**
  String factRanMinutes(int count);

  /// No description provided for @factPresent.
  ///
  /// In en, this message translates to:
  /// **'Present'**
  String get factPresent;

  /// No description provided for @factPresentOf.
  ///
  /// In en, this message translates to:
  /// **'{present} of {total}'**
  String factPresentOf(int present, int total);

  /// No description provided for @factKeptBy.
  ///
  /// In en, this message translates to:
  /// **'Kept by'**
  String get factKeptBy;

  /// No description provided for @factAudio.
  ///
  /// In en, this message translates to:
  /// **'Audio'**
  String get factAudio;

  /// No description provided for @factTranscribed.
  ///
  /// In en, this message translates to:
  /// **'Transcribed'**
  String get factTranscribed;

  /// No description provided for @factPapers.
  ///
  /// In en, this message translates to:
  /// **'Papers'**
  String get factPapers;

  /// No description provided for @factPapersCount.
  ///
  /// In en, this message translates to:
  /// **'{count} attached'**
  String factPapersCount(int count);

  /// No description provided for @approvedStamp.
  ///
  /// In en, this message translates to:
  /// **'Approved'**
  String get approvedStamp;

  /// No description provided for @onTheRecord.
  ///
  /// In en, this message translates to:
  /// **'On the record'**
  String get onTheRecord;

  /// No description provided for @movedBy.
  ///
  /// In en, this message translates to:
  /// **'Moved by {name}'**
  String movedBy(String name);

  /// No description provided for @secondedBy.
  ///
  /// In en, this message translates to:
  /// **'seconded by {name}'**
  String secondedBy(String name);

  /// No description provided for @noVote.
  ///
  /// In en, this message translates to:
  /// **'no vote'**
  String get noVote;

  /// No description provided for @noDate.
  ///
  /// In en, this message translates to:
  /// **'no date'**
  String get noDate;

  /// No description provided for @apologies.
  ///
  /// In en, this message translates to:
  /// **'Apologies'**
  String get apologies;

  /// No description provided for @tasks.
  ///
  /// In en, this message translates to:
  /// **'Tasks'**
  String get tasks;

  /// No description provided for @tasksMeta.
  ///
  /// In en, this message translates to:
  /// **'{count} OPEN · ASSIGNED TO YOU'**
  String tasksMeta(int count);

  /// No description provided for @overdue.
  ///
  /// In en, this message translates to:
  /// **'Overdue'**
  String get overdue;

  /// No description provided for @dueToday.
  ///
  /// In en, this message translates to:
  /// **'Due today'**
  String get dueToday;

  /// No description provided for @later.
  ///
  /// In en, this message translates to:
  /// **'Later'**
  String get later;

  /// No description provided for @closed.
  ///
  /// In en, this message translates to:
  /// **'CLOSED'**
  String get closed;

  /// No description provided for @markComplete.
  ///
  /// In en, this message translates to:
  /// **'Mark complete'**
  String get markComplete;

  /// No description provided for @loadingSection.
  ///
  /// In en, this message translates to:
  /// **'Loading'**
  String get loadingSection;

  /// No description provided for @nothingAssigned.
  ///
  /// In en, this message translates to:
  /// **'NOTHING ASSIGNED'**
  String get nothingAssigned;

  /// No description provided for @youAreClear.
  ///
  /// In en, this message translates to:
  /// **'You are clear'**
  String get youAreClear;

  /// No description provided for @nothingAssignedDetail.
  ///
  /// In en, this message translates to:
  /// **'Action items assigned to you in a meeting will appear here.'**
  String get nothingAssignedDetail;

  /// No description provided for @newMeetingEyebrow.
  ///
  /// In en, this message translates to:
  /// **'NEW MEETING'**
  String get newMeetingEyebrow;

  /// No description provided for @meetingTitle.
  ///
  /// In en, this message translates to:
  /// **'Meeting title'**
  String get meetingTitle;

  /// No description provided for @titleRequired.
  ///
  /// In en, this message translates to:
  /// **'Title is required'**
  String get titleRequired;

  /// No description provided for @gutterDate.
  ///
  /// In en, this message translates to:
  /// **'date'**
  String get gutterDate;

  /// No description provided for @noDateSet.
  ///
  /// In en, this message translates to:
  /// **'No date set'**
  String get noDateSet;

  /// No description provided for @gutterPlace.
  ///
  /// In en, this message translates to:
  /// **'place'**
  String get gutterPlace;

  /// No description provided for @locationOptional.
  ///
  /// In en, this message translates to:
  /// **'Location (optional)'**
  String get locationOptional;

  /// No description provided for @createMeeting.
  ///
  /// In en, this message translates to:
  /// **'Create meeting'**
  String get createMeeting;

  /// No description provided for @typeGeneral.
  ///
  /// In en, this message translates to:
  /// **'General'**
  String get typeGeneral;

  /// No description provided for @typeBoard.
  ///
  /// In en, this message translates to:
  /// **'Board'**
  String get typeBoard;

  /// No description provided for @typeStandUp.
  ///
  /// In en, this message translates to:
  /// **'Stand-up'**
  String get typeStandUp;

  /// No description provided for @typeClientCall.
  ///
  /// In en, this message translates to:
  /// **'Client Call'**
  String get typeClientCall;

  /// No description provided for @typeOneOnOne.
  ///
  /// In en, this message translates to:
  /// **'1-on-1'**
  String get typeOneOnOne;

  /// No description provided for @typeWorkshop.
  ///
  /// In en, this message translates to:
  /// **'Workshop'**
  String get typeWorkshop;

  /// No description provided for @typeRetrospective.
  ///
  /// In en, this message translates to:
  /// **'Retrospective'**
  String get typeRetrospective;

  /// No description provided for @recordInto.
  ///
  /// In en, this message translates to:
  /// **'RECORD INTO'**
  String get recordInto;

  /// No description provided for @sittingNotOnTheList.
  ///
  /// In en, this message translates to:
  /// **'A sitting not on the list'**
  String get sittingNotOnTheList;

  /// No description provided for @orPlanAhead.
  ///
  /// In en, this message translates to:
  /// **'OR PLAN AHEAD'**
  String get orPlanAhead;

  /// No description provided for @setUpAMeeting.
  ///
  /// In en, this message translates to:
  /// **'Set up a meeting'**
  String get setUpAMeeting;

  /// No description provided for @gutterNew.
  ///
  /// In en, this message translates to:
  /// **'new'**
  String get gutterNew;

  /// No description provided for @gutterPlan.
  ///
  /// In en, this message translates to:
  /// **'plan'**
  String get gutterPlan;

  /// No description provided for @defaultRecordingTitle.
  ///
  /// In en, this message translates to:
  /// **'Recording, {date} at {time}'**
  String defaultRecordingTitle(String date, String time);

  /// No description provided for @organisation.
  ///
  /// In en, this message translates to:
  /// **'Organisation'**
  String get organisation;

  /// No description provided for @settings.
  ///
  /// In en, this message translates to:
  /// **'Settings'**
  String get settings;

  /// No description provided for @settingsNotificationsDetail.
  ///
  /// In en, this message translates to:
  /// **'What reaches this phone, and when'**
  String get settingsNotificationsDetail;

  /// No description provided for @language.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get language;

  /// No description provided for @languageDetail.
  ///
  /// In en, this message translates to:
  /// **'Bahasa Melayu · English'**
  String get languageDetail;

  /// No description provided for @session.
  ///
  /// In en, this message translates to:
  /// **'Session'**
  String get session;

  /// No description provided for @signOut.
  ///
  /// In en, this message translates to:
  /// **'Sign out'**
  String get signOut;

  /// No description provided for @signOutDetail.
  ///
  /// In en, this message translates to:
  /// **'Recordings already sent stay on the record'**
  String get signOutDetail;

  /// No description provided for @switchToOrg.
  ///
  /// In en, this message translates to:
  /// **'Switch to {name}?'**
  String switchToOrg(String name);

  /// No description provided for @switchToOrgDetail.
  ///
  /// In en, this message translates to:
  /// **'Meetings, tasks and recordings will all be that organisation’s from here on.'**
  String get switchToOrgDetail;

  /// No description provided for @switchAction.
  ///
  /// In en, this message translates to:
  /// **'Switch'**
  String get switchAction;

  /// No description provided for @signOutConfirm.
  ///
  /// In en, this message translates to:
  /// **'Sign out?'**
  String get signOutConfirm;

  /// No description provided for @signOutConfirmDetail.
  ///
  /// In en, this message translates to:
  /// **'You will need your password again to reach the minute book.'**
  String get signOutConfirmDetail;

  /// No description provided for @cancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get cancel;

  /// No description provided for @gutterCurrent.
  ///
  /// In en, this message translates to:
  /// **'current'**
  String get gutterCurrent;

  /// No description provided for @gutterSwitch.
  ///
  /// In en, this message translates to:
  /// **'switch'**
  String get gutterSwitch;

  /// No description provided for @openingMicrophone.
  ///
  /// In en, this message translates to:
  /// **'Opening the microphone'**
  String get openingMicrophone;

  /// No description provided for @filingLastAudio.
  ///
  /// In en, this message translates to:
  /// **'Filing the last of the audio'**
  String get filingLastAudio;

  /// No description provided for @minimise.
  ///
  /// In en, this message translates to:
  /// **'Minimise'**
  String get minimise;

  /// No description provided for @recording.
  ///
  /// In en, this message translates to:
  /// **'RECORDING'**
  String get recording;

  /// No description provided for @paused.
  ///
  /// In en, this message translates to:
  /// **'PAUSED'**
  String get paused;

  /// No description provided for @starting.
  ///
  /// In en, this message translates to:
  /// **'STARTING'**
  String get starting;

  /// No description provided for @retrying.
  ///
  /// In en, this message translates to:
  /// **'RETRYING'**
  String get retrying;

  /// No description provided for @autosaving.
  ///
  /// In en, this message translates to:
  /// **'AUTOSAVING'**
  String get autosaving;

  /// No description provided for @marksSection.
  ///
  /// In en, this message translates to:
  /// **'MARKS'**
  String get marksSection;

  /// No description provided for @marksEmpty.
  ///
  /// In en, this message translates to:
  /// **'Mark a decision as it is carried and it lands here, timed to the second. The marks become the skeleton of the minutes.'**
  String get marksEmpty;

  /// No description provided for @notYetSent.
  ///
  /// In en, this message translates to:
  /// **'Not yet sent'**
  String get notYetSent;

  /// No description provided for @pause.
  ///
  /// In en, this message translates to:
  /// **'Pause'**
  String get pause;

  /// No description provided for @resume.
  ///
  /// In en, this message translates to:
  /// **'Resume'**
  String get resume;

  /// No description provided for @endRecording.
  ///
  /// In en, this message translates to:
  /// **'End the recording?'**
  String get endRecording;

  /// No description provided for @endRecordingDetail.
  ///
  /// In en, this message translates to:
  /// **'The audio still in the queue is sent first, then the minutes are drafted from the transcript.'**
  String get endRecordingDetail;

  /// No description provided for @keepRecording.
  ///
  /// In en, this message translates to:
  /// **'Keep recording'**
  String get keepRecording;

  /// No description provided for @end.
  ///
  /// In en, this message translates to:
  /// **'End'**
  String get end;

  /// No description provided for @markHint.
  ///
  /// In en, this message translates to:
  /// **'Mark this moment. Hold to choose a kind.'**
  String get markHint;

  /// No description provided for @markThis.
  ///
  /// In en, this message translates to:
  /// **'Mark this'**
  String get markThis;

  /// No description provided for @whatWasIt.
  ///
  /// In en, this message translates to:
  /// **'WHAT WAS IT'**
  String get whatWasIt;

  /// No description provided for @microphoneRefused.
  ///
  /// In en, this message translates to:
  /// **'MICROPHONE REFUSED'**
  String get microphoneRefused;

  /// No description provided for @cannotHearTheRoom.
  ///
  /// In en, this message translates to:
  /// **'antaraNote cannot hear the room'**
  String get cannotHearTheRoom;

  /// No description provided for @microphoneDetail.
  ///
  /// In en, this message translates to:
  /// **'Recording needs the microphone. Grant it in Settings and come back to this screen.'**
  String get microphoneDetail;

  /// No description provided for @openSettings.
  ///
  /// In en, this message translates to:
  /// **'Open settings'**
  String get openSettings;

  /// No description provided for @notStarted.
  ///
  /// In en, this message translates to:
  /// **'NOT STARTED'**
  String get notStarted;

  /// No description provided for @sessionDidNotOpen.
  ///
  /// In en, this message translates to:
  /// **'The session did not open'**
  String get sessionDidNotOpen;

  /// No description provided for @sessionDidNotOpenDetail.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong reaching the server.'**
  String get sessionDidNotOpenDetail;

  /// No description provided for @close.
  ///
  /// In en, this message translates to:
  /// **'Close'**
  String get close;

  /// No description provided for @filed.
  ///
  /// In en, this message translates to:
  /// **'FILED'**
  String get filed;

  /// No description provided for @recordedWithGaps.
  ///
  /// In en, this message translates to:
  /// **'Recorded, with gaps'**
  String get recordedWithGaps;

  /// No description provided for @recordedAndFiled.
  ///
  /// In en, this message translates to:
  /// **'Recorded and filed'**
  String get recordedAndFiled;

  /// No description provided for @marksCount.
  ///
  /// In en, this message translates to:
  /// **'marks'**
  String get marksCount;

  /// No description provided for @transcriptShort.
  ///
  /// In en, this message translates to:
  /// **'transcript will be short in places'**
  String get transcriptShort;

  /// No description provided for @noDeskOpen.
  ///
  /// In en, this message translates to:
  /// **'NO DESK OPEN'**
  String get noDeskOpen;

  /// No description provided for @nobodyCanSignIn.
  ///
  /// In en, this message translates to:
  /// **'Nobody can sign themselves in yet'**
  String get nobodyCanSignIn;

  /// No description provided for @deskExplain.
  ///
  /// In en, this message translates to:
  /// **'Opening the desk creates a link. Put it on a screen the room can see: it shows a code to scan, and every name lands on it as they sign in. The link closes at the end of the sitting.'**
  String get deskExplain;

  /// No description provided for @openTheDesk.
  ///
  /// In en, this message translates to:
  /// **'Open the desk'**
  String get openTheDesk;

  /// No description provided for @closeTheDesk.
  ///
  /// In en, this message translates to:
  /// **'Close the desk'**
  String get closeTheDesk;

  /// No description provided for @closeTheDeskConfirm.
  ///
  /// In en, this message translates to:
  /// **'Close the desk?'**
  String get closeTheDeskConfirm;

  /// No description provided for @closeTheDeskDetail.
  ///
  /// In en, this message translates to:
  /// **'The link stops working immediately. Anybody who has not signed in yet will have to be added by hand.'**
  String get closeTheDeskDetail;

  /// No description provided for @keepItOpen.
  ///
  /// In en, this message translates to:
  /// **'Keep it open'**
  String get keepItOpen;

  /// No description provided for @deskOpen.
  ///
  /// In en, this message translates to:
  /// **'OPEN · {code}'**
  String deskOpen(String code);

  /// No description provided for @deskClosed.
  ///
  /// In en, this message translates to:
  /// **'CLOSED'**
  String get deskClosed;

  /// No description provided for @signedInCount.
  ///
  /// In en, this message translates to:
  /// **'signed in'**
  String get signedInCount;

  /// No description provided for @linkCloses.
  ///
  /// In en, this message translates to:
  /// **'Link closes {when}'**
  String linkCloses(String when);

  /// No description provided for @theScreenToShare.
  ///
  /// In en, this message translates to:
  /// **'THE SCREEN TO SHARE'**
  String get theScreenToShare;

  /// No description provided for @share.
  ///
  /// In en, this message translates to:
  /// **'Share'**
  String get share;

  /// No description provided for @copy.
  ///
  /// In en, this message translates to:
  /// **'Copy'**
  String get copy;

  /// No description provided for @gutterCode.
  ///
  /// In en, this message translates to:
  /// **'code'**
  String get gutterCode;

  /// No description provided for @signInTo.
  ///
  /// In en, this message translates to:
  /// **'Sign in — {title}'**
  String signInTo(String title);

  /// No description provided for @linkCopied.
  ///
  /// In en, this message translates to:
  /// **'Link copied'**
  String get linkCopied;

  /// No description provided for @nobodyYet.
  ///
  /// In en, this message translates to:
  /// **'NOBODY YET'**
  String get nobodyYet;

  /// No description provided for @nobodyYetDetail.
  ///
  /// In en, this message translates to:
  /// **'Names appear here the moment somebody scans.'**
  String get nobodyYetDetail;

  /// No description provided for @signedInSection.
  ///
  /// In en, this message translates to:
  /// **'SIGNED IN'**
  String get signedInSection;

  /// No description provided for @guest.
  ///
  /// In en, this message translates to:
  /// **'guest'**
  String get guest;

  /// No description provided for @member.
  ///
  /// In en, this message translates to:
  /// **'member'**
  String get member;

  /// No description provided for @readingTheDesk.
  ///
  /// In en, this message translates to:
  /// **'READING THE DESK'**
  String get readingTheDesk;

  /// No description provided for @audioOf.
  ///
  /// In en, this message translates to:
  /// **'{clock} of audio'**
  String audioOf(String clock);

  /// No description provided for @markCount.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 mark} other{{count} marks}}'**
  String markCount(int count);

  /// No description provided for @piecesLost.
  ///
  /// In en, this message translates to:
  /// **'{count} pieces never reached the server, so the '**
  String piecesLost(int count);

  /// No description provided for @done.
  ///
  /// In en, this message translates to:
  /// **'Done'**
  String get done;

  /// No description provided for @languageFollowPhone.
  ///
  /// In en, this message translates to:
  /// **'Follow the phone'**
  String get languageFollowPhone;

  /// No description provided for @languageFollowPhoneDetail.
  ///
  /// In en, this message translates to:
  /// **'Whatever your device is set to'**
  String get languageFollowPhoneDetail;

  /// No description provided for @languageEnglish.
  ///
  /// In en, this message translates to:
  /// **'English'**
  String get languageEnglish;

  /// No description provided for @languageMalay.
  ///
  /// In en, this message translates to:
  /// **'Bahasa Melayu'**
  String get languageMalay;

  /// No description provided for @gutterUsing.
  ///
  /// In en, this message translates to:
  /// **'using'**
  String get gutterUsing;

  /// No description provided for @gutterChoose.
  ///
  /// In en, this message translates to:
  /// **'choose'**
  String get gutterChoose;

  /// No description provided for @markAllRead.
  ///
  /// In en, this message translates to:
  /// **'Mark all read'**
  String get markAllRead;

  /// No description provided for @allRead.
  ///
  /// In en, this message translates to:
  /// **'ALL READ'**
  String get allRead;

  /// No description provided for @unreadCount.
  ///
  /// In en, this message translates to:
  /// **'{count} UNREAD'**
  String unreadCount(int count);

  /// No description provided for @upToDate.
  ///
  /// In en, this message translates to:
  /// **'You are up to date'**
  String get upToDate;

  /// No description provided for @nothingWaitingNotifications.
  ///
  /// In en, this message translates to:
  /// **'Minutes to approve, tasks assigned to you and mentions all arrive here.'**
  String get nothingWaitingNotifications;

  /// No description provided for @notification.
  ///
  /// In en, this message translates to:
  /// **'Notification'**
  String get notification;

  /// No description provided for @meeting.
  ///
  /// In en, this message translates to:
  /// **'Meeting'**
  String get meeting;

  /// No description provided for @prefsIntro.
  ///
  /// In en, this message translates to:
  /// **'Push reaches this phone. Email reaches you wherever you read it.'**
  String get prefsIntro;

  /// No description provided for @prefsPush.
  ///
  /// In en, this message translates to:
  /// **'Push'**
  String get prefsPush;

  /// No description provided for @prefsEmail.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get prefsEmail;

  /// No description provided for @readingYourSettings.
  ///
  /// In en, this message translates to:
  /// **'READING YOUR SETTINGS'**
  String get readingYourSettings;

  /// No description provided for @kindAssignedLabel.
  ///
  /// In en, this message translates to:
  /// **'A task lands on you'**
  String get kindAssignedLabel;

  /// No description provided for @kindAssignedDetail.
  ///
  /// In en, this message translates to:
  /// **'Somebody assigns you an action item'**
  String get kindAssignedDetail;

  /// No description provided for @kindFinalizedLabel.
  ///
  /// In en, this message translates to:
  /// **'Minutes are finalised'**
  String get kindFinalizedLabel;

  /// No description provided for @kindFinalizedDetail.
  ///
  /// In en, this message translates to:
  /// **'A sitting closes for editing and opens for approval'**
  String get kindFinalizedDetail;

  /// No description provided for @kindApprovedLabel.
  ///
  /// In en, this message translates to:
  /// **'Minutes are approved'**
  String get kindApprovedLabel;

  /// No description provided for @kindApprovedDetail.
  ///
  /// In en, this message translates to:
  /// **'A record is settled and cannot change'**
  String get kindApprovedDetail;

  /// No description provided for @kindCirculationLabel.
  ///
  /// In en, this message translates to:
  /// **'Something waits for your signature'**
  String get kindCirculationLabel;

  /// No description provided for @kindCirculationDetail.
  ///
  /// In en, this message translates to:
  /// **'Minutes circulated to you for confirmation'**
  String get kindCirculationDetail;

  /// No description provided for @kindMentionLabel.
  ///
  /// In en, this message translates to:
  /// **'You are mentioned'**
  String get kindMentionLabel;

  /// No description provided for @kindMentionDetail.
  ///
  /// In en, this message translates to:
  /// **'In a comment or a note'**
  String get kindMentionDetail;

  /// No description provided for @kindTranscriptionLabel.
  ///
  /// In en, this message translates to:
  /// **'A recording finishes transcribing'**
  String get kindTranscriptionLabel;

  /// No description provided for @kindTranscriptionDetail.
  ///
  /// In en, this message translates to:
  /// **'The audio has become text'**
  String get kindTranscriptionDetail;

  /// No description provided for @markTask.
  ///
  /// In en, this message translates to:
  /// **'task'**
  String get markTask;

  /// No description provided for @markLate.
  ///
  /// In en, this message translates to:
  /// **'late'**
  String get markLate;

  /// No description provided for @markSoon.
  ///
  /// In en, this message translates to:
  /// **'soon'**
  String get markSoon;

  /// No description provided for @markDrafted.
  ///
  /// In en, this message translates to:
  /// **'drafted'**
  String get markDrafted;

  /// No description provided for @markAudio.
  ///
  /// In en, this message translates to:
  /// **'audio'**
  String get markAudio;

  /// No description provided for @markStale.
  ///
  /// In en, this message translates to:
  /// **'stale'**
  String get markStale;

  /// No description provided for @markFailed.
  ///
  /// In en, this message translates to:
  /// **'failed'**
  String get markFailed;

  /// No description provided for @markNote.
  ///
  /// In en, this message translates to:
  /// **'note'**
  String get markNote;

  /// No description provided for @agoNow.
  ///
  /// In en, this message translates to:
  /// **'now'**
  String get agoNow;

  /// No description provided for @agoMinutes.
  ///
  /// In en, this message translates to:
  /// **'{count}m'**
  String agoMinutes(int count);

  /// No description provided for @agoHours.
  ///
  /// In en, this message translates to:
  /// **'{count}h'**
  String agoHours(int count);

  /// No description provided for @agoDays.
  ///
  /// In en, this message translates to:
  /// **'{count}d'**
  String agoDays(int count);

  /// No description provided for @organisationSwitchFailed.
  ///
  /// In en, this message translates to:
  /// **'Could not switch organisation'**
  String get organisationSwitchFailed;

  /// No description provided for @kindOverdueLabel.
  ///
  /// In en, this message translates to:
  /// **'A task of yours goes overdue'**
  String get kindOverdueLabel;

  /// No description provided for @kindOverdueDetail.
  ///
  /// In en, this message translates to:
  /// **'Its due date passes and it is still open'**
  String get kindOverdueDetail;

  /// No description provided for @kindStartingLabel.
  ///
  /// In en, this message translates to:
  /// **'A meeting is about to start'**
  String get kindStartingLabel;

  /// No description provided for @kindStartingDetail.
  ///
  /// In en, this message translates to:
  /// **'Shortly before a sitting you are invited to'**
  String get kindStartingDetail;

  /// No description provided for @kindExtractionLabel.
  ///
  /// In en, this message translates to:
  /// **'Minutes are drafted from a recording'**
  String get kindExtractionLabel;

  /// No description provided for @kindExtractionDetail.
  ///
  /// In en, this message translates to:
  /// **'The AI has finished reading a transcript'**
  String get kindExtractionDetail;

  /// No description provided for @kindStaleLabel.
  ///
  /// In en, this message translates to:
  /// **'A decision goes stale'**
  String get kindStaleLabel;

  /// No description provided for @kindStaleDetail.
  ///
  /// In en, this message translates to:
  /// **'Something agreed has sat untouched too long'**
  String get kindStaleDetail;

  /// No description provided for @kindFailedLabel.
  ///
  /// In en, this message translates to:
  /// **'Processing fails'**
  String get kindFailedLabel;

  /// No description provided for @kindFailedDetail.
  ///
  /// In en, this message translates to:
  /// **'A recording could not be transcribed or read'**
  String get kindFailedDetail;

  /// No description provided for @circulationRound.
  ///
  /// In en, this message translates to:
  /// **'Round {round}'**
  String circulationRound(int round);

  /// No description provided for @approvalsEmptyDetail.
  ///
  /// In en, this message translates to:
  /// **'Minutes circulated to you for confirmation appear here until you answer them.'**
  String get approvalsEmptyDetail;

  /// No description provided for @unassigned.
  ///
  /// In en, this message translates to:
  /// **'Unassigned'**
  String get unassigned;

  /// No description provided for @unassignedDetail.
  ///
  /// In en, this message translates to:
  /// **'Extracted from your sittings with no name on them'**
  String get unassignedDetail;

  /// No description provided for @gutterUnassigned.
  ///
  /// In en, this message translates to:
  /// **'nobody'**
  String get gutterUnassigned;
}

class _LDelegate extends LocalizationsDelegate<L> {
  const _LDelegate();

  @override
  Future<L> load(Locale locale) {
    return SynchronousFuture<L>(lookupL(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['en', 'ms'].contains(locale.languageCode);

  @override
  bool shouldReload(_LDelegate old) => false;
}

L lookupL(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'en':
      return LEn();
    case 'ms':
      return LMs();
  }

  throw FlutterError(
    'L.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
