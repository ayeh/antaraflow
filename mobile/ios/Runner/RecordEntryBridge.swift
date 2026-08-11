import Flutter
import Foundation

/// Every way into the recorder that does not start with opening the app.
///
/// The home-screen widget, the Action button and Siri all mean the same thing
/// — start recording — and all of them arrive before Flutter is necessarily
/// listening. So the request is held until Dart asks for it, rather than
/// posted into the void: the app can be cold-launched by any of them.
final class RecordEntryBridge {
  static let shared = RecordEntryBridge()

  static let channelName = "cloud.antara.note/record_entry"

  private var channel: FlutterMethodChannel?

  /// Whether Dart has a handler attached.
  ///
  /// The channel exists from the moment the engine registers plugins, which is
  /// well before any Dart widget starts listening — so "channel is not nil" is
  /// not the same question as "somebody is listening", and using it as one
  /// drops the request that cold-launched the app. `consumePending` is the
  /// handshake: Dart only calls it once its handler is attached.
  private var ready = false

  /// Set when a request arrives before then, drained when Dart asks.
  private var pending = false

  private init() {}

  static func register(with messenger: FlutterBinaryMessenger) {
    let channel = FlutterMethodChannel(name: channelName, binaryMessenger: messenger)
    shared.channel = channel

    channel.setMethodCallHandler { call, result in
      switch call.method {
      // Dart calls this once it is listening. A widget tap that cold-launched
      // the app would otherwise be delivered to nobody and silently lost.
      case "consumePending":
        shared.ready = true
        let wasPending = shared.pending
        shared.pending = false
        result(wasPending)
      default:
        result(FlutterMethodNotImplemented)
      }
    }
  }

  /// Called from the scene delegate and from the App Intent.
  func requestRecording() {
    guard ready, let channel else {
      pending = true
      return
    }

    channel.invokeMethod("startRecording", arguments: nil)
  }

  /// True when the URL was one of ours and has been dealt with.
  @discardableResult
  func handle(_ url: URL) -> Bool {
    guard url.scheme == "antaranote" else { return false }

    // Only one destination for now. Anything else under the scheme simply
    // opens the app, which is the harmless outcome.
    if url.host == "record" {
      requestRecording()
    }

    return true
  }
}
