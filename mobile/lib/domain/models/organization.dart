class Organization {
  const Organization({
    required this.id,
    required this.name,
    this.role,
    this.logoUrl,
    this.isCurrent = false,
  });

  factory Organization.fromJson(Map<String, dynamic> json) => Organization(
    id: json['id'] as int,
    name: json['name'] as String? ?? '',
    role: json['role'] as String?,
    logoUrl: json['logo_url'] as String?,
    isCurrent: json['is_current'] as bool? ?? false,
  );

  final int id;
  final String name;
  final String? role;
  final String? logoUrl;
  final bool isCurrent;
}
