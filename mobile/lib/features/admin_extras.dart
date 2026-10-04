import 'dart:typed_data';

import 'package:flutter/material.dart';

import '../core/format.dart';
import '../core/theme.dart';
import '../services/api.dart';
import '../ui/widgets.dart';

// ───────────────────────── Vérifications d'identité ─────────────────────────

class VerificationsScreen extends StatelessWidget {
  const VerificationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DataView<Map<String, dynamic>>(
      load: () async => Map<String, dynamic>.from(await Api.i.get('/admin/kyc') as Map),
      builder: (context, d, refresh) {
        final pending = (d['pending'] as List).cast<Map>();
        final done = (d['done'] as List).cast<Map>();
        Widget row(Map s) => Panel(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(14),
              onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => VerificationDetailScreen(id: (s['id'] as num).toInt()))).then((_) => refresh()),
              child: Row(children: [
                CircleAvatar(backgroundColor: VT.netBg, child: Text('${s['client']['name']}'.isEmpty ? '?' : '${s['client']['name']}'[0].toUpperCase(), style: const TextStyle(color: VT.teal, fontWeight: FontWeight.w800))),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('${s['client']['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                    Text('${s['id_type']} · ${since(s['submitted_at'])}', style: const TextStyle(color: VT.mut, fontSize: 12)),
                  ]),
                ),
                if ((s['flags'] as List).isNotEmpty) Pill('⚠ ${(s['flags'] as List).length}', kind: 'bad') else Pill('${s['status_label']}', kind: s['status'] == 'approved' ? 'ok' : (s['status'] == 'pending' ? 'wait' : 'bad')),
              ]),
            );
        return ListView(padding: kPagePadding, children: [
          const PageTitle("Vérifications d'identité", subtitle: 'Comparez visage, pièce et code manuscrit'),
          Text('À traiter (${pending.length})', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          const SizedBox(height: 10),
          if (pending.isEmpty) const Padding(padding: EdgeInsets.all(24), child: Center(child: Text('Aucun dossier à traiter. 🎉', style: TextStyle(color: VT.mut)))),
          for (final s in pending) row(s),
          const SizedBox(height: 14),
          const Text('Derniers dossiers traités', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          const SizedBox(height: 10),
          for (final s in done) row(s),
        ]);
      },
    );
  }
}

class VerificationDetailScreen extends StatefulWidget {
  const VerificationDetailScreen({super.key, required this.id});
  final int id;
  @override
  State<VerificationDetailScreen> createState() => _VerificationDetailScreenState();
}

class _VerificationDetailScreenState extends State<VerificationDetailScreen> {
  bool busy = false;

  Future<void> _decide(String action, {String? reason}) async {
    setState(() => busy = true);
    try {
      await Api.i.post('/admin/kyc/${widget.id}/$action', data: reason == null ? null : {'reason': reason});
      if (mounted) {
        toast(context, action == 'approve' ? 'Identité vérifiée. Le plafond du client est mis à jour.' : 'Dossier refusé, le client est prévenu.');
        Navigator.of(context).pop();
      }
    } catch (e) {
      if (mounted) toast(context, '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> _reject() async {
    final c = TextEditingController();
    final r = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Refuser le dossier'),
        content: TextField(controller: c, autofocus: true, decoration: const InputDecoration(hintText: 'Raison montrée au client (ex. photo floue)')),
        actions: [TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Annuler')), FilledButton(onPressed: () => Navigator.pop(ctx, c.text.trim()), child: const Text('Refuser'))],
      ),
    );
    if (r != null && r.isNotEmpty) await _decide('reject', reason: r);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Dossier d\'identité', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18))),
      body: DataView<Map<String, dynamic>>(
        load: () async => Map<String, dynamic>.from(await Api.i.get('/admin/kyc/${widget.id}') as Map),
        builder: (context, d, refresh) {
          final flags = (d['flags'] as List).cast<String>();
          final dup = (d['same_photos_other_accounts'] as List).cast<String>();
          final pending = d['status'] == 'pending';
          return ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 40), children: [
            if (flags.isNotEmpty || dup.isNotEmpty)
              Container(
                margin: const EdgeInsets.only(bottom: 14),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: VT.badBg, borderRadius: BorderRadius.circular(16)),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('⚠ Alertes automatiques', style: TextStyle(color: VT.badFg, fontWeight: FontWeight.w800)),
                  for (final f in flags) Text('• $f', style: const TextStyle(color: VT.badFg, fontSize: 13)),
                  if (dup.isNotEmpty) Text('• Photos identiques sur d\'autres comptes : ${dup.join(', ')}', style: const TextStyle(color: VT.badFg, fontSize: 13)),
                ]),
              ),
            Panel(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('${d['client']['name']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
                Text('${d['client']['phone']} · ${d['id_type']}', style: const TextStyle(color: VT.mut)),
                const SizedBox(height: 12),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(color: VT.netBg, borderRadius: BorderRadius.circular(16)),
                  child: Column(children: [
                    const Text('Code attendu sur la feuille', style: TextStyle(color: VT.mut, fontSize: 12)),
                    Text('${d['challenge_code']}', style: const TextStyle(fontSize: 40, fontWeight: FontWeight.w800, letterSpacing: 8, color: VT.teal)),
                    const Text('Le selfie doit montrer ce code écrit à la main, le visage et la pièce dans la main droite.', textAlign: TextAlign.center, style: TextStyle(color: VT.mut, fontSize: 11.5)),
                  ]),
                ),
              ]),
            ),
            const SizedBox(height: 14),
            _photo('Selfie', '/admin/kyc/${widget.id}/file/selfie'),
            _photo("Pièce d'identité (avant)", '/admin/kyc/${widget.id}/file/front'),
            if (d['has_back'] == true) _photo("Pièce d'identité (arrière)", '/admin/kyc/${widget.id}/file/back'),
            if (pending) ...[
              FilledButton(onPressed: busy ? null : () => _decide('approve'), child: const Text("✓ Tout correspond : approuver l'identité")),
              const SizedBox(height: 10),
              OutlinedButton(style: OutlinedButton.styleFrom(foregroundColor: VT.badFg), onPressed: busy ? null : _reject, child: const Text('Refuser le dossier')),
            ] else if (d['rejection_reason'] != null)
              Container(padding: const EdgeInsets.all(14), decoration: BoxDecoration(color: VT.badBg, borderRadius: BorderRadius.circular(16)), child: Text('Refusé : ${d['rejection_reason']}', style: const TextStyle(color: VT.badFg, fontWeight: FontWeight.w600))),
          ]);
        },
      ),
    );
  }

  Widget _photo(String label, String path) => Padding(
        padding: const EdgeInsets.only(bottom: 14),
        child: Panel(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(label, style: const TextStyle(fontWeight: FontWeight.w800)),
            const SizedBox(height: 10),
            FutureBuilder<Uint8List>(
              future: Api.i.bytes(path),
              builder: (context, s) {
                if (s.hasError) return const Text('Image indisponible.', style: TextStyle(color: VT.mut));
                if (!s.hasData) return const SizedBox(height: 120, child: Center(child: CircularProgressIndicator(color: VT.teal)));
                return GestureDetector(
                  onTap: () => showDialog<void>(context: context, builder: (_) => Dialog(child: InteractiveViewer(child: Image.memory(s.data!)))),
                  child: ClipRRect(borderRadius: BorderRadius.circular(14), child: Image.memory(s.data!, width: double.infinity, fit: BoxFit.cover)),
                );
              },
            ),
          ]),
        ),
      );
}

// ───────────────────────── Intégrations : FlexPay, emails (Resend), notifications (Google) ─────────────────────────

class IntegrationsScreen extends StatefulWidget {
  const IntegrationsScreen({super.key});
  @override
  State<IntegrationsScreen> createState() => _IntegrationsScreenState();
}

class _IntegrationsScreenState extends State<IntegrationsScreen> {
  int rev = 0;

  @override
  Widget build(BuildContext context) {
    return DataView<Map<String, dynamic>>(
      key: ValueKey(rev),
      load: () async => Map<String, dynamic>.from(await Api.i.get('/admin/settings') as Map),
      builder: (context, s, refresh) => _Form(settings: s, onSaved: () => setState(() => rev++)),
    );
  }
}

class _Form extends StatefulWidget {
  const _Form({required this.settings, required this.onSaved});
  final Map<String, dynamic> settings;
  final VoidCallback onSaved;
  @override
  State<_Form> createState() => _FormState();
}

class _FormState extends State<_Form> {
  late bool flexEnabled = widget.settings['flexpay.enabled'] == true;
  late bool payoutEnabled = widget.settings['flexpay.payout_enabled'] == true;
  late String env = '${widget.settings['flexpay.environment'] ?? 'sandbox'}';
  late final merchant = TextEditingController(text: '${widget.settings['flexpay.merchant'] ?? ''}');
  final token = TextEditingController();
  final resend = TextEditingController();
  late final fromAddr = TextEditingController(text: '${widget.settings['mail.from_address'] ?? ''}');
  late final fromName = TextEditingController(text: '${widget.settings['mail.from_name'] ?? 'Viratech'}');
  late final project = TextEditingController(text: '${widget.settings['push.fcm_project_id'] ?? ''}');
  final account = TextEditingController();
  bool busy = false;

  @override
  void dispose() {
    for (final c in [merchant, token, resend, fromAddr, fromName, project, account]) {
      c.dispose();
    }
    super.dispose();
  }

  String? _hint(String key, String empty) {
    final v = widget.settings[key] as Map?;
    return v != null && v['set'] == true ? '${v['masked']} · laisser vide pour garder' : empty;
  }

  Future<void> _save() async {
    setState(() => busy = true);
    try {
      await Api.i.post('/admin/settings', data: {
        'flexpay_enabled': flexEnabled,
        'flexpay_payout_enabled': payoutEnabled,
        'flexpay_environment': env,
        'flexpay_merchant': merchant.text.trim(),
        if (token.text.trim().isNotEmpty) 'flexpay_token': token.text.trim(),
        if (resend.text.trim().isNotEmpty) 'mail_resend_key': resend.text.trim(),
        'mail_from_address': fromAddr.text.trim(),
        'mail_from_name': fromName.text.trim(),
        'push_fcm_project_id': project.text.trim(),
        if (account.text.trim().isNotEmpty) 'push_fcm_service_account': account.text.trim(),
      });
      if (mounted) {
        toast(context, 'Paramètres enregistrés.');
        widget.onSaved();
      }
    } catch (e) {
      if (mounted) toast(context, '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListView(padding: kPagePadding, children: [
      const PageTitle('Intégrations', subtitle: 'Les clés sont chiffrées et jamais réaffichées'),
      Panel(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            const Expanded(child: Text('FlexPay (mobile money et carte Visa)', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16))),
            Switch(value: flexEnabled, activeThumbColor: VT.teal, onChanged: (v) => setState(() => flexEnabled = v)),
          ]),
          const SizedBox(height: 8),
          DropdownButtonFormField<String>(initialValue: env, decoration: const InputDecoration(labelText: 'Environnement'), items: const [DropdownMenuItem(value: 'sandbox', child: Text('Test (sandbox)')), DropdownMenuItem(value: 'live', child: Text('Production'))], onChanged: (v) => setState(() => env = v ?? env)),
          const SizedBox(height: 10),
          TextField(controller: merchant, decoration: const InputDecoration(labelText: 'Code marchand')),
          const SizedBox(height: 10),
          TextField(controller: token, obscureText: true, decoration: InputDecoration(labelText: 'Jeton (token) FlexPay', hintText: _hint('flexpay.token', 'Collez le jeton'))),
          SwitchListTile(contentPadding: EdgeInsets.zero, activeThumbColor: VT.teal, title: const Text('Versement vers les clients', style: TextStyle(fontSize: 14)), subtitle: const Text("Opération inverse : l'argent part de votre mobile money vers le client. À activer aussi chez FlexPay.", style: TextStyle(fontSize: 11.5)), value: payoutEnabled, onChanged: (v) => setState(() => payoutEnabled = v)),
          const Text('Adresse de rappel à donner à FlexPay :', style: TextStyle(color: VT.mut, fontSize: 12)),
          SelectableText('${widget.settings['flexpay.callback_url']}', style: const TextStyle(fontFamily: 'monospace', fontSize: 11.5)),
          const SizedBox(height: 10),
          Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: VT.waitBg, borderRadius: BorderRadius.circular(14)), child: const Text("Les adresses et champs viennent d'une bibliothèque tierce (la documentation officielle n'est pas publique) : faites un premier essai en mode Test avec un petit montant.", style: TextStyle(color: VT.waitFg, fontSize: 12))),
        ]),
      ),
      const SizedBox(height: 14),
      Panel(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Emails (Resend)', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          const SizedBox(height: 10),
          TextField(controller: resend, obscureText: true, decoration: InputDecoration(labelText: 'Clé API Resend', hintText: _hint('mail.resend_key', 're_...'))),
          const SizedBox(height: 10),
          TextField(controller: fromAddr, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: "Adresse d'envoi (domaine vérifié)")),
          const SizedBox(height: 10),
          TextField(controller: fromName, decoration: const InputDecoration(labelText: 'Nom affiché')),
          const SizedBox(height: 10),
          OutlinedButton(
            onPressed: busy
                ? null
                : () async {
                    try {
                      await Api.i.post('/admin/settings/test-mail');
                      if (context.mounted) toast(context, 'Email de test envoyé.');
                    } catch (e) {
                      if (context.mounted) toast(context, '$e');
                    }
                  },
            child: const Text('Envoyer un email de test à moi'),
          ),
        ]),
      ),
      const SizedBox(height: 14),
      Panel(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Notifications push (Google / Firebase)', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          const SizedBox(height: 10),
          TextField(controller: project, decoration: const InputDecoration(labelText: 'Identifiant du projet Firebase')),
          const SizedBox(height: 10),
          TextField(controller: account, maxLines: 4, decoration: InputDecoration(labelText: 'Compte de service (JSON)', hintText: (widget.settings['push.fcm_service_account'] as Map?)?['set'] == true ? 'Fichier enregistré · laisser vide pour garder' : 'Collez le contenu du fichier JSON Firebase')),
          const SizedBox(height: 6),
          const Text('Firebase → Paramètres du projet → Comptes de service → Générer une nouvelle clé privée.', style: TextStyle(color: VT.mut, fontSize: 11.5)),
        ]),
      ),
      const SizedBox(height: 16),
      FilledButton(onPressed: busy ? null : _save, child: const Text('Enregistrer les paramètres')),
    ]);
  }
}
