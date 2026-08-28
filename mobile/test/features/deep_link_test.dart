import 'package:antaranote/features/recorder/deep_link.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('DeepLinkEntry.tokenFrom', () {
    test('reads the token from a satellite invite link', () {
      final uri = Uri.parse('antaranote://live/join/abc123TOKEN');

      expect(DeepLinkEntry.tokenFrom(uri), 'abc123TOKEN');
    });

    test('ignores a link on a scheme that is not ours', () {
      final uri = Uri.parse('https://note.antara.cloud/live/join/abc123');

      expect(DeepLinkEntry.tokenFrom(uri), isNull);
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
    });

    test('ignores extra path beyond the token', () {
      expect(
        DeepLinkEntry.tokenFrom(Uri.parse('antaranote://live/join/tok/extra')),
        isNull,
      );
    });
  });

  group('DeepLinkEntry.linkFor', () {
    test('builds a link the parser reads back to the same token', () {
      const token = 'roundTripTOKEN0123456789';

      final link = DeepLinkEntry.linkFor(token);

      expect(link, 'antaranote://live/join/$token');
      expect(DeepLinkEntry.tokenFrom(Uri.parse(link)), token);
    });
  });
}
