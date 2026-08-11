import Foundation

#if canImport(AppIntents)
import AppIntents

/// "Record a sitting", as the rest of the system can invoke it.
///
/// One intent covers three entry points: the Action button, Siri, and the
/// Shortcuts app. All of them want the same thing from someone walking into a
/// room — start capturing, without finding and opening an app first.
///
/// `openAppWhenRun` because recording needs the microphone, the session and
/// the upload queue, none of which belong in an extension process.
@available(iOS 16.0, *)
struct StartRecordingIntent: AppIntent {
  static var title: LocalizedStringResource = "Record a sitting"

  static var description = IntentDescription(
    "Opens antaraNote and starts recording the meeting you are in."
  )

  static var openAppWhenRun: Bool = true

  @MainActor
  func perform() async throws -> some IntentResult {
    RecordEntryBridge.shared.requestRecording()

    return .result()
  }
}

/// Puts the intent in front of people without them going looking for it: it
/// appears in Shortcuts, in Spotlight, and in the Action button picker.
@available(iOS 16.0, *)
struct AntaraNoteShortcuts: AppShortcutsProvider {
  static var appShortcuts: [AppShortcut] {
    AppShortcut(
      intent: StartRecordingIntent(),
      phrases: [
        "Record a sitting with \(.applicationName)",
        "Start recording in \(.applicationName)",
        "Take minutes with \(.applicationName)",
      ],
      shortTitle: "Record",
      systemImageName: "record.circle"
    )
  }
}
#endif
