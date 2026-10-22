// Pemakaian: node m15-load-check.mjs [detik=60] [pengguna=20] (fixture privat di %USERPROFILE%/.rt05, di luar Git).
// Uji beban M15 staging (TESTING.md: P95 ≤1 dtk nonupload, 20 pengguna bersamaan). Hanya baca dan hanya
// akun sintetis: campuran layar aplikasi warga (beranda, iuran, agenda, layanan, inbox) dan halaman website
// publik. Mengukur juga permintaan pertama setelah Neon idle ("dingin") bila dijalankan dengan --cold.
// Tidak boleh diarahkan ke production.
// Gate (m15-evidence-lib.mjs): setiap respons harus sesuai status yang diharapkan (bawaan 200) dan P95 sukses per
// endpoint ≤1 dtk; pelanggaran membuat proses exit 1. Status yang diharapkan selain 200 harus ditulis eksplisit
// di EXPECT dengan penjelasan setelah penyebabnya diperiksa; jangan menormalkan 404 tanpa alasan.
import fs from 'node:fs';
import { GATE, loadFailures, summarize } from './m15-evidence-lib.mjs';
const ORIGIN = 'https://takeda-staging.morriz.tech';
const args = process.argv.slice(2).filter(a => !a.startsWith('--'));
const seconds = Number(args[0] || 60), users = Number(args[1] || 20), cold = process.argv.includes('--cold');
const p = process.env.USERPROFILE + '/.rt05/';
const actors = { ...JSON.parse(fs.readFileSync(p + 'm07-stage-check/m07-state.private.json', 'utf8')).actors, admin: JSON.parse(fs.readFileSync(p + 'm06-stage-check/fixtures.json', 'utf8')).accounts.admin };
const h = { Accept: 'application/json', 'Content-Type': 'application/json' };
const sleep = ms => new Promise(r => setTimeout(r, ms));

// Diisi hanya untuk skenario negatif yang sudah dijelaskan.
// #47 (M16): akun pengurus sintetis tidak terhubung ke rumah/keluarga, jadi GET /household dan GET /dues/my menjawab
// 404 HOUSEHOLD_NOT_LINKED (HouseholdController::keluargaOf, FinanceController::my; aplikasi menampilkan "Belum terhubung
// ke rumah"). Ini sumber 404 di bukti M15: 1/3 pengguna × 2/8 layar × 3/4 iterasi aplikasi ≈ 6,3% permintaan
// (ulang 22 Okt 2026: 49/848 = 5,8%, seluruhnya admin pada dua endpoint ini).
const EXPECT = { 'admin GET /household': [404], 'admin GET /dues/my': [404] };

async function timed(label, url, headers, role) {
    const t = performance.now();
    try {
        const r = await fetch(url, { headers });
        await r.arrayBuffer();
        return { label, role, ms: performance.now() - t, status: r.status };
    } catch (e) {
        return { label, role, ms: performance.now() - t, status: 0 };
    }
}

const out = { run: new Date().toISOString(), origin: ORIGIN, seconds, users };
if (cold) {
    // Permintaan pertama tanpa cache aplikasi yang menyentuh database (Neon mungkin tersuspensi).
    out.cold = [await timed('health/ready (dingin)', ORIGIN + '/health/ready', h), await timed('health/ready (hangat)', ORIGIN + '/health/ready', h)];
}

const tokens = [], roles = [];
for (const key of ['a1', 'a3', 'admin']) {
    const a = actors[key];
    const r = await fetch(ORIGIN + '/api/v1/auth/mobile-login', { method: 'POST', headers: h, body: JSON.stringify({ email: a.email, password: a.password, device_name: 'M15 load check' }) });
    const token = (await r.json()).data?.token;
    if (!token) throw new Error('login sintetis gagal: ' + key + ' ' + r.status);
    tokens.push(token);
    roles.push(key);
}

const screens = [
    ['GET /auth/me', '/api/v1/auth/me'],
    ['GET /dues/my', '/api/v1/dues/my'],
    ['GET /agendas', '/api/v1/agendas'],
    ['GET /announcements', '/api/v1/announcements'],
    ['GET /complaints', '/api/v1/complaints'],
    ['GET /notifications', '/api/v1/notifications'],
    ['GET /household', '/api/v1/household'],
    ['GET /public/articles', '/api/v1/public/articles'],
];
const pages = [['WEB /', '/'], ['WEB /artikel', '/artikel'], ['WEB /dokumentasi', '/dokumentasi']];
const results = [];
const end = Date.now() + seconds * 1000;
await Promise.all(Array.from({ length: users }, async (_, i) => {
    const auth = { ...h, Authorization: 'Bearer ' + tokens[i % tokens.length] };
    const role = roles[i % roles.length];
    await sleep(Math.random() * 1000);
    while (Date.now() < end) {
        // 1 dari 4 iterasi pengunjung website publik, sisanya layar aplikasi warga.
        if (Math.random() < 0.25) {
            const [label, path] = pages[Math.floor(Math.random() * pages.length)];
            results.push(await timed(label, ORIGIN + path, { Accept: 'text/html' }, 'web'));
        } else {
            const [label, path] = screens[Math.floor(Math.random() * screens.length)];
            results.push(await timed(label, ORIGIN + path, auth, role));
        }
        await sleep(500 + Math.random() * 1000); // jeda baca pengguna
    }
}));
for (const t of tokens) await fetch(ORIGIN + '/api/v1/auth/logout', { method: 'POST', headers: { ...h, Authorization: 'Bearer ' + t } });

const summary = summarize(results, seconds, EXPECT);
Object.assign(out, summary);
const failures = loadFailures(summary, GATE);
out.gate = { p95Ms: GATE.p95Ms, passed: failures.length === 0, failures };
console.log(JSON.stringify(out, null, 1));
if (failures.length) {
    console.error('GATE BEBAN GAGAL:\n- ' + failures.join('\n- '));
    process.exitCode = 1;
}
