// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Malay (`ms`).
class LMs extends L {
  LMs([String locale = 'ms']) : super(locale);

  @override
  String get notifications => 'Notifikasi';

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

  @override
  String get appTagline => 'Minit,\nditandatangani dan dimuktamadkan.';

  @override
  String get signIn => 'LOG MASUK';

  @override
  String get email => 'E-mel';

  @override
  String get emailHint => 'Masukkan alamat e-mel anda';

  @override
  String get password => 'Kata laluan';

  @override
  String get passwordHint => 'Masukkan kata laluan anda';

  @override
  String get showPassword => 'Tunjuk kata laluan';

  @override
  String get hidePassword => 'Sembunyi kata laluan';

  @override
  String get signInAction => 'Log masuk';

  @override
  String get forgotPassword => 'Lupa kata laluan?';

  @override
  String get updateRequired => 'Kemas kini diperlukan';

  @override
  String get updateGeneric => 'Sila kemas kini antaraNote untuk meneruskan.';

  @override
  String updateVersioned(String version) {
    return 'antaraNote $version atau lebih baharu diperlukan untuk meneruskan.';
  }

  @override
  String get openTheStore => 'Buka gedung';

  @override
  String get notBuiltYet => 'Belum dibina';

  @override
  String get firstRunOneEyebrow => 'Butang merah';

  @override
  String get firstRunOneLine => 'Ia merakam bilik, bukan panggilan.';

  @override
  String get firstRunOneBody =>
      'Letak telefon menghadap ke atas di atas meja dan tekan rakam. antaraNote mendengar melalui mikrofon — tiada bot untuk dijemput dan tiada apa-apa untuk dipasang oleh orang lain.';

  @override
  String get firstRunTwoEyebrow => 'Semasa ia berjalan';

  @override
  String get firstRunTwoLine => 'Tandai keputusan sebaik ia diputuskan.';

  @override
  String get firstRunTwoBody =>
      'Satu ketukan pada “Tandai ini” mencatat saat anda mendengarnya. Tanda itu menjadi rangka minit, jadi anda tidak perlu membina semula sejam perbincangan daripada ingatan.';

  @override
  String get firstRunThreeEyebrow => 'Selepas itu';

  @override
  String get firstRunThreeLine => 'Minit, bernombor dan diedarkan.';

  @override
  String get firstRunThreeBody =>
      'Rakaman menjadi transkrip, transkrip menjadi draf, dan draf diedarkan untuk pengesahan. Semua yang hadir perlu diberitahu bahawa mereka sedang dirakam — bahagian itu masih tanggungjawab anda.';

  @override
  String get start => 'Mula';

  @override
  String get somethingWentWrong => 'Ada sesuatu yang tidak kena.';

  @override
  String get tryAgain => 'Cuba lagi';

  @override
  String get offline =>
      'Tiada sambungan. Ia akan dicuba semula secara automatik.';

  @override
  String get tabHome => 'Utama';

  @override
  String get tabMeetings => 'Mesyuarat';

  @override
  String get tabTasks => 'Tugasan';

  @override
  String get tabMe => 'Saya';

  @override
  String get record => 'Rakam';

  @override
  String get next => 'Seterusnya';

  @override
  String get skip => 'Langkau';

  @override
  String get startRecording => 'Mula merakam';

  @override
  String get meetings => 'Mesyuarat';

  @override
  String get searchMeetings => 'Cari';

  @override
  String get closeSearch => 'Tutup carian';

  @override
  String get newMeeting => 'Mesyuarat baharu';

  @override
  String get searchHint => 'Tajuk, nombor atau tempat';

  @override
  String get filterAll => 'Semua';

  @override
  String countOf(int shown, int total) {
    return '$shown DARIPADA $total';
  }

  @override
  String meetingCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count MESYUARAT',
      one: '1 MESYUARAT',
    );
    return '$_temp0';
  }

  @override
  String get loading => 'MEMUATKAN';

  @override
  String get nothingMatches => 'TIADA PADANAN';

  @override
  String get noMeetingsInState => 'Tiada mesyuarat dalam keadaan itu';

  @override
  String noMatchFor(String term) {
    return 'Tiada “$term”';
  }

  @override
  String get searchCovers =>
      'Carian meliputi tajuk, nombor rujukan dan tempat.';

  @override
  String get clearTheFilter => 'Kosongkan penapis';

  @override
  String get nothingRecorded => 'TIADA REKOD';

  @override
  String get noMeetingsYet => 'Belum ada mesyuarat';

  @override
  String get noMeetingsYetDetail =>
      'Failkan mesyuarat lebih awal, atau rakam satu dan minitnya akan difailkan di sini.';

  @override
  String present(int count) {
    return '$count hadir';
  }

  @override
  String get statusDraft => 'Draf';

  @override
  String get statusInProgress => 'Sedang berjalan';

  @override
  String get statusFinalized => 'Dimuktamadkan';

  @override
  String get statusPendingConfirmation => 'Menunggu pengesahan';

  @override
  String get statusApproved => 'Diluluskan';

  @override
  String get stepFinalizeLabel => 'Muktamadkan minit';

  @override
  String get stepFinalizeDetail =>
      'Menutupnya daripada suntingan dan membukanya untuk kelulusan.';

  @override
  String get stepFinalizeConfirm => 'Muktamadkan';

  @override
  String get stepApproveLabel => 'Luluskan minit';

  @override
  String get stepApproveDetail =>
      'Merekodkannya secara rasmi. Ini tidak boleh dibatalkan.';

  @override
  String get stepApproveConfirm => 'Luluskan';

  @override
  String get notYet => 'Belum lagi';

  @override
  String get summary => 'Ringkasan';

  @override
  String get minutes => 'Minit';

  @override
  String get resolutions => 'Resolusi';

  @override
  String get attendance => 'Kehadiran';

  @override
  String get signInDesk => 'Kaunter pendaftaran';

  @override
  String get recordSection => 'Rakaman';

  @override
  String get recordThisSitting => 'Rakam mesyuarat ini';

  @override
  String get factDate => 'Tarikh';

  @override
  String get factAt => 'Tempat';

  @override
  String get factRan => 'Tempoh';

  @override
  String factRanMinutes(int count) {
    return '$count minit';
  }

  @override
  String get factPresent => 'Hadir';

  @override
  String factPresentOf(int present, int total) {
    return '$present daripada $total';
  }

  @override
  String get factKeptBy => 'Dicatat oleh';

  @override
  String get factAudio => 'Audio';

  @override
  String get factTranscribed => 'Ditranskripsi';

  @override
  String get factPapers => 'Dokumen';

  @override
  String factPapersCount(int count) {
    return '$count dilampirkan';
  }

  @override
  String get approvedStamp => 'Diluluskan';

  @override
  String get onTheRecord => 'Termaktub dalam rekod';

  @override
  String movedBy(String name) {
    return 'Dicadangkan oleh $name';
  }

  @override
  String secondedBy(String name) {
    return 'disokong oleh $name';
  }

  @override
  String get noVote => 'tiada undian';

  @override
  String get noDate => 'tiada tarikh';

  @override
  String get apologies => 'Mohon maaf';

  @override
  String get tasks => 'Tugasan';

  @override
  String tasksMeta(int count) {
    return '$count TERBUKA · DITUGASKAN KEPADA ANDA';
  }

  @override
  String get overdue => 'Tertunggak';

  @override
  String get dueToday => 'Perlu hari ini';

  @override
  String get later => 'Kemudian';

  @override
  String get closed => 'SELESAI';

  @override
  String get markComplete => 'Tandakan selesai';

  @override
  String get loadingSection => 'Memuatkan';

  @override
  String get nothingAssigned => 'TIADA TUGASAN';

  @override
  String get youAreClear => 'Anda bebas';

  @override
  String get nothingAssignedDetail =>
      'Tindakan yang ditugaskan kepada anda dalam mesyuarat akan muncul di sini.';

  @override
  String get newMeetingEyebrow => 'MESYUARAT BAHARU';

  @override
  String get meetingTitle => 'Tajuk mesyuarat';

  @override
  String get titleRequired => 'Tajuk diperlukan';

  @override
  String get gutterDate => 'tarikh';

  @override
  String get noDateSet => 'Tiada tarikh ditetapkan';

  @override
  String get gutterPlace => 'tempat';

  @override
  String get locationOptional => 'Tempat (pilihan)';

  @override
  String get createMeeting => 'Cipta mesyuarat';

  @override
  String get typeGeneral => 'Umum';

  @override
  String get typeBoard => 'Lembaga';

  @override
  String get typeStandUp => 'Ringkas';

  @override
  String get typeClientCall => 'Panggilan Klien';

  @override
  String get typeOneOnOne => 'Empat Mata';

  @override
  String get typeWorkshop => 'Bengkel';

  @override
  String get typeRetrospective => 'Retrospektif';

  @override
  String get recordInto => 'RAKAM KE DALAM';

  @override
  String get sittingNotOnTheList => 'Mesyuarat yang tiada dalam senarai';

  @override
  String get orPlanAhead => 'ATAU RANCANG AWAL';

  @override
  String get setUpAMeeting => 'Sediakan mesyuarat';

  @override
  String get gutterNew => 'baharu';

  @override
  String get gutterPlan => 'rancang';

  @override
  String defaultRecordingTitle(String date, String time) {
    return 'Rakaman, $date pada $time';
  }

  @override
  String get organisation => 'Organisasi';

  @override
  String get settings => 'Tetapan';

  @override
  String get settingsNotificationsDetail =>
      'Apa yang sampai ke telefon ini, dan bila';

  @override
  String get language => 'Bahasa';

  @override
  String get languageDetail => 'Bahasa Melayu · English';

  @override
  String get session => 'Sesi';

  @override
  String get signOut => 'Log keluar';

  @override
  String get signOutDetail => 'Rakaman yang telah dihantar kekal dalam rekod';

  @override
  String switchToOrg(String name) {
    return 'Tukar ke $name?';
  }

  @override
  String get switchToOrgDetail =>
      'Mesyuarat, tugasan dan rakaman semuanya milik organisasi itu selepas ini.';

  @override
  String get switchAction => 'Tukar';

  @override
  String get signOutConfirm => 'Log keluar?';

  @override
  String get signOutConfirmDetail =>
      'Anda perlukan kata laluan sekali lagi untuk kembali ke buku minit.';

  @override
  String get cancel => 'Batal';

  @override
  String get gutterCurrent => 'semasa';

  @override
  String get gutterSwitch => 'tukar';

  @override
  String get openingMicrophone => 'Membuka mikrofon';

  @override
  String get filingLastAudio => 'Memfailkan baki audio';

  @override
  String get minimise => 'Kecilkan';

  @override
  String get recording => 'MEREKAM';

  @override
  String get paused => 'DIJEDA';

  @override
  String get starting => 'MEMULAKAN';

  @override
  String get retrying => 'MENCUBA SEMULA';

  @override
  String get autosaving => 'SIMPAN AUTO';

  @override
  String get marksSection => 'TANDA';

  @override
  String get marksEmpty =>
      'Tandai keputusan sebaik ia diputuskan dan ia akan mendarat di sini, tepat pada saatnya. Tanda itu menjadi rangka minit.';

  @override
  String get notYetSent => 'Belum dihantar';

  @override
  String get pause => 'Jeda';

  @override
  String get resume => 'Sambung';

  @override
  String get endRecording => 'Tamatkan rakaman?';

  @override
  String get endRecordingDetail =>
      'Audio yang masih dalam barisan dihantar dahulu, kemudian minit dirangka daripada transkrip.';

  @override
  String get keepRecording => 'Terus merakam';

  @override
  String get end => 'Tamat';

  @override
  String get markHint => 'Tandai saat ini. Tahan untuk pilih jenis.';

  @override
  String get markThis => 'Tandai ini';

  @override
  String get whatWasIt => 'APA ITU TADI';

  @override
  String get microphoneRefused => 'MIKROFON DITOLAK';

  @override
  String get cannotHearTheRoom => 'antaraNote tidak dapat mendengar bilik';

  @override
  String get microphoneDetail =>
      'Rakaman memerlukan mikrofon. Benarkan dalam Tetapan dan kembali ke skrin ini.';

  @override
  String get openSettings => 'Buka tetapan';

  @override
  String get notStarted => 'TIDAK BERMULA';

  @override
  String get sessionDidNotOpen => 'Sesi tidak dapat dibuka';

  @override
  String get sessionDidNotOpenDetail =>
      'Ada masalah semasa menghubungi pelayan.';

  @override
  String get close => 'Tutup';

  @override
  String get filed => 'DIFAILKAN';

  @override
  String get recordedWithGaps => 'Dirakam, dengan jurang';

  @override
  String get recordedAndFiled => 'Dirakam dan difailkan';

  @override
  String get marksCount => 'tanda';

  @override
  String get transcriptShort => 'transkrip akan pendek di beberapa bahagian';

  @override
  String get noDeskOpen => 'KAUNTER BELUM DIBUKA';

  @override
  String get nobodyCanSignIn => 'Belum ada sesiapa boleh mendaftar sendiri';

  @override
  String get deskExplain =>
      'Membuka kaunter mencipta satu pautan. Paparkannya pada skrin yang boleh dilihat sebilik: ia menunjukkan kod untuk diimbas, dan setiap nama akan muncul di situ sebaik mereka mendaftar. Pautan itu tutup di penghujung mesyuarat.';

  @override
  String get openTheDesk => 'Buka kaunter';

  @override
  String get closeTheDesk => 'Tutup kaunter';

  @override
  String get closeTheDeskConfirm => 'Tutup kaunter?';

  @override
  String get closeTheDeskDetail =>
      'Pautan itu berhenti berfungsi serta-merta. Sesiapa yang belum mendaftar terpaksa ditambah secara manual.';

  @override
  String get keepItOpen => 'Biarkan terbuka';

  @override
  String deskOpen(String code) {
    return 'BUKA · $code';
  }

  @override
  String get deskClosed => 'TUTUP';

  @override
  String get signedInCount => 'telah mendaftar';

  @override
  String linkCloses(String when) {
    return 'Pautan tutup $when';
  }

  @override
  String get theScreenToShare => 'SKRIN UNTUK DIKONGSI';

  @override
  String get share => 'Kongsi';

  @override
  String get copy => 'Salin';

  @override
  String get gutterCode => 'kod';

  @override
  String signInTo(String title) {
    return 'Daftar masuk — $title';
  }

  @override
  String get linkCopied => 'Pautan disalin';

  @override
  String get nobodyYet => 'BELUM ADA SESIAPA';

  @override
  String get nobodyYetDetail =>
      'Nama akan muncul di sini sebaik ada yang mengimbas.';

  @override
  String get signedInSection => 'TELAH MENDAFTAR';

  @override
  String get guest => 'tetamu';

  @override
  String get member => 'ahli';

  @override
  String get readingTheDesk => 'MEMBACA KAUNTER';

  @override
  String audioOf(String clock) {
    return '$clock audio';
  }

  @override
  String markCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count tanda',
      one: '1 tanda',
    );
    return '$_temp0';
  }

  @override
  String piecesLost(int count) {
    return '$count bahagian tidak sampai ke pelayan, jadi ';
  }

  @override
  String get done => 'Selesai';

  @override
  String get languageFollowPhone => 'Ikut telefon';

  @override
  String get languageFollowPhoneDetail => 'Mengikut tetapan peranti anda';

  @override
  String get languageEnglish => 'English';

  @override
  String get languageMalay => 'Bahasa Melayu';

  @override
  String get gutterUsing => 'guna';

  @override
  String get gutterChoose => 'pilih';

  @override
  String get markAllRead => 'Tanda semua dibaca';

  @override
  String get allRead => 'SEMUA DIBACA';

  @override
  String unreadCount(int count) {
    return '$count BELUM DIBACA';
  }

  @override
  String get upToDate => 'Anda sudah terkini';

  @override
  String get nothingWaitingNotifications =>
      'Minit untuk diluluskan, tugasan yang diberikan kepada anda dan sebutan semuanya sampai di sini.';

  @override
  String get notification => 'Pemberitahuan';

  @override
  String get meeting => 'Mesyuarat';

  @override
  String get prefsIntro =>
      'Push sampai ke telefon ini. E-mel sampai kepada anda di mana sahaja anda membacanya.';

  @override
  String get prefsPush => 'Push';

  @override
  String get prefsEmail => 'E-mel';

  @override
  String get readingYourSettings => 'MEMBACA TETAPAN ANDA';

  @override
  String get kindAssignedLabel => 'Tugasan diberikan kepada anda';

  @override
  String get kindAssignedDetail => 'Seseorang menugaskan tindakan kepada anda';

  @override
  String get kindFinalizedLabel => 'Minit dimuktamadkan';

  @override
  String get kindFinalizedDetail =>
      'Mesyuarat ditutup daripada suntingan dan dibuka untuk kelulusan';

  @override
  String get kindApprovedLabel => 'Minit diluluskan';

  @override
  String get kindApprovedDetail =>
      'Rekod telah dimuktamadkan dan tidak boleh diubah';

  @override
  String get kindCirculationLabel => 'Ada yang menunggu tandatangan anda';

  @override
  String get kindCirculationDetail =>
      'Minit diedarkan kepada anda untuk pengesahan';

  @override
  String get kindMentionLabel => 'Anda disebut';

  @override
  String get kindMentionDetail => 'Dalam komen atau nota';

  @override
  String get kindTranscriptionLabel => 'Rakaman selesai ditranskripsi';

  @override
  String get kindTranscriptionDetail => 'Audio telah menjadi teks';

  @override
  String get markTask => 'tugas';

  @override
  String get markLate => 'lewat';

  @override
  String get markSoon => 'akan';

  @override
  String get markDrafted => 'draf';

  @override
  String get markAudio => 'audio';

  @override
  String get markStale => 'basi';

  @override
  String get markFailed => 'gagal';

  @override
  String get markNote => 'nota';

  @override
  String get agoNow => 'kini';

  @override
  String agoMinutes(int count) {
    return '${count}m';
  }

  @override
  String agoHours(int count) {
    return '${count}j';
  }

  @override
  String agoDays(int count) {
    return '${count}h';
  }

  @override
  String get organisationSwitchFailed => 'Tidak dapat menukar organisasi';

  @override
  String get kindOverdueLabel => 'Tugasan anda tertunggak';

  @override
  String get kindOverdueDetail =>
      'Tarikh akhirnya berlalu dan ia masih terbuka';

  @override
  String get kindStartingLabel => 'Mesyuarat hampir bermula';

  @override
  String get kindStartingDetail =>
      'Sebentar sebelum mesyuarat yang anda dijemput';

  @override
  String get kindExtractionLabel => 'Minit dirangka daripada rakaman';

  @override
  String get kindExtractionDetail => 'AI selesai membaca transkrip';

  @override
  String get kindStaleLabel => 'Keputusan menjadi basi';

  @override
  String get kindStaleDetail =>
      'Sesuatu yang dipersetujui terbiar terlalu lama';

  @override
  String get kindFailedLabel => 'Pemprosesan gagal';

  @override
  String get kindFailedDetail =>
      'Rakaman tidak dapat ditranskripsi atau dibaca';

  @override
  String circulationRound(int round) {
    return 'Pusingan $round';
  }

  @override
  String get approvalsEmptyDetail =>
      'Minit yang diedarkan kepada anda untuk pengesahan muncul di sini sehingga anda menjawabnya.';

  @override
  String get unassigned => 'Belum ditugaskan';

  @override
  String get unassignedDetail =>
      'Diekstrak daripada mesyuarat anda tanpa nama padanya';

  @override
  String get gutterUnassigned => 'belum';
}
