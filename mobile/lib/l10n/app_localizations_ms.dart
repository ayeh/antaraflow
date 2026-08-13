// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Malay (`ms`).
class LMs extends L {
  LMs([String locale = 'ms']) : super(locale);

  @override
  String get notifications => 'Pemberitahuan';

  @override
  String get back => 'Kembali';

  @override
  String get goodMorning => 'Selamat pagi';

  @override
  String get goodAfternoon => 'Selamat petang';

  @override
  String get goodEvening => 'Selamat malam';

  @override
  String get homeDateLine => 'EEEE d MMMM · HH:mm';

  @override
  String get standingClearPrefix => 'Anda ';

  @override
  String get standingClearMarked => 'tiada tugasan';

  @override
  String get standingClearSuffix => ' hari ini.';

  @override
  String get standingDuePrefix => 'Anda ada ';

  @override
  String get standingDueMarkedTail => ' perlu tindakan';

  @override
  String get standingDueSuffix => ' hari ini.';

  @override
  String get standingClearDetail =>
      'Tiada yang tertunggak dan tiada yang menunggu kelulusan anda.';

  @override
  String get standingDueDetail =>
      'Selebihnya boleh tunggu sehingga ini selesai.';

  @override
  String get upNext => 'Seterusnya';

  @override
  String get nothingScheduled => 'Tiada dijadualkan';

  @override
  String get nothingScheduledDetail =>
      'Mesyuarat yang anda dijemput akan muncul di sini.';

  @override
  String get diaryUnavailable => 'Diari tidak dapat dimuatkan';

  @override
  String get pullToRetry => 'Tarik ke bawah untuk cuba lagi.';

  @override
  String get waitingOnYou => 'Menunggu anda';

  @override
  String get nothingWaiting => 'Tiada yang menunggu';

  @override
  String get nothingWaitingDetail =>
      'Kelulusan dan perkara tertunggak akan sampai di sini.';

  @override
  String get actionItems => 'Tindakan';

  @override
  String get actionItemsDetail => 'Perlu diselesaikan hari ini atau lebih awal';

  @override
  String get minutesToApprove => 'Minit untuk diluluskan';

  @override
  String get minutesToApproveDetail => 'Diedarkan kepada anda, belum dijawab';

  @override
  String get gutterToday => 'hari ini';

  @override
  String get gutterUndated => 'tiada tarikh';

  @override
  String get gutterNil => 'nil';

  @override
  String get gutterNow => 'kini';

  @override
  String get gutterDue => 'perlu';

  @override
  String get gutterOpen => 'terbuka';
}
