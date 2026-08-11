import SwiftUI
import WidgetKit

/// One tap from the home screen to a running recording.
///
/// Deliberately the only thing on it. A widget that showed the next sitting
/// would need an App Group and the app writing shared state, and it would put
/// meeting titles on a home screen that anyone glancing over a shoulder can
/// read. What somebody wants from a home screen at the door of a boardroom is
/// the button, so that is all this is.
struct RecordWidget: Widget {
  var body: some WidgetConfiguration {
    StaticConfiguration(kind: "cloud.antara.note.record", provider: Provider()) { _ in
      RecordWidgetView()
        .widgetBackground(Brand.navy)
    }
    .configurationDisplayName("Record")
    .description("Start recording a sitting.")
    .supportedFamilies([.systemSmall, .systemMedium])
  }

  struct Entry: TimelineEntry {
    let date: Date
  }

  /// Nothing changes, so nothing is scheduled. The widget is a button.
  struct Provider: TimelineProvider {
    func placeholder(in context: Context) -> Entry { Entry(date: .now) }

    func getSnapshot(in context: Context, completion: @escaping (Entry) -> Void) {
      completion(Entry(date: .now))
    }

    func getTimeline(in context: Context, completion: @escaping (Timeline<Entry>) -> Void) {
      completion(Timeline(entries: [Entry(date: .now)], policy: .never))
    }
  }
}

struct RecordWidgetView: View {
  @Environment(\.widgetFamily) private var family

  var body: some View {
    Link(destination: URL(string: "antaranote://record")!) {
      if family == .systemMedium {
        HStack(spacing: 16) {
          Dot(size: 44)

          VStack(alignment: .leading, spacing: 3) {
            Text("RECORD A SITTING")
              .font(.system(size: 11, weight: .heavy))
              .kerning(1.4)
              .foregroundStyle(Brand.lime)

            Text("Minutes, signed and settled.")
              .font(.system(size: 13))
              .foregroundStyle(Brand.faint)
              .lineLimit(1)
          }

          Spacer(minLength: 0)
        }
      } else {
        VStack(alignment: .leading, spacing: 12) {
          Dot(size: 40)

          Spacer(minLength: 0)

          Text("RECORD\nA SITTING")
            .font(.system(size: 12, weight: .heavy))
            .kerning(1.2)
            .lineSpacing(2)
            .foregroundStyle(Brand.lime)
        }
        .frame(maxWidth: .infinity, alignment: .leading)
      }
    }
  }
}

extension View {
  /// `containerBackground` is iOS 17. The extension deploys to 16.2 so that
  /// Live Activities still reach iOS 16, and on 17 and up a widget that does
  /// not use it is letterboxed with a default background — so both are needed
  /// rather than one or the other.
  @ViewBuilder
  func widgetBackground(_ colour: Color) -> some View {
    if #available(iOS 17.0, *) {
      containerBackground(colour, for: .widget)
    } else {
      padding().background(colour)
    }
  }
}

/// The same hard-edged red block the tab bar carries, not a round button —
/// nothing else in this interface is round.
struct Dot: View {
  let size: CGFloat

  var body: some View {
    RoundedRectangle(cornerRadius: 6)
      .fill(Brand.red)
      .frame(width: size * 1.32, height: size)
      .overlay(
        Circle()
          .fill(.white)
          .frame(width: size * 0.34, height: size * 0.34)
      )
  }
}
