import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/haptics.dart';
import '../../core/locale_controller.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_theme.dart';
import '../../l10n/app_localizations.dart';
import '../widgets/ledger_scaffold.dart';

/// Three choices, and the first one is the honest default.
///
/// Following the phone is what most people mean and nobody asks for, so it is
/// listed first and is what an install starts on. The other two are for the
/// case the phone is wrong — a Malay speaker with an English handset, which in
/// this market is most of them.
class LanguageScreen extends ConsumerWidget {
  const LanguageScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = L.of(context);
    final chosen = ref.watch(localeProvider);

    return LedgerScaffold(
      title: l.language,
      meta: Localizations.localeOf(context).languageCode.toUpperCase(),
      leading: IconButton(
        icon: const Icon(Icons.arrow_back_rounded),
        color: const Color(0xFFC3D0F0),
        tooltip: l.back,
        onPressed: () => Navigator.of(context).maybePop(),
      ),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.only(bottom: 110),
        children: [
          const SizedBox(height: 14),
          _Choice(
            label: l.languageFollowPhone,
            detail: l.languageFollowPhoneDetail,
            selected: chosen == null,
            onTap: () => _set(ref, null),
          ),
          // No second line on these two. The label is already the language
          // named in itself, and repeating it underneath said the same thing
          // twice in the same words.
          _Choice(
            label: l.languageMalay,
            selected: chosen?.languageCode == 'ms',
            onTap: () => _set(ref, const Locale('ms')),
          ),
          _Choice(
            label: l.languageEnglish,
            selected: chosen?.languageCode == 'en',
            onTap: () => _set(ref, const Locale('en')),
          ),
        ],
      ),
    );
  }

  void _set(WidgetRef ref, Locale? locale) {
    Haptics.select();
    ref.read(localeProvider.notifier).choose(locale);
  }
}

class _Choice extends StatelessWidget {
  const _Choice({
    required this.label,
    required this.selected,
    required this.onTap,
    this.detail,
  });

  final String label;
  final String? detail;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l = L.of(context);

    return InkWell(
      onTap: selected ? null : onTap,
      child: Container(
        decoration: const BoxDecoration(
          border: Border(top: BorderSide(color: AppColors.rule)),
        ),
        padding: const EdgeInsets.fromLTRB(20, 15, 20, 15),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: AppTheme.gutter,
              child: Text(
                selected ? l.gutterUsing : l.gutterChoose,
                style: AppTheme.mono(
                  size: 11.5,
                  weight: selected ? FontWeight.w700 : FontWeight.w500,
                  colour: selected ? AppColors.primaryInk : AppColors.inkFaint,
                ),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label, style: Theme.of(context).textTheme.titleMedium),
                  if (detail != null) ...[
                    const SizedBox(height: 3),
                    Text(detail!, style: Theme.of(context).textTheme.bodySmall),
                  ],
                ],
              ),
            ),
            if (selected)
              const Icon(
                Icons.check_rounded,
                size: 20,
                color: AppColors.primaryInk,
              ),
          ],
        ),
      ),
    );
  }
}
