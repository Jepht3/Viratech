import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../core/config.dart';
import 'api.dart';

/// Session de l'utilisateur : jeton conservé dans le stockage sécurisé du téléphone.
class Session extends ChangeNotifier {
  static const _key = 'viratech_token';
  final _store = const FlutterSecureStorage();

  bool ready = false;
  Map<String, dynamic>? user;

  bool get loggedIn => Api.i.token != null && user != null;
  String get appName => AppConfig.isAdmin ? 'admin' : 'client';
  bool get isAdmin => user?['role'] == 'admin';

  Future<void> restore() async {
    Api.i.onUnauthorized = () => logout(remote: false);
    try {
      final t = await _store.read(key: _key);
      if (t != null) {
        Api.i.token = t;
        final me = await Api.i.get('/me');
        user = Map<String, dynamic>.from(me['user'] as Map);
      }
    } on ApiException catch (e) {
      // Jeton refusé : on repart de la connexion. Hors ligne : on garde le jeton pour réessayer.
      if (e.status == 401) Api.i.token = null;
    } catch (_) {}
    ready = true;
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    final r = await Api.i.post('/auth/login', data: {'email': email, 'password': password, 'app': appName, 'device': 'android'});
    await _accept(r);
  }

  Future<void> register(String name, String email, String phone, String password) async {
    final r = await Api.i.post('/auth/register', data: {'name': name, 'email': email, 'phone': phone, 'password': password, 'device': 'android'});
    await _accept(r);
  }

  Future<void> _accept(dynamic r) async {
    Api.i.token = r['token'] as String;
    user = Map<String, dynamic>.from(r['user'] as Map);
    await _store.write(key: _key, value: Api.i.token);
    notifyListeners();
  }

  Future<void> refreshUser() async {
    final me = await Api.i.get('/me');
    user = Map<String, dynamic>.from(me['user'] as Map);
    notifyListeners();
  }

  Future<void> logout({bool remote = true}) async {
    if (remote && Api.i.token != null) {
      try {
        await Api.i.post('/auth/logout');
      } catch (_) {}
    }
    Api.i.token = null;
    user = null;
    await _store.delete(key: _key);
    notifyListeners();
  }
}

final session = Session();
