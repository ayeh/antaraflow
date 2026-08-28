import Flutter
import UIKit

class SceneDelegate: FlutterSceneDelegate {
  /// A widget tap or an Action button press while the app is already running.
  override func scene(_ scene: UIScene, openURLContexts URLContexts: Set<UIOpenURLContext>) {
    for context in URLContexts {
      if RecordEntryBridge.shared.handle(context.url) { return }
      if DeepLinkBridge.shared.handle(context.url) { return }
    }

    super.scene(scene, openURLContexts: URLContexts)
  }

  /// A tapped universal link — https://note.antara.cloud/live/join/<token> —
  /// while the app is already running. It arrives as a browsing activity, not a
  /// URL context, so app_links (hooked to the URL path) never sees it.
  override func scene(_ scene: UIScene, continue userActivity: NSUserActivity) {
    if userActivity.activityType == NSUserActivityTypeBrowsingWeb,
       let url = userActivity.webpageURL,
       DeepLinkBridge.shared.handle(url) {
      return
    }

    super.scene(scene, continue: userActivity)
  }

  /// The same tap when it cold-launched the app. The URL arrives here instead,
  /// before Flutter exists — the bridge holds it until Dart asks.
  override func scene(
    _ scene: UIScene,
    willConnectTo session: UISceneSession,
    options connectionOptions: UIScene.ConnectionOptions
  ) {
    super.scene(scene, willConnectTo: session, options: connectionOptions)

    for context in connectionOptions.urlContexts {
      if RecordEntryBridge.shared.handle(context.url) { continue }
      DeepLinkBridge.shared.handle(context.url)
    }

    // The same universal link when it cold-launched the app: it comes in as a
    // browsing activity on the connection options rather than a URL context.
    for activity in connectionOptions.userActivities
    where activity.activityType == NSUserActivityTypeBrowsingWeb {
      if let url = activity.webpageURL {
        DeepLinkBridge.shared.handle(url)
      }
    }
  }
}
