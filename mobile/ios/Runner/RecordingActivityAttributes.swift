import Foundation

#if canImport(ActivityKit)
import ActivityKit

/// What the lock screen and Dynamic Island show while a sitting is being
/// recorded.
///
/// Compiled into both the app and the widget extension: the app declares the
/// activity and pushes state, the extension draws it, and the type has to be
/// literally the same one on both sides.
///
/// The elapsed time is a plain second count pushed by the app, not a date for
/// SwiftUI to run a clock from — see `Clock` in the widget for why the
/// self-running forms could not be used. It is the length of the whole
/// recording, so a resumed session continues rather than restarting.
@available(iOS 16.2, *)
struct RecordingActivityAttributes: ActivityAttributes {
  public struct ContentState: Codable, Hashable {
    var elapsedSeconds: Int
    var marks: Int
    var queued: Int
    var paused: Bool
  }

  var meetingTitle: String
}
#endif
