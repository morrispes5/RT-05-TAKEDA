// Pemakaian: node m15-load-check.mjs [detik=60] [pengguna=20] (fixture privat di %USERPROFILE%/.rt05, di luar Git).
// Uji beban M15 staging (TESTING.md: P95 ≤1 dtk nonupload, 20 pengguna bersamaan). Hanya baca dan hanya
// akun sintetis: campuran layar aplikasi warga (beranda, iuran, agenda, layanan, inbox) dan halaman website
// publik. Mengukur juga permintaan pertama setelah Neon idle ("dingin") bila dijalankan dengan --cold.
// Tidak boleh diarahkan ke production.
import fs from 'node:fs';
const ORIGIN = 'https://takeda-staging.morriz.tech';
const args = process.argv.slice(2).filter(a => !a.startsWith('--'));
const seconds = Number(args[0] || 60), users = Number(args[1] || 20), cold = process.argv.includes('--cold');
const p = process.env.USERPROFILE + '/.rt05/';
const actors = { ...JSON.parse(fs.readFileSync(p + 'm07-stage-check/m07-state.private.json', 'utf8')).actors, admin: JSON.parse(fs.readFileSync(p + 'm06-stage-check/fixtures.json', 'utf8')).accounts.admin };
const h = { Accept: 'application/json', 'Content-Type': 'application/json' };
const sleep = ms => new Promise(r => setTimeout(r, ms));

async function timed(label, url, headers) {
    const t = performance.now();
    try {
        const r = await fetch(url, { headers });
        await r.arrayBuffer();
        return { label, ms: performance.now() - t, status: r.status };
    } catch (e) {
        return { label, ms: performance.now() - t, status: 0 };
    }
}

const out = { run: new Date().toISOString(), origin: ORIGIN, seconds, users };
if (cold) {
    // Permintaan pertama tanpa cache aplikasi yang menyentuh database (Neon mungkin tersuspensi).
    out.cold = [await timed('health/ready (dingin)', ORIGIN + '/health/ready', h), await timed('health/ready (hangat)', ORIGIN + '/health/ready', h)];
}

const tokens = [];
for (const key of ['a1', 'a3', 'admin']) {
    const a = actors[key];
    const r = await fetch(ORIGIN + '/api/v1/auth/mobile-login', { method: 'POST', headers: h, body: JSON.stringify({ email: a.email, password: a.password, device_name: 'M15 load check' }) });
    const token = (await r.json()).data?.token;
    if (!token) throw new Error('login sintetis gagal: ' + key + ' ' + r.status);
    tokens.push(token);
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
    await sleep(Math.random() * 1000);
    while (Date.now() < end) {
        // 1 dari 4 iterasi pengunjung website publik, sisanya layar aplikasi warga.
        if (Math.random() < 0.25) {
            const [label, path] = pages[Math.floor(Math.random() * pages.length)];
            results.push(await timed(label, ORIGIN + path, { Accept: 'text/html' }));
        } else {
            const [label, path] = screens[Math.floor(Math.random() * screens.length)];
            results.push(await timed(label, ORIGIN + path, auth));
        }
        await sleep(500 + Math.random() * 1000); // jeda baca pengguna
    }
}));
for (const t of tokens) await fetch(ORIGIN + '/api/v1/auth/logout', { method: 'POST', headers: { ...h, Authorization: 'Bearer ' + t } });

const pct = (xs, q) => { const s = [...xs].sort((a, b) => a - b); return s.length ? Math.round(s[Math.min(s.length - 1, Math.ceil(q * s.length) - 1)]) : null; };
const group = {};
for (const r of results) (group[r.label] ||= []).push(r);
out.endpoints = Object.entries(group).sort().map(([label, rs]) => ({
    label, n: rs.length, errors: rs.filter(r => r.status === 0 || r.status >= 500 || r.status === 429).length,
    p50: pct(rs.map(r => r.ms), 0.5), p95: pct(rs.map(r => r.ms), 0.95), max: Math.round(Math.max(...rs.map(r => r.ms))),
}));
const ok = results.filter(r => r.status > 0 && r.status < 500 && r.status !== 429);
out.total = { requests: results.length, errors: results.length - ok.length, rps: +(results.length / seconds).toFixed(1), p50: pct(ok.map(r => r.ms), 0.5), p95: pct(ok.map(r => r.ms), 0.95) };
out.statuses = results.reduce((m, r) => ({ ...m, [r.status]: (m[r.status] || 0) + 1 }), {});
console.log(JSON.stringify(out, null, 1));
