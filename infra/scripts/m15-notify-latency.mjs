// Pemakaian: node m15-notify-latency.mjs (fixture privat di %USERPROFILE%/.rt05, di luar Git).
// M15/ADR31: setelah staging idle (sweep outbox hanya saat aktif), event baru tetap sampai ke inbox ≤60 dtk.
// Pengurus sintetis menerbitkan pengumuman uji, warga sintetis memantau inbox tiap 5 dtk, lalu pengumuman diarsipkan.
// Catatan: pengumuman memicu push ke perangkat staging terdaftar (judul ditandai "Uji").
import fs from 'node:fs';
const ORIGIN = 'https://takeda-staging.morriz.tech';
const p = process.env.USERPROFILE + '/.rt05/';
const admin = JSON.parse(fs.readFileSync(p + 'm06-stage-check/fixtures.json', 'utf8')).accounts.admin;
const warga = JSON.parse(fs.readFileSync(p + 'm07-stage-check/m07-state.private.json', 'utf8')).actors.a1;
const h = { 'Content-Type': 'application/json', Accept: 'application/json' };
const login = async c => (await (await fetch(ORIGIN + '/api/v1/auth/mobile-login', { method: 'POST', headers: h, body: JSON.stringify({ email: c.email, password: c.password, device_name: 'M15 latency' }) })).json()).data.token;
const auth = t => ({ ...h, Authorization: 'Bearer ' + t });
const ta = await login(admin), tw = await login(warga);
const t0 = Date.now();
const r = await fetch(ORIGIN + '/api/v1/admin/announcements', { method: 'POST', headers: auth(ta), body: JSON.stringify({ judul: 'Uji latensi notifikasi M15', isi: 'Pengumuman sintetis untuk menguji notifikasi setelah server idle. Abaikan.' }) });
const id = (await r.json()).data?.id;
let seen = null;
while (Date.now() - t0 < 120000) {
    const list = (await (await fetch(ORIGIN + '/api/v1/notifications?per_page=20', { headers: auth(tw) })).json()).data || [];
    if (list.some(n => n.referensi?.tipe === 'pengumuman' && n.referensi?.id === id)) { seen = Date.now() - t0; break; }
    await new Promise(res => setTimeout(res, 5000));
}
const archived = (await fetch(`${ORIGIN}/api/v1/admin/announcements/${id}/archive`, { method: 'POST', headers: auth(ta) })).status;
for (const t of [ta, tw]) await fetch(ORIGIN + '/api/v1/auth/logout', { method: 'POST', headers: auth(t) });
console.log(JSON.stringify({ run: new Date(t0).toISOString(), created: r.status, inboxSeconds: seen === null ? null : +(seen / 1000).toFixed(1), withinTarget: seen !== null && seen <= 60000, archived }));
