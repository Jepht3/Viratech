import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../core/format.dart';
import '../core/theme.dart';

/// Carte blanche arrondie (style des dashboards Viratech).
class Panel extends StatelessWidget {
  const Panel({super.key, required this.child, this.padding = const EdgeInsets.all(18), this.onTap, this.margin});
  final Widget child;
  final EdgeInsets padding;
  final EdgeInsets? margin;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final box = Container(
      margin: margin,
      decoration: BoxDecoration(
        color: VT.card,
        borderRadius: BorderRadius.circular(26),
        boxShadow: [BoxShadow(color: VT.navy.withValues(alpha: 0.08), blurRadius: 20, offset: const Offset(0, 8))],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(26),
        child: InkWell(borderRadius: BorderRadius.circular(26), onTap: onTap, child: Padding(padding: padding, child: child)),
      ),
    );
    return box;
  }
}

class Pill extends StatelessWidget {
  const Pill(this.text, {super.key, this.kind = 'info'});
  final String text;
  final String kind; // ok | wait | bad | info

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = switch (kind) {
      'ok' => (VT.okBg, VT.okFg),
      'wait' => (VT.waitBg, VT.waitFg),
      'bad' => (VT.badBg, VT.badFg),
      _ => (VT.infoBg, VT.infoFg),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(99)),
      child: Text(text, style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w700)),
    );
  }
}

String statusKind(String status) => status == 'completed' ? 'ok' : (status == 'active' ? 'wait' : 'bad');

/// Carte de statistique colorée (pétrole, ambre ou marine).
class StatTile extends StatelessWidget {
  const StatTile({super.key, required this.title, required this.value, required this.footer, required this.color, this.icon = Icons.attach_money, this.dark = true});
  final String title;
  final String value;
  final String footer;
  final Color color;
  final IconData icon;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    final fg = dark ? Colors.white : const Color(0xFF2B2208);
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(20), boxShadow: [BoxShadow(color: color.withValues(alpha: 0.35), blurRadius: 16, offset: const Offset(0, 8))]),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(child: Text(title, style: TextStyle(color: fg.withValues(alpha: 0.85), fontSize: 11.5))),
          Container(width: 30, height: 30, decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle), child: Icon(icon, size: 16, color: VT.navy)),
        ]),
        FittedBox(fit: BoxFit.scaleDown, alignment: Alignment.centerLeft, child: Text(value, style: TextStyle(color: fg, fontSize: 21, fontWeight: FontWeight.w700, letterSpacing: -0.3))),
        Text(footer, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: fg.withValues(alpha: 0.8), fontSize: 10.5)),
      ]),
    );
  }
}

/// Logo d'un canal (PayPal, Equity, M-Pesa, Airtel) : les vrais logos des sociétés.
class Chan extends StatelessWidget {
  const Chan(this.kind, {super.key, this.size = 36});
  final String kind;
  final double size;

  static const _assets = {'paypal': 'paypal', 'equity': 'equity', 'mpesa': 'mpesa', 'airtel': 'airtel'};

  Widget _box(Widget child) => Container(
        width: size,
        height: size,
        padding: EdgeInsets.all(size * 0.14),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(size * 0.32), border: Border.all(color: const Color(0xFFE6E4DC))),
        child: child,
      );

  @override
  Widget build(BuildContext context) {
    if (kind == 'mobile_money') {
      return Row(mainAxisSize: MainAxisSize.min, children: [Chan('mpesa', size: size), const SizedBox(width: 3), Chan('airtel', size: size)]);
    }
    final a = _assets[kind];
    if (a != null) return _box(Image.asset('assets/logos/$a.png', fit: BoxFit.contain));
    final label = kind == 'crypto' ? '₮' : (kind.length >= 2 ? kind.substring(0, 2).toUpperCase() : kind);
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(color: VT.navy, borderRadius: BorderRadius.circular(size * 0.32)),
      child: Text(label, style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: size * 0.34)),
    );
  }
}

class Corridor2 extends StatelessWidget {
  const Corridor2(this.source, this.target, {super.key, this.size = 34});
  final String source;
  final String target;
  final double size;

  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [
        Chan(source, size: size),
        const Padding(padding: EdgeInsets.symmetric(horizontal: 4), child: Icon(Icons.arrow_forward_rounded, size: 15, color: VT.mut)),
        Chan(target, size: size),
      ]);
}

/// Anneau : part des étapes réellement faites (donnée du serveur, jamais simulée).
class Ring extends StatelessWidget {
  const Ring({super.key, required this.percent, this.color = VT.teal, this.size = 46});
  final num percent;
  final Color color;
  final double size;

  @override
  Widget build(BuildContext context) => SizedBox(
        width: size,
        height: size,
        child: CustomPaint(painter: _RingPainter(percent.toDouble() / 100, color), child: Center(child: Text('${percent.round()}%', style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700)))),
      );
}

class _RingPainter extends CustomPainter {
  _RingPainter(this.value, this.color);
  final double value;
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;
    final track = Paint()..color = const Color(0xFFE4E8EF)..style = PaintingStyle.stroke..strokeWidth = 5;
    final arc = Paint()..color = color..style = PaintingStyle.stroke..strokeWidth = 5..strokeCap = StrokeCap.round;
    canvas.drawArc(rect.deflate(3), 0, math.pi * 2, false, track);
    canvas.drawArc(rect.deflate(3), -math.pi / 2, math.pi * 2 * value.clamp(0, 1), false, arc);
  }

  @override
  bool shouldRepaint(_RingPainter old) => old.value != value || old.color != color;
}

Color ringColor(String status) => status == 'completed' ? const Color(0xFF16A34A) : (status == 'active' ? VT.teal : const Color(0xFFDC2626));

/// Ligne de commande : anneau, libellé, étape en cours, montant net.
class OrderRow extends StatelessWidget {
  const OrderRow({super.key, required this.order, required this.onTap, this.showClient = false});
  final Map<String, dynamic> order;
  final VoidCallback onTap;
  final bool showClient;

  @override
  Widget build(BuildContext context) {
    final o = order;
    final step = o['current_step'] as Map?;
    final active = o['status'] == 'active';
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(border: Border.all(color: VT.line), borderRadius: BorderRadius.circular(18), color: Colors.white),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(children: [
            Ring(percent: n(o['progress']), color: ringColor('${o['status']}')),
            const SizedBox(width: 12),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('${o['corridor']['label']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                Text(showClient && o['client'] != null ? '${o['reference']} · ${o['client']['name']}' : '${o['reference']} · ${dayTime(o['created_at'])}',
                    style: const TextStyle(color: VT.mut, fontSize: 11.5)),
                if (active && step != null)
                  Text('${step['label']}', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: step['blocked'] == true ? VT.badFg : VT.waitFg, fontSize: 11.5)),
              ]),
            ),
            const SizedBox(width: 8),
            Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
              Text(money(n(showClient ? o['amount'] : o['net_amount'])), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
              const SizedBox(height: 3),
              Pill('${o['status_label']}', kind: statusKind('${o['status']}')),
            ]),
          ]),
        ),
      ),
    );
  }
}

/// Timeline des étapes réelles : heure et auteur de chaque étape faite, raison d'un blocage.
class StepsTimeline extends StatelessWidget {
  const StepsTimeline({super.key, required this.steps});
  final List steps;

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      for (var i = 0; i < steps.length; i++) _step(steps[i] as Map, i == steps.length - 1),
    ]);
  }

  Widget _step(Map s, bool last) {
    final st = '${s['status']}';
    final color = st == 'done' ? VT.teal : (st == 'current' ? VT.amber : (st == 'blocked' ? const Color(0xFFDC2626) : VT.line));
    String title = '${s['label']}';
    String? sub;
    if (st == 'done') {
      sub = '${dayTime(s['done_at'])} · ${s['done_by'] ?? ''}${(s['note'] != null && s['key'] != 'created') ? ' · ${s['note']}' : ''}';
    } else if (st == 'current' || st == 'blocked') {
      title = '${s['pending_label']}';
      if (st == 'blocked') {
        sub = 'Bloquée : ${s['note']}';
      } else {
        final since0 = s['started_at'] != null ? 'Depuis ${since(s['started_at'])} · ' : '';
        final who = {'client': 'Action attendue de votre part', 'operator': 'Traité par un opérateur', 'system': 'Confirmation automatique ou par un opérateur'}['${s['actor']}'] ?? '';
        sub = '$since0$who';
      }
    }
    return IntrinsicHeight(
      child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        SizedBox(
          width: 26,
          child: Column(children: [
            Container(
              margin: const EdgeInsets.only(top: 3),
              width: 14,
              height: 14,
              decoration: BoxDecoration(color: color, shape: BoxShape.circle, boxShadow: st == 'current' ? [BoxShadow(color: VT.amber.withValues(alpha: 0.35), spreadRadius: 4)] : null),
            ),
            if (!last) Expanded(child: Container(width: 2, color: st == 'done' ? VT.teal : VT.line)),
          ]),
        ),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(bottom: 18),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, style: TextStyle(fontWeight: st == 'todo' ? FontWeight.w500 : FontWeight.w700, color: st == 'todo' ? VT.mut : VT.ink, fontSize: 14)),
              if (sub != null) Text(sub, style: TextStyle(color: st == 'blocked' ? VT.badFg : VT.mut, fontSize: 11.5)),
            ]),
          ),
        ),
      ]),
    );
  }
}

/// Courbe lissée à partir de données réelles.
class LineChart extends StatelessWidget {
  const LineChart({super.key, required this.labels, required this.series, this.height = 170});
  final List labels;
  final List series;
  final double height;

  @override
  Widget build(BuildContext context) => SizedBox(
        height: height,
        width: double.infinity,
        child: CustomPaint(painter: _LinePainter(labels.map((e) => '$e').toList(), series.map((e) => n(e).toDouble()).toList())),
      );
}

class _LinePainter extends CustomPainter {
  _LinePainter(this.labels, this.series);
  final List<String> labels;
  final List<double> series;

  @override
  void paint(Canvas canvas, Size size) {
    const padB = 22.0, padT = 26.0;
    final h = size.height - padB - padT;
    final grid = Paint()..color = const Color(0xFFEEF1F5)..strokeWidth = 1;
    for (var g = 0; g < 4; g++) {
      final y = padT + h * g / 3;
      canvas.drawLine(Offset(0, y), Offset(size.width, y), grid);
    }
    if (series.length < 2) return;
    final maxV = math.max(series.reduce(math.max), 1.0);
    final pts = [for (var i = 0; i < series.length; i++) Offset(size.width * i / (series.length - 1), padT + h * (1 - series[i] / maxV))];
    final path = Path()..moveTo(pts.first.dx, pts.first.dy);
    for (var i = 1; i < pts.length; i++) {
      final mx = (pts[i - 1].dx + pts[i].dx) / 2;
      path.cubicTo(mx, pts[i - 1].dy, mx, pts[i].dy, pts[i].dx, pts[i].dy);
    }
    canvas.drawPath(path, Paint()..color = VT.navy..style = PaintingStyle.stroke..strokeWidth = 2.6..strokeCap = StrokeCap.round);
    final peak = series.indexOf(series.reduce(math.max));
    if (series[peak] > 0) {
      final p = pts[peak];
      canvas.drawCircle(p, 5, Paint()..color = Colors.white);
      canvas.drawCircle(p, 5, Paint()..color = VT.navy..style = PaintingStyle.stroke..strokeWidth = 2.4);
      final tp = TextPainter(text: TextSpan(text: money(series[peak], decimals: 0), style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)), textDirection: TextDirection.ltr)..layout();
      final w = tp.width + 16;
      final x = (p.dx - w / 2).clamp(0.0, size.width - w);
      final r = RRect.fromRectAndRadius(Rect.fromLTWH(x, math.max(p.dy - 36, 0), w, 24), const Radius.circular(8));
      canvas.drawRRect(r, Paint()..color = VT.navy);
      tp.paint(canvas, Offset(x + 8, r.top + 4));
    }
    for (var i = 0; i < labels.length && i < pts.length; i++) {
      if (labels.length > 8 && i.isOdd) continue;
      final t = TextPainter(text: TextSpan(text: labels[i], style: const TextStyle(color: VT.mut, fontSize: 10)), textDirection: TextDirection.ltr)..layout();
      t.paint(canvas, Offset((pts[i].dx - t.width / 2).clamp(0.0, size.width - t.width), size.height - 14));
    }
  }

  @override
  bool shouldRepaint(_LinePainter old) => old.series != series;
}

class Loader extends StatelessWidget {
  const Loader({super.key});
  @override
  Widget build(BuildContext context) => const Center(child: Padding(padding: EdgeInsets.all(40), child: CircularProgressIndicator(color: VT.teal)));
}

class ErrorView extends StatelessWidget {
  const ErrorView(this.message, {super.key, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off_rounded, size: 44, color: VT.mut),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center, style: const TextStyle(color: VT.mut)),
            const SizedBox(height: 16),
            FilledButton(onPressed: onRetry, child: const Text('Réessayer')),
          ]),
        ),
      );
}

/// Charge des données du serveur, affiche chargement / erreur / contenu, avec tirer-pour-actualiser.
class DataView<T> extends StatefulWidget {
  const DataView({super.key, required this.load, required this.builder, this.pollSeconds});
  final Future<T> Function() load;
  final Widget Function(BuildContext, T, Future<void> Function() refresh) builder;
  final int? pollSeconds;

  @override
  State<DataView<T>> createState() => _DataViewState<T>();
}

class _DataViewState<T> extends State<DataView<T>> {
  T? _data;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _fetch();
  }

  Future<void> _fetch() async {
    try {
      final d = await widget.load();
      if (!mounted) return;
      setState(() {
        _data = d;
        _error = null;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = '$e';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Loader();
    if (_data == null) {
      return ErrorView(_error ?? 'Erreur', onRetry: () {
        setState(() => _loading = true);
        _fetch();
      });
    }
    return RefreshIndicator(color: VT.teal, onRefresh: _fetch, child: widget.builder(context, _data as T, _fetch));
  }
}

void toast(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message)));
}

/// Titre de page (grand, comme sur le site).
class PageTitle extends StatelessWidget {
  const PageTitle(this.title, {super.key, this.subtitle});
  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(2, 4, 2, 14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (subtitle != null) Text(subtitle!, style: const TextStyle(color: VT.mut, fontSize: 12.5)),
          Text(title, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: -0.5, color: VT.ink)),
        ]),
      );
}

const kPagePadding = EdgeInsets.fromLTRB(16, 4, 16, 110);
