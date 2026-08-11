import 'package:antaranote/domain/models/bootstrap.dart';
import 'package:antaranote/domain/models/session.dart';
import 'package:antaranote/domain/models/user.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('AuthSession', () {
    Map<String, dynamic> payload({
      String? expiresAt,
      List<Map<String, dynamic>>? organizations,
    }) => {
      'token': '12|abc',
      'expires_at': expiresAt,
      'user': {'id': 42, 'name': 'Ariff Hakim', 'email': 'ariff@example.com'},
      'organizations':
          organizations ??
          [
            {'id': 3, 'name': 'Antara', 'role': 'owner', 'is_current': true},
            {'id': 8, 'name': 'Rocketweb', 'role': 'member'},
          ],
      'abilities': ['meetings:read', 'votes:cast'],
    };

    test('picks the organization the server flagged as current', () {
      final session = AuthSession.fromJson(payload());

      expect(session.currentOrganization?.id, 3);
    });

    test('falls back to the first organization when none is flagged', () {
      final session = AuthSession.fromJson(
        payload(
          organizations: [
            {'id': 8, 'name': 'Rocketweb', 'role': 'member'},
          ],
        ),
      );

      expect(session.currentOrganization?.id, 8);
    });

    test('has no current organization when the list is empty', () {
      final session = AuthSession.fromJson(payload(organizations: []));

      expect(session.currentOrganization, isNull);
    });

    test('asks for a refresh once expiry is inside a week', () {
      final session = AuthSession.fromJson(
        payload(
          expiresAt: DateTime.now().add(const Duration(days: 3)).toIso8601String(),
        ),
      );

      expect(session.needsRefresh, isTrue);
    });

    test('does not ask for a refresh while expiry is far off', () {
      final session = AuthSession.fromJson(
        payload(
          expiresAt: DateTime.now()
              .add(const Duration(days: 60))
              .toIso8601String(),
        ),
      );

      expect(session.needsRefresh, isFalse);
    });

    test('a token without an expiry never asks for a refresh', () {
      expect(AuthSession.fromJson(payload()).needsRefresh, isFalse);
    });

    test('reports the abilities the token was granted', () {
      final session = AuthSession.fromJson(payload());

      expect(session.can('votes:cast'), isTrue);
      expect(session.can('billing:manage'), isFalse);
    });
  });

  group('AppUser initials', () {
    AppUser user(String name, {String email = 'someone@example.com'}) =>
        AppUser.fromJson({'id': 1, 'name': name, 'email': email});

    test('takes the first and last word', () {
      expect(user('Ariff Hakim').initials, 'AH');
      expect(user('Dato Seri Rahim Abdullah').initials, 'DA');
    });

    test('uses one letter for a single word', () {
      expect(user('Ariff').initials, 'A');
    });

    test('falls back to the email when the name is blank', () {
      expect(user('   ', email: 'ariff.hakim@example.com').initials, 'AH');
    });
  });

  group('BootstrapData', () {
    test('reads capabilities and badge counts', () {
      final data = BootstrapData.fromJson({
        'user': {'id': 1, 'name': 'Ariff', 'email': 'a@b.com'},
        'organization': {'id': 3, 'name': 'Antara', 'brand_color': '#0D7377'},
        'subscription': {'plan': 'business'},
        'capabilities': {'transcription': true, 'voting': true},
        'unread': {
          'notifications': 4,
          'action_items_due': 3,
          'pending_approvals': 1,
        },
      });

      expect(data.planSlug, 'business');
      expect(data.brandColor, '#0D7377');
      expect(data.capabilities.transcription, isTrue);
      expect(data.capabilities.annotations, isFalse);
      expect(data.unread.total, 8);
    });

    test('treats missing capabilities as switched off', () {
      final data = BootstrapData.fromJson({
        'user': {'id': 1, 'name': 'Ariff', 'email': 'a@b.com'},
        'organization': {'id': 3, 'name': 'Antara'},
      });

      expect(data.capabilities.aiEnabled, isFalse);
      expect(data.unread.total, 0);
    });
  });
}
