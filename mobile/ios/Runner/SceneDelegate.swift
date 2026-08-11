import Flutter
import UIKit

class SceneDelegate: FlutterSceneDelegate {
  /// A widget tap or an Action button press while the app is already running.
  override func scene(_ scene: UIScene, openURLContexts URLContexts: Set<UIOpenURLContext>) {
    for context in URLContexts where RecordEntryBridge.shared.handle(context.url) {
      return
    }

    super.scene(scene, openURLContexts: URLContexts)
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
      RecordEntryBridge.shared.handle(context.url)
    }
  }
}
