/// Formats d'affichage (français) sans dépendance : montants en USD et dates.
String money(num? v, {int decimals = 2}) {
  final n = (v ?? 0).toDouble();
  final neg = n < 0;
  final parts = n.abs().toStringAsFixed(decimals).split('.');
  final digits = parts[0];
  final buf = StringBuffer();
  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) buf.write(' ');
    buf.write(digits[i]);
  }
  final dec = decimals > 0 ? ',${parts[1]}' : '';
  return '${neg ? '−' : ''}$buf$dec \$';
}

num n(dynamic v) => v is num ? v : (num.tryParse('${v ?? 0}') ?? 0);

String two(int v) => v.toString().padLeft(2, '0');

DateTime? parseDate(dynamic v) => v == null ? null : DateTime.tryParse('$v')?.toLocal();

String dayTime(dynamic v) {
  final d = parseDate(v);
  return d == null ? '' : '${two(d.day)}/${two(d.month)} ${two(d.hour)}:${two(d.minute)}';
}

String dateOnly(dynamic v) {
  final d = parseDate(v);
  return d == null ? '' : '${two(d.day)}/${two(d.month)}/${d.year}';
}

/// « 6 min », « 2 h », « 3 j » depuis une date.
String since(dynamic v) {
  final d = parseDate(v);
  if (d == null) return '';
  final m = DateTime.now().difference(d).inMinutes;
  if (m < 1) return "à l'instant";
  if (m < 60) return '$m min';
  if (m < 1440) return '${m ~/ 60} h';
  return '${m ~/ 1440} j';
}

const monthsFr = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const daysFr = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

String todayFr() {
  final d = DateTime.now();
  return '${daysFr[d.weekday - 1]} ${d.day} ${monthsFr[d.month - 1]}';
}

String pct(num v) => v == v.roundToDouble() ? '${v.toInt()} %' : '${v.toString().replaceAll('.', ',')} %';
