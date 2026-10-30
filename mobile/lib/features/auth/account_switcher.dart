import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/session.dart';
import 'auth_pages.dart';

class AccountSwitcher extends ConsumerWidget {
  const AccountSwitcher({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionProvider);
    final accounts = session.accounts;
    final currentId = session.akun?.id;

    if (accounts.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Text(
            'Akun',
            style: TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
        ...accounts.map((a) {
          final isActive = a.id == currentId;
          return ListTile(
            title: Text(a.nama ?? a.email),
            subtitle: Text(a.email),
            trailing: isActive
                ? const Icon(Icons.check_circle, color: Colors.green)
                : null,
            onTap: isActive
                ? null
                : () => ref.read(sessionProvider.notifier).switchTo(a.id),
          );
        }),
        ListTile(
          leading: const Icon(Icons.add),
          title: const Text('Tambah akun'),
          onTap: () {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const LoginPage()),
            );
          },
        ),
      ],
    );
  }
}