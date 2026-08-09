import 'package:flutter/material.dart';

import '../widgets/placeholder_screen.dart';

class MeetingsScreen extends StatelessWidget {
  const MeetingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const PlaceholderScreen(
      title: 'Meetings',
      icon: Icons.event_note_outlined,
      body: 'The meeting list and minutes reader land next.',
    );
  }
}
