import 'package:antaranote/data/api/api_client.dart';
import 'package:antaranote/data/local/secure_store.dart';
import 'package:antaranote/data/repositories/live_repository.dart';
import 'package:antaranote/features/recorder/recorder_controller.dart';
import 'package:antaranote/features/recorder/recorder_screen.dart';
import 'package:antaranote/features/recorder/room_level.dart';
import 'package:antaranote/l10n/app_localizations.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

/// The real controller with its one outward-facing act removed.
///
/// Everything the screen reads off the controller — the meter, the outbox, the
/// lost count — comes from the real object, so this exercises the wiring the
/// screen actually uses. Only [begin] is stubbed, because it opens a
/// microphone and a session, and neither exists in a test.
class _Stub extends RecorderController {
  _Stub(RecorderState initial)
    : super(
        LiveRepository(
          client: ApiClient(store: SecureStore(), dio: Dio()),
        ),
      ) {
    state = initial;
  }

  @override
  Future<void> begin({
    required int meetingId,
    required String title,
    required RoomNotices notices,
  }) async {}
}

void main() {
  // AudioChunker builds an AudioRecorder the moment a controller is
  // constructed, and that reaches for a platform channel. Nothing here opens a
  // microphone; the channel only has to answer.
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
          const MethodChannel('com.llfbandit.record/messages'),
          (_) async => null,
        );
  });

  setUp(() => FlutterSecureStorage.setMockInitialValues({}));

  Future<void> pumpRecorder(WidgetTester tester, RecorderState state) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          recorderControllerProvider.overrideWith((ref) => _Stub(state)),
        ],
        child: MaterialApp(
          localizationsDelegates: L.localizationsDelegates,
          supportedLocales: L.supportedLocales,
          // The beacon repeats forever while recording, so a settle would
          // never return. The widgets already honour reduced motion, and this
          // is the same switch somebody with it turned on would flip.
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context).copyWith(disableAnimations: true),
            child: child!,
          ),
          home: const RecorderScreen(meetingId: 1, title: 'Board meeting'),
        ),
      ),
    );

    await tester.pump();
  }

  // The failure this file exists for. A screen that throws on its first build
  // is invisible to the analyzer, and has shipped twice.
  group('every phase draws something', () {
    for (final phase in RecorderPhase.values) {
      testWidgets('$phase', (tester) async {
        await pumpRecorder(tester, RecorderState(phase: phase));

        expect(tester.takeException(), isNull);
        expect(find.byType(RecorderScreen), findsOneWidget);
      });
    }
  });

  group('the room line', () {
    testWidgets('asks for the far end of the table while it is listening', (
      tester,
    ) async {
      await pumpRecorder(
        tester,
        const RecorderState(phase: RecorderPhase.recording),
      );

      expect(find.text('MIC CHECK'), findsOneWidget);
    });

    testWidgets('says so when the room is too quiet', (tester) async {
      await pumpRecorder(
        tester,
        const RecorderState(
          phase: RecorderPhase.recording,
          check: RoomVerdict.faint,
          room: RoomVerdict.faint,
        ),
      );

      expect(find.text('TOO QUIET'), findsOneWidget);
    });

    testWidgets('says so when nothing is reaching the microphone', (
      tester,
    ) async {
      await pumpRecorder(
        tester,
        const RecorderState(
          phase: RecorderPhase.recording,
          check: RoomVerdict.silent,
          room: RoomVerdict.silent,
        ),
      );

      expect(find.text('NO SOUND'), findsOneWidget);
    });

    // Reassurance that stays becomes furniture, so it goes once the opening of
    // the sitting is past.
    testWidgets('stops congratulating a good room after a while', (
      tester,
    ) async {
      await pumpRecorder(
        tester,
        const RecorderState(
          phase: RecorderPhase.recording,
          check: RoomVerdict.clear,
          room: RoomVerdict.clear,
          elapsed: Duration(minutes: 4),
        ),
      );

      expect(find.text('CLEAR'), findsNothing);
      expect(find.text('MIC CHECK'), findsNothing);
    });

    // The room being hard to hear outranks the opening check: it is the same
    // finding, arrived at later and with more evidence behind it.
    testWidgets('a quiet room outranks the opening check', (tester) async {
      await pumpRecorder(
        tester,
        const RecorderState(
          phase: RecorderPhase.recording,
          check: RoomVerdict.listening,
          room: RoomVerdict.faint,
        ),
      );

      expect(find.text('TOO QUIET'), findsOneWidget);
      expect(find.text('MIC CHECK'), findsNothing);
    });
  });
}
