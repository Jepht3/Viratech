import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/config.dart';
import '../core/theme.dart';
import '../services/api.dart';

/// Résultat d'un contrôle de mise à jour (même système que LeWebPOS : GET /api/application/version).
class UpdateInfo {
  const UpdateInfo({required this.build, this.version, this.url, this.notes = const []});
  final int build;
  final String? version;
  final String? url;
  final List<String> notes;

  bool get available => build > AppConfig.buildNumber && url != null;
}

Future<UpdateInfo> checkForUpdate() async {
  final d = Map<String, dynamic>.from(await Api.i.get('/application/version') as Map);
  return UpdateInfo(
    build: (d['build'] as num?)?.toInt() ?? 0,
    version: d['version'] as String?,
    url: (AppConfig.isAdmin ? d['url_admin'] : d['url']) as String?,
    notes: [for (final n in (d['notes'] as List? ?? const [])) '$n'],
  );
}

/// Au démarrage : propose la mise à jour une seule fois par version (« Plus tard » ne la remontre pas avant la suivante).
Future<void> checkForUpdateOnStart(BuildContext context) async {
  if (AppConfig.buildNumber <= 0) return; // développement
  try {
    final info = await checkForUpdate();
    if (!info.available || !context.mounted) return;
    const store = FlutterSecureStorage();
    final key = 'maj_proposee_${AppConfig.isAdmin ? 'admin' : 'client'}';
    if (await store.read(key: key) == '${info.build}') return;
    await store.write(key: key, value: '${info.build}');
    if (context.mounted) await showUpdateDialog(context, info);
  } catch (_) {
    // Hors ligne ou serveur injoignable : on ne dérange pas l'utilisateur.
  }
}

Future<void> showUpdateDialog(BuildContext context, UpdateInfo info) => showGeneralDialog<void>(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'Fermer',
      barrierColor: Colors.black.withValues(alpha: 0.55),
      transitionDuration: const Duration(milliseconds: 260),
      pageBuilder: (ctx, _, _) => _UpdateCard(info: info),
      transitionBuilder: (ctx, a, _, child) => FadeTransition(opacity: a, child: ScaleTransition(scale: Tween(begin: 0.92, end: 1.0).animate(CurvedAnimation(parent: a, curve: Curves.easeOutBack)), child: child)),
    );

class _UpdateCard extends StatelessWidget {
  const _UpdateCard({required this.info});
  final UpdateInfo info;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Material(
        color: Colors.transparent,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 400),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: SingleChildScrollView(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Container(
                  clipBehavior: Clip.antiAlias,
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(28)),
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    Container(
                      height: 140,
                      width: double.infinity,
                      decoration: const BoxDecoration(gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [VT.teal, VT.navy])),
                      child: Center(child: Container(width: 80, height: 80, decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle), child: const Icon(Icons.rocket_launch_rounded, color: VT.teal, size: 42))),
                    ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(24, 20, 24, 22),
                      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        const Text('Une nouvelle version est disponible !', textAlign: TextAlign.center, style: TextStyle(color: VT.teal, fontWeight: FontWeight.w900, fontSize: 20, height: 1.2)),
                        if (info.version != null) ...[
                          const SizedBox(height: 8),
                          Center(child: Container(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4), decoration: BoxDecoration(color: VT.netBg, borderRadius: BorderRadius.circular(99)), child: Text('Version ${info.version}', style: const TextStyle(color: VT.teal, fontWeight: FontWeight.w800, fontSize: 12.5)))),
                        ],
                        const SizedBox(height: 16),
                        if (info.notes.isEmpty)
                          const Text('Améliorations et corrections.', textAlign: TextAlign.center, style: TextStyle(color: VT.mut))
                        else
                          for (final n in info.notes.take(5))
                            Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                const Padding(padding: EdgeInsets.only(top: 2), child: Icon(Icons.check_circle_rounded, color: Color(0xFF16A34A), size: 18)),
                                const SizedBox(width: 10),
                                Expanded(child: Text(n, style: const TextStyle(fontSize: 14.5, height: 1.3))),
                              ]),
                            ),
                        const SizedBox(height: 14),
                        FilledButton(
                          style: FilledButton.styleFrom(backgroundColor: VT.navy, minimumSize: const Size.fromHeight(52)),
                          onPressed: () {
                            launchUrl(Uri.parse(info.url!), mode: LaunchMode.externalApplication);
                            Navigator.of(context).pop();
                          },
                          child: const Text('Mettre à jour maintenant'),
                        ),
                        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Plus tard', style: TextStyle(color: VT.mut))),
                      ]),
                    ),
                  ]),
                ),
                const SizedBox(height: 16),
                Material(color: Colors.white, shape: const CircleBorder(), elevation: 3, child: InkWell(customBorder: const CircleBorder(), onTap: () => Navigator.of(context).pop(), child: const Padding(padding: EdgeInsets.all(14), child: Icon(Icons.close_rounded, color: Colors.black87)))),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}
