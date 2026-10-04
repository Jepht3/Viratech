import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/format.dart';
import '../core/theme.dart';
import '../services/api.dart';
import '../services/session.dart';
import '../ui/widgets.dart';

/// Détail et suivi d'une commande. Même écran pour le client et pour l'opérateur (admin = true).
///
/// Règles : le client envoie la capture de SON paiement (sauf facture PayPal / FlexPay, confirmés automatiquement) ;
/// l'opérateur envoie la capture du VERSEMENT, que le client retrouve ici.
class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({super.key, required this.reference, this.admin = false});
  final String reference;
  final bool admin;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  Map<String, dynamic>? o;
  String? error;
  bool busy = false;
  Timer? _timer;
  final _code = TextEditingController();
  final _phone = TextEditingController();
  String? _file;

  String get _base => widget.admin ? '/admin/orders/${widget.reference}' : '/orders/${widget.reference}';

  @override
  void initState() {
    super.initState();
    _phone.text = '${session.user?['phone'] ?? ''}';
    _load();
    _timer = Timer.periodic(const Duration(seconds: 15), (_) => _load());
  }

  @override
  void dispose() {
    _timer?.cancel();
    _code.dispose();
    _phone.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final r = await Api.i.get(_base);
      if (mounted) {
        setState(() {
          o = Map<String, dynamic>.from(r as Map);
          error = null;
        });
      }
    } catch (e) {
      if (mounted && o == null) setState(() => error = '$e');
    }
  }

  Future<void> _run(Future<dynamic> Function() action, {String? ok}) async {
    setState(() => busy = true);
    try {
      final r = await action();
      if (r is Map && r['reference'] != null) o = Map<String, dynamic>.from(r);
      _code.clear();
      _file = null;
      if (ok != null && mounted) toast(context, ok);
    } catch (e) {
      if (mounted) toast(context, '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> _pick() async {
    final path = await pickPhoto(context);
    if (path != null && mounted) setState(() => _file = path);
  }

  Future<String?> _ask(String title, String hint) async {
    final c = TextEditingController();
    final r = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: TextField(controller: c, autofocus: true, decoration: InputDecoration(hintText: hint)),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(ctx, c.text.trim()), child: const Text('Confirmer')),
        ],
      ),
    );
    return (r == null || r.isEmpty) ? null : r;
  }

  Future<void> _viewProof(int id) async {
    try {
      final bytes = await Api.i.bytes('/orders/${widget.reference}/proofs/$id');
      if (!mounted) return;
      showDialog<void>(context: context, builder: (_) => Dialog(child: InteractiveViewer(child: Image.memory(bytes, errorBuilder: (_, _, _) => const Padding(padding: EdgeInsets.all(30), child: Text('Ce fichier ne peut pas être affiché.'))))));
    } catch (e) {
      if (mounted) toast(context, '$e');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Commande ${widget.reference}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18))),
      body: o == null ? (error != null ? ErrorView(error!, onRetry: _load) : const Loader()) : RefreshIndicator(color: VT.teal, onRefresh: _load, child: _content(o!)),
    );
  }

  Widget _content(Map<String, dynamic> o) {
    final c = o['corridor'] as Map;
    final active = o['status'] == 'active';
    final steps = o['steps'] as List;
    final payout = o['payout'] as Map;
    final hold = o['hold_active'] == true;
    return ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 40), children: [
      Panel(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Corridor2('${c['source_kind']}', '${c['target_kind']}'),
            const SizedBox(width: 10),
            Expanded(child: Text('${c['label']}', style: const TextStyle(fontWeight: FontWeight.w800))),
            Pill('${o['status_label']}', kind: statusKind('${o['status']}')),
          ]),
          const SizedBox(height: 14),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(color: VT.netBg, borderRadius: BorderRadius.circular(18), border: Border.all(color: const Color(0xFFBFE3CD))),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(widget.admin ? 'À verser' : 'Vous recevrez', style: const TextStyle(color: VT.mut, fontSize: 12)),
              Text(money(n(o['net_amount'])), style: const TextStyle(color: VT.teal, fontSize: 32, fontWeight: FontWeight.w800, letterSpacing: -0.5)),
              Text('sur ${payout['kind']} · ${payout['holder']} · ${payout['account']}', style: const TextStyle(color: VT.mut, fontSize: 12)),
            ]),
          ),
          const SizedBox(height: 8),
          _kv(widget.admin ? 'Montant reçu' : 'Montant que vous envoyez', money(n(o['amount']))),
          _kv('Frais (${pct(n(o['percent']))})', '− ${money(n(o['percent_fee']))}'),
          if (n(o['fixed_fee']) > 0) _kv('Frais fixes', '− ${money(n(o['fixed_fee']))}'),
          _kv('Délai habituel', '${o['eta']}'),
          if (o['payout_not_before'] != null) _kv('Délai de sécurité', hold ? "jusqu'au ${dateOnly(o['payout_not_before'])}" : 'écoulé'),
          if (widget.admin && o['client'] != null) _kv('Client', '${o['client']['name']} · N${o['client']['kyc_level']}'),
          if (widget.admin) _kv('Façon de payer', _methodLabel('${o['payment_method']}')),
          if (widget.admin) _kv('Nom du titulaire', payout['name_matches'] == true ? 'Identique au client ✓' : 'Différent du client ⚠'),
        ]),
      ),
      const SizedBox(height: 14),
      if (active && !widget.admin && o['instructions'] != null) _instructions(o),
      if (active && !widget.admin && o['instructions'] == null && hold) _holdInfoClient(o),
      if (active && widget.admin) _adminActions(o),
      if (!active && o['closed_reason'] != null)
        Container(margin: const EdgeInsets.only(bottom: 14), padding: const EdgeInsets.all(14), decoration: BoxDecoration(color: VT.badBg, borderRadius: BorderRadius.circular(16)), child: Text('${o['closed_reason']}', style: const TextStyle(color: VT.badFg, fontWeight: FontWeight.w600))),
      Panel(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Où en est ma commande ?', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          const Text('Étapes réelles, mises à jour à chaque action.', style: TextStyle(color: VT.mut, fontSize: 12)),
          const SizedBox(height: 14),
          StepsTimeline(steps: steps),
          if ((o['proofs'] as List).isNotEmpty) ...[
            const Divider(),
            for (final p in o['proofs'] as List)
              ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,
                title: Text('${p['kind'] == 'operator_payout' ? (widget.admin ? 'Versement (Viratech)' : 'Preuve du versement (Viratech)') : (widget.admin ? 'Paiement du client' : 'Ma preuve de paiement')}${p['reference'] != null ? ' · ${p['reference']}' : ''}', style: const TextStyle(fontSize: 13)),
                subtitle: Text(dayTime(p['created_at']), style: const TextStyle(fontSize: 11.5)),
                trailing: p['has_file'] == true ? TextButton(onPressed: () => _viewProof((p['id'] as num).toInt()), child: const Text('Voir la capture')) : null,
              ),
          ],
        ]),
      ),
    ]);
  }

  String _methodLabel(String m) => {
        'paypal_invoice': 'Facture PayPal (automatique)',
        'paypal_account': 'Envoi à notre PayPal + capture',
        'transfer': 'Virement direct + capture',
        'flexpay_mobile': 'Mobile money FlexPay',
        'flexpay_card': 'Carte Visa FlexPay',
      }[m] ??
      m;

  Widget _kv(String k, String v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 7),
        child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(k, style: const TextStyle(color: VT.mut, fontSize: 13.5)),
          const SizedBox(width: 12),
          Flexible(child: Text(v, textAlign: TextAlign.right, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5))),
        ]),
      );

  Widget _copyBox(String value) => Container(
        width: double.infinity,
        margin: const EdgeInsets.symmetric(vertical: 8),
        padding: const EdgeInsets.fromLTRB(14, 10, 6, 10),
        decoration: BoxDecoration(color: VT.soft, borderRadius: BorderRadius.circular(14), border: Border.all(color: const Color(0xFFCFD5E0))),
        child: Row(children: [
          Expanded(child: SelectableText(value, style: const TextStyle(fontFamily: 'monospace', fontWeight: FontWeight.w600))),
          IconButton(
            icon: const Icon(Icons.copy_rounded, size: 18),
            onPressed: () {
              Clipboard.setData(ClipboardData(text: value));
              toast(context, 'Copié');
            },
          ),
        ]),
      );

  Widget _holdInfoClient(Map<String, dynamic> o) => Panel(
        margin: const EdgeInsets.only(bottom: 14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Délai de sécurité', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          const SizedBox(height: 6),
          Text("Pour vous protéger comme nous protéger des litiges et rétrofacturations PayPal, les fonds issus de PayPal sont gardés jusqu'au ${dateOnly(o['payout_not_before'])} avant d'être versés. Votre paiement est bien reçu : rien à faire de votre côté.", style: const TextStyle(color: VT.mut, fontSize: 13.5)),
        ]),
      );

  /// Instructions de paiement du client + envoi de la preuve (capture obligatoire sauf paiement automatique).
  Widget _instructions(Map<String, dynamic> o) {
    final i = o['instructions'] as Map;
    final type = '${i['type']}';
    final amount = money(n(i['amount']));
    final needsProof = i['proof_required'] == true;
    return Panel(
      margin: const EdgeInsets.only(bottom: 14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [if (type == 'flexpay') ...[const Chan('flexpay', size: 30), const SizedBox(width: 10)], Text(type == 'deposit' ? "Envoyer l'argent" : (type == 'flexpay' ? 'Payer avec FlexPay' : 'Payer sur PayPal'), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16))]),
        const SizedBox(height: 8),
        if (type == 'paypal_invoice') ...[
          Text('Une facture PayPal de $amount est prête. Payez-la : votre paiement est détecté automatiquement, sans capture à envoyer.'),
          _copyBox('${i['invoice_id']}'),
        ] else if (type == 'paypal_account') ...[
          Text('Envoyez exactement $amount en « Biens et services » à :'),
          _copyBox('${i['account']}'),
          Text('Dans la note, indiquez : ${i['reference']}'),
        ] else if (type == 'flexpay') ...[
          Text(i['method'] == 'flexpay_mobile' ? 'Payez $amount avec votre mobile money : validez la demande sur votre téléphone avec votre code. La confirmation est automatique, sans capture.' : 'Payez $amount par carte Visa sur la page sécurisée FlexPay. La confirmation est automatique, sans capture.'),
          const SizedBox(height: 12),
          if (i['started'] == true) ...[
            Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: VT.okBg, borderRadius: BorderRadius.circular(14)), child: const Text('Demande envoyée. Confirmez sur votre téléphone : la commande avance toute seule.', style: TextStyle(color: VT.okFg, fontWeight: FontWeight.w600))),
            if (i['url'] != null) ...[const SizedBox(height: 10), FilledButton(onPressed: () => launchUrl(Uri.parse('${i['url']}'), mode: LaunchMode.externalApplication), child: const Text('Ouvrir la page de paiement'))],
          ] else ...[
            if (i['method'] == 'flexpay_mobile') TextField(controller: _phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Numéro mobile money à débiter')),
            const SizedBox(height: 10),
            FilledButton(
              onPressed: busy ? null : () => _run(() => Api.i.post('/orders/${widget.reference}/flexpay', data: {'phone': _phone.text.trim()}), ok: i['method'] == 'flexpay_mobile' ? 'Demande envoyée sur votre téléphone.' : 'Page de paiement prête.'),
              child: Text(i['method'] == 'flexpay_mobile' ? 'Payer avec mobile money' : 'Payer par carte Visa'),
            ),
          ],
        ] else ...[
          Text('Envoyez exactement $amount depuis votre compte ${i['from']} vers le numéro ${i['network'] ?? ''} de Viratech :'),
          _copyBox('${i['account'] ?? 'Numéro non configuré : contactez Viratech'}'),
          if (i['holder'] != null) Text('Au nom de ${i['holder']}', style: const TextStyle(color: VT.mut, fontSize: 12)),
          Text('Référence à indiquer : ${i['reference']}'),
        ],
        if (o['can_simulate_payment'] == true) ...[
          const SizedBox(height: 12),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: VT.navy),
            onPressed: busy ? null : () => _run(() => Api.i.post('/orders/${widget.reference}/simulate-payment', data: {'phone': _phone.text.trim()}), ok: 'Paiement simulé (mode local).'),
            child: const Text('Simuler le paiement (mode local)'),
          ),
        ],
        if (needsProof) ...[
          const SizedBox(height: 14),
          Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: VT.waitBg, borderRadius: BorderRadius.circular(14)), child: const Text('Après votre paiement, envoyez la capture de la transaction : sans elle, nous ne pouvons pas vérifier la réception.', style: TextStyle(color: VT.waitFg, fontWeight: FontWeight.w600, fontSize: 13))),
          const SizedBox(height: 12),
          OutlinedButton.icon(onPressed: _pick, icon: const Icon(Icons.add_photo_alternate_rounded, size: 18), label: Text(_file == null ? 'Joindre la capture (obligatoire)' : 'Capture jointe ✓')),
          const SizedBox(height: 10),
          TextField(controller: _code, decoration: const InputDecoration(labelText: 'Référence de la transaction (facultatif)')),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: (busy || _file == null) ? null : () => _run(() => Api.i.upload('/orders/${widget.reference}/proof', {'reference_code': _code.text.trim()}, filePath: _file), ok: 'Preuve envoyée. Un opérateur vérifie la réception.'),
            child: const Text('Envoyer ma preuve de paiement'),
          ),
        ],
        if (o['fees_locked_until'] != null) Padding(padding: const EdgeInsets.only(top: 10), child: Text("🔒 Frais garantis jusqu'à ${dayTime(o['fees_locked_until']).split(' ').last}", style: const TextStyle(color: VT.mut, fontSize: 12))),
      ]),
    );
  }

  /// Actions de l'opérateur : valider l'étape en cours (capture obligatoire pour le versement), FlexPay, bloquer, refuser.
  Widget _adminActions(Map<String, dynamic> o) {
    final cur = o['current_step'] as Map?;
    if (cur == null) return const SizedBox.shrink();
    final key = '${cur['key']}';
    final blocked = cur['blocked'] == true;
    final hold = o['hold_active'] == true;
    final needsProof = key == 'payout_done';
    final steps = o['steps'] as List;
    final label = steps.cast<Map>().firstWhere((s) => s['key'] == key, orElse: () => {'label': key})['label'];
    final isAdminUser = session.user?['role'] == 'admin';
    return Panel(
      margin: const EdgeInsets.only(bottom: 14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Étape en cours : ${cur['label']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
        const SizedBox(height: 10),
        if (blocked)
          FilledButton(onPressed: busy ? null : () => _run(() => Api.i.post('/admin/orders/${widget.reference}/unblock'), ok: 'Blocage levé.'), child: const Text('Lever le blocage'))
        else if (key == 'client_payment')
          const Text('En attente du paiement du client (capture ou confirmation automatique). Rien à faire pour le moment.', style: TextStyle(color: VT.mut))
        else if (hold && const ['security_check', 'payout_in_progress', 'payout_done'].contains(key)) ...[
          Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: VT.waitBg, borderRadius: BorderRadius.circular(14)), child: Text("⏳ Délai de sécurité jusqu'au ${dateOnly(o['payout_not_before'])} : vérifiez qu'aucun litige ou rétrofacturation n'est ouvert avant de verser.", style: const TextStyle(color: VT.waitFg, fontWeight: FontWeight.w600, fontSize: 13))),
          if (isAdminUser) ...[
            const SizedBox(height: 10),
            OutlinedButton(
              onPressed: busy
                  ? null
                  : () async {
                      final r = await _ask('Lever le délai de sécurité', 'Raison (journalisée)');
                      if (r != null) await _run(() => Api.i.post('/admin/orders/${widget.reference}/release-hold', data: {'reason': r}), ok: 'Délai de sécurité levé.');
                    },
              child: const Text('Lever le délai (administrateur)'),
            ),
          ],
        ] else ...[
          if (needsProof) ...[
            OutlinedButton.icon(onPressed: _pick, icon: const Icon(Icons.add_photo_alternate_rounded, size: 18), label: Text(_file == null ? 'Joindre la capture du versement (obligatoire)' : 'Capture jointe ✓')),
            const SizedBox(height: 10),
            TextField(controller: _code, decoration: const InputDecoration(labelText: 'Référence de la transaction (facultatif)')),
            const SizedBox(height: 10),
          ],
          FilledButton(
            onPressed: (busy || (needsProof && _file == null))
                ? null
                : () => _run(() => Api.i.upload('/admin/orders/${widget.reference}/step', {'key': key, if (_code.text.trim().isNotEmpty) 'reference_code': _code.text.trim()}, filePath: _file), ok: 'Étape validée. Le client est prévenu.'),
            child: Text('✓ Valider : $label'),
          ),
          if (key == 'payment_verified') const Padding(padding: EdgeInsets.only(top: 6), child: Text("Confirmez uniquement si l'argent est bien arrivé et que le nom du payeur correspond au client.", style: TextStyle(color: VT.mut, fontSize: 11.5))),
          if (key == 'payout_done' && o['can_flexpay_payout'] == true) ...[
            const Divider(height: 28),
            const Text('Ou verser automatiquement par FlexPay', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
            const SizedBox(height: 8),
            FilledButton(
              style: FilledButton.styleFrom(backgroundColor: VT.navy),
              onPressed: busy
                  ? null
                  : () async {
                      final ok = await showDialog<bool>(
                        context: context,
                        builder: (ctx) => AlertDialog(
                          title: const Text('Verser via FlexPay ?'),
                          content: Text('${money(n(o['net_amount']))} seront envoyés à ${(o['payout'] as Map)['account']}.'),
                          actions: [TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Annuler')), FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Verser'))],
                        ),
                      );
                      if (ok == true) await _run(() => Api.i.post('/admin/orders/${widget.reference}/flexpay-payout'), ok: 'Versement FlexPay lancé : confirmation automatique.');
                    },
              child: Text('Verser ${money(n(o['net_amount']))} via FlexPay'),
            ),
          ],
        ],
        if (o['payout_via'] == 'flexpay' && key == 'payout_done') ...[
          const SizedBox(height: 10),
          Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: VT.okBg, borderRadius: BorderRadius.circular(14)), child: Text('Versement FlexPay lancé (réf. ${o['flexpay_payout_reference']}). Il se confirme automatiquement.', style: const TextStyle(color: VT.okFg, fontWeight: FontWeight.w600))),
          if (o['can_simulate_payout'] == true) ...[const SizedBox(height: 8), OutlinedButton(onPressed: busy ? null : () => _run(() => Api.i.post('/admin/orders/${widget.reference}/simulate-payout'), ok: 'Versement FlexPay simulé.'), child: const Text('Simuler la confirmation FlexPay (mode local)'))],
        ],
        const Divider(height: 28),
        Row(children: [
          Expanded(
            child: OutlinedButton(
              onPressed: busy
                  ? null
                  : () async {
                      final r = await _ask('Bloquer avec une raison', 'ex. Capture illisible, merci de renvoyer');
                      if (r != null) await _run(() => Api.i.post('/admin/orders/${widget.reference}/block', data: {'reason': r}), ok: 'Étape bloquée, le client voit la raison.');
                    },
              child: const Text('Bloquer'),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: OutlinedButton(
              style: OutlinedButton.styleFrom(foregroundColor: VT.badFg),
              onPressed: busy
                  ? null
                  : () async {
                      final r = await _ask('Refuser la commande', 'Raison du refus');
                      if (r != null) await _run(() => Api.i.post('/admin/orders/${widget.reference}/reject', data: {'reason': r}), ok: 'Commande refusée.');
                    },
              child: const Text('Refuser'),
            ),
          ),
        ]),
      ]),
    );
  }
}
