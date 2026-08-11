import 'organization.dart';
import 'user.dart';

/// The result of signing in, and of `auth/me` on a cold start.
class AuthSession {
  const AuthSession({
    required this.user,
    required this.organizations,
    this.token,
    this.expiresAt,
    this.abilities = const [],
  });

  factory AuthSession.fromJson(Map<String, dynamic> json) => AuthSession(
    token: json['token'] as String?,
    expiresAt: DateTime.tryParse(json['expires_at'] as String? ?? ''),
    user: AppUser.fromJson(json['user'] as Map<String, dynamic>),
    organizations: (json['organizations'] as List? ?? [])
        .map((e) => Organization.fromJson(e as Map<String, dynamic>))
        .toList(),
    abilities: (json['abilities'] as List? ?? []).cast<String>(),
  );

  final String? token;
  final DateTime? expiresAt;
  final AppUser user;
  final List<Organization> organizations;
  final List<String> abilities;

  Organization? get currentOrganization {
    for (final organization in organizations) {
      if (organization.isCurrent) return organization;
    }
    return organizations.isEmpty ? null : organizations.first;
  }

  bool can(String ability) => abilities.contains(ability);

  /// True once the token is close enough to expiry that the app should refresh
  /// rather than wait for a 401 in the middle of something.
  bool get needsRefresh {
    final expiry = expiresAt;
    if (expiry == null) return false;

    return expiry.difference(DateTime.now()) < const Duration(days: 7);
  }
}
