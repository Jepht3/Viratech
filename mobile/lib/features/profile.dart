import 'package:flutter/material.dart';

import '../core/format.dart';
import '../core/theme.dart';
import '../services/api.dart';
import '../services/session.dart';
import '../ui/widgets.dart';

/// Profil : photo, email vérifié, plafond mensuel et vérification d'identité en deux temps (clients).
/// Les photos d'identité se prennent uniquement avec l'appareil photo : aucun fichier à envoyer.
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
  final _otp = TextEditingController();
  String idType = 'carte_electeur';
  String? front, back, selfie;
  bool busy = false;

  Map<String, dynamic> get user => Map<String, dynamic>.from(widget.data['user'] as Map);
  bool get isClient => user['role'] == 'client';

  @override
  void dispose() {
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
    final stage = '${widget.data['kyc_stage']}';
    final kyc = u['kyc'] as Map?;
    final verified = u['email_verified'] == true;
    final challenge = widget.data['challenge'] as Map?;
    final types = Map<String, dynamic>.from(widget.data['id_types'] as Map);

    return ListView(padding: kPagePadding, children: [
      const PageTitle('Mon profil', subtitle: 'Photo, email et vérification'),
      Panel(
        child: Row(children: [
          GestureDetector(
            onTap: busy ? null : _changePhoto,
            child: Stack(children: [
              Avatar(user: u, size: 84),
              Positioned(right: 0, bottom: 0, child: Container(padding: const EdgeInsets.all(5), decoration: const BoxDecoration(color: VT.accent, shape: BoxShape.circle), child: const Icon(Icons.photo_camera_rounded, size: 16, color: Color(0xFF04222B)))),
            ]),
          ),
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
              const Text('Adresse email', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              Pill(verified ? '✓ Vérifiée' : 'À vérifier', kind: verified ? 'ok' : 'wait'),
            ]),
            const SizedBox(height: 8),
            if (verified)
              Text('${u['email']} est vérifiée.', style: const TextStyle(color: VT.mut))
            else ...[
              Text('Un code à 6 chiffres est envoyé à ${u['email']}. La vérification est obligatoire avant tout échange.', style: const TextStyle(color: VT.mut, fontSize: 13)),
              const SizedBox(height: 10),
              OutlinedButton(
                onPressed: busy
                    ? null
                    : () => _run(() async {
                          final r = await Api.i.post('/email/send');
                          if (r is Map && r['dev_code'] != null && context.mounted) toast(context, 'Mode test : le code est ${r['dev_code']}');
                        }, ok: 'Code envoyé par email.', reload: false),
                child: const Text('Envoyer le code'),
              ),
              const SizedBox(height: 10),
              TextField(controller: _otp, keyboardType: TextInputType.number, maxLength: 6, decoration: const InputDecoration(labelText: 'Code reçu par email', counterText: '')),
              const SizedBox(height: 10),
              FilledButton(onPressed: busy ? null : () => _run(() => Api.i.post('/email/verify', data: {'code': _otp.text.trim()}), ok: 'Email vérifié.'), child: const Text('Vérifier')),
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
              Text(
                limit['custom'] == true
                    ? 'Plafond fixé par Viratech'
                    : (limit['email_verified'] != true ? 'Vérifiez votre email pour pouvoir faire des échanges' : (limit['identity_verified'] != true ? 'Identité non vérifiée : ${money(n(limit['limit']), decimals: 0)} seulement' : 'Identité vérifiée${n(limit['multiplier']) > 1 ? ' · bonus ×${limit['multiplier']} grâce à vos échanges réussis' : ''}')),
                style: const TextStyle(color: VT.mut, fontSize: 12.5),
              ),
              if (limit['email_verified'] == true && limit['identity_verified'] != true) ...[
                const SizedBox(height: 10),
                Container(width: double.infinity, padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: VT.netBg, borderRadius: BorderRadius.circular(14)), child: Text('Faites vérifier votre identité (pièce + photo avec la pièce en main) pour passer à ${money(n(limit['identity_limit']), decimals: 0)} par mois.', style: const TextStyle(color: VT.teal, fontWeight: FontWeight.w600, fontSize: 13))),
              ],
              if (limit['next'] != null) ...[
                const SizedBox(height: 10),
                Container(width: double.infinity, padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: VT.netBg, borderRadius: BorderRadius.circular(14)), child: Text('Encore ${limit['next']['orders_needed']} échange(s) réussi(s) et votre plafond passe à ${money(n(limit['next']['limit']), decimals: 0)}.', style: const TextStyle(color: VT.teal, fontWeight: FontWeight.w600, fontSize: 13))),
              ],
            ]),
          ),
        ],
        const SizedBox(height: 14),
        Panel(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              const Text("Vérification d'identité", style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              if (stage == 'approved') const Pill('✓ Vérifiée', kind: 'ok') else if (stage == 'pending') const Pill('En cours', kind: 'wait') else if (stage == 'document') const Pill('Étape 2 sur 2') else if (stage == 'rejected') const Pill('Refusée', kind: 'bad') else const Pill('Non vérifiée'),
            ]),
            const SizedBox(height: 8),
            if (stage == 'approved')
              const Text('Votre identité est vérifiée : votre plafond mensuel est plus élevé.', style: TextStyle(color: VT.mut))
            else if (stage == 'pending')
              Text('Dossier envoyé le ${dayTime(kyc?['submitted_at'])}. Nous vous prévenons dès qu\'il est vérifié.', style: const TextStyle(color: VT.mut))
            else if (!verified)
              const Text("Vérifiez d'abord votre adresse email.", style: TextStyle(color: VT.mut))
            else if (stage == 'document') ...[
              const Text("Étape 2 sur 2. Votre pièce est reçue. Prenez maintenant une photo de vous qui tenez cette pièce dans la main droite, avec une feuille portant ce code :", style: TextStyle(color: VT.mut, fontSize: 13)),
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: VT.soft, borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFFCFD5E0))),
                child: Column(children: [
                  Text('${challenge?['code'] ?? '—'}', style: const TextStyle(fontSize: 38, fontWeight: FontWeight.w800, letterSpacing: 6, color: VT.teal)),
                  const Text('À écrire à la main sur une feuille', style: TextStyle(color: VT.mut, fontSize: 12)),
                  TextButton(onPressed: busy ? null : () => _run(() => Api.i.post('/kyc/challenge'), ok: 'Nouveau code généré.'), child: const Text('Nouveau code')),
                ]),
              ),
              const SizedBox(height: 12),
              _shot('Selfie avec la pièce en main et le code', selfie, () async {
                final p = await pickPhoto(context, cameraOnly: true, front: true);
                if (p != null) setState(() => selfie = p);
              }),
              const SizedBox(height: 8),
              FilledButton(
                onPressed: (busy || selfie == null) ? null : () => _run(() => Api.i.uploadFiles('/kyc/selfie', {}, {'selfie': selfie!}), ok: 'Dossier envoyé. Vous serez prévenu dès sa vérification.'),
                child: const Text('Envoyer mon dossier'),
              ),
            ] else ...[
              if (stage == 'rejected') Container(margin: const EdgeInsets.only(bottom: 10), padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: VT.badBg, borderRadius: BorderRadius.circular(14)), child: Text('Dossier refusé : ${kyc?['rejection_reason']}', style: const TextStyle(color: VT.badFg, fontWeight: FontWeight.w600))),
              const Text("Étape 1 sur 2. Envoyez votre pièce d'identité : carte d'électeur ou passeport. Les photos se prennent avec l'appareil photo, aucun fichier à envoyer.", style: TextStyle(color: VT.mut, fontSize: 13)),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(initialValue: idType, decoration: const InputDecoration(labelText: 'Type de pièce'), items: [for (final e in types.entries) DropdownMenuItem(value: e.key, child: Text('${e.value}'))], onChanged: (v) => setState(() => idType = v ?? idType)),
              const SizedBox(height: 12),
              _shot(idType == 'passeport' ? 'Page photo du passeport (bien lisible)' : "Carte d'électeur : face avant", front, () async {
                final p = await pickPhoto(context, cameraOnly: true);
                if (p != null) setState(() => front = p);
              }),
              if (idType == 'carte_electeur')
                _shot("Carte d'électeur : face arrière", back, () async {
                  final p = await pickPhoto(context, cameraOnly: true);
                  if (p != null) setState(() => back = p);
                }),
              const SizedBox(height: 8),
              FilledButton(
                onPressed: (busy || front == null || (idType == 'carte_electeur' && back == null))
                    ? null
                    : () => _run(() => Api.i.uploadFiles('/kyc/document', {'id_type': idType}, {'id_front': front!, 'id_back': ?(idType == 'carte_electeur' ? back : null)}), ok: 'Pièce reçue. Dernière étape : le selfie avec la pièce.'),
                child: const Text('Envoyer ma pièce'),
              ),
            ],
            const SizedBox(height: 8),
            const Text("Vos photos sont stockées de façon privée et ne servent qu'à vérifier votre identité. Les anciennes photos ou celles déjà utilisées par un autre compte sont refusées.", style: TextStyle(color: VT.mut, fontSize: 11.5)),
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
          label: Text(path == null ? label : 'Photo prise ✓  ·  $label', style: const TextStyle(fontSize: 12.5)),
        ),
      );
}
