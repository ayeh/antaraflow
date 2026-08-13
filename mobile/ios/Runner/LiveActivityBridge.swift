import Flutter
import Foundation

#if canImport(ActivityKit)
import ActivityKit
#endif

/// Drives the lock-screen activity from the Flutter recorder.
///
/// Everything here fails soft. A Live Activity is a nicety; a recording is
/// not. If the device is too old, the user has switched activities off, or the
/// system refuses one because too many are running, the recorder must carry on
/// exactly as before — so every entry point answers `false` rather than
/// throwing, and the Dart side treats that as "no activity, never mind".
final class LiveActivityBridge {
  static let channelName = "cloud.antara.note/live_activity"

  private var current: Any?

  static func register(with messenger: FlutterBinaryMessenger) {
    let channel = FlutterMethodChannel(name: channelName, binaryMessenger: messenger)
    let bridge = LiveActivityBridge()

    channel.setMethodCallHandler { call, result in
      bridge.handle(call, result: result)
    }
  }

  private func handle(_ call: FlutterMethodCall, result: @escaping FlutterResult) {
    let arguments = call.arguments as? [String: Any] ?? [:]

    switch call.method {
    case "start":
      result(start(arguments))
    case "update":
      result(update(arguments))
    case "end":
      end()
      result(true)
    default:
      result(FlutterMethodNotImplemented)
    }
  }

  // MARK: - ActivityKit

  private func start(_ arguments: [String: Any]) -> Bool {
    #if canImport(ActivityKit)
    guard #available(iOS 16.2, *) else { return false }
    guard ActivityAuthorizationInfo().areActivitiesEnabled else { return false }

    // A second start means the app rejoined a session that was already
    // running. Reuse the activity rather than stacking another one on the
    // lock screen.
    if current != nil {
      return update(arguments)
    }

    let attributes = RecordingActivityAttributes(
      meetingTitle: arguments["title"] as? String ?? "Meeting"
    )

    do {
      current = try Activity.request(
        attributes: attributes,
        content: .init(state: state(from: arguments), staleDate: nil)
      )

      return true
    } catch {
      return false
    }
    #else
    return false
    #endif
  }

  private func update(_ arguments: [String: Any]) -> Bool {
    #if canImport(ActivityKit)
    guard #available(iOS 16.2, *),
          let activity = current as? Activity<RecordingActivityAttributes>
    else { return false }

    Task {
      await activity.update(.init(state: state(from: arguments), staleDate: nil))
    }

    return true
    #else
    return false
    #endif
  }

  private func end() {
    #if canImport(ActivityKit)
    guard #available(iOS 16.2, *),
          let activity = current as? Activity<RecordingActivityAttributes>
    else { return }

    current = nil

    Task {
      // Immediately: the meeting is over, and a card lingering on the lock
      // screen saying otherwise is worse than no card at all.
      await activity.end(nil, dismissalPolicy: .immediate)
    }
    #endif
  }

  #if canImport(ActivityKit)
  @available(iOS 16.2, *)
  private func state(from arguments: [String: Any]) -> RecordingActivityAttributes.ContentState {
    let elapsed = arguments["elapsed"] as? Int ?? 0

    return .init(
      elapsedSeconds: elapsed,
      marks: arguments["marks"] as? Int ?? 0,
      queued: arguments["queued"] as? Int ?? 0,
      paused: arguments["paused"] as? Bool ?? false,
      quiet: arguments["quiet"] as? Bool ?? false
    )
  }
  #endif
}
