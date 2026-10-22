#!/usr/bin/env bash
# Pengamanan dasar VPS BARU RT05 (rencana pindah 2 Nov 2026, ADR38 butir 6). Dijalankan sebagai root lewat SSH berkunci:
#   bash vps-hardening.sh --yes
# Menolak berjalan bila root belum punya authorized_keys atau sesi ini bukan login berkunci, supaya tidak terkunci keluar.
# Yang dilakukan: update keamanan otomatis, firewall (22/80/443, IPv4+IPv6), fail2ban untuk sshd, dan SSH tanpa password
# (root hanya dengan kunci). JANGAN dijalankan di VPS lama (keputusan pemilik: VPS lama tidak diubah sampai habis 4 Nov).
# Sebelum menjalankan: pastikan konsol browser Hostinger bisa masuk, dan cadangan kunci SSH sudah ada di Drive pribadi pemilik.
set -euo pipefail
[ "${1:-}" = "--yes" ] || { echo "Tambahkan --yes setelah membaca komentar di atas."; exit 1; }
[ "$(id -u)" = 0 ] || { echo "Jalankan sebagai root."; exit 1; }
[ -s /root/.ssh/authorized_keys ] || { echo "GAGAL: /root/.ssh/authorized_keys kosong. Pasang kunci dulu."; exit 1; }
if ! journalctl -u ssh -u sshd --since "-30min" 2>/dev/null | grep -q "Accepted publickey for root"; then
  echo "GAGAL: tidak terlihat login root berkunci dalam 30 menit terakhir. Masuk dengan kunci SSH dulu."; exit 1
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get install -y -q unattended-upgrades ufw fail2ban
dpkg-reconfigure -f noninteractive unattended-upgrades

# Firewall: hanya SSH dan web. Docker mem-publish port lewat iptables sendiri; jangan publish port database/Redis ke publik.
sed -i 's/^IPV6=.*/IPV6=yes/' /etc/default/ufw
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

cat > /etc/fail2ban/jail.d/rt05-sshd.local <<'EOF'
[sshd]
enabled = true
maxretry = 5
findtime = 10m
bantime = 1h
EOF
systemctl enable --now fail2ban
systemctl restart fail2ban

# SSH: kunci saja. Berkas drop-in diurutkan paling awal agar menang dari cloud-init (50-cloud-init.conf).
cat > /etc/ssh/sshd_config.d/00-rt05-hardening.conf <<'EOF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin prohibit-password
PubkeyAuthentication yes
EOF
sshd -t
systemctl reload ssh 2>/dev/null || systemctl reload sshd
sshd -T | grep -iE '^(passwordauthentication|permitrootlogin|pubkeyauthentication) '
echo ">>> Selesai. JANGAN tutup sesi ini sebelum login baru dengan kunci dari terminal lain berhasil."
