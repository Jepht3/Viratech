import 'package:flutter/material.dart';

import '../core/format.dart';
import '../core/theme.dart';
import '../services/api.dart';
import '../services/session.dart';
import '../ui/widgets.dart';

/// Profil : photo, téléphone vérifié, plafond mensuel et vérification d'identité (clients).
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});
  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  int rev = 0;

  void _reload() => setState(() => rev++);

  @override
  Widget build(BuildContext context) {
    return DataView<Map<String, dynamic>>(
      key: ValueKey(rev),
      load: () async {
        final r = Map<String, dynamic>.from(await Api.i.get('/profile') as Map);
        await session.refreshUser();
        return r;
      },
      builder: (context, d, refresh) => _Body(data: d, onChanged: _reload),
    );
  }
}

class _Body extends StatefulWidget {
  const _Body({required this.data, required this.onChanged});
  final Map<String, dynamic> data;
  final VoidCallback onChanged;
  @override
  State<_Body> createState() => _BodyState();
}

class _BodyState extends State<_Body> {
  final _phone = TextEditingController();
  final _otp = TextEditingController();
  String idType = 'carte_electeur';
  String? selfie, front, back;
  bool busy = false;

  Map<String, dynamic> get user => Map<String, dynamic>.from(widget.data['user'] as Map);
  bool get isClient => user['role'] == 'client';

  @override
  void initState() {
    super.initState();
    _phone.text = '${user['phone'] ?? ''}';
  }

  @override
  void dispose() {
    _phone.dispose();
    _otp.dispose();
    super.dispose();
  }

  Future<void> _run(Future<dynamic> Function() f, {String? ok, bool reload = true}) async {
    setState(() => busy = true);
    try {
      await f();
      if (ok != null && mounted) toast(context, ok);
      if (reload) widget.onChanged();
    } catch (e) {
      if (mounted) toast(context, '$e');
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> _changePhoto() async {
    final path = await pickPhoto(context, front: true);
    if (path == null) return;
    await _run(() => Api.i.uploadFiles('/profile/avatar', {}, {'photo': path}), ok: 'Photo de profil enregistrée.');
  }

  @override
  Widget build(BuildContext context) {
    final u = user;
    final limit = u['limit'] as Map?;
    final kyc = u['kyc'] as Map?;
    final level = (u['kyc_level'] as num?)?.toInt() ?? 0;
    final verified = u['phone_verified'] == true;
    final challenge = widget.data['challenge'] as Map?;
    final types = Map<String, dynamic>.from(widget.data['id_types'] as Map);

    return ListView(padding: kPagePadding, children: [
      const PageTitle('Mon profil', subtitle: 'Photo, téléphone et vérification'),
      Panel(
        child: Row(children: [
          GestureDetector(onTap: busy ? null : _changePhoto, child: Stack(children: [
            Avatar(user: u, size: 84),
            Positioned(right: 0, bottom: 0, child: Container(padding: const EdgeInsets.all(5), decoration: const BoxDecoration(color: VT.accent, shape: BoxShape.circle), child: const Icon(Icons.photo_camera_rounded, size: 16, color: Color(0xFF04222B)))),
          ])),
          const SizedBox(width: 16),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${u['name']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
              Text('${u['email']}', style: const TextStyle(color: VT.mut, fontSize: 12.5)),
              const SizedBox(height: 6),
              TextButton.icon(onPressed: busy ? null : _changePhoto, style: TextButton.styleFrom(padding: EdgeInsets.zero, minimumSize: const Size(0, 30)), icon: const Icon(Icons.edit_rounded, size: 16), label: const Text('Changer la photo')),
            ]),
          ),
        ]),
      ),
      if (isClient) ...[
        const SizedBox(height: 14),
        Panel(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              const Text('Téléphone', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              Pill(verified ? '✓ Vérifié' : 'À vérifier', kind: verified ? 'ok' : 'wait'),
            ]),
            const SizedBox(height: 8),
            if (verified)
              Text('${u['phone']} est vérifié.', style: const TextStyle(color: VT.mut))
            else ...[
              const Text('Un code est envoyé par SMS. La vérification est obligatoire avant tout échange.', style: TextStyle(color: VT.mut, fontSize: 13)),
              const SizedBox(height: 10),
              TextField(controller: _phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Numéro de téléphone')),
              const SizedBox(height: 10),
              OutlinedButton(
                onPressed: busy
                    ? null
                    : () => _run(() async {
                          final r = await Api.i.post('/phone/send', data: {'phone': _phone.text.trim()});
                          if (r is Map && r['dev_code'] != null && context.mounted) toast(context, 'Mode test : le code est ${r['dev_code']}');
                        }, ok: 'Code envoyé par SMS.', reload: false),
                child: const Text('Envoyer le code'),
              ),
              const SizedBox(height: 10),
              TextField(controller: _otp, keyboardType: TextInputType.number, maxLength: 6, decoration: const InputDecoration(labelText: 'Code reçu par SMS', counterText: '')),
              const SizedBox(height: 10),
              FilledButton(onPressed: busy ? null : () => _run(() => Api.i.post('/phone/verify', data: {'code': _otp.text.trim()}), ok: 'Téléphone vérifié.'), child: const Text('Vérifier')),
            ],
          ]),
        ),
        if (limit != null) ...[
          const SizedBox(height: 14),
          Panel(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Mon plafond mensuel', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(height: 6),
              Text(money(n(limit['limit']), decimals: 0), style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w800)),
              Text(limit['custom'] == true ? 'Plafond fixé par Viratech' : 'Niveau ${limit['level']}${n(limit['multiplier']) > 1 ? ' · bonus ×${limit['multiplier']} grâce à vos échanges réussis' : ''}', style: const TextStyle(color: VT.mut, fontSize: 12.5)),
              if (limit['next'] != null) ...[
                const SizedBox(height: 10),
                Container(width: double.infinity, padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: VT.netBg, borderRadius: BorderRadius.circular(14)), child: Text('Encore ${limit['next']['orders_needed']} échange(s) réussi(s) et votre plafond passe à ${money(n(limit['next']['limit']), decimals: 0)}.', style: const TextStyle(color: VT.teal, fontWeight: FontWeight.w600, fontSize: 13))),
              ],
              const SizedBox(height: 8),
              const Text("Téléphone vérifié : 500 \$/mois · Identité vérifiée : 3 000 \$/mois. La limite augmente aussi avec le nombre d'échanges terminés.", style: TextStyle(color: VT.mut, fontSize: 11.5)),
            ]),
          ),
        ],
        const SizedBox(height: 14),
        Panel(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              const Text("Vérification d'identité", style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              if (level >= 2) const Pill('✓ Vérifiée', kind: 'ok') else if (kyc?['status'] == 'pending') const Pill('En cours', kind: 'wait') else if (kyc?['status'] == 'rejected') const Pill('Refusée', kind: 'bad') else const Pill('Non vérifiée'),
            ]),
            const SizedBox(height: 8),
            if (level >= 2)
              const Text('Votre identité est vérifiée : votre plafond mensuel est plus élevé.', style: TextStyle(color: VT.mut))
            else if (kyc?['status'] == 'pending')
              Text('Dossier envoyé le ${dayTime(kyc?['submitted_at'])}. Nous vous prévenons dès qu\'il est vérifié.', style: const TextStyle(color: VT.mut))
            else if (!verified)
              const Text("Vérifiez d'abord votre numéro de téléphone.", style: TextStyle(color: VT.mut))
            else ...[
              if (kyc?['status'] == 'rejected') Container(margin: const EdgeInsets.only(bottom: 10), padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: VT.badBg, borderRadius: BorderRadius.circular(14)), child: Text('Dossier refusé : ${kyc?['rejection_reason']}', style: const TextStyle(color: VT.badFg, fontWeight: FontWeight.w600))),
              const Text("Pour protéger votre compte, prenez 2 photos maintenant avec l'appareil photo de votre téléphone.", style: TextStyle(color: VT.mut, fontSize: 13)),
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: VT.soft, borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFFCFD5E0))),
                child: Column(children: [
                  const Text('Écrivez ce code sur une feuille et tenez-la sur la photo', style: TextStyle(color: VT.mut, fontSize: 12), textAlign: TextAlign.center),
                  Text('${challenge?['code'] ?? '—'}', style: const TextStyle(fontSize: 38, fontWeight: FontWeight.w800, letterSpacing: 6, color: VT.teal)),
                  TextButton(onPressed: busy ? null : () => _run(() => Api.i.post('/kyc/challenge'), ok: 'Nouveau code généré.'), child: const Text('Nouveau code')),
                ]),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(initialValue: idType, decoration: const InputDecoration(labelText: 'Type de pièce'), items: [for (final e in types.entries) DropdownMenuItem(value: e.key, child: Text('${e.value}'))], onChanged: (v) => setState(() => idType = v ?? idType)),
              const SizedBox(height: 12),
              _shot('1. Selfie : visage, pièce dans la main droite, feuille avec le code', selfie, () async {
                final p = await pickPhoto(context, cameraOnly: true, front: true);
                if (p != null) setState(() => selfie = p);
              }),
              _shot("2. Photo de la pièce d'identité (face avant, lisible)", front, () async {
                final p = await pickPhoto(context, cameraOnly: true);
                if (p != null) setState(() => front = p);
              }),
              _shot('3. Face arrière de la pièce (si elle existe)', back, () async {
                final p = await pickPhoto(context, cameraOnly: true);
                if (p != null) setState(() => back = p);
              }),
              const SizedBox(height: 8),
              FilledButton(
                onPressed: (busy || selfie == null || front == null)
                    ? null
                    : () => _run(() => Api.i.uploadFiles('/kyc', {'id_type': idType}, {'selfie': selfie!, 'id_front': front!, 'id_back': ?back}), ok: 'Dossier envoyé. Vous serez prévenu dès sa vérification.'),
                child: const Text('Envoyer mon dossier'),
              ),
              const SizedBox(height: 8),
              const Text("Vos photos sont stockées de façon privée et ne servent qu'à vérifier votre identité. Les anciennes photos ou celles déjà utilisées par un autre compte sont refusées.", style: TextStyle(color: VT.mut, fontSize: 11.5)),
            ],
          ]),
        ),
      ],
    ]);
  }

  Widget _shot(String label, String? path, VoidCallback onTap) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: OutlinedButton.icon(
          style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(50), alignment: Alignment.centerLeft),
          onPressed: onTap,
          icon: Icon(path == null ? Icons.photo_camera_rounded : Icons.check_circle_rounded, color: path == null ? VT.mut : VT.teal),
          label: Text(path == null ? label : 'Photo prise ✓  ·  ${label.split(':').first}', style: const TextStyle(fontSize: 12.5)),
        ),
      );
}
