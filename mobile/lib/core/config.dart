/// Paramètres de l'application, fixés à la compilation (--dart-define).
class AppConfig {
  /// Adresse du serveur. Émulateur Android : http://10.0.2.2:8000 (le PC). Téléphone réel : l'adresse IP du PC sur le Wi-Fi.
  static const String apiUrl = String.fromEnvironment('API_URL', defaultValue: 'http://10.0.2.2:8000');
  static const String version = String.fromEnvironment('APP_VERSION', defaultValue: '1.0.0');

  /// Numéro de build publié par GitHub Actions (0 = développement : pas de contrôle de mise à jour).
  static const int buildNumber = int.fromEnvironment('BUILD_NUMBER', defaultValue: 0);

  static String get apiBase => '$apiUrl/api';

  /// Vrai dans l'application Viratech Admin (main_admin.dart), faux dans l'application cliente.
  static bool isAdmin = false;
}
