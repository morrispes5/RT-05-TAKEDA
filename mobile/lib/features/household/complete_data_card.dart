import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../app/theme.dart';
import '../../core/models.dart';
import '../../core/session.dart';
import '../../core/widgets.dart';
import '../warga/providers.dart';
import 'family_page.dart';
import 'identity_data.dart';

/// "Nanti saja" berlaku sampai aplikasi ditutup; kartu muncul lagi pada sesi berikutnya selama data belum lengkap.
class _Dismissed extends Notifier<bool> {
  @override
  bool build() => false;
  void dismiss() => state = true;
}

final completeDataDismissedProvider = NotifierProvider<_Dismissed, bool>(_Dismissed.new);

/// Data opsional yang masih kosong untuk kartu "Lengkapi data" (ADR35). Kosong = tidak perlu kartu.
List<String> missingHouseholdData(Json household) {
  Map? kepala;
  for (final m in ((household['anggota'] as List?) ?? const []).whereType<Map>()) {
    if (m['is_kepala'] == true) kepala = m;
  }
  return [
    if ('${kepala?['whatsapp'] ?? ''}'.trim().isEmpty) 'WhatsApp',
    if (kepala != null && kepala['nik_tersamar'] == null) 'NIK',
    if (household['nomor_kk_tersamar'] == null) 'nomor KK',
  ];
}

/// Ajakan opsional untuk akun utama melengkapi WhatsApp, NIK, dan nomor KK setelah masuk. Tidak tampil untuk
/// akun tambahan (tidak dapat mengubah data keluarga) atau bila semua sudah terisi.
class CompleteDataCard extends ConsumerWidget {
  const CompleteDataCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final akun = ref.watch(sessionProvider).akun;
    if (akun == null || !akun.dapatUbahKeluarga || ref.watch(completeDataDismissedProvider)) return const SizedBox.shrink();
    final household = ref.watch(householdProvider).asData?.value;
    if (household == null) return const SizedBox.shrink();
    final missing = missingHouseholdData(household);
    if (missing.isEmpty) return const SizedBox.shrink();
    final waMissing = missing.contains('WhatsApp');
    final idMissing = missing.contains('NIK') || missing.contains('nomor KK');
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: SectionCard(
        key: const ValueKey('lengkapi-data'),
        title: 'Lengkapi data keluarga',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'Opsional: ${missing.join(', ')} belum diisi. Data ini membantu pengurus menghubungi dan mencatat keluarga Anda, '
              'dan hanya dapat dilihat pengurus RT serta keluarga sendiri.',
              style: const TextStyle(height: 1.4),
            ),
            const SizedBox(height: 12),
            if (waMissing)
              OutlinedButton.icon(
                onPressed: () async {
                  await Navigator.push<void>(context, MaterialPageRoute(builder: (_) => FamilyEditPage(household: household)));
                  ref.invalidate(householdProvider);
                },
                icon: const Icon(Icons.phone_outlined),
                label: const Text('Isi WhatsApp'),
              ),
            if (waMissing && idMissing) const SizedBox(height: 8),
            if (idMissing)
              OutlinedButton.icon(
                onPressed: () async {
                  await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => const IdentityDataPage()));
                  ref.invalidate(householdProvider);
                },
                icon: const Icon(Icons.badge_outlined),
                label: const Text('Isi NIK & nomor KK'),
              ),
            TextButton(
              onPressed: () => ref.read(completeDataDismissedProvider.notifier).dismiss(),
              child: const Text('Nanti saja', style: TextStyle(color: Brand.muted)),
            ),
          ],
        ),
      ),
    );
  }
}
