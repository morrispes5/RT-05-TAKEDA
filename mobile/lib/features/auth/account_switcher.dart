import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/theme.dart';
import '../../core/account_store.dart';
import '../../core/session.dart';
import '../../core/widgets.dart';

/// Daftar akun yang tersimpan di HP ini.
/// Satu orang dapat punya lebih dari satu akun, misalnya dua rumah.
/// Iuran, keluarga, dan laporan tidak digabung antar akun.
class AccountSwitcher extends ConsumerStatefulWidget {
  const AccountSwitcher({super.key, this.onAdd, this.embeddedInLogin = false});

  /// Membuka halaman masuk untuk menambah akun. Kosong di halaman login.
  final VoidCallback? onAdd;

  /// Di halaman masuk: daftar dipilih dulu, formulir email ada di bawahnya.
  final bool embeddedInLogin;

  @override
  ConsumerState<AccountSwitcher> createState() => _AccountSwitcherState();
}

class _AccountSwitcherState extends ConsumerState<AccountSwitcher> {
  String? _busyId;

  Future<void> _switch(StoredAccount account) async {
    if (_busyId != null) return;
    setState(() => _busyId = account.id);
    final error = await ref.read(sessionProvider.notifier).switchTo(account.id);
    if (!mounted) return;
    setState(() => _busyId = null);
    if (error != null) showMessage(context, error, error: true);
  }

  Future<void> _forget(StoredAccount account) async {
    if (_busyId != null) return;
    final label = _titleOf(account);
    final yes = await confirm(
      context,
      title: 'Hapus akun dari HP ini?',
      message:
          '$label dilepas dari HP ini. Data di server tidak dihapus. Masuk lagi kapan saja dengan email dan kata sandi.',
      yes: 'Hapus dari HP',
      danger: true,
      icon: Icons.phonelink_erase_outlined,
    );
    if (!yes || !mounted) return;
    setState(() => _busyId = account.id);
    final error = await ref
        .read(sessionProvider.notifier)
        .forgetAccount(account.id);
    if (!mounted) return;
    setState(() => _busyId = null);
    if (error != null) showMessage(context, error, error: true);
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionProvider);
    final accounts = session.accounts;
    if (accounts.isEmpty) return const SizedBox.shrink();
    final currentId = session.akun?.id;
    final tiles = [
      for (final account in accounts)
        _tile(account, active: account.id == currentId),
    ];

    if (widget.embeddedInLogin) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Akun di HP ini',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 4),
          const Text(
            'Pilih akun. Data rumah yang satu tidak tercampur dengan rumah yang lain.',
            style: TextStyle(color: Brand.muted, height: 1.35),
          ),
          const SizedBox(height: 8),
          ...tiles,
          const Divider(height: 32),
          const Text(
            'Atau masuk dengan email lain',
            style: TextStyle(color: Brand.muted),
          ),
          const SizedBox(height: 12),
        ],
      );
    }

    return SectionCard(
      title: 'Akun di HP ini',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Satu orang bisa masuk dengan lebih dari satu akun, misalnya bila memiliki dua rumah. Iuran, keluarga, dan laporan tiap akun tidak digabung.',
            style: TextStyle(color: Brand.muted, height: 1.35),
          ),
          const SizedBox(height: 8),
          ...tiles,
          if (widget.onAdd != null)
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(
                Icons.person_add_alt_1_outlined,
                color: Brand.navy,
              ),
              title: const Text('Tambah akun'),
              subtitle: const Text('Masuk dengan akun rumah yang lain'),
              enabled: _busyId == null,
              onTap: _busyId == null ? widget.onAdd : null,
            ),
        ],
      ),
    );
  }

  Widget _tile(StoredAccount account, {required bool active}) {
    final title = _titleOf(account);
    final busy = _busyId == account.id;
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Material(
        color: active ? Brand.sky : Colors.transparent,
        borderRadius: BorderRadius.circular(12),
        child: ListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: 8),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          leading: CircleAvatar(
            backgroundColor: Brand.navy,
            child: Text(
              title.isEmpty ? '?' : title[0].toUpperCase(),
              style: const TextStyle(color: Colors.white),
            ),
          ),
          title: Text(title, maxLines: 1, overflow: TextOverflow.ellipsis),
          subtitle: Text(
            _subtitleOf(account),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          trailing: busy
              ? const SizedBox(
                  width: 24,
                  height: 24,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : active
              ? const Icon(Icons.check_circle, color: Brand.navy)
              : IconButton(
                  tooltip: 'Hapus dari HP ini',
                  onPressed: _busyId == null ? () => _forget(account) : null,
                  icon: const Icon(Icons.close, color: Brand.muted),
                ),
          onTap: active || _busyId != null ? null : () => _switch(account),
        ),
      ),
    );
  }
}

String _titleOf(StoredAccount account) {
  final name = account.nama?.trim() ?? '';
  if (name.isNotEmpty && name != 'null') return name;
  return account.email;
}

String _subtitleOf(StoredAccount account) {
  final role = switch (account.peran) {
    'pengurus' => 'Pengurus',
    'warga' => 'Warga',
    _ => '',
  };
  final status = switch (account.status) {
    'menunggu' => 'Menunggu',
    'nonaktif' => 'Nonaktif',
    'diarsipkan' => 'Diarsipkan',
    _ => '',
  };
  return [
    account.email,
    role,
    status,
  ].where((part) => part.trim().isNotEmpty).join(' · ');
}
