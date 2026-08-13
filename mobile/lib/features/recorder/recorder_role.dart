/// What this phone is doing in the room.
enum RecorderRole {
  /// This device is the recording. It opened the session, it is what the
  /// minutes will be drafted from, and a sitting has exactly one.
  primary('primary'),

  /// This device is helping — usually placed nearer the far end of the table
  /// than the primary. Additive and never required: if it fails, drops out, or
  /// is killed by the operating system, the sitting is exactly what it would
  /// have been without it.
  satellite('satellite');

  const RecorderRole(this.wire);

  final String wire;

  bool get isSatellite => this == RecorderRole.satellite;
}
