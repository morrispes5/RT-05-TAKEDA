// Pemakaian: node m14-stage-check.mjs (fixture privat di %USERPROFILE%/.rt05, di luar Git).
// Cek live M14 staging: website membaca API konten, CMS web memakai sesi cookie + CSRF seperti browser
// (origin staging), draf terisolasi, unggah foto, terbit → halaman website publik, arsip → hilang. Konten uji
// sintetis diarsipkan di akhir. Tanpa data warga; foto uji dibuat GD (kotak warna) di ~/.rt05/m14-stage-check.
import fs from 'node:fs';
const ORIGIN = 'https://takeda-staging.morriz.tech';
const p = process.env.USERPROFILE + '/.rt05/';
const admin = JSON.parse(fs.readFileSync(p + 'm06-stage-check/fixtures.json', 'utf8')).accounts.admin;
const warga = JSON.parse(fs.readFileSync(p + 'm07-stage-check/m07-state.private.json', 'utf8')).actors.a3;
const photo = fs.readFileSync(p + 'm14-stage-check/uji-sintetis.jpg');
const out = { run: new Date().toISOString(), cases: [] };
const rec = (name, ok, detail = {}) => { out.cases.push({ name, result: ok ? 'PASS' : 'FAIL', ...detail }); };
const sleep = ms => new Promise(r => setTimeout(r, ms));
const run = Math.random().toString(16).slice(2, 8);

// Browser minimal: cookie jar per nama, Origin/Referer CMS, X-XSRF-TOKEN dari cookie rt05_api_xsrf.
function browser() {
    const jar = new Map();
    const store = res => { for (const c of res.headers.getSetCookie?.() || []) { const [kv] = c.split(';'); const i = kv.indexOf('='); const v = kv.slice(i + 1); if (v === '' || /max-age=0|expires=thu, 01 jan 1970/i.test(c)) jar.delete(kv.slice(0, i)); else jar.set(kv.slice(0, i), v); } };
    return async (method, path, { json, form, csrf = true, origin = ORIGIN } = {}) => {
        const headers = { Accept: 'application/json', Cookie: [...jar].map(([k, v]) => k + '=' + v).join('; ') };
        if (origin) { headers.Origin = origin; headers.Referer = origin + '/pengurus'; }
        if (csrf && jar.has('rt05_api_xsrf')) headers['X-XSRF-TOKEN'] = decodeURIComponent(jar.get('rt05_api_xsrf'));
        if (json) headers['Content-Type'] = 'application/json';
        const res = await fetch(ORIGIN + path, { method, redirect: 'manual', headers, body: json ? JSON.stringify(json) : form });
        store(res);
        const type = res.headers.get('content-type') || '';
        return { status: res.status, headers: res.headers, body: type.includes('json') ? await res.json().catch(() => null) : await res.text() };
    };
}
const page = async path => { const r = await fetch(ORIGIN + path, { headers: { Accept: 'text/html' } }); return { status: r.status, html: await r.text() }; };
async function until(check, seconds = 90) { for (let t = 0; t <= seconds; t += 15) { const v = await check(); if (v) return { ok: true, waited: t }; await sleep(15000); } return { ok: false, waited: seconds }; }

let r = await page('/artikel');
rec('W01 website /artikel dirender dari konten (impor)', r.status === 200 && r.html.includes('Tangan bersih'), { status: r.status });
r = await page('/pengurus/masuk');
rec('W02 area pengurus mode server', r.status === 200 && r.html.includes('data-cms-mode="server"') && !r.html.includes('id="cms-seeds"'), { status: r.status });

const w = browser();
await w('GET', '/sanctum/csrf-cookie');
r = await w('POST', '/api/v1/auth/web-login', { json: { email: warga.email, password: warga.password } });
rec('W03 warga tidak mendapat sesi CMS (pesan generik)', r.status === 422 && r.body?.error?.fields?.email?.[0] === 'Email atau kata sandi salah.', { status: r.status });

const b = browser();
await b('GET', '/sanctum/csrf-cookie');
r = await b('POST', '/api/v1/auth/web-login', { json: { email: admin.email, password: admin.password }, csrf: false });
rec('W04 web-login tanpa CSRF ditolak', r.status === 419, { status: r.status });
r = await b('POST', '/api/v1/auth/web-login', { json: { email: admin.email, password: admin.password } });
rec('W05 pengurus masuk lewat cookie, tanpa token', r.status === 200 && r.body?.data?.peran === 'pengurus' && !('token' in (r.body?.data || {})), { status: r.status });

const slug = 'uji-m14-' + run;
const draf = { title: 'Artikel uji sintetis M14 ' + run, category: 'Lingkungan', art: 'park', summary: 'Artikel sintetis untuk uji staging M14.', intro: 'Tidak untuk warga; diarsipkan setelah uji.', sections: [{ title: 'Uji', body: 'Isi sintetis untuk memeriksa alur terbit CMS web.' }], note: null, source: null };
r = await b('POST', '/api/v1/admin/articles', { json: { slug, draf }, csrf: false });
rec('W06 mutation tanpa CSRF ditolak', r.status === 419, { status: r.status });
r = await b('POST', '/api/v1/admin/articles', { json: { slug, draf }, origin: 'https://jahat.test' });
rec('W07 origin asing tidak memakai cookie', r.status === 401, { status: r.status });
r = await b('POST', '/api/v1/admin/articles', { json: { slug, draf } });
const article = r.body?.data;
rec('W08 draf artikel dibuat lewat sesi cookie', r.status === 201 && article?.status === 'draf', { status: r.status });

const form = new FormData();
form.append('foto', new Blob([photo], { type: 'image/jpeg' }), 'uji.jpg');
r = await b('POST', '/api/v1/admin/content-media', { form });
const media = r.body?.data;
rec('W09 unggah foto lewat sesi cookie', r.status === 201 && media?.status === 'siap', { status: r.status });
r = await b('GET', media?.pratinjau_url || '/x');
rec('W10 pratinjau foto draf privat untuk pengurus', r.status === 200 && (r.headers.get('content-type') || '').startsWith('image/jpeg'), { status: r.status });
r = await fetch(ORIGIN + '/api/v1/public/media/' + media?.id);
rec('W11 foto draf belum publik', r.status === 404, { status: r.status });
const albumSlug = 'uji-m14-album-' + run;
r = await b('POST', '/api/v1/admin/albums', { json: { slug: albumSlug, draf: { title: 'Album uji sintetis M14', description: 'Album sintetis untuk uji staging M14.', date: null, photos: [{ media_id: media?.id, alt: 'Kotak kuning di latar biru tua', caption: null }], cover_media_id: media?.id } } });
const album = r.body?.data;
rec('W12 draf album dengan foto unggahan', r.status === 201, { status: r.status });

r = await page('/artikel/' + slug);
rec('W13 draf tidak tampil di website publik', r.status === 404, { status: r.status });

r = await b('POST', `/api/v1/admin/articles/${article?.id}/publish`, { json: { versi: 1 } });
const r2 = await b('POST', `/api/v1/admin/albums/${album?.id}/publish`, { json: { versi: 1 } });
rec('W14 terbitkan artikel dan album', r.status === 200 && r2.status === 200, { status: [r.status, r2.status] });
let u = await until(async () => { const x = await page('/artikel/' + slug); return x.status === 200 && x.html.includes('Isi sintetis untuk memeriksa alur terbit CMS web.'); });
rec('W15 artikel terbit tampil di website (browser lain, tanpa akun)', u.ok, { waitedSeconds: u.waited });
u = await until(async () => { const x = await page('/dokumentasi/' + albumSlug); return x.status === 200 && x.html.includes('/api/v1/public/media/' + media?.id); });
rec('W16 album terbit tampil di website dengan foto unggahan', u.ok, { waitedSeconds: u.waited });
r = await fetch(ORIGIN + '/api/v1/public/media/' + media?.id);
rec('W17 foto publik setelah terbit', r.status === 200 && (r.headers.get('content-type') || '').startsWith('image/jpeg'), { status: r.status });

await b('PUT', `/api/v1/admin/articles/${article?.id}`, { json: { versi: 1, slug, draf: { ...draf, title: 'Judul draf baru M14' } } });
r = await b('PUT', `/api/v1/admin/articles/${article?.id}`, { json: { versi: 1, slug, draf: { ...draf, title: 'Judul basi' } } });
rec('W18 versi basi 409 (tab lain)', r.status === 409, { status: r.status, code: r.body?.error?.code });

r = await b('POST', `/api/v1/admin/articles/${article?.id}/archive`, { json: { versi: 2 } });
const r3 = await b('POST', `/api/v1/admin/albums/${album?.id}/archive`, { json: { versi: 1 } });
rec('W19 arsipkan konten uji', r.status === 200 && r3.status === 200, { status: [r.status, r3.status] });
u = await until(async () => (await page('/artikel/' + slug)).status === 404 && (await page('/dokumentasi/' + albumSlug)).status === 404);
rec('W20 arsip hilang dari website', u.ok, { waitedSeconds: u.waited });

r = await b('POST', '/api/v1/auth/logout');
const me = await b('GET', '/api/v1/auth/me');
rec('W21 keluar mengakhiri sesi cookie', r.status === 204 && me.status === 401, { status: [r.status, me.status] });

out.summary = { total: out.cases.length, pass: out.cases.filter(c => c.result === 'PASS').length };
console.log(JSON.stringify(out, null, 1));
