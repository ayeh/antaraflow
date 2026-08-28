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

  /// True when the URL was a recorder request and has been dealt with.
  ///
  /// Only `antaranote://record` is claimed here. Other destinations under the
  /// scheme — an invite link, say — must fall through to whoever handles them,
  /// so this no longer swallows every `antaranote://` URL.
  @discardableResult
  func handle(_ url: URL) -> Bool {
    guard url.scheme == "antaranote", url.host == "record" else { return false }

    requestRecording()
    return true
  }
}

/// An invite link that opens the app straight onto a sitting to help record:
/// `antaranote://live/join/<token>`.
///
/// Custom-scheme URLs on this app arrive through the scene delegate, not the
/// app delegate, so `app_links` never sees them — this is the iOS half of the
/// deep link. Like a recorder request, the link can cold-launch the app before
/// Flutter is listening, so the token is held until Dart asks for it rather
/// than posted into the void.
final class DeepLinkBridge {
  static let shared = DeepLinkBridge()

  static let channelName = "cloud.antara.note/deep_link"

  private var channel: FlutterMethodChannel?

  /// Whether Dart has attached its handler. See RecordEntryBridge.ready: the
  /// channel exists long before any widget listens, so this is the real
  /// "somebody is listening" question, and `consumePending` is the handshake.
  private var ready = false

  /// A token that arrived before then, drained when Dart asks.
  private var pending: String?

  private init() {}

  static func register(with messenger: FlutterBinaryMessenger) {
    let channel = FlutterMethodChannel(name: channelName, binaryMessenger: messenger)
    shared.channel = channel

    channel.setMethodCallHandler { call, result in
      switch call.method {
      case "consumePending":
        shared.ready = true
        let token = shared.pending
        shared.pending = nil
        result(token)
      default:
        result(FlutterMethodNotImplemented)
      }
    }
  }

  /// True when the URL was an invite link and its token has been dealt with.
  @discardableResult
  func handle(_ url: URL) -> Bool {
    guard url.scheme == "antaranote", let token = Self.token(from: url) else {
      return false
    }

    deliver(token)
    return true
  }

  private func deliver(_ token: String) {
    guard ready, let channel else {
      pending = token
      return
    }

    channel.invokeMethod("link", arguments: token)
  }

  /// The token in `antaranote://live/join/<token>`, or nil for anything else.
  static func token(from url: URL) -> String? {
    guard url.host == "live" else { return nil }

    // pathComponents on antaranote://live/join/<token> is ["/", "join", "<token>"].
    let parts = url.pathComponents.filter { $0 != "/" }
    guard parts.count == 2, parts[0] == "join" else { return nil }

    return parts[1]
  }
}
