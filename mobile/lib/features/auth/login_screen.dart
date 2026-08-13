import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../widgets/brand_mark.dart';
import '../widgets/ledger_scaffold.dart';
import 'auth_controller.dart';
import '../../l10n/app_localizations.dart';

/// Sign in.
///
/// The navy slab is the same masthead every other screen carries, so the first
/// thing someone sees is already the shape of the app. Below it the form sits
/// on paper with the fields ruled rather than floating, and the whole page has
/// exactly one saturated element: the button.
class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    FocusScope.of(context).unfocus();

    await ref
        .read(authControllerProvider.notifier)
        .login(
          email: _emailController.text.trim(),
          password: _passwordController.text,
        );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(authControllerProvider);
    final theme = Theme.of(context);

    return LightStatusBar(
      child: Scaffold(
        backgroundColor: AppColors.paper,
        body: Column(
          children: [
            Container(
              width: double.infinity,
              color: AppColors.navyDeep,
              padding: EdgeInsets.only(
                top: MediaQuery.paddingOf(context).top + 46,
                bottom: 40,
                left: 24,
                right: 24,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const BrandMark(size: 40, onDark: true),
                  const SizedBox(height: 26),
                  Text(
                    L.of(context).appTagline,
                    style: theme.textTheme.displaySmall?.copyWith(
                      color: Colors.white,
                      height: 1.12,
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(24, 30, 24, 32),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 440),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(L.of(context).signIn, style: AppTheme.eyebrow()),
                        const SizedBox(height: 22),
                        _Field(
                          label: L.of(context).email,
                          controller: _emailController,
                          keyboardType: TextInputType.emailAddress,
                          textInputAction: TextInputAction.next,
                          autofillHints: const [AutofillHints.username],
                          enabled: !state.isSubmitting,
                          error: state.fieldErrors['email']?.first,
                          validate: (v) => (v == null || v.trim().isEmpty)
                              ? L.of(context).emailHint
                              : null,
                        ),
                        const SizedBox(height: 20),
                        _Field(
                          label: L.of(context).password,
                          controller: _passwordController,
                          obscure: _obscurePassword,
                          textInputAction: TextInputAction.done,
                          autofillHints: const [AutofillHints.password],
                          enabled: !state.isSubmitting,
                          error: state.fieldErrors['password']?.first,
                          onSubmitted: (_) => _submit(),
                          validate: (v) => (v == null || v.isEmpty)
                              ? L.of(context).passwordHint
                              : null,
                          suffix: IconButton(
                            icon: Icon(
                              _obscurePassword
                                  ? Icons.visibility_outlined
                                  : Icons.visibility_off_outlined,
                              size: 20,
                            ),
                            color: AppColors.inkFaint,
                            tooltip: _obscurePassword
                                ? L.of(context).showPassword
                                : L.of(context).hidePassword,
                            onPressed: () => setState(
                              () => _obscurePassword = !_obscurePassword,
                            ),
                          ),
                        ),
                        if (state.errorMessage != null) ...[
                          const SizedBox(height: 18),
                          _ErrorNotice(message: state.errorMessage!),
                        ],
                        const SizedBox(height: 28),
                        FilledButton(
                          onPressed: state.isSubmitting ? null : _submit,
                          child: state.isSubmitting
                              ? const SizedBox.square(
                                  dimension: 20,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: AppColors.navy,
                                  ),
                                )
                              : Text(L.of(context).signInAction),
                        ),
                        const SizedBox(height: 18),
                        Center(
                          child: TextButton(
                            onPressed: state.isSubmitting ? null : () {},
                            style: TextButton.styleFrom(
                              foregroundColor: AppColors.primaryInk,
                              textStyle: TextStyle(
                                fontWeight: FontWeight.w700,
                                fontVariations: AppTheme.axis(FontWeight.w700),
                                fontSize: 14,
                              ),
                            ),
                            child: Text(L.of(context).forgotPassword),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Label above the input, never a placeholder standing in for one: a
/// placeholder disappears the moment someone types, which is exactly when they
/// need to check what the field was.
class _Field extends StatelessWidget {
  const _Field({
    required this.label,
    required this.controller,
    required this.validate,
    this.error,
    this.obscure = false,
    this.enabled = true,
    this.keyboardType,
    this.textInputAction,
    this.autofillHints,
    this.suffix,
    this.onSubmitted,
  });

  final String label;
  final TextEditingController controller;
  final String? Function(String?) validate;
  final String? error;
  final bool obscure;
  final bool enabled;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final Iterable<String>? autofillHints;
  final Widget? suffix;
  final ValueChanged<String>? onSubmitted;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: AppTheme.eyebrow(colour: AppColors.inkSoft),
        ),
        const SizedBox(height: 8),
        TextFormField(
          controller: controller,
          obscureText: obscure,
          enabled: enabled,
          keyboardType: keyboardType,
          textInputAction: textInputAction,
          autofillHints: autofillHints,
          onFieldSubmitted: onSubmitted,
          validator: validate,
          style: Theme.of(context).textTheme.bodyLarge,
          decoration: InputDecoration(
            errorText: error,
            suffixIcon: suffix,
            isDense: true,
          ),
        ),
      ],
    );
  }
}

/// Ruled, not a tinted box. A filled red panel on a page this quiet reads as
/// alarm rather than correction.
class _ErrorNotice extends StatelessWidget {
  const _ErrorNotice({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(
        border: Border(left: BorderSide(color: AppColors.danger, width: 3)),
        color: AppColors.danger.withValues(alpha: 0.05),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.error_outline, size: 18, color: AppColors.danger),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: Theme.of(
                context,
              ).textTheme.bodySmall?.copyWith(color: AppColors.ink),
            ),
          ),
        ],
      ),
    );
  }
}
