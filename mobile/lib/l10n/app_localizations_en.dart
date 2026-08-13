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

  @override
  String get appTagline => 'Minutes,\nsigned and settled.';

  @override
  String get signIn => 'SIGN IN';

  @override
  String get email => 'Email';

  @override
  String get emailHint => 'Enter your email address';

  @override
  String get password => 'Password';

  @override
  String get passwordHint => 'Enter your password';

  @override
  String get showPassword => 'Show password';

  @override
  String get hidePassword => 'Hide password';

  @override
  String get signInAction => 'Sign in';

  @override
  String get forgotPassword => 'Forgot your password?';

  @override
  String get updateRequired => 'Update required';

  @override
  String get updateGeneric => 'Please update antaraNote to continue.';

  @override
  String updateVersioned(String version) {
    return 'antaraNote $version or later is needed to continue.';
  }

  @override
  String get openTheStore => 'Open the store';

  @override
  String get notBuiltYet => 'Not built yet';

  @override
  String get firstRunOneEyebrow => 'The red button';

  @override
  String get firstRunOneLine => 'It records the room, not a call.';

  @override
  String get firstRunOneBody =>
      'Put the phone face up on the table and press record. antaraNote listens through the microphone — there is no bot to invite and nothing for anyone else to install.';

  @override
  String get firstRunTwoEyebrow => 'While it runs';

  @override
  String get firstRunTwoLine => 'Mark a decision the moment it is carried.';

  @override
  String get firstRunTwoBody =>
      'One tap on “Mark this” stamps the second you heard it. Those marks become the skeleton of the minutes, so you are not reconstructing an hour from memory afterwards.';

  @override
  String get firstRunThreeEyebrow => 'Afterwards';

  @override
  String get firstRunThreeLine => 'Minutes, numbered and circulated.';

  @override
  String get firstRunThreeBody =>
      'The recording becomes a transcript, the transcript becomes a draft, and the draft goes out for confirmation. Everyone present should be told they are being recorded — that part is still yours.';

  @override
  String get start => 'Start';

  @override
  String get somethingWentWrong => 'Something went wrong.';

  @override
  String get tryAgain => 'Try again';

  @override
  String get offline => 'No connection. This will be retried automatically.';

  @override
  String get tabHome => 'Home';

  @override
  String get tabMeetings => 'Meetings';

  @override
  String get tabTasks => 'Tasks';

  @override
  String get tabMe => 'Me';

  @override
  String get record => 'Record';

  @override
  String get next => 'Next';

  @override
  String get skip => 'Skip';

  @override
  String get startRecording => 'Start recording';

  @override
  String get meetings => 'Meetings';

  @override
  String get searchMeetings => 'Search';

  @override
  String get closeSearch => 'Close search';

  @override
  String get newMeeting => 'New meeting';

  @override
  String get searchHint => 'Title, number or place';

  @override
  String get filterAll => 'All';

  @override
  String countOf(int shown, int total) {
    return '$shown OF $total';
  }

  @override
  String meetingCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count MEETINGS',
      one: '1 MEETING',
    );
    return '$_temp0';
  }

  @override
  String get loading => 'LOADING';

  @override
  String get nothingMatches => 'NOTHING MATCHES';

  @override
  String get noMeetingsInState => 'No meetings in that state';

  @override
  String noMatchFor(String term) {
    return 'No “$term”';
  }

  @override
  String get searchCovers =>
      'Search covers the title, the reference number and the place.';

  @override
  String get clearTheFilter => 'Clear the filter';

  @override
  String get nothingRecorded => 'NOTHING RECORDED';

  @override
  String get noMeetingsYet => 'No meetings yet';

  @override
  String get noMeetingsYetDetail =>
      'File a sitting ahead of time, or record one and its minutes will be filed here.';

  @override
  String present(int count) {
    return '$count present';
  }

  @override
  String get statusDraft => 'Draft';

  @override
  String get statusInProgress => 'In progress';

  @override
  String get statusFinalized => 'Finalised';

  @override
  String get statusPendingConfirmation => 'Awaiting confirmation';

  @override
  String get statusApproved => 'Approved';

  @override
  String get stepFinalizeLabel => 'Finalise the minutes';

  @override
  String get stepFinalizeDetail =>
      'Closes them for editing and opens them for approval.';

  @override
  String get stepFinalizeConfirm => 'Finalise';

  @override
  String get stepApproveLabel => 'Approve the minutes';

  @override
  String get stepApproveDetail =>
      'Puts them on the record. This cannot be undone.';

  @override
  String get stepApproveConfirm => 'Approve';

  @override
  String get notYet => 'Not yet';

  @override
  String get summary => 'Summary';

  @override
  String get minutes => 'Minutes';

  @override
  String get resolutions => 'Resolutions';

  @override
  String get attendance => 'Attendance';

  @override
  String get signInDesk => 'Sign-in desk';

  @override
  String get recordSection => 'Record';

  @override
  String get recordThisSitting => 'Record this sitting';

  @override
  String get factDate => 'Date';

  @override
  String get factAt => 'At';

  @override
  String get factRan => 'Ran';

  @override
  String factRanMinutes(int count) {
    return '$count minutes';
  }

  @override
  String get factPresent => 'Present';

  @override
  String factPresentOf(int present, int total) {
    return '$present of $total';
  }

  @override
  String get factKeptBy => 'Kept by';

  @override
  String get factAudio => 'Audio';

  @override
  String get factTranscribed => 'Transcribed';

  @override
  String get factPapers => 'Papers';

  @override
  String factPapersCount(int count) {
    return '$count attached';
  }

  @override
  String get approvedStamp => 'Approved';

  @override
  String get onTheRecord => 'On the record';

  @override
  String movedBy(String name) {
    return 'Moved by $name';
  }

  @override
  String secondedBy(String name) {
    return 'seconded by $name';
  }

  @override
  String get noVote => 'no vote';

  @override
  String get noDate => 'no date';

  @override
  String get apologies => 'Apologies';

  @override
  String get tasks => 'Tasks';

  @override
  String tasksMeta(int count) {
    return '$count OPEN · ASSIGNED TO YOU';
  }

  @override
  String get overdue => 'Overdue';

  @override
  String get dueToday => 'Due today';

  @override
  String get later => 'Later';

  @override
  String get closed => 'CLOSED';

  @override
  String get markComplete => 'Mark complete';

  @override
  String get loadingSection => 'Loading';

  @override
  String get nothingAssigned => 'NOTHING ASSIGNED';

  @override
  String get youAreClear => 'You are clear';

  @override
  String get nothingAssignedDetail =>
      'Action items assigned to you in a meeting will appear here.';

  @override
  String get newMeetingEyebrow => 'NEW MEETING';

  @override
  String get meetingTitle => 'Meeting title';

  @override
  String get titleRequired => 'Title is required';

  @override
  String get gutterDate => 'date';

  @override
  String get noDateSet => 'No date set';

  @override
  String get gutterPlace => 'place';

  @override
  String get locationOptional => 'Location (optional)';

  @override
  String get createMeeting => 'Create meeting';

  @override
  String get typeGeneral => 'General';

  @override
  String get typeBoard => 'Board';

  @override
  String get typeStandUp => 'Stand-up';

  @override
  String get typeClientCall => 'Client Call';

  @override
  String get typeOneOnOne => '1-on-1';

  @override
  String get typeWorkshop => 'Workshop';

  @override
  String get typeRetrospective => 'Retrospective';

  @override
  String get recordInto => 'RECORD INTO';

  @override
  String get sittingNotOnTheList => 'A sitting not on the list';

  @override
  String get orPlanAhead => 'OR PLAN AHEAD';

  @override
  String get setUpAMeeting => 'Set up a meeting';

  @override
  String get gutterNew => 'new';

  @override
  String get gutterPlan => 'plan';

  @override
  String defaultRecordingTitle(String date, String time) {
    return 'Recording, $date at $time';
  }

  @override
  String get organisation => 'Organisation';

  @override
  String get settings => 'Settings';

  @override
  String get settingsNotificationsDetail => 'What reaches this phone, and when';

  @override
  String get language => 'Language';

  @override
  String get languageDetail => 'Bahasa Melayu · English';

  @override
  String get session => 'Session';

  @override
  String get signOut => 'Sign out';

  @override
  String get signOutDetail => 'Recordings already sent stay on the record';

  @override
  String switchToOrg(String name) {
    return 'Switch to $name?';
  }

  @override
  String get switchToOrgDetail =>
      'Meetings, tasks and recordings will all be that organisation’s from here on.';

  @override
  String get switchAction => 'Switch';

  @override
  String get signOutConfirm => 'Sign out?';

  @override
  String get signOutConfirmDetail =>
      'You will need your password again to reach the minute book.';

  @override
  String get cancel => 'Cancel';

  @override
  String get gutterCurrent => 'current';

  @override
  String get gutterSwitch => 'switch';

  @override
  String get openingMicrophone => 'Opening the microphone';

  @override
  String get filingLastAudio => 'Filing the last of the audio';

  @override
  String get minimise => 'Minimise';

  @override
  String get recording => 'RECORDING';

  @override
  String get paused => 'PAUSED';

  @override
  String get starting => 'STARTING';

  @override
  String get retrying => 'RETRYING';

  @override
  String get autosaving => 'AUTOSAVING';

  @override
  String get micCheck => 'MIC CHECK';

  @override
  String get micCheckPrompt =>
      'Ask whoever is sitting furthest away to say something.';

  @override
  String get roomClear => 'CLEAR';

  @override
  String get roomClearDetail => 'Voices are arriving at a good level.';

  @override
  String get roomFaint => 'TOO QUIET';

  @override
  String get roomFaintDetail => 'Move the phone to the middle of the table.';

  @override
  String get roomSilent => 'NO SOUND';

  @override
  String get roomSilentDetail =>
      'Nothing is reaching the microphone. Check that nothing is covering it.';

  @override
  String get recordingNote => 'Recording the sitting';

  @override
  String get recordingNoteFaint => 'Too quiet — move the phone closer';

  @override
  String get recordingNoteSilent => 'No sound is reaching the microphone';

  @override
  String get marksSection => 'MARKS';

  @override
  String get marksEmpty =>
      'Mark a decision as it is carried and it lands here, timed to the second. The marks become the skeleton of the minutes.';

  @override
  String get notYetSent => 'Not yet sent';

  @override
  String get pause => 'Pause';

  @override
  String get resume => 'Resume';

  @override
  String get endRecording => 'End the recording?';

  @override
  String get endRecordingDetail =>
      'The audio still in the queue is sent first, then the minutes are drafted from the transcript.';

  @override
  String get keepRecording => 'Keep recording';

  @override
  String get end => 'End';

  @override
  String get markHint => 'Mark this moment. Hold to choose a kind.';

  @override
  String get markThis => 'Mark this';

  @override
  String get whatWasIt => 'WHAT WAS IT';

  @override
  String get microphoneRefused => 'MICROPHONE REFUSED';

  @override
  String get cannotHearTheRoom => 'antaraNote cannot hear the room';

  @override
  String get microphoneDetail =>
      'Recording needs the microphone. Grant it in Settings and come back to this screen.';

  @override
  String get openSettings => 'Open settings';

  @override
  String get notStarted => 'NOT STARTED';

  @override
  String get sessionDidNotOpen => 'The session did not open';

  @override
  String get sessionDidNotOpenDetail =>
      'Something went wrong reaching the server.';

  @override
  String get close => 'Close';

  @override
  String get filed => 'FILED';

  @override
  String get recordedWithGaps => 'Recorded, with gaps';

  @override
  String get recordedAndFiled => 'Recorded and filed';

  @override
  String get marksCount => 'marks';

  @override
  String get transcriptShort => 'transcript will be short in places';

  @override
  String get noDeskOpen => 'NO DESK OPEN';

  @override
  String get nobodyCanSignIn => 'Nobody can sign themselves in yet';

  @override
  String get deskExplain =>
      'Opening the desk creates a link. Put it on a screen the room can see: it shows a code to scan, and every name lands on it as they sign in. The link closes at the end of the sitting.';

  @override
  String get openTheDesk => 'Open the desk';

  @override
  String get closeTheDesk => 'Close the desk';

  @override
  String get closeTheDeskConfirm => 'Close the desk?';

  @override
  String get closeTheDeskDetail =>
      'The link stops working immediately. Anybody who has not signed in yet will have to be added by hand.';

  @override
  String get keepItOpen => 'Keep it open';

  @override
  String deskOpen(String code) {
    return 'OPEN · $code';
  }

  @override
  String get deskClosed => 'CLOSED';

  @override
  String get signedInCount => 'signed in';

  @override
  String linkCloses(String when) {
    return 'Link closes $when';
  }

  @override
  String get theScreenToShare => 'THE SCREEN TO SHARE';

  @override
  String get share => 'Share';

  @override
  String get copy => 'Copy';

  @override
  String get gutterCode => 'code';

  @override
  String signInTo(String title) {
    return 'Sign in — $title';
  }

  @override
  String get linkCopied => 'Link copied';

  @override
  String get nobodyYet => 'NOBODY YET';

  @override
  String get nobodyYetDetail => 'Names appear here the moment somebody scans.';

  @override
  String get signedInSection => 'SIGNED IN';

  @override
  String get guest => 'guest';

  @override
  String get member => 'member';

  @override
  String get readingTheDesk => 'READING THE DESK';

  @override
  String audioOf(String clock) {
    return '$clock of audio';
  }

  @override
  String markCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count marks',
      one: '1 mark',
    );
    return '$_temp0';
  }

  @override
  String piecesLost(int count) {
    return '$count pieces never reached the server, so the ';
  }

  @override
  String get done => 'Done';

  @override
  String get languageFollowPhone => 'Follow the phone';

  @override
  String get languageFollowPhoneDetail => 'Whatever your device is set to';

  @override
  String get languageEnglish => 'English';

  @override
  String get languageMalay => 'Bahasa Melayu';

  @override
  String get gutterUsing => 'using';

  @override
  String get gutterChoose => 'choose';

  @override
  String get markAllRead => 'Mark all read';

  @override
  String get allRead => 'ALL READ';

  @override
  String unreadCount(int count) {
    return '$count UNREAD';
  }

  @override
  String get upToDate => 'You are up to date';

  @override
  String get nothingWaitingNotifications =>
      'Minutes to approve, tasks assigned to you and mentions all arrive here.';

  @override
  String get notification => 'Notification';

  @override
  String get meeting => 'Meeting';

  @override
  String get prefsIntro =>
      'Push reaches this phone. Email reaches you wherever you read it.';

  @override
  String get prefsPush => 'Push';

  @override
  String get prefsEmail => 'Email';

  @override
  String get readingYourSettings => 'READING YOUR SETTINGS';

  @override
  String get kindAssignedLabel => 'A task lands on you';

  @override
  String get kindAssignedDetail => 'Somebody assigns you an action item';

  @override
  String get kindFinalizedLabel => 'Minutes are finalised';

  @override
  String get kindFinalizedDetail =>
      'A sitting closes for editing and opens for approval';

  @override
  String get kindApprovedLabel => 'Minutes are approved';

  @override
  String get kindApprovedDetail => 'A record is settled and cannot change';

  @override
  String get kindCirculationLabel => 'Something waits for your signature';

  @override
  String get kindCirculationDetail =>
      'Minutes circulated to you for confirmation';

  @override
  String get kindMentionLabel => 'You are mentioned';

  @override
  String get kindMentionDetail => 'In a comment or a note';

  @override
  String get kindTranscriptionLabel => 'A recording finishes transcribing';

  @override
  String get kindTranscriptionDetail => 'The audio has become text';

  @override
  String get markTask => 'task';

  @override
  String get markLate => 'late';

  @override
  String get markSoon => 'soon';

  @override
  String get markDrafted => 'drafted';

  @override
  String get markAudio => 'audio';

  @override
  String get markStale => 'stale';

  @override
  String get markFailed => 'failed';

  @override
  String get markNote => 'note';

  @override
  String get agoNow => 'now';

  @override
  String agoMinutes(int count) {
    return '${count}m';
  }

  @override
  String agoHours(int count) {
    return '${count}h';
  }

  @override
  String agoDays(int count) {
    return '${count}d';
  }

  @override
  String get organisationSwitchFailed => 'Could not switch organisation';

  @override
  String get kindOverdueLabel => 'A task of yours goes overdue';

  @override
  String get kindOverdueDetail => 'Its due date passes and it is still open';

  @override
  String get kindStartingLabel => 'A meeting is about to start';

  @override
  String get kindStartingDetail =>
      'Shortly before a sitting you are invited to';

  @override
  String get kindExtractionLabel => 'Minutes are drafted from a recording';

  @override
  String get kindExtractionDetail => 'The AI has finished reading a transcript';

  @override
  String get kindStaleLabel => 'A decision goes stale';

  @override
  String get kindStaleDetail => 'Something agreed has sat untouched too long';

  @override
  String get kindFailedLabel => 'Processing fails';

  @override
  String get kindFailedDetail => 'A recording could not be transcribed or read';

  @override
  String circulationRound(int round) {
    return 'Round $round';
  }

  @override
  String get approvalsEmptyDetail =>
      'Minutes circulated to you for confirmation appear here until you answer them.';

  @override
  String get unassigned => 'Unassigned';

  @override
  String get unassignedDetail =>
      'Extracted from your sittings with no name on them';

  @override
  String get gutterUnassigned => 'nobody';

  @override
  String showingOf(int shown, int total) {
    return 'Showing $shown of $total. The rest are on the web.';
  }

  @override
  String get ownedElsewhere => 'Owned elsewhere';

  @override
  String get ownedElsewhereDetail =>
      'From your sittings, assigned to somebody without an account here';
}
