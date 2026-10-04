import 'dart:async';

import 'package:flutter/material.dart';

import '../core/format.dart';
import '../core/theme.dart';
import '../services/api.dart';
import '../services/session.dart';
import '../ui/shell.dart';
import '../ui/widgets.dart';
import 'order_detail.dart';

Future<void> openOrder(BuildContext context, String reference, {bool admin = false}) =>
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => OrderDetailScreen(reference: reference, admin: admin)));

// ───────────────────────── Accueil ─────────────────────────

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final first = '${session.user?['name'] ?? ''}'.split(' ').first;
    return DataView<Map<String, dynamic>>(
      load: () async => Map<String, dynamic>.from(await Api.i.get('/dashboard') as Map),
      builder: (context, d, refresh) {
        final limit = d['monthly_limit'];
        final recent = (d['recent'] as List).cast<Map>();
        final chart = d['chart'] as Map;
        return ListView(padding: kPagePadding, children: [
          PageTitle('Bonjour $first 👋', subtitle: todayFr()),
          GridView.count(
            padding: EdgeInsets.zero,
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 1.32,
            children: [
              StatTile(title: 'À recevoir (en cours)', value: money(n(d['to_receive'])), footer: '${d['active_count']} commande(s) en cours', color: VT.teal, icon: Icons.attach_money),
              StatTile(title: 'Reçu ce mois', value: money(n(d['received_month'])), footer: 'Commandes terminées', color: VT.accent, icon: Icons.check_rounded, dark: false),
              StatTile(
                  title: 'Plafond mensuel',
                  value: limit == null ? 'Sur mesure' : '${money(n(d['used_month']), decimals: 0).replaceAll(' \$', '')} / ${money(n(limit), decimals: 0)}',
                  footer: 'Niveau de vérification ${session.user?['kyc_level']}',
                  color: VT.teal,
                  icon: Icons.speed_rounded),
              StatTile(title: 'Total échangé', value: money(n(d['total_exchanged']), decimals: 0), footer: '${d['completed_count']} commande(s) terminée(s)', color: VT.navy, icon: Icons.trending_up_rounded),
            ],
          ),
          const SizedBox(height: 14),
          FilledButton.icon(
            style: FilledButton.styleFrom(backgroundColor: VT.accent, foregroundColor: const Color(0xFF04222B)),
            onPressed: () => ShellScope.of(context).go('Échanger'),
            icon: const Icon(Icons.swap_horiz_rounded),
            label: const Text('Nouvel échange'),
          ),
          const SizedBox(height: 14),
          Panel(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                const Text('Mes commandes', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                TextButton(onPressed: () => ShellScope.of(context).go('Historique'), child: const Text('Tout voir')),
              ]),
              if (recent.isEmpty) const Padding(padding: EdgeInsets.symmetric(vertical: 24), child: Center(child: Text('Pas encore de commande.', style: TextStyle(color: VT.mut)))),
              for (final o in recent) OrderRow(order: Map<String, dynamic>.from(o), onTap: () => openOrder(context, '${o['reference']}').then((_) => refresh())),
            ]),
          ),
          const SizedBox(height: 14),
          Panel(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Montants reçus par mois', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(height: 10),
              LineChart(labels: chart['labels'] as List, series: chart['series'] as List),
            ]),
          ),
        ]);
      },
    );
  }
}

// ───────────────────────── Historique ─────────────────────────

class OrdersScreen extends StatelessWidget {
  const OrdersScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DataView<List>(
      load: () async => (await Api.i.get('/orders')) as List,
      builder: (context, list, refresh) => ListView(padding: kPagePadding, children: [
        const PageTitle('Historique'),
        if (list.isEmpty) const Padding(padding: EdgeInsets.all(30), child: Center(child: Text('Aucune commande pour le moment.', style: TextStyle(color: VT.mut)))),
        for (final o in list) OrderRow(order: Map<String, dynamic>.from(o as Map), onTap: () => openOrder(context, '${o['reference']}').then((_) => refresh())),
      ]),
    );
  }
}

// ───────────────────────── Nouvel échange ─────────────────────────

const _targetKinds = {
  'equity': ['equity'],
  'mobile_money': ['mpesa', 'airtel', 'orange', 'afrimoney'],
  'paypal': ['paypal'],
};

const _sourceLabels = {'mpesa': 'M-Pesa', 'airtel': 'Airtel Money', 'orange': 'Orange Money', 'afrimoney': 'Afrimoney', 'equity': 'Equity'};

class NewOrderScreen extends StatefulWidget {
  const NewOrderScreen({super.key});
  @override
  State<NewOrderScreen> createState() => _NewOrderScreenState();
}

class _NewOrderScreenState extends State<NewOrderScreen> {
  List<Map<String, dynamic>> corridors = [];
  List<Map<String, dynamic>> methods = [];
  bool loading = true;
  String? loadError;

  String? code;
  final _amount = TextEditingController();
  String source = 'mpesa';
  String depositMode = 'invoice';
  int? methodId;
  Map<String, dynamic>? quote;
  String? quoteError;
  String eta = '';
  bool busy = false;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _amount.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      loadError = null;
    });
    try {
      final r = await Future.wait([Api.i.get('/corridors'), Api.i.get('/payout-methods')]);
      corridors = (r[0] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
      methods = (r[1] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
      final firstActive = corridors.where((c) => c['is_active'] == true && c['coming_soon'] != true);
      code ??= firstActive.isEmpty ? null : '${firstActive.first['code']}';
      _syncChoices();
    } catch (e) {
      loadError = '$e';
    }
    if (mounted) setState(() => loading = false);
  }

  Map<String, dynamic>? get corridor => corridors.where((c) => c['code'] == code).firstOrNull;

  List<Map<String, dynamic>> get eligibleMethods {
    final kinds = _targetKinds['${corridor?['target_kind']}'] ?? [];
    return methods.where((m) => kinds.contains(m['kind'])).toList();
  }

  List<String> get sources => corridor?['source_kind'] == 'equity' ? ['equity'] : ['mpesa', 'airtel', 'orange', 'afrimoney'];

  void _syncChoices() {
    final el = eligibleMethods;
    if (!el.any((m) => m['id'] == methodId)) methodId = el.isEmpty ? null : (el.first['id'] as num).toInt();
    if (!sources.contains(source)) source = sources.first;
  }

  void _onAmount() {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), _fetchQuote);
  }

  Future<void> _fetchQuote() async {
    final amount = double.tryParse(_amount.text.replaceAll(',', '.'));
    if (code == null || amount == null) {
      setState(() {
        quote = null;
        quoteError = null;
      });
      return;
    }
    try {
      final r = await Api.i.post('/quote', data: {'corridor': code, 'amount': amount});
      if (!mounted) return;
      setState(() {
        if (r['ok'] == true) {
          quote = Map<String, dynamic>.from(r['quote'] as Map);
          eta = '${r['eta']}';
          quoteError = null;
        } else {
          quote = null;
          quoteError = '${r['message']}';
        }
      });
    } catch (e) {
      if (mounted) setState(() => quoteError = '$e');
    }
  }

  Future<void> _submit() async {
    setState(() => busy = true);
    try {
      final c = corridor!;
      final r = await Api.i.post('/orders', data: {
        'corridor': code,
        'amount': double.parse(_amount.text.replaceAll(',', '.')),
        'payout_method_id': methodId,
        if (c['is_withdrawal'] == true) 'deposit_mode': depositMode else 'source_kind': source,
      });
      if (!mounted) return;
      _amount.clear();
      quote = null;
      await openOrder(context, '${r['reference']}');
    } catch (e) {
      if (mounted) toast(context, '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Loader();
    if (loadError != null) return ErrorView(loadError!, onRetry: _load);
    final c = corridor;
    final el = eligibleMethods;
    return ListView(padding: kPagePadding, children: [
      const PageTitle('Nouvel échange', subtitle: 'Vous voyez le montant net exact avant de payer.'),
      Panel(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text("Type d'échange", style: TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 10),
          for (final k in corridors) _corridorCard(k),
        ]),
      ),
      const SizedBox(height: 14),
      Panel(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          TextField(
            controller: _amount,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            onChanged: (_) => _onAmount(),
            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
            decoration: InputDecoration(labelText: 'Montant envoyé (USD)', hintText: c == null ? '' : '${n(c['min_amount']).toInt()}.00', suffixText: '\$'),
          ),
          const SizedBox(height: 14),
          if (c != null && c['is_withdrawal'] == true)
            DropdownButtonFormField<String>(
              initialValue: depositMode,
              decoration: const InputDecoration(labelText: 'Comment payer sur PayPal'),
              items: const [
                DropdownMenuItem(value: 'invoice', child: Text('Facture PayPal au montant exact')),
                DropdownMenuItem(value: 'account', child: Text('Envoyer à notre compte PayPal')),
              ],
              onChanged: (v) => setState(() => depositMode = v ?? 'invoice'),
            )
          else if (c != null)
            DropdownButtonFormField<String>(
              key: ValueKey('src-$code'),
              initialValue: source,
              decoration: const InputDecoration(labelText: "Vous envoyez l'argent depuis"),
              items: [for (final s in sources) DropdownMenuItem(value: s, child: Text(_sourceLabels[s] ?? s))],
              onChanged: (v) => setState(() => source = v ?? source),
            ),
          const SizedBox(height: 14),
          if (el.isEmpty)
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: VT.waitBg, borderRadius: BorderRadius.circular(14)),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text("Aucun moyen de réception pour ce type d'échange.", style: TextStyle(color: VT.waitFg, fontWeight: FontWeight.w600)),
                TextButton(onPressed: () => ShellScope.of(context).go('Moyens de réception'), child: const Text('Ajouter un moyen de réception')),
              ]),
            )
          else
            DropdownButtonFormField<int>(
              key: ValueKey('pm-$code'),
              initialValue: methodId,
              isExpanded: true,
              decoration: const InputDecoration(labelText: "Où voulez-vous recevoir l'argent ?"),
              items: [for (final m in el) DropdownMenuItem(value: (m['id'] as num).toInt(), child: Text('${m['kind_label']} · ${m['masked']} · ${m['holder_name']}', overflow: TextOverflow.ellipsis))],
              onChanged: (v) => setState(() => methodId = v),
            ),
        ]),
      ),
      const SizedBox(height: 14),
      Panel(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Ce que vous recevrez', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          const SizedBox(height: 10),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: VT.netBg, borderRadius: BorderRadius.circular(16)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Montant net', style: TextStyle(color: VT.mut, fontSize: 12)),
              Text(quote == null ? '—' : money(n(quote!['net'])), style: const TextStyle(color: VT.teal, fontSize: 30, fontWeight: FontWeight.w800)),
            ]),
          ),
          if (quoteError != null) Padding(padding: const EdgeInsets.only(top: 10), child: Text(quoteError!, style: const TextStyle(color: VT.badFg, fontSize: 13))),
          if (quote != null) ...[
            const SizedBox(height: 8),
            _kv('Montant envoyé', money(n(quote!['amount']))),
            _kv('Frais (${pct(n(quote!['percent']))})', '− ${money(n(quote!['percent_fee']))}'),
            _kv('Frais fixes', '− ${money(n(quote!['fixed_fee']))}'),
            _kv('Délai estimé', eta),
          ],
          const SizedBox(height: 10),
          const Wrap(spacing: 8, runSpacing: 6, children: [Pill('🔒 Frais garantis 20 min', kind: 'ok'), Pill('✓ Aucun frais caché')]),
          const SizedBox(height: 14),
          FilledButton(onPressed: (busy || quote == null || methodId == null) ? null : _submit, child: busy ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white)) : const Text('Continuer')),
        ]),
      ),
    ]);
  }

  Widget _kv(String k, String v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text(k, style: const TextStyle(color: VT.mut, fontSize: 13.5)), Text(v, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5))]),
      );

  Widget _corridorCard(Map<String, dynamic> k) {
    final soon = k['coming_soon'] == true || k['is_active'] != true;
    final on = k['code'] == code;
    return Opacity(
      opacity: soon ? 0.5 : 1,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        decoration: BoxDecoration(color: on ? VT.netBg : Colors.white, border: Border.all(color: on ? VT.teal : VT.line, width: on ? 2 : 1), borderRadius: BorderRadius.circular(18)),
        child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap: soon
              ? null
              : () {
                  setState(() {
                    code = '${k['code']}';
                    quote = null;
                    _syncChoices();
                  });
                  _fetchQuote();
                },
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Row(children: [
              Corridor2('${k['source_kind']}', '${k['target_kind']}', size: 30),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${k['label']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                  Text(soon ? 'Bientôt disponible' : 'Min ${n(k['min_amount']).toInt()} \$ · ${k['eta']}', style: const TextStyle(color: VT.mut, fontSize: 11.5)),
                ]),
              ),
              if (on) const Icon(Icons.check_circle_rounded, color: VT.teal),
            ]),
          ),
        ),
      ),
    );
  }
}

// ───────────────────────── Moyens de réception ─────────────────────────

class MethodsScreen extends StatefulWidget {
  const MethodsScreen({super.key});
  @override
  State<MethodsScreen> createState() => _MethodsScreenState();
}

class _MethodsScreenState extends State<MethodsScreen> {
  int rev = 0;

  Future<void> _add() async {
    final done = await showModalBottomSheet<bool>(context: context, isScrollControlled: true, backgroundColor: VT.card, shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(28))), builder: (_) => const _AddMethodSheet());
    if (done == true) setState(() => rev++);
  }

  @override
  Widget build(BuildContext context) {
    return DataView<List>(
      key: ValueKey(rev),
      load: () async => (await Api.i.get('/payout-methods')) as List,
      builder: (context, list, refresh) => ListView(padding: kPagePadding, children: [
        const PageTitle('Moyens de réception', subtitle: 'Le nom doit être le vôtre.'),
        FilledButton.icon(onPressed: _add, icon: const Icon(Icons.add_rounded), label: const Text('Ajouter un moyen')),
        const SizedBox(height: 14),
        if (list.isEmpty) const Padding(padding: EdgeInsets.all(30), child: Center(child: Text('Aucun moyen enregistré. Ajoutez le premier.', style: TextStyle(color: VT.mut)))),
        for (final m in list)
          Panel(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(14),
            child: Row(children: [
              Chan('${m['kind']}', size: 38),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${m['kind_label']}${m['label'] != null ? ' · ${m['label']}' : ''}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  Text('${m['holder_name']} · ${m['masked']}', style: const TextStyle(color: VT.mut, fontSize: 12)),
                  const SizedBox(height: 4),
                  Pill(m['is_verified'] == true ? 'Vérifié' : 'À vérifier', kind: m['is_verified'] == true ? 'ok' : 'wait'),
                ]),
              ),
              IconButton(
                icon: const Icon(Icons.delete_outline_rounded, color: VT.mut),
                onPressed: () async {
                  final ok = await showDialog<bool>(
                    context: context,
                    builder: (ctx) => AlertDialog(title: const Text('Supprimer ce moyen ?'), actions: [TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Annuler')), FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Supprimer'))]),
                  );
                  if (ok == true) {
                    try {
                      await Api.i.delete('/payout-methods/${m['id']}');
                      await refresh();
                    } catch (e) {
                      if (context.mounted) toast(context, '$e');
                    }
                  }
                },
              ),
            ]),
          ),
      ]),
    );
  }
}

class _AddMethodSheet extends StatefulWidget {
  const _AddMethodSheet();
  @override
  State<_AddMethodSheet> createState() => _AddMethodSheetState();
}

class _AddMethodSheetState extends State<_AddMethodSheet> {
  String kind = 'equity';
  final _value = TextEditingController();
  final _holder = TextEditingController(text: '${session.user?['name'] ?? ''}');
  final _label = TextEditingController();
  bool busy = false;
  String? error;

  static const kinds = {'equity': 'Equity', 'mpesa': 'M-Pesa', 'airtel': 'Airtel Money', 'orange': 'Orange Money', 'afrimoney': 'Afrimoney', 'paypal': 'PayPal'};

  Future<void> _save() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await Api.i.post('/payout-methods', data: {'kind': kind, 'account_value': _value.text.trim(), 'holder_name': _holder.text.trim(), if (_label.text.trim().isNotEmpty) 'label': _label.text.trim()});
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) setState(() => error = '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 22, 20, 22 + MediaQuery.of(context).viewInsets.bottom),
      child: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Text('Ajouter un moyen de réception', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(initialValue: kind, decoration: const InputDecoration(labelText: 'Type'), items: [for (final e in kinds.entries) DropdownMenuItem(value: e.key, child: Text(e.value))], onChanged: (v) => setState(() => kind = v ?? kind)),
          const SizedBox(height: 12),
          TextField(controller: _value, keyboardType: kind == 'paypal' ? TextInputType.emailAddress : TextInputType.text, decoration: InputDecoration(labelText: kind == 'paypal' ? 'Email PayPal' : (kind == 'equity' ? 'Numéro de compte' : 'Numéro de téléphone'))),
          const SizedBox(height: 12),
          TextField(controller: _holder, decoration: const InputDecoration(labelText: 'Nom du titulaire')),
          const SizedBox(height: 12),
          TextField(controller: _label, decoration: const InputDecoration(labelText: 'Nom court (facultatif)')),
          if (error != null) Padding(padding: const EdgeInsets.only(top: 10), child: Text(error!, style: const TextStyle(color: VT.badFg))),
          const SizedBox(height: 16),
          FilledButton(onPressed: busy ? null : _save, child: const Text('Ajouter')),
        ]),
      ),
    );
  }
}
