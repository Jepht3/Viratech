import 'package:flutter/material.dart';

import '../core/config.dart';
import '../core/theme.dart';
import '../services/session.dart';
import '../ui/widgets.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _pass = TextEditingController();
  bool register = false;
  bool busy = false;
  bool hide = true;
  String? error;

  @override
  void dispose() {
    for (final c in [_name, _email, _phone, _pass]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      if (register) {
        await session.register(_name.text.trim(), _email.text.trim(), _phone.text.trim(), _pass.text);
      } else {
        await session.login(_email.text.trim(), _pass.text);
      }
    } catch (e) {
      if (mounted) setState(() => error = '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final admin = AppConfig.isAdmin;
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [VT.teal, VT.navy])),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(22),
              child: Column(children: [
                Container(
                  width: 84,
                  height: 84,
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(26), boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.2), blurRadius: 24, offset: const Offset(0, 10))]),
                  child: Image.asset('assets/brand/mark.png', fit: BoxFit.contain),
                ),
                const SizedBox(height: 14),
                Text(admin ? 'Viratech Admin' : 'Viratech', style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w800, letterSpacing: -0.5)),
                const SizedBox(height: 4),
                Text(admin ? "Console de l'équipe" : 'Reçois en dollars, retire en local, sans stress.', style: TextStyle(color: Colors.white.withValues(alpha: 0.8), fontSize: 13)),
                const SizedBox(height: 26),
                Panel(
                  padding: const EdgeInsets.all(22),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    Text(register ? 'Créer un compte' : 'Connexion', style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 16),
                    if (register) ...[
                      TextField(controller: _name, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: "Nom complet (comme sur votre pièce d'identité)")),
                      const SizedBox(height: 12),
                    ],
                    TextField(controller: _email, keyboardType: TextInputType.emailAddress, autocorrect: false, decoration: const InputDecoration(labelText: 'Email')),
                    const SizedBox(height: 12),
                    if (register) ...[
                      TextField(controller: _phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Téléphone (WhatsApp)', hintText: '+243 ...')),
                      const SizedBox(height: 12),
                    ],
                    TextField(
                      controller: _pass,
                      obscureText: hide,
                      onSubmitted: (_) => busy ? null : _submit(),
                      decoration: InputDecoration(labelText: 'Mot de passe', suffixIcon: IconButton(icon: Icon(hide ? Icons.visibility_rounded : Icons.visibility_off_rounded), onPressed: () => setState(() => hide = !hide))),
                    ),
                    if (error != null) Padding(padding: const EdgeInsets.only(top: 12), child: Text(error!, style: const TextStyle(color: VT.badFg, fontSize: 13))),
                    const SizedBox(height: 18),
                    FilledButton(onPressed: busy ? null : _submit, child: busy ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white)) : Text(register ? 'Créer mon compte' : 'Se connecter')),
                    if (!admin)
                      TextButton(
                        onPressed: () => setState(() {
                          register = !register;
                          error = null;
                        }),
                        child: Text(register ? 'Déjà inscrit ? Se connecter' : 'Pas encore de compte ? Créer un compte'),
                      ),
                  ]),
                ),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}
