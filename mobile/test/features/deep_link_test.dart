import 'package:antaranote/core/config/app_config.dart';
import 'package:antaranote/features/recorder/deep_link.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('DeepLinkEntry.tokenFrom', () {
    test('reads the token from a shared https invite link', () {
      final uri = Uri.parse('https://note.antara.cloud/live/join/abc123TOKEN');

      expect(DeepLinkEntry.tokenFrom(uri), 'abc123TOKEN');
    });

    test('still reads the token from the custom-scheme fallback link', () {
      final uri = Uri.parse('antaranote://live/join/abc123TOKEN');

      expect(DeepLinkEntry.tokenFrom(uri), 'abc123TOKEN');
    });

    test('ignores an https link that is not a join', () {
      expect(
        DeepLinkEntry.tokenFrom(Uri.parse('https://note.antara.cloud/lobby/x')),
        isNull,
      );
      expect(
        DeepLinkEntry.tokenFrom(Uri.parse('https://note.antara.cloud/')),
        isNull,
      );
    });

    test('ignores a scheme that is neither ours nor the web', () {
      expect(
        DeepLinkEntry.tokenFrom(Uri.parse('mailto:live/join/abc')),
        isNull,
      );
    });

    test('ignores our scheme pointed at something other than a join', () {
      expect(
        DeepLinkEntry.tokenFrom(Uri.parse('antaranote://live/start/abc123')),
        isNull,
      );
      expect(
        DeepLinkEntry.tokenFrom(Uri.parse('antaranote://meeting/42')),
        isNull,
      );
    });

    test('ignores a join link with no token', () {
      expect(
        DeepLinkEntry.tokenFrom(Uri.parse('antaranote://live/join')),
        isNull,
      );
      expect(
        DeepLinkEntry.tokenFrom(Uri.parse('https://note.antara.cloud/live/join')),
        isNull,
      );
    });

    test('ignores extra path beyond the token', () {
      expect(
        DeepLinkEntry.tokenFrom(Uri.parse('antaranote://live/join/tok/extra')),
        isNull,
      );
    });
  });

  group('DeepLinkEntry.linkFor', () {
    test('shares an https link the parser reads back to the same token', () {
      const token = 'roundTripTOKEN0123456789';

      final link = DeepLinkEntry.linkFor(token);

      expect(link, '${AppConfig.apiBaseUrl}/live/join/$token');
      expect(link, startsWith('https://'));
      expect(DeepLinkEntry.tokenFrom(Uri.parse(link)), token);
    });
  });
}
