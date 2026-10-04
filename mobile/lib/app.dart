import 'package:flutter/material.dart';

import 'core/config.dart';
import 'core/theme.dart';
import 'features/auth.dart';
import 'services/session.dart';
import 'ui/shell.dart';

void runViratech({required bool admin}) {
  WidgetsFlutterBinding.ensureInitialized();
  AppConfig.isAdmin = admin;
  runApp(const ViratechApp());
}

class ViratechApp extends StatelessWidget {
  const ViratechApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: AppConfig.isAdmin ? 'Viratech Admin' : 'Viratech',
      debugShowCheckedModeBanner: false,
      theme: VT.theme(),
      home: const _Gate(),
    );
  }
}

/// Affiche la connexion ou l'application selon la session.
class _Gate extends StatefulWidget {
  const _Gate();
  @override
  State<_Gate> createState() => _GateState();
}

class _GateState extends State<_Gate> {
  @override
  void initState() {
    super.initState();
    session.addListener(_changed);
    session.restore();
  }

  @override
  void dispose() {
    session.removeListener(_changed);
    super.dispose();
  }

  void _changed() {
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    if (!session.ready) {
      return const Scaffold(body: Center(child: CircularProgressIndicator(color: VT.teal)));
    }
    return session.loggedIn ? const AppShell() : const LoginScreen();
  }
}
