import 'package:flutter/material.dart';

import '../widgets/placeholder_screen.dart';

class TasksScreen extends StatelessWidget {
  const TasksScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const PlaceholderScreen(
      title: 'Tasks',
      icon: Icons.checklist_outlined,
      body: 'Your action items, with swipe to complete, land next.',
    );
  }
}
