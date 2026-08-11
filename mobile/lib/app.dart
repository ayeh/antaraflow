import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/providers.dart';
import 'core/theme/app_colors.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/auth_controller.dart';
import 'features/auth/auth_state.dart';
import 'features/auth/login_screen.dart';
import 'features/onboarding/first_run.dart';
import 'features/shell/app_shell.dart';
import 'features/widgets/brand_mark.dart';
import 'features/widgets/upgrade_gate.dart';

class AntaraNoteApp extends ConsumerStatefulWidget {
  const AntaraNoteApp({super.key});

  @override
  ConsumerState<AntaraNoteApp> createState() => _AntaraNoteAppState();
}

class _AntaraNoteAppState extends ConsumerState<AntaraNoteApp> {
  @override
  void initState() {
    super.initState();

    // Deferred to the first frame: restore() reaches the network, and doing
    // that during initState blocks the first paint.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(authControllerProvider.notifier).restore();
    });
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider);
    final upgrade = ref.watch(upgradeRequiredProvider);

    return MaterialApp(
      title: 'antaraNote',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      // Locked to light, and the reason is physical: this is a document read
      // across a boardroom table under ceiling light, often by people with
      // reading glasses. The one screen that genuinely belongs in the dark is
      // the recorder, which sits face-up on a table for an hour, and it carries
      // its own dark ground rather than flipping the whole app.
      //
      // A dark theme is defined in AppTheme but the screens hardcode the paper
      // and navy tokens, so shipping ThemeMode.system would have delivered a
      // half-broken second theme nobody had looked at.
      themeMode: ThemeMode.light,
      home: switch (upgrade) {
        // Blocks everything: the server has said this build can no longer be
        // trusted to talk to it, and letting it keep trying produces confusing
        // failures rather than one clear instruction.
        final requirement? => UpgradeGate(requirement: requirement),
        null => switch (auth.status) {
          AuthStatus.unknown => const _Splash(),
          // The three cards sit after sign-in, not before it: they answer
          // questions about recording, and somebody who has not got past the
          // password does not have those questions yet.
          AuthStatus.authenticated => const _SignedIn(),
          AuthStatus.unauthenticated => const LoginScreen(),
        },
      },
    );
  }
}

/// Shows the three first-run cards once, then the app.
class _SignedIn extends ConsumerWidget {
  const _SignedIn();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final first = ref.watch(firstRunProvider);

    // While the flag is being read the shell is shown rather than a spinner:
    // the read is a keychain lookup, and flashing a loading screen over a
    // returning user to decide whether to greet them is worse than a card
    // arriving a frame late.
    return switch (first) {
      AsyncData(value: true) => FirstRun(
        onDone: () => ref.invalidate(firstRunProvider),
      ),
      _ => const AppShell(),
    };
  }
}

/// The first thing anybody sees.
///
/// A bare Material spinner on a white field is the default, and it says
/// nothing — for the second or two the stored token is being checked, this is
/// the only surface the brand has. The mark fades up on the same navy slab
/// every other screen is topped with, so the app has already started before
/// the first screen arrives.
class _Splash extends StatelessWidget {
  const _Splash();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.navyDeep,
      body: Center(
        child: TweenAnimationBuilder<double>(
          tween: Tween(begin: 0, end: 1),
          duration: AppTheme.enter,
          curve: AppTheme.easeOut,
          builder: (context, value, child) =>
              Opacity(opacity: value, child: child),
          child: const BrandMark(size: 56, onDark: true),
        ),
      ),
    );
  }
}
