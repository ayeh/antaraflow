import Flutter
import UIKit

/// A pane of Apple's Liquid Glass, composited underneath Flutter content.
///
/// Flutter draws its own pixels and inherits nothing from UIKit, so the only
/// way to get the real material — refraction, specular edge, the way it picks
/// up what scrolls behind it — is to host an actual `UIVisualEffectView` as a
/// platform view and let Flutter paint the tab items on top of it.
///
/// iOS only; `GlassPane` on the Dart side falls back to an opaque ground
/// everywhere else, so the bar keeps its shape and behaviour without it.
final class GlassPlatformView: NSObject, FlutterPlatformView {
    private let container: SquircleView

    init(frame: CGRect) {
        container = SquircleView(frame: frame)
        container.backgroundColor = .clear

        let effectView: UIVisualEffectView

        if #available(iOS 26.0, *) {
            let glass = UIGlassEffect()
            glass.isInteractive = true
            // Tinted toward the brand navy rather than left clear. Clear glass
            // over a pale list lets the rows read straight through the bar;
            // a little darkness gives the tab labels a ground of their own
            // without turning the pill into a solid slab.
            glass.tintColor = UIColor(red: 0x01/255, green: 0x26/255, blue: 0x6E/255, alpha: 0.22)
            effectView = UIVisualEffectView(effect: glass)
        } else {
            // Everything before iOS 26 gets the nearest older material. It is
            // not Liquid Glass and does not pretend to be.
            effectView = UIVisualEffectView(effect: UIBlurEffect(style: .systemUltraThinMaterial))
        }

        effectView.frame = container.bounds
        effectView.autoresizingMask = [.flexibleWidth, .flexibleHeight]
        container.addSubview(effectView)

        super.init()
    }

    func view() -> UIView { container }
}

/// The corner has to be cut natively — clipping a platform view from the
/// Flutter side leaves the UIKit layer square underneath the mask.
///
/// A squircle rather than a full capsule, matching the selection shape and the
/// tags elsewhere in the app. A stadium end would be the only true semicircle
/// in the whole interface.
final class SquircleView: UIView {
    static let cornerRadius: CGFloat = 22

    override init(frame: CGRect) {
        super.init(frame: frame)
        layer.cornerCurve = .continuous
        clipsToBounds = true
    }

    required init?(coder: NSCoder) { fatalError("not used") }

    override func layoutSubviews() {
        super.layoutSubviews()
        layer.cornerRadius = min(Self.cornerRadius, bounds.height / 2)
    }
}

final class GlassPlatformViewFactory: NSObject, FlutterPlatformViewFactory {
    static let viewType = "cloud.antara.note/glass"

    func create(
        withFrame frame: CGRect,
        viewIdentifier viewId: Int64,
        arguments args: Any?
    ) -> FlutterPlatformView {
        GlassPlatformView(frame: frame)
    }
}
