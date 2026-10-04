import 'package:flutter/material.dart';

/// Couleurs Viratech : pétrole, marine et ambre sur fond bleu-gris clair (mêmes que le site web).
class VT {
  static const teal = Color(0xFF08924B); // vert Viratech (couleur principale)
  static const navy = Color(0xFF1C2429); // anthracite du logo
  static const amber = Color(0xFFF4B350); // réservé au statut « en cours »
  static const accent = Color(0xFF14B4E0); // cyan du logo (accent de marque)
  static const bg = Color(0xFFE2E9E5);
  static const card = Colors.white;
  static const ink = Color(0xFF1B2326);
  static const mut = Color(0xFF7C8499);
  static const line = Color(0xFFE8EBF0);
  static const soft = Color(0xFFF5F7FA);
  static const okBg = Color(0xFFDDF4E4);
  static const okFg = Color(0xFF166534);
  static const waitBg = Color(0xFFFDF1D8);
  static const waitFg = Color(0xFF8A5A00);
  static const badBg = Color(0xFFFDE4E4);
  static const badFg = Color(0xFF991B1B);
  static const infoBg = Color(0xFFE4ECFF);
  static const infoFg = Color(0xFF1E40AF);
  static const netBg = Color(0xFFE1F3E8);

  static ThemeData theme() {
    final scheme = ColorScheme.fromSeed(seedColor: teal, primary: teal, secondary: amber, surface: card);
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: bg,
      fontFamily: 'Roboto',
      appBarTheme: const AppBarTheme(backgroundColor: bg, elevation: 0, scrolledUnderElevation: 0, foregroundColor: ink),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: soft,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: line)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: line)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: teal, width: 2)),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: teal,
          foregroundColor: Colors.white,
          shape: const StadiumBorder(),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 15),
          textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: ink,
          side: const BorderSide(color: line),
          shape: const StadiumBorder(),
          padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
        ),
      ),
      snackBarTheme: SnackBarThemeData(behavior: SnackBarBehavior.floating, backgroundColor: navy, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
    );
  }
}
