import 'package:flutter/material.dart';

import '../core/format.dart';
import '../core/theme.dart';
import '../services/api.dart';
import '../ui/shell.dart';
import '../ui/widgets.dart';
import '../services/session.dart';
import 'client.dart';

/// Centre de notifications (cloche) : lu / non lu, lien direct vers la commande.
class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});
  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  int rev = 0;

  @override
  Widget build(BuildContext context) {
    final scope = ShellScope.of(context);
    return DataView<List>(
      key: ValueKey(rev),
      load: () async => (await Api.i.get('/notifications')) as List,
      builder: (context, list, refresh) {
        final unread = list.where((n0) => n0['read'] != true).length;
        return ListView(padding: kPagePadding, children: [
          const PageTitle('Notifications'),
          if (unread > 0)
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton.icon(
                onPressed: () async {
                  await Api.i.post('/notifications/read-all');
                  await scope.refreshBadge();
                  setState(() => rev++);
                },
                icon: const Icon(Icons.done_all_rounded, size: 18),
                label: const Text('Tout marquer comme lu'),
              ),
            ),
          Panel(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
            child: list.isEmpty
                ? const Padding(padding: EdgeInsets.all(26), child: Center(child: Text('Aucune notification pour le moment.', style: TextStyle(color: VT.mut))))
                : Column(children: [
                    for (var i = 0; i < list.length; i++)
                      InkWell(
                        onTap: () async {
                          final item = list[i] as Map;
                          if (item['read'] != true) {
                            await Api.i.post('/notifications/${item['id']}/read');
                            scope.refreshBadge();
                          }
                          if (item['reference'] != null && context.mounted) await openOrder(context, '${item['reference']}', admin: session.user?['role'] != 'client');
                          if (mounted) setState(() => rev++);
                        },
                        child: Container(
                          padding: const EdgeInsets.symmetric(vertical: 13),
                          decoration: BoxDecoration(border: i == 0 ? null : const Border(top: BorderSide(color: VT.line))),
                          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Container(width: 8, height: 8, margin: const EdgeInsets.only(top: 6, right: 10), decoration: BoxDecoration(color: list[i]['read'] == true ? Colors.transparent : VT.teal, shape: BoxShape.circle)),
                            Expanded(
                              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                Text('${list[i]['title']}', style: TextStyle(fontWeight: list[i]['read'] == true ? FontWeight.w500 : FontWeight.w800, fontSize: 14)),
                                Text('${list[i]['body']}', style: const TextStyle(color: VT.mut, fontSize: 12.5)),
                              ]),
                            ),
                            const SizedBox(width: 8),
                            Text(since(list[i]['created_at']), style: const TextStyle(color: VT.mut, fontSize: 11)),
                          ]),
                        ),
                      ),
                  ]),
          ),
        ]);
      },
    );
  }
}
