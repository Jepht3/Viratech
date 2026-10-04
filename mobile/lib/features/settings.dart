import 'package:flutter/material.dart';

import '../core/config.dart';
import '../core/theme.dart';
import '../services/api.dart';
import '../services/session.dart';
import '../ui/widgets.dart';
import 'update.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});
  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  bool checking = false;

  Future<void> _savePrefs({bool? email, bool? push}) async {
    final u = session.user!;
    try {
      await Api.i.post('/preferences', data: {'notify_email': email ?? u['notify_email'] == true, 'notify_push': push ?? u['notify_push'] == true});
      await session.refreshUser();
      if (mounted) setState(() {});
    } catch (e) {
      if (mounted) toast(context, '$e');
    }
  }

  Future<void> _checkUpdate() async {
    setState(() => checking = true);
    try {
      final info = await checkForUpdate();
      if (!mounted) return;
      if (info.available) {
        await showUpdateDialog(context, info);
      } else {
        toast(context, 'Vous avez déjà la dernière version.');
      }
    } catch (_) {
      if (mounted) toast(context, 'Impossible de vérifier pour le moment.');
    } finally {
      if (mounted) setState(() => checking = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final u = session.user!;
    return ListView(padding: kPagePadding, children: [
      const PageTitle('Paramètres'),
      Panel(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Mon compte', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          const SizedBox(height: 8),
          _line('Nom', '${u['name']}'),
          _line('Email', '${u['email']}'),
          _line('Téléphone', '${u['phone'] ?? '—'}'),
          if (u['role'] == 'client') _line('Niveau de vérification', '${u['kyc_level']}'),
        ]),
      ),
      const SizedBox(height: 14),
      Panel(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
        child: Column(children: [
          SwitchListTile(contentPadding: EdgeInsets.zero, activeThumbColor: VT.teal, title: const Text('Recevoir les emails'), value: u['notify_email'] == true, onChanged: (v) => _savePrefs(email: v)),
          SwitchListTile(contentPadding: EdgeInsets.zero, activeThumbColor: VT.teal, title: const Text('Notifications dans la barre Android'), subtitle: const Text('Les alertes de sécurité sont toujours envoyées.', style: TextStyle(fontSize: 11.5)), value: u['notify_push'] == true, onChanged: (v) => _savePrefs(push: v)),
        ]),
      ),
      const SizedBox(height: 14),
      Panel(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('À propos', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          const SizedBox(height: 8),
          _line('Application', AppConfig.isAdmin ? 'Viratech Admin' : 'Viratech'),
          _line('Version', '${AppConfig.version}${AppConfig.buildNumber > 0 ? ' (build ${AppConfig.buildNumber})' : ' (développement)'}'),
          _line('Serveur', AppConfig.apiUrl),
          const SizedBox(height: 10),
          OutlinedButton.icon(onPressed: checking ? null : _checkUpdate, icon: const Icon(Icons.system_update_rounded, size: 18), label: Text(checking ? 'Vérification…' : 'Vérifier la mise à jour')),
        ]),
      ),
      const SizedBox(height: 14),
      OutlinedButton.icon(style: OutlinedButton.styleFrom(foregroundColor: VT.badFg), onPressed: () => session.logout(), icon: const Icon(Icons.logout_rounded, size: 18), label: const Text('Se déconnecter')),
    ]);
  }

  Widget _line(String k, String v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text(k, style: const TextStyle(color: VT.mut)), const SizedBox(width: 12), Flexible(child: Text(v, textAlign: TextAlign.right, style: const TextStyle(fontWeight: FontWeight.w600)))]),
      );
}
