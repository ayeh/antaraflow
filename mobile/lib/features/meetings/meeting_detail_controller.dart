import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../../domain/models/meeting_detail.dart';
import '../shell/app_shell.dart';
import 'meetings_screen.dart';

/// One sitting, in full.
///
/// Keyed by id and auto-disposed: a board member scrolling through a year of
/// minutes should not accumulate every one of them in memory.
final meetingDetailProvider = AsyncNotifierProvider.autoDispose
    .family<MeetingDetailNotifier, MeetingDetail, int>(
      MeetingDetailNotifier.new,
    );

class MeetingDetailNotifier
    extends AutoDisposeFamilyAsyncNotifier<MeetingDetail, int> {
  @override
  Future<MeetingDetail> build(int arg) async {
    final body = await ref.read(apiClientProvider).get('/meetings/$arg');

    // The detail endpoint answers with the resource at the top level, not
    // wrapped in `data` the way the collections are.
    final json = body['data'] is Map<String, dynamic>
        ? body['data'] as Map<String, dynamic>
        : body;

    return MeetingDetail.fromJson(json);
  }

  /// Moves the record to its next state — finalised, then approved.
  ///
  /// The server answers with the whole record, so the screen never has to
  /// guess what changed; and every list that showed the old status is dropped
  /// rather than left to drift.
  Future<void> take(MeetingStep step) async {
    await ref.read(apiClientProvider).post('/meetings/$arg/${step.path}');

    // The mutation answers with the record, but built from `fresh()` with no
    // relations loaded — so attendees, resolutions and action items all come
    // back empty. Reading that response would blank half the screen at the
    // exact moment somebody has just committed to it. One extra request on a
    // once-per-record action is the cheaper mistake.
    state = await AsyncValue.guard(() => build(arg));

    ref
      ..invalidate(meetingsProvider)
      ..invalidate(bootstrapProvider);
  }
}
