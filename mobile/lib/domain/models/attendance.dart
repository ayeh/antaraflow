/// The registration link for a sitting, and who has walked through it.
///
/// One active token per meeting: generating a second one closes the first, so
/// a link that was shared into the wrong group stops working the moment a new
/// one is made.
class AttendanceToken {
  const AttendanceToken({
    required this.token,
    required this.lobbyUrl,
    required this.registrationUrl,
    required this.registrationsCount,
    this.joinCode,
    this.expiresAt,
    this.maxAttendees,
  });

  factory AttendanceToken.fromJson(Map<String, dynamic> json) =>
      AttendanceToken(
        token: json['token'] as String,
        lobbyUrl: json['lobby_url'] as String? ?? '',
        registrationUrl: json['qr_payload'] as String? ?? '',
        registrationsCount:
            (json['registrations_count'] as num?)?.toInt() ?? 0,
        joinCode: json['join_code'] as String?,
        expiresAt: DateTime.tryParse(json['expires_at'] as String? ?? ''),
        maxAttendees: (json['max_attendees'] as num?)?.toInt(),
      );

  final String token;

  /// The screen everybody looks at: it renders the QR and the names as they
  /// land. This is what gets shared, not the registration link.
  final String lobbyUrl;

  /// What the QR itself encodes — the form a scanner is taken to.
  final String registrationUrl;

  final int registrationsCount;
  final String? joinCode;
  final DateTime? expiresAt;
  final int? maxAttendees;

  bool get isFull =>
      maxAttendees != null && registrationsCount >= maxAttendees!;

  bool get hasExpired =>
      expiresAt != null && expiresAt!.isBefore(DateTime.now());
}

/// Somebody who has checked in.
class RegisteredAttendee {
  const RegisteredAttendee({
    required this.id,
    required this.name,
    this.company,
    this.position,
    this.isExternal = false,
  });

  factory RegisteredAttendee.fromJson(Map<String, dynamic> json) =>
      RegisteredAttendee(
        id: json['id'] as int,
        name: json['name'] as String? ?? 'Unnamed',
        company: json['company'] as String?,
        position: json['position'] as String?,
        isExternal: json['is_external'] as bool? ?? false,
      );

  final int id;
  final String name;
  final String? company;
  final String? position;
  final bool isExternal;

  /// Whatever is known about them beyond the name, on one line.
  String? get detail {
    final parts = <String>[
      if (position != null && position!.isNotEmpty) position!,
      if (company != null && company!.isNotEmpty) company!,
    ];

    return parts.isEmpty ? null : parts.join(' · ');
  }
}

/// The whole attendance desk in one value: the link, and the queue behind it.
class AttendanceDesk {
  const AttendanceDesk({this.token, this.registered = const []});

  factory AttendanceDesk.fromJson(Map<String, dynamic> json) {
    if (json['active'] != true) return const AttendanceDesk();

    return AttendanceDesk(
      token: AttendanceToken.fromJson(json),
      registered: (json['registered'] as List?)
              ?.cast<Map<String, dynamic>>()
              .map(RegisteredAttendee.fromJson)
              .toList() ??
          const [],
    );
  }

  final AttendanceToken? token;
  final List<RegisteredAttendee> registered;

  bool get isOpen => token != null;
}
