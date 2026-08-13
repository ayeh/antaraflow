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
