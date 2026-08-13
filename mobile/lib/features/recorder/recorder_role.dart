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

  /// Reads what the server said this device should be.
  ///
  /// Anything unrecognised, including nothing at all, is the recording: a
  /// server that does not answer this is one where satellites do not exist,
  /// and every rejoin there is a recorder coming back to its own sitting.
  static RecorderRole fromWire(String? value) => RecorderRole.values.firstWhere(
    (role) => role.wire == value,
    orElse: () => RecorderRole.primary,
  );

  bool get isSatellite => this == RecorderRole.satellite;
}
