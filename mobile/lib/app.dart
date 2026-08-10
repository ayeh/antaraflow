import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/providers.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/auth_controller.dart';
import 'features/auth/auth_state.dart';
import 'features/auth/login_screen.dart';
import 'features/shell/app_shell.dart';
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
          AuthStatus.authenticated => const AppShell(),
          AuthStatus.unauthenticated => const LoginScreen(),
        },
      },
    );
  }
}

class _Splash extends StatelessWidget {
  const _Splash();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(child: CircularProgressIndicator()),
    );
  }
}
