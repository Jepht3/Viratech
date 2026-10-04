import 'package:flutter/material.dart';

import '../core/format.dart';
import '../core/theme.dart';
import '../services/api.dart';
import '../ui/widgets.dart';
import 'client.dart';

// ───────────────────────── File de validation ─────────────────────────

class QueueScreen extends StatelessWidget {
  const QueueScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DataView<Map<String, dynamic>>(
      load: () async => Map<String, dynamic>.from(await Api.i.get('/admin/queue') as Map),
      builder: (context, d, refresh) {
        final active = (d['active'] as List).cast<Map>();
        final channels = (d['channels'] as List).cast<Map>();
        final chart = d['chart'] as Map;
        return ListView(padding: kPagePadding, children: [
          const PageTitle('File de validation', subtitle: 'Console opérateur'),
          GridView.count(
            padding: EdgeInsets.zero,
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 1.32,
            children: [
              StatTile(title: 'Volume du jour', value: money(n(d['volume_today']), decimals: 0), footer: "Commandes créées aujourd'hui", color: VT.teal, icon: Icons.attach_money),
              StatTile(title: "Frais encaissés aujourd'hui", value: money(n(d['fees_today'])), footer: 'Commandes terminées', color: VT.accent, icon: Icons.percent_rounded, dark: false),
              StatTile(title: 'Délai moyen de traitement', value: d['avg_minutes'] == null ? '—' : '${d['avg_minutes']} min', footer: 'Dernières commandes', color: VT.orange, icon: Icons.timer_outlined, dark: false),
              StatTile(title: 'Commandes en cours', value: '${active.length}', footer: 'À traiter', color: VT.navy, icon: Icons.list_alt_rounded),
            ],
          ),
          const SizedBox(height: 14),
          Panel(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('À traiter', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(height: 10),
              if (active.isEmpty) const Padding(padding: EdgeInsets.all(24), child: Center(child: Text('Aucune commande à traiter. 🎉', style: TextStyle(color: VT.mut)))),
              for (final o in active) OrderRow(order: Map<String, dynamic>.from(o), showClient: true, onTap: () => openOrder(context, '${o['reference']}', admin: true).then((_) => refresh())),
            ]),
          ),
          const SizedBox(height: 14),
          Panel(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Volume par mois', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(height: 10),
              LineChart(labels: chart['labels'] as List, series: chart['series'] as List),
            ]),
          ),
          const SizedBox(height: 14),
          Panel(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Mouvements par canal', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              const Text("D'après les opérations enregistrées : à comparer aux soldes réels.", style: TextStyle(color: VT.mut, fontSize: 11.5)),
              const SizedBox(height: 8),
              for (final c in channels)
                ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  leading: Chan('${c['kind']}', size: 34),
                  title: Text('${c['label']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  trailing: Text('${n(c['amount']) >= 0 ? '+' : ''}${money(n(c['amount']))}', style: TextStyle(fontWeight: FontWeight.w800, color: n(c['amount']) >= 0 ? VT.okFg : VT.badFg)),
                ),
              ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,
                leading: const Chan('sum', size: 34),
                title: const Text('Dû aux clients', style: TextStyle(fontWeight: FontWeight.w700)),
                subtitle: const Text('Fonds reçus, pas encore versés', style: TextStyle(fontSize: 11.5)),
                trailing: Text(money(n(d['owed_to_clients'])), style: const TextStyle(fontWeight: FontWeight.w800)),
              ),
            ]),
          ),
        ]);
      },
    );
  }
}

// ───────────────────────── Commandes ─────────────────────────

class AdminOrdersScreen extends StatefulWidget {
  const AdminOrdersScreen({super.key});
  @override
  State<AdminOrdersScreen> createState() => _AdminOrdersScreenState();
}

class _AdminOrdersScreenState extends State<AdminOrdersScreen> {
  String? status;

  static const filters = {null: 'Toutes', 'active': 'En cours', 'completed': 'Terminées', 'rejected': 'Refusées', 'expired': 'Expirées'};

  @override
  Widget build(BuildContext context) {
    return DataView<List>(
      key: ValueKey(status),
      load: () async => (await Api.i.get('/admin/orders', query: {if (status != null) 'status': status})) as List,
      builder: (context, list, refresh) => ListView(padding: kPagePadding, children: [
        const PageTitle('Commandes'),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(children: [
            for (final e in filters.entries)
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: Text(e.value),
                  selected: status == e.key,
                  selectedColor: VT.teal,
                  labelStyle: TextStyle(color: status == e.key ? Colors.white : VT.ink, fontWeight: FontWeight.w600),
                  onSelected: (_) => setState(() => status = e.key),
                ),
              ),
          ]),
        ),
        const SizedBox(height: 14),
        if (list.isEmpty) const Padding(padding: EdgeInsets.all(30), child: Center(child: Text('Aucune commande.', style: TextStyle(color: VT.mut)))),
        for (final o in list) OrderRow(order: Map<String, dynamic>.from(o as Map), showClient: true, onTap: () => openOrder(context, '${o['reference']}', admin: true).then((_) => refresh())),
      ]),
    );
  }
}

// ───────────────────────── Clients ─────────────────────────

class ClientsScreen extends StatelessWidget {
  const ClientsScreen({super.key});

  static const levels = {0: 'N0 · 500 \$/mois', 1: 'N1 · 500 \$/mois', 2: 'N2 · 3 000 \$/mois', 3: 'N3 · sur mesure'};

  @override
  Widget build(BuildContext context) {
    return DataView<List>(
      load: () async => (await Api.i.get('/admin/clients')) as List,
      builder: (context, list, refresh) => ListView(padding: kPagePadding, children: [
        const PageTitle('Clients et vérification'),
        if (list.isEmpty) const Padding(padding: EdgeInsets.all(30), child: Center(child: Text('Aucun client.', style: TextStyle(color: VT.mut)))),
        for (final c in list)
          Panel(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(14),
            child: Row(children: [
              CircleAvatar(backgroundColor: VT.netBg, child: Text('${c['name']}'.isEmpty ? '?' : '${c['name']}'[0].toUpperCase(), style: const TextStyle(color: VT.teal, fontWeight: FontWeight.w800))),
              const SizedBox(width: 12),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${c['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                  Text('${c['email']} · ${c['phone'] ?? ''}', style: const TextStyle(color: VT.mut, fontSize: 11.5)),
                  Text('${c['orders_count']} commande(s)', style: const TextStyle(color: VT.mut, fontSize: 11.5)),
                ]),
              ),
              PopupMenuButton<int>(
                tooltip: 'Niveau de vérification',
                onSelected: (lvl) async {
                  try {
                    await Api.i.post('/admin/clients/${c['id']}/kyc', data: {'kyc_level': lvl});
                    await refresh();
                  } catch (e) {
                    if (context.mounted) toast(context, '$e');
                  }
                },
                itemBuilder: (_) => [for (final e in levels.entries) PopupMenuItem(value: e.key, child: Text(e.value))],
                child: Pill('N${c['kyc_level']}'),
              ),
            ]),
          ),
      ]),
    );
  }
}

// ───────────────────────── Frais et minimums (administrateur) ─────────────────────────

class FeesScreen extends StatelessWidget {
  const FeesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DataView<List>(
      load: () async => (await Api.i.get('/admin/fees')) as List,
      builder: (context, list, refresh) => ListView(padding: kPagePadding, children: [
        const PageTitle('Frais et minimums', subtitle: "Le palier retenu est celui du montant total. S'applique aux nouvelles commandes."),
        for (final c in list) _FeeCard(corridor: Map<String, dynamic>.from(c as Map)),
      ]),
    );
  }
}

class _FeeCard extends StatefulWidget {
  const _FeeCard({required this.corridor});
  final Map<String, dynamic> corridor;
  @override
  State<_FeeCard> createState() => _FeeCardState();
}

class _FeeCardState extends State<_FeeCard> {
  late final TextEditingController min, fixed, etaMin, etaMax;
  late final List<List<TextEditingController>> tiers;
  late bool active;
  bool busy = false;

  @override
  void initState() {
    super.initState();
    final c = widget.corridor;
    String s(dynamic v) => v == null ? '' : (n(v) == n(v).roundToDouble() ? '${n(v).toInt()}' : '${n(v)}');
    min = TextEditingController(text: s(c['min_amount']));
    fixed = TextEditingController(text: s(c['fixed_fee']));
    etaMin = TextEditingController(text: s(c['eta_min_minutes']));
    etaMax = TextEditingController(text: s(c['eta_max_minutes']));
    active = c['is_active'] == true;
    final t = (c['tiers'] as List).cast<Map>();
    tiers = [for (var i = 0; i < 4; i++) [TextEditingController(text: i < t.length ? s(t[i]['min_amount']) : ''), TextEditingController(text: i < t.length ? s(t[i]['percent']) : '')]];
  }

  @override
  void dispose() {
    for (final c in [min, fixed, etaMin, etaMax, ...tiers.expand((e) => e)]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => busy = true);
    try {
      final ts = [
        for (final t in tiers)
          if (t[0].text.trim().isNotEmpty && t[1].text.trim().isNotEmpty) {'min_amount': double.parse(t[0].text.replaceAll(',', '.')), 'percent': double.parse(t[1].text.replaceAll(',', '.'))},
      ];
      await Api.i.post('/admin/fees/${_id()}', data: {
        'min_amount': double.parse(min.text.replaceAll(',', '.')),
        'fixed_fee': double.parse(fixed.text.replaceAll(',', '.')),
        'eta_min_minutes': int.parse(etaMin.text),
        'eta_max_minutes': int.parse(etaMax.text),
        'is_active': active,
        'tiers': ts,
      });
      if (mounted) toast(context, 'Barème enregistré.');
    } catch (e) {
      if (mounted) toast(context, '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  String _id() => '${widget.corridor['id']}';

  @override
  Widget build(BuildContext context) {
    final c = widget.corridor;
    final soon = c['coming_soon'] == true;
    return Panel(
      margin: const EdgeInsets.only(bottom: 14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Corridor2('${c['source_kind']}', '${c['target_kind']}', size: 30),
          const SizedBox(width: 10),
          Expanded(child: Text('${c['label']}', style: const TextStyle(fontWeight: FontWeight.w800))),
          if (soon) const Pill('Bientôt', kind: 'wait') else Switch(value: active, activeThumbColor: VT.teal, onChanged: (v) => setState(() => active = v)),
        ]),
        const SizedBox(height: 12),
        Row(children: [
          Expanded(child: TextField(controller: min, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Minimum (\$)'))),
          const SizedBox(width: 10),
          Expanded(child: TextField(controller: fixed, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Frais fixes (\$)'))),
        ]),
        const SizedBox(height: 10),
        Row(children: [
          Expanded(child: TextField(controller: etaMin, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Délai min (min)'))),
          const SizedBox(width: 10),
          Expanded(child: TextField(controller: etaMax, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Délai max (min)'))),
        ]),
        const SizedBox(height: 14),
        const Text('Paliers : à partir de ... \$  →  pourcentage', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
        const SizedBox(height: 8),
        for (final t in tiers)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Row(children: [
              Expanded(child: TextField(controller: t[0], keyboardType: TextInputType.number, decoration: const InputDecoration(hintText: 'à partir de (\$)'))),
              const SizedBox(width: 10),
              Expanded(child: TextField(controller: t[1], keyboardType: TextInputType.number, decoration: const InputDecoration(hintText: '%'))),
            ]),
          ),
        const SizedBox(height: 6),
        FilledButton(onPressed: busy ? null : _save, child: const Text('Enregistrer')),
      ]),
    );
  }
}

// ───────────────────────── Comptes de réception (administrateur) ─────────────────────────

class AccountsScreen extends StatelessWidget {
  const AccountsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DataView<List>(
      load: () async => (await Api.i.get('/admin/accounts')) as List,
      builder: (context, list, refresh) => ListView(padding: kPagePadding, children: [
        const PageTitle('Comptes de réception', subtitle: 'Affichés aux clients quand ils doivent payer. Changements journalisés.'),
        for (final a in list) _AccountCard(account: Map<String, dynamic>.from(a as Map)),
      ]),
    );
  }
}

class _AccountCard extends StatefulWidget {
  const _AccountCard({required this.account});
  final Map<String, dynamic> account;
  @override
  State<_AccountCard> createState() => _AccountCardState();
}

class _AccountCardState extends State<_AccountCard> {
  late final label = TextEditingController(text: '${widget.account['label']}');
  late final value = TextEditingController(text: '${widget.account['account_value']}');
  late final holder = TextEditingController(text: '${widget.account['holder_name'] ?? ''}');
  late bool active = widget.account['is_active'] == true;
  bool busy = false;

  @override
  void dispose() {
    label.dispose();
    value.dispose();
    holder.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => busy = true);
    try {
      await Api.i.post('/admin/accounts/${widget.account['id']}', data: {'label': label.text.trim(), 'account_value': value.text.trim(), 'holder_name': holder.text.trim(), 'is_active': active});
      if (mounted) toast(context, 'Compte enregistré.');
    } catch (e) {
      if (mounted) toast(context, '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final kind = '${widget.account['kind']}';
    final provisional = value.text.startsWith('000');
    return Panel(
      margin: const EdgeInsets.only(bottom: 14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [Chan(kind, size: 34), const SizedBox(width: 10), Expanded(child: Text('${widget.account['label']}', style: const TextStyle(fontWeight: FontWeight.w800))), Switch(value: active, activeThumbColor: VT.teal, onChanged: (v) => setState(() => active = v))]),
        const SizedBox(height: 10),
        TextField(controller: label, decoration: const InputDecoration(labelText: 'Libellé')),
        const SizedBox(height: 10),
        TextField(controller: value, onChanged: (_) => setState(() {}), decoration: InputDecoration(labelText: kind == 'paypal' ? 'Adresse PayPal Business' : 'Numéro de compte')),
        const SizedBox(height: 10),
        TextField(controller: holder, decoration: const InputDecoration(labelText: 'Titulaire (facultatif)')),
        if (provisional) Container(margin: const EdgeInsets.only(top: 10), padding: const EdgeInsets.all(10), decoration: BoxDecoration(color: VT.badBg, borderRadius: BorderRadius.circular(12)), child: const Text('Valeur provisoire : à remplacer avant la mise en service.', style: TextStyle(color: VT.badFg, fontSize: 12.5, fontWeight: FontWeight.w600))),
        const SizedBox(height: 12),
        FilledButton(onPressed: busy ? null : _save, child: const Text('Enregistrer')),
      ]),
    );
  }
}
