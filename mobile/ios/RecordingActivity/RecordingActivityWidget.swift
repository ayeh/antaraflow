import ActivityKit
import SwiftUI
import WidgetKit

/// The recorder, as the rest of iOS sees it.
///
/// The phone is face up on the table for an hour and the screen is off. This
/// is the only thing telling the room the meeting is still being captured, so
/// it answers exactly two questions — is it running, and how long for — and
/// nothing else competes with them.
@available(iOS 16.2, *)
struct RecordingActivityWidget: Widget {
  var body: some WidgetConfiguration {
    ActivityConfiguration(for: RecordingActivityAttributes.self) { context in
      LockScreenView(context: context)
        .activityBackgroundTint(Brand.navy)
        .activitySystemActionForegroundColor(Brand.lime)
    } dynamicIsland: { context in
      DynamicIsland {
        DynamicIslandExpandedRegion(.leading) {
          Beacon(paused: context.state.paused)
            .padding(.leading, 6)
        }
        DynamicIslandExpandedRegion(.trailing) {
          Clock(state: context.state, size: 17)
            .padding(.trailing, 6)
        }
        DynamicIslandExpandedRegion(.bottom) {
          Text(context.attributes.meetingTitle)
            .font(.footnote)
            .foregroundStyle(.secondary)
            .lineLimit(1)
        }
      } compactLeading: {
        Beacon(paused: context.state.paused)
      } compactTrailing: {
        Clock(state: context.state, size: 13)
      } minimal: {
        Beacon(paused: context.state.paused)
      }
      .keylineTint(Brand.lime)
    }
  }
}

@available(iOS 16.2, *)
struct LockScreenView: View {
  let context: ActivityViewContext<RecordingActivityAttributes>

  var body: some View {
    HStack(alignment: .center, spacing: 14) {
      VStack(alignment: .leading, spacing: 5) {
        HStack(spacing: 7) {
          Beacon(paused: context.state.paused)
          Text(context.state.paused ? "PAUSED" : "RECORDING")
            .font(.system(size: 11, weight: .heavy))
            .kerning(1.3)
            .foregroundStyle(context.state.paused ? Brand.amber : Brand.red)
        }

        Text(context.attributes.meetingTitle)
          .font(.system(size: 14, weight: .semibold))
          .foregroundStyle(.white)
          .lineLimit(1)
          .truncationMode(.tail)

        Text(subtitle)
          .font(.system(size: 11, design: .monospaced))
          .foregroundStyle(Brand.faint)
      }

      Spacer(minLength: 0)

      Clock(state: context.state, size: 22)
    }
    .padding(.horizontal, 18)
    .padding(.vertical, 14)
  }

  private var subtitle: String {
    let marks = context.state.marks
    var parts = [marks == 1 ? "1 MARK" : "\(marks) MARKS"]

    if context.state.queued > 0 {
      parts.append("\(context.state.queued) QUEUED")
    } else {
      parts.append("AUTOSAVING")
    }

    return parts.joined(separator: " · ")
  }
}

/// The dot that never stops while recording runs.
@available(iOS 16.2, *)
struct Beacon: View {
  let paused: Bool

  var body: some View {
    Circle()
      .fill(paused ? Brand.amber : Brand.red)
      .frame(width: 9, height: 9)
  }
}

/// How long the recording has been running, as last reported by the app.
///
/// Deliberately not one of SwiftUI's self-running clocks. `Text(timerInterval:)`
/// renders "1:——" the moment a recording passes a minute — a wider frame, a
/// `fixedSize`, and a bounded range all fail to talk it out of that — and
/// `Text(_:style: .timer)` formats as prose, "5 minut…", which is worse. A
/// figure pushed from the app is always right and never guesses.
///
/// The cost is granularity: this advances in fifteen-second steps, with the
/// recorder's own updates. That is the correct trade for a card whose job is
/// to say *this is still running*, which the beacon and the word RECORDING do
/// continuously anyway. The exact second lives in the app.
@available(iOS 16.2, *)
struct Clock: View {
  let state: RecordingActivityAttributes.ContentState
  let size: CGFloat

  /// Reserved so the layout cannot squeeze the figure as it lengthens; sized
  /// for the longest the clock can be, "11:02:33".
  private var reserved: CGFloat { size * 5.4 }

  var body: some View {
    Text(Self.stamp(state.elapsedSeconds))
    .font(.system(size: size, weight: .light, design: .monospaced))
    .monospacedDigit()
    .lineLimit(1)
    .multilineTextAlignment(.trailing)
    .frame(width: reserved, alignment: .trailing)
    .fixedSize(horizontal: true, vertical: false)
    .foregroundStyle(.white)
  }

  static func stamp(_ seconds: Int) -> String {
    let h = seconds / 3600
    let m = (seconds % 3600) / 60
    let s = seconds % 60

    return h > 0
      ? String(format: "%d:%02d:%02d", h, m, s)
      : String(format: "%02d:%02d", m, s)
  }
}

/// The live brand, matched to AppColors on the Flutter side.
enum Brand {
  static let navy = Color(red: 0x01 / 255, green: 0x1A / 255, blue: 0x4D / 255)
  static let lime = Color(red: 0x87 / 255, green: 0xFF / 255, blue: 0x51 / 255)
  static let red = Color(red: 0xFF / 255, green: 0x5A / 255, blue: 0x5A / 255)
  static let amber = Color(red: 0xF5 / 255, green: 0x9E / 255, blue: 0x0B / 255)
  static let faint = Color(red: 0x6C / 255, green: 0x85 / 255, blue: 0xC4 / 255)
}

@main
struct RecordingActivityBundle: WidgetBundle {
  var body: some Widget {
    RecordWidget()

    if #available(iOS 16.2, *) {
      RecordingActivityWidget()
    }
  }
}
