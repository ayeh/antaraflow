import 'package:flutter/material.dart';

import '../../core/theme/app_colors.dart';

/// The body of a minute, set for reading.
///
/// The server stores `content` as HTML from the web editor, but older records
/// and anything drafted from a transcript are plain text with blank lines
/// between paragraphs. Both arrive on the same field, so both are handled here.
///
/// No HTML package: adding a dependency to render six tags would be a poor
/// trade, and a general renderer would bring its own type scale and undo the
/// one this app is built on. What is supported is exactly what the editor
/// emits — paragraphs, three levels of heading, lists, bold, italic, and line
/// breaks. Anything else is shown as its text rather than as markup.
class Prose extends StatelessWidget {
  const Prose({super.key, required this.source});

  final String source;

  @override
  Widget build(BuildContext context) {
    final blocks = _parse(source);

    if (blocks.isEmpty) {
      return Text(
        'No minutes have been written yet.',
        style: Theme.of(context).textTheme.bodySmall,
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var i = 0; i < blocks.length; i++)
          Padding(
            padding: EdgeInsets.only(top: i == 0 ? 0 : blocks[i].spaceAbove),
            child: blocks[i].build(context),
          ),
      ],
    );
  }

  static List<_Block> _parse(String raw) {
    final trimmed = raw.trim();
    if (trimmed.isEmpty) return const [];

    return _looksLikeHtml(trimmed) ? _parseHtml(trimmed) : _parsePlain(trimmed);
  }

  /// A lone `<` in prose ("value < 5") must not turn the whole minute into
  /// markup, so this looks for an actual opening tag.
  static bool _looksLikeHtml(String raw) => RegExp(
    r'<(p|h[1-3]|ul|ol|li|div|br|strong|b|em|i)\b',
    caseSensitive: false,
  ).hasMatch(raw);

  static List<_Block> _parsePlain(String raw) {
    return raw
        .split(RegExp(r'\n\s*\n'))
        .map((paragraph) => paragraph.trim())
        .where((paragraph) => paragraph.isNotEmpty)
        .map((paragraph) => _Block.paragraph([TextSpan(text: paragraph)]))
        .toList();
  }

  static List<_Block> _parseHtml(String raw) {
    final blocks = <_Block>[];
    // Block-level tags, in document order. Anything outside one is loose text
    // and is treated as its own paragraph rather than dropped.
    final pattern = RegExp(
      r'<(h1|h2|h3|p|li)[^>]*>(.*?)</\1>',
      caseSensitive: false,
      dotAll: true,
    );

    var cursor = 0;

    void loose(String text) {
      final cleaned = _text(text).trim();
      if (cleaned.isNotEmpty) {
        blocks.add(_Block.paragraph([TextSpan(text: cleaned)]));
      }
    }

    for (final match in pattern.allMatches(raw)) {
      loose(raw.substring(cursor, match.start));
      cursor = match.end;

      final tag = match.group(1)!.toLowerCase();
      final spans = _inline(match.group(2) ?? '');
      if (spans.isEmpty) continue;

      blocks.add(switch (tag) {
        'h1' || 'h2' => _Block.heading(spans, large: true),
        'h3' => _Block.heading(spans, large: false),
        'li' => _Block.item(spans),
        _ => _Block.paragraph(spans),
      });
    }

    loose(raw.substring(cursor));

    return blocks;
  }

  /// Bold and italic within a block. Nesting is not supported, and does not
  /// need to be: minutes are not typeset.
  static List<InlineSpan> _inline(String html) {
    final spans = <InlineSpan>[];
    final pattern = RegExp(
      r'<(strong|b|em|i)\b[^>]*>(.*?)</\1>',
      caseSensitive: false,
      dotAll: true,
    );

    var cursor = 0;

    void plain(String text) {
      final cleaned = _text(text);
      if (cleaned.isNotEmpty) spans.add(TextSpan(text: cleaned));
    }

    for (final match in pattern.allMatches(html)) {
      plain(html.substring(cursor, match.start));
      cursor = match.end;

      final tag = match.group(1)!.toLowerCase();
      final bold = tag == 'strong' || tag == 'b';

      spans.add(
        TextSpan(
          text: _text(match.group(2) ?? ''),
          style: TextStyle(
            fontWeight: bold ? FontWeight.w700 : null,
            fontVariations: bold ? const [FontVariation('wght', 700)] : null,
            fontStyle: bold ? null : FontStyle.italic,
          ),
        ),
      );
    }

    plain(html.substring(cursor));

    return spans;
  }

  /// Strips remaining tags and resolves the entities an editor actually emits.
  static String _text(String html) {
    return html
        .replaceAll(RegExp(r'<br\s*/?>', caseSensitive: false), '\n')
        .replaceAll(RegExp(r'</p>|</div>', caseSensitive: false), '\n')
        .replaceAll(RegExp(r'<[^>]+>'), '')
        .replaceAll('&nbsp;', ' ')
        .replaceAll('&amp;', '&')
        .replaceAll('&lt;', '<')
        .replaceAll('&gt;', '>')
        .replaceAll('&quot;', '"')
        .replaceAll('&#39;', "'")
        .replaceAll(RegExp(r'[ \t]+'), ' ')
        .replaceAll(RegExp(r'\n{3,}'), '\n\n');
  }
}

enum _Kind { paragraph, headingLarge, headingSmall, item }

class _Block {
  const _Block(this.kind, this.spans);

  factory _Block.paragraph(List<InlineSpan> spans) =>
      _Block(_Kind.paragraph, spans);

  factory _Block.heading(List<InlineSpan> spans, {required bool large}) =>
      _Block(large ? _Kind.headingLarge : _Kind.headingSmall, spans);

  factory _Block.item(List<InlineSpan> spans) => _Block(_Kind.item, spans);

  final _Kind kind;
  final List<InlineSpan> spans;

  double get spaceAbove => switch (kind) {
    _Kind.headingLarge => 26,
    _Kind.headingSmall => 20,
    _Kind.item => 8,
    _Kind.paragraph => 16,
  };

  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    if (kind == _Kind.item) {
      return Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // A rule, not a bullet. The rest of the app marks entries with
          // rules; a dot would be the only circle on the page.
          Container(
            width: 8,
            height: 1.5,
            margin: const EdgeInsets.only(top: 12, right: 12),
            color: AppColors.ruleStrong,
          ),
          Expanded(
            child: Text.rich(
              TextSpan(children: spans),
              style: theme.textTheme.bodyLarge?.copyWith(height: 1.6),
            ),
          ),
        ],
      );
    }

    return Text.rich(
      TextSpan(children: spans),
      style: switch (kind) {
        _Kind.headingLarge => theme.textTheme.titleMedium,
        _Kind.headingSmall => theme.textTheme.titleSmall,
        _ => theme.textTheme.bodyLarge?.copyWith(height: 1.65),
      },
    );
  }
}
