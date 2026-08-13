import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../shell/app_shell.dart';
import '../../domain/models/app_notification.dart';

/// The notification list, one cursor page at a time.
///
/// Held as a notifier rather than a FutureProvider because marking one read
/// has to change a single row in place. Refetching the page would reorder
/// nothing but would flash the whole list, and marking all read would throw
/// away the scroll position of somebody halfway down it.
class NotificationsController extends StateNotifier<NotificationsState> {
  NotificationsController(this._ref) : super(const NotificationsState()) {
    unawaited(load());
  }

  final Ref _ref;

  Future<void> load() async {
    state = state.copyWith(loading: true, error: null);

    try {
      final body = await _ref.read(apiClientProvider).get('/notifications');

      state = NotificationsState(items: _rows(body), nextCursor: _cursor(body));
    } catch (error) {
      state = state.copyWith(loading: false, error: error);
    }
  }

  /// Appends the next page. Silent about failure: somebody scrolling has a
  /// list in front of them already, and an error banner over it would replace
  /// something useful with something not.
  Future<void> loadMore() async {
    final cursor = state.nextCursor;
    if (cursor == null || state.loadingMore) return;

    state = state.copyWith(loadingMore: true);

    try {
      final body = await _ref
          .read(apiClientProvider)
          .get('/notifications', query: {'cursor': cursor});

      state = state.copyWith(
        items: [...state.items, ..._rows(body)],
        nextCursor: _cursor(body),
        clearCursor: _cursor(body) == null,
        loadingMore: false,
      );
    } catch (_) {
      state = state.copyWith(loadingMore: false);
    }
  }

  /// Marked locally first. The row greys the instant it is tapped; the server
  /// is told afterwards, and a failure leaves it read on screen rather than
  /// snapping it back under the reader's thumb.
  Future<void> markRead(AppNotification notification) async {
    if (!notification.isUnread) return;

    _replace(notification.markRead(DateTime.now()));

    try {
      await _ref
          .read(apiClientProvider)
          .patch('/notifications/${notification.id}/read');
      _ref.invalidate(bootstrapProvider);
    } catch (_) {
      // Left read. The next load will tell the truth.
    }
  }

  Future<void> markAllRead() async {
    final now = DateTime.now();

    state = state.copyWith(
      items: [
        for (final item in state.items)
          item.isUnread ? item.markRead(now) : item,
      ],
    );

    try {
      await _ref.read(apiClientProvider).post('/notifications/read-all');
      _ref.invalidate(bootstrapProvider);
    } catch (_) {}
  }

  void _replace(AppNotification updated) {
    state = state.copyWith(
      items: [
        for (final item in state.items) item.id == updated.id ? updated : item,
      ],
    );
  }

  List<AppNotification> _rows(Map<String, dynamic> body) =>
      (body['data'] as List?)
          ?.cast<Map<String, dynamic>>()
          .map(AppNotification.fromJson)
          .toList() ??
      const [];

  String? _cursor(Map<String, dynamic> body) =>
      (body['meta'] as Map<String, dynamic>?)?['next_cursor'] as String?;
}

class NotificationsState {
  const NotificationsState({
    this.items = const [],
    this.loading = true,
    this.loadingMore = false,
    this.nextCursor,
    this.error,
  });

  final List<AppNotification> items;
  final bool loading;
  final bool loadingMore;
  final String? nextCursor;
  final Object? error;

  bool get hasMore => nextCursor != null;
  int get unread => items.where((item) => item.isUnread).length;

  NotificationsState copyWith({
    List<AppNotification>? items,
    bool? loading,
    bool? loadingMore,
    String? nextCursor,
    bool clearCursor = false,
    Object? error,
  }) => NotificationsState(
    items: items ?? this.items,
    loading: loading ?? false,
    loadingMore: loadingMore ?? this.loadingMore,
    nextCursor: clearCursor ? null : (nextCursor ?? this.nextCursor),
    error: error,
  );
}

final notificationsProvider =
    StateNotifierProvider.autoDispose<
      NotificationsController,
      NotificationsState
    >(NotificationsController.new);
