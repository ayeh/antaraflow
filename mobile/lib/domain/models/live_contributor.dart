import '../../features/recorder/recorder_role.dart';

/// A person who fed a sitting: the recording, or a satellite that helped.
///
/// Named rather than counted, because "Ariff added a mic" is the useful thing
/// to say — both live, on the recorder, and afterwards on the record.
class LiveContributor {
  const LiveContributor({required this.name, required this.role});

  factory LiveContributor.fromJson(Map<String, dynamic> json) {
    return LiveContributor(
      name: json['name'] as String? ?? '',
      role: RecorderRole.fromWire(json['role'] as String?),
    );
  }

  final String name;

  final RecorderRole role;

  bool get isSatellite => role.isSatellite;

  @override
  bool operator ==(Object other) =>
      other is LiveContributor && other.name == name && other.role == role;

  @override
  int get hashCode => Object.hash(name, role);
}
