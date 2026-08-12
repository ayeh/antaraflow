import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../core/providers.dart';
import '../../domain/models/attendance.dart';

/// The desk for one meeting, re-read on a timer while the screen is open.
///
/// Polled rather than pushed: the same endpoint the web lobby polls, at the
/// same cadence, and a websocket for a list that grows a handful of times an
/// hour would be a second transport to keep alive for no gain.
///
/// `autoDispose` matters — the timer must die with the screen, or a phone in a
/// pocket keeps asking the server who else arrived.
final attendanceProvider =
    StreamProvider.autoDispose.family<AttendanceDesk, int>((ref, meetingId) {
  final client = ref.watch(apiClientProvider);

  Future<AttendanceDesk> read() async => AttendanceDesk.fromJson(
        await client.get('/meetings/$meetingId/qr-registration'),
      );

  late final StreamController<AttendanceDesk> controller;
  Timer? timer;
  var inFlight = false;
  var settled = false;

  Future<void> poll() async {
    // A slow response must not stack requests behind it.
    if (inFlight || controller.isClosed) return;
    inFlight = true;

    try {
      final desk = await read();

      if (!controller.isClosed) {
        settled = true;
        controller.add(desk);
      }
    } catch (error, stack) {
      // Only the first read is fatal. Once a desk is on screen, a dropped
      // request is a blip in a room with bad signal, not a reason to replace
      // the list of people standing in front of you with an error page.
      if (!settled && !controller.isClosed) {
        controller.addError(error, stack);
      }
    } finally {
      inFlight = false;
    }
  }

  controller = StreamController<AttendanceDesk>(
    onListen: () {
      unawaited(poll());
      timer = Timer.periodic(const Duration(seconds: 3), (_) => poll());
    },
    onCancel: () => timer?.cancel(),
  );

  ref.onDispose(() {
    timer?.cancel();
    controller.close();
  });

  return controller.stream;
});

/// Opening and closing the desk.
final attendanceDeskProvider = Provider.autoDispose<AttendanceDeskActions>(
  AttendanceDeskActions.new,
);

class AttendanceDeskActions {
  const AttendanceDeskActions(this._ref);

  final Ref _ref;

  Future<AttendanceToken> open(int meetingId) async {
    final body = await _ref.read(apiClientProvider).post(
      '/meetings/$meetingId/qr-registration',
      body: {'client_id': const Uuid().v4()},
    );

    _ref.invalidate(attendanceProvider(meetingId));

    return AttendanceToken.fromJson(body);
  }

  Future<void> close(int meetingId) async {
    await _ref
        .read(apiClientProvider)
        .delete('/meetings/$meetingId/qr-registration');

    _ref.invalidate(attendanceProvider(meetingId));
  }
}
