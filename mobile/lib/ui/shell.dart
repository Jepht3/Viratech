import 'dart:async';

import 'package:flutter/material.dart';

import '../core/config.dart';
import '../core/format.dart';
import '../core/theme.dart';
import '../features/admin.dart';
import '../features/client.dart';
import '../features/notifications.dart';
import '../features/settings.dart';
import '../features/update.dart';
import '../services/api.dart';
import '../services/session.dart';

class NavItem {
  const NavItem(this.label, this.icon, this.page, {this.bottom = false, this.adminOnly = false});
  final String label;
  final IconData icon;
  final Widget Function() page;
  final bool bottom;
  final bool adminOnly;
}

/// Permet aux écrans de changer d'onglet (ex. « Nouvel échange » depuis l'accueil).
class ShellScope extends InheritedWidget {
  const ShellScope({super.key, required this.go, required this.refreshBadge, required super.child});
  final void Function(String label) go;
  final Future<void> Function() refreshBadge;

  static ShellScope of(BuildContext c) => c.dependOnInheritedWidgetOfExactType<ShellScope>()!;

  @override
  bool updateShouldNotify(ShellScope old) => false;
}

List<NavItem> navItems() {
  if (AppConfig.isAdmin) {
    return [
      NavItem('File de validation', Icons.checklist_rounded, () => const QueueScreen(), bottom: true),
      NavItem('Commandes', Icons.receipt_long_rounded, () => const AdminOrdersScreen(), bottom: true),
      NavItem('Clients', Icons.people_alt_rounded, () => const ClientsScreen(), bottom: true),
      NavItem('Frais et minimums', Icons.percent_rounded, () => const FeesScreen(), adminOnly: true),
      NavItem('Comptes de réception', Icons.account_balance_wallet_rounded, () => const AccountsScreen(), adminOnly: true),
      NavItem('Notifications', Icons.notifications_rounded, () => const NotificationsScreen()),
      NavItem('Paramètres', Icons.settings_rounded, () => const SettingsScreen()),
    ];
  }
  return [
    NavItem('Accueil', Icons.home_rounded, () => const DashboardScreen(), bottom: true),
    NavItem('Échanger', Icons.swap_horiz_rounded, () => const NewOrderScreen(), bottom: true),
    NavItem('Historique', Icons.receipt_long_rounded, () => const OrdersScreen(), bottom: true),
    NavItem('Moyens de réception', Icons.account_balance_rounded, () => const MethodsScreen()),
    NavItem('Notifications', Icons.notifications_rounded, () => const NotificationsScreen()),
    NavItem('Paramètres', Icons.settings_rounded, () => const SettingsScreen()),
  ];
}

class AppShell extends StatefulWidget {
  const AppShell({super.key});
  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  late final List<NavItem> items = navItems().where((i) => !i.adminOnly || session.isAdmin).toList();
  final _scaffold = GlobalKey<ScaffoldState>();
  int index = 0;
  int rev = 0;
  int unread = 0;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _badge();
    _timer = Timer.periodic(const Duration(seconds: 45), (_) => _badge());
    WidgetsBinding.instance.addPostFrameCallback((_) => checkForUpdateOnStart(context));
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _badge() async {
    try {
      final me = await Api.i.get('/me');
      if (mounted) setState(() => unread = (me['unread_notifications'] as num?)?.toInt() ?? 0);
    } catch (_) {}
  }

  void _select(int i) {
    setState(() {
      if (i == index) rev++;
      index = i;
    });
    _badge();
  }

  void _goLabel(String label) {
    final i = items.indexWhere((e) => e.label == label);
    if (i >= 0) _select(i);
  }

  @override
  Widget build(BuildContext context) {
    final u = session.user!;
    final notifIndex = items.indexWhere((e) => e.label == 'Notifications');
    final bottom = [for (var i = 0; i < items.length; i++) if (items[i].bottom) i, notifIndex];
    return ShellScope(
      go: _goLabel,
      refreshBadge: _badge,
      child: Scaffold(
        key: _scaffold,
        drawer: _drawer(u),
        appBar: AppBar(
          automaticallyImplyLeading: false,
          toolbarHeight: 64,
          titleSpacing: 14,
          title: Row(children: [
            _circle(Icons.menu_rounded, () => _scaffold.currentState?.openDrawer()),
            const Spacer(),
            Image.asset('assets/brand/mark.png', height: 30),
            const SizedBox(width: 8),
            Text(AppConfig.isAdmin ? 'Viratech Admin' : 'Viratech', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
            const Spacer(),
            Stack(clipBehavior: Clip.none, children: [
              _circle(Icons.notifications_rounded, () => _select(notifIndex)),
              if (unread > 0)
                Positioned(
                  top: -3,
                  right: -3,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                    decoration: BoxDecoration(color: const Color(0xFFDC2626), borderRadius: BorderRadius.circular(99)),
                    child: Text('$unread', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700)),
                  ),
                ),
            ]),
          ]),
        ),
        body: KeyedSubtree(key: ValueKey('$index-$rev'), child: items[index].page()),
        bottomNavigationBar: Material(
          color: VT.navy,
          elevation: 12,
          child: SafeArea(
            top: false,
            child: SizedBox(
              height: 64,
              child: Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: [
                for (final i in bottom) _tab(i, i == notifIndex ? unread : 0),
              ]),
            ),
          ),
        ),
      ),
    );
  }
  Widget _circle(IconData icon, VoidCallback onTap) => Material(
        color: Colors.white,
        shape: const CircleBorder(),
        elevation: 2,
        shadowColor: VT.navy.withValues(alpha: 0.3),
        child: InkWell(customBorder: const CircleBorder(), onTap: onTap, child: SizedBox(width: 42, height: 42, child: Icon(icon, color: VT.navy, size: 22))),
      );

  Widget _tab(int i, int badge) {
    final on = i == index;
    final it = items[i];
    final label = it.label == 'File de validation' ? 'File' : (it.label == 'Notifications' ? 'Alertes' : it.label);
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: () => _select(i),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
        decoration: BoxDecoration(color: on ? VT.amber : Colors.transparent, borderRadius: BorderRadius.circular(99)),
        child: Stack(clipBehavior: Clip.none, children: [
          Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(it.icon, size: 22, color: on ? const Color(0xFF2B2208) : const Color(0xFFAEB4D1)),
            Text(label, style: TextStyle(fontSize: 10.5, fontWeight: on ? FontWeight.w800 : FontWeight.w500, color: on ? const Color(0xFF2B2208) : const Color(0xFFAEB4D1))),
          ]),
          if (badge > 0)
            Positioned(
              top: -2,
              right: -4,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 4),
                decoration: BoxDecoration(color: const Color(0xFFDC2626), borderRadius: BorderRadius.circular(99)),
                child: Text('$badge', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w700)),
              ),
            ),
        ]),
      ),
    );
  }

  /// Menu latéral qui se glisse depuis la gauche : profil, navigation, calendrier, déconnexion.
  Widget _drawer(Map<String, dynamic> u) {
    final name = '${u['name']}';
    final initials = name.trim().split(RegExp(r'\s+')).take(2).map((p) => p.isEmpty ? '' : p[0].toUpperCase()).join();
    final role = {'admin': 'Administrateur', 'operator': 'Opérateur'}['${u['role']}'] ?? 'Client · niveau ${u['kyc_level']}';
    return Drawer(
      width: 300,
      backgroundColor: VT.navy,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.horizontal(right: Radius.circular(32))),
      child: SafeArea(
        child: Column(children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(18, 24, 18, 20),
            decoration: const BoxDecoration(color: VT.teal, borderRadius: BorderRadius.only(topRight: Radius.circular(32), bottomRight: Radius.circular(32))),
            child: Column(children: [
              Container(
                width: 68,
                height: 68,
                alignment: Alignment.center,
                decoration: BoxDecoration(color: const Color(0xFF12394A), shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 3)),
                child: Text(initials, style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w700)),
              ),
              const SizedBox(height: 10),
              Text(name, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 15)),
              Text('${u['email']}', style: TextStyle(color: Colors.white.withValues(alpha: 0.8), fontSize: 11.5)),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.16), borderRadius: BorderRadius.circular(99)),
                child: Text(role, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
              ),
            ]),
          ),
          const SizedBox(height: 14),
          Expanded(
            child: ListView(padding: const EdgeInsets.only(left: 14), children: [
              for (var i = 0; i < items.length; i++) _drawerItem(i),
              const SizedBox(height: 14),
              const Padding(padding: EdgeInsets.only(right: 14), child: MiniCalendar()),
            ]),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 8, 14, 14),
            child: OutlinedButton.icon(
              style: OutlinedButton.styleFrom(foregroundColor: Colors.white, side: BorderSide(color: Colors.white.withValues(alpha: 0.25)), minimumSize: const Size.fromHeight(44)),
              onPressed: () => session.logout(),
              icon: const Icon(Icons.logout_rounded, size: 18),
              label: const Text('Se déconnecter'),
            ),
          ),
        ]),
      ),
    );
  }

  Widget _drawerItem(int i) {
    final on = i == index;
    final it = items[i];
    return InkWell(
      borderRadius: const BorderRadius.horizontal(left: Radius.circular(30)),
      onTap: () {
        Navigator.of(context).pop();
        _select(i);
      },
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 13),
        decoration: BoxDecoration(color: on ? VT.bg : Colors.transparent, borderRadius: const BorderRadius.horizontal(left: Radius.circular(30))),
        child: Row(children: [
          Icon(it.icon, size: 20, color: on ? VT.teal : const Color(0xFFC9CEE3)),
          const SizedBox(width: 14),
          Expanded(child: Text(it.label, style: TextStyle(color: on ? VT.teal : const Color(0xFFC9CEE3), fontWeight: on ? FontWeight.w700 : FontWeight.w500, fontSize: 14.5))),
          if (it.label == 'Notifications' && unread > 0)
            Container(padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 1), decoration: BoxDecoration(color: VT.badBg, borderRadius: BorderRadius.circular(99)), child: Text('$unread', style: const TextStyle(color: VT.badFg, fontSize: 11, fontWeight: FontWeight.w700))),
        ]),
      ),
    );
  }
}

/// Calendrier du mois en cours (jour d'aujourd'hui mis en évidence).
class MiniCalendar extends StatelessWidget {
  const MiniCalendar({super.key});

  @override
  Widget build(BuildContext context) {
    final now = DateTime.now();
    final first = DateTime(now.year, now.month, 1);
    final lead = first.weekday - 1;
    final days = DateTime(now.year, now.month + 1, 0).day;
    final cells = <Widget>[
      for (final d in ['L', 'M', 'M', 'J', 'V', 'S', 'D']) Center(child: Text(d, style: const TextStyle(fontSize: 10.5, color: Color(0xFF5B5873)))),
      for (var i = 0; i < lead; i++) const SizedBox.shrink(),
      for (var d = 1; d <= days; d++)
        Center(
          child: Container(
            width: 24,
            height: 24,
            alignment: Alignment.center,
            decoration: d == now.day ? const BoxDecoration(color: Color(0xFF6A5AA8), shape: BoxShape.circle) : null,
            child: Text('$d', style: TextStyle(fontSize: 11, color: d == now.day ? Colors.white : VT.ink, fontWeight: d == now.day ? FontWeight.w700 : FontWeight.w400)),
          ),
        ),
    ];
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: const Color(0xFFEDE9F6), borderRadius: BorderRadius.circular(18)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('${daysFr[now.weekday - 1].substring(0, 3)}. ${now.day} ${monthsFr[now.month - 1]}', style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: VT.ink)),
        const SizedBox(height: 8),
        GridView.count(crossAxisCount: 7, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(), childAspectRatio: 1.15, children: cells),
      ]),
    );
  }
}
