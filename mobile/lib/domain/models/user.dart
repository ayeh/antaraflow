class AppUser {
  const AppUser({
    required this.id,
    required this.name,
    required this.email,
    this.avatarUrl,
    this.locale,
    this.timezone,
  });

  factory AppUser.fromJson(Map<String, dynamic> json) => AppUser(
    id: json['id'] as int,
    name: json['name'] as String? ?? '',
    email: json['email'] as String? ?? '',
    avatarUrl: json['avatar_url'] as String?,
    locale: json['locale'] as String?,
    timezone: json['timezone'] as String?,
  );

  final int id;
  final String name;
  final String email;
  final String? avatarUrl;
  final String? locale;
  final String? timezone;

  /// Shown in place of an avatar. Falls back to the email so a person with a
  /// blank name still gets something recognisable rather than a question mark.
  String get initials {
    // Only the local part of an email is a name; including the domain would
    // turn ariff.hakim@example.com into "AC".
    final source = name.trim().isNotEmpty
        ? name.trim()
        : email.trim().split('@').first;

    final parts = source.split(RegExp(r'[\s._-]+')).where((p) => p.isNotEmpty);

    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();

    return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
        .toUpperCase();
  }
}
