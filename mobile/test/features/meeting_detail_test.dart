import 'package:antaranote/domain/models/meeting_detail.dart';
import 'package:antaranote/features/widgets/prose.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Map<String, dynamic> meeting({
  String status = 'draft',
  bool canFinalize = true,
  bool canApprove = true,
}) => {
  'id': 7,
  'title': 'Board Meeting',
  'status': status,
  'permissions': {
    'can_finalize': canFinalize,
    'can_approve': canApprove,
    'can_update': true,
    'can_start_live': true,
  },
};

void main() {
  // The screen shows one action, never a menu of the workflow. Getting this
  // wrong either offers Approve on a draft — which the server rejects — or
  // leaves a record with no way forward.
  group('the next step a record is waiting for', () {
    test('a draft is waiting to be finalised', () {
      final step = MeetingDetail.fromJson(meeting()).nextStep;

      expect(step?.path, 'finalize');
    });

    test('a finalised record is waiting to be approved', () {
      final step = MeetingDetail.fromJson(
        meeting(status: 'finalized'),
      ).nextStep;

      expect(step?.path, 'approve');
    });

    test('an approved record is waiting for nothing', () {
      expect(
        MeetingDetail.fromJson(meeting(status: 'approved')).nextStep,
        isNull,
      );
    });

    test('somebody who cannot finalise is offered nothing on a draft', () {
      final detail = MeetingDetail.fromJson(meeting(canFinalize: false));

      expect(detail.nextStep, isNull);
    });

    test(
      'somebody who cannot approve is offered nothing on a finalised one',
      () {
        final detail = MeetingDetail.fromJson(
          meeting(status: 'finalized', canApprove: false),
        );

        expect(detail.nextStep, isNull);
      },
    );
  });

  group('resolutions', () {
    Resolution parse(Map<String, dynamic> json) => Resolution.fromJson({
      'id': 1,
      'title': 'That the budget be adopted',
      ...json,
    });

    test('reads the tally the way a minute book writes it', () {
      final resolution = parse({
        'status': 'passed',
        'tally': {'for': 12, 'against': 1, 'abstain': 2},
      });

      expect(resolution.tally, '12–1–2');
      expect(resolution.carried, isTrue);
      expect(resolution.wasVoted, isTrue);
    });

    test('knows when nobody has voted yet', () {
      final resolution = parse({'status': 'proposed', 'tally': null});

      expect(resolution.wasVoted, isFalse);
      expect(resolution.open, isTrue);
    });
  });

  // The same field carries HTML from the web editor and plain text from a
  // transcript draft, so both have to come out as readable blocks.
  group('the minutes body', () {
    List<String> textOf(WidgetTester tester) => tester
        .widgetList<RichText>(find.byType(RichText))
        .map((widget) => widget.text.toPlainText())
        .toList();

    Future<void> pump(WidgetTester tester, String source) => tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: Prose(source: source)),
      ),
    );

    testWidgets('splits plain text on blank lines', (tester) async {
      await pump(tester, 'First point.\n\nSecond point.');

      expect(textOf(tester), ['First point.', 'Second point.']);
    });

    testWidgets('reads paragraphs, headings and list items out of HTML', (
      tester,
    ) async {
      await pump(
        tester,
        '<h2>Apologies</h2><p>None were received.</p>'
        '<ul><li>Chair to circulate</li></ul>',
      );

      expect(textOf(tester), [
        'Apologies',
        'None were received.',
        'Chair to circulate',
      ]);
    });

    testWidgets('resolves entities rather than printing them', (tester) async {
      await pump(tester, '<p>Ali &amp; Sons &lt; budget</p>');

      expect(textOf(tester).single, 'Ali & Sons < budget');
    });

    testWidgets('keeps text that sits outside any tag', (tester) async {
      await pump(tester, 'Loose opening.<p>In a tag.</p>');

      expect(textOf(tester), ['Loose opening.', 'In a tag.']);
    });

    // A comparison in prose must not be mistaken for markup.
    testWidgets('a bare angle bracket is not treated as HTML', (tester) async {
      await pump(tester, 'Spend < RM50,000 needs no approval.');

      expect(textOf(tester).single, 'Spend < RM50,000 needs no approval.');
    });

    testWidgets('says so when there are no minutes rather than nothing', (
      tester,
    ) async {
      await pump(tester, '   ');

      expect(textOf(tester).single, 'No minutes have been written yet.');
    });
  });
}
