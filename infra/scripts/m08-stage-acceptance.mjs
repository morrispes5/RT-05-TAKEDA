#!/usr/bin/env node
/*
 * M08 synthetic LIVE acceptance — staging only (docs/evidence/m08/REPORT.md).
 *
 * Node 22, standard fetch/FormData, tanpa dependency. Hanya memanggil
 * https://takeda-staging.morriz.tech/api/v1/*; host lain dan redirect ditolak.
 * Tanpa --execute script hanya memvalidasi argumen/fixture lalu mencetak rencana (tanpa jaringan).
 *
 *   node infra/scripts/m08-stage-acceptance.mjs --sha <40hex> \
 *     --admin-fixture C:/Users/USER/.rt05/m06-stage-check/fixtures.json \
 *     --actors C:/Users/USER/.rt05/m07-stage-check/m07-state.private.json \
 *     --media C:/Users/USER/.rt05/m08-stage-check/media \
 *     --out C:/Users/USER/.rt05/m08-stage-check [--execute]
 *
 * Aktor sintetis dari fixture privat M06 (admin) dan M07 (a1 utama aktif = pelapor, a3 aktif =
 * warga lain, a2 menunggu). Nilai fixture tidak pernah dicetak/ditulis ulang. Foto adalah gambar
 * buatan generator (bukan foto warga). Ringkasan publik: {case, routeTemplate, httpStatus, code,
 * assertions} tanpa token/kata sandi/email/ID akun. Tidak ada hapus data, reset, SMTP, atau Neon.
 * Satu-satunya perubahan akun: a3 dinonaktifkan sementara lalu diaktifkan kembali (uji akun nonaktif).
 */
import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import { realpathSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ORIGIN = 'https://takeda-staging.morriz.tech';
const API = '/api/v1';
const REPO = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const IS_UUID = v => typeof v === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(v);
const SUMMARY_FILE = 'm08-live-summary.json';
const ANDROID_FILE = 'm08-android-fixtures.private.json';
const EXIF_MARKER = 'RT05-GPS-SINTETIS-M08';
const MEDIA = {
  exif: ['sintetis-exif.jpg', 'image/jpeg'],
  png: ['sintetis.png', 'image/png'],
  webp: ['sintetis.webp', 'image/webp'],
  spoof: ['spoof.jpg', 'image/jpeg'],
  svg: ['gambar.svg', 'image/svg+xml'],
  gif: ['animasi.gif', 'image/gif'],
  dims: ['besar-dimensi.png', 'image/png'],
  big: ['besar-13mb.jpg', 'image/jpeg'],
};

class UsageError extends Error {}
class CaseFailure extends Error {}

function parseArgs(argv) {
  const a = { execute: false };
  for (let i = 0; i < argv.length; i++) {
    const k = argv[i];
    const v = () => {
      const x = argv[++i];
      if (x === undefined || x.startsWith('--')) throw new UsageError(`${k} memerlukan nilai`);
      return x;
    };
    if (k === '--sha') a.sha = v();
    else if (k === '--admin-fixture') a.adminFixture = v();
    else if (k === '--actors') a.actors = v();
    else if (k === '--media') a.media = v();
    else if (k === '--out') a.out = v();
    else if (k === '--execute') a.execute = true;
    else throw new UsageError(`Argumen tidak dikenal: ${k}`);
  }
  if (!/^[0-9a-f]{40}$/.test(a.sha ?? '')) throw new UsageError('--sha wajib 40 hex (SHA deployment yang diverifikasi lewat SSH).');
  for (const k of ['adminFixture', 'actors', 'media', 'out']) {
    if (!a[k] || !path.isAbsolute(a[k])) throw new UsageError(`--${k.replace(/[A-Z]/g, c => `-${c.toLowerCase()}`)} wajib path absolut.`);
  }
  const canon = p => { let r = path.resolve(p); try { r = realpathSync.native(r); } catch { /* belum ada */ } return process.platform === 'win32' ? r.toLowerCase() : r; };
  const repo = canon(REPO);
  for (const k of ['adminFixture', 'actors', 'media', 'out']) {
    const rel = path.relative(repo, canon(a[k]));
    if (rel === '' || (!rel.startsWith('..') && !path.isAbsolute(rel))) throw new UsageError('Fixture, media, dan output privat harus di luar repository Git.');
  }
  return a;
}

let args;
const secrets = new Set();
const tokens = new Map();
const actors = {};
const cases = [];
const remember = (...vs) => vs.forEach(v => typeof v === 'string' && v.length >= 6 && secrets.add(v));
const sleep = ms => new Promise(r => setTimeout(r, ms));

function buildUrl(route, params = {}, query) {
  const filled = route.replace(/\{(\w+)\}/g, (_, k) => {
    if (!IS_UUID(params[k])) throw new Error(`Parameter ${k} bukan UUID`);
    return params[k];
  });
  const url = new URL(API + filled, ORIGIN);
  for (const [k, v] of Object.entries(query ?? {})) url.searchParams.set(k, String(v));
  if (url.origin !== ORIGIN || url.protocol !== 'https:' || (!url.pathname.startsWith(`${API}/`) && url.pathname !== API)) {
    throw new Error('URL di luar staging API ditolak.');
  }
  return url;
}

/** Satu request; 429 diulang sesuai Retry-After (≤ 5 menit); body JSON atau FormData. */
async function http(method, route, { params, query, token, body, binary = false } = {}) {
  const url = buildUrl(route, params, query);
  for (let attempt = 1; ; attempt++) {
    const multipart = body instanceof FormData;
    const response = await fetch(url, {
      method,
      redirect: 'manual',
      signal: AbortSignal.timeout(120_000),
      headers: {
        Accept: 'application/json',
        'User-Agent': 'rt05-m08-synthetic-acceptance',
        ...(body !== undefined && !multipart ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body === undefined ? undefined : multipart ? body : JSON.stringify(body),
    });
    if (response.status >= 300 && response.status < 400) throw new Error(`${method} ${route}: redirect ditolak.`);
    if (response.status === 429 && attempt <= 3) {
      const wait = Number(response.headers.get('retry-after')) || 60;
      if (wait > 300) throw new Error(`${method} ${route}: Retry-After ${wait}s terlalu lama.`);
      console.log(`  rate limit ${route}: tunggu ${wait + 1}s`);
      await sleep((wait + 1) * 1000);
      continue;
    }
    const headers = Object.fromEntries(['content-type', 'content-disposition', 'x-content-type-options', 'cache-control'].map(h => [h, response.headers.get(h) ?? '']));
    if (binary && response.ok) return { method, route, status: response.status, bytes: Buffer.from(await response.arrayBuffer()), headers, code: null, body: null };
    const text = await response.text();
    let json = null;
    if (text) { try { json = JSON.parse(text); } catch { json = null; } }
    return { method, route, status: response.status, body: json, code: json?.error?.code ?? null, headers, raw: text };
  }
}

function record(id, res, expect, { code, checks = [] } = {}) {
  const expected = Array.isArray(expect) ? expect : [expect];
  const assertions = [[`status ${expected.join('|')}`, expected.includes(res.status)]];
  if (res.status >= 400) assertions.push(['error.code + request_id', typeof res.body?.error?.code === 'string' && typeof res.body?.error?.request_id === 'string']);
  else if (res.status !== 204 && !res.bytes) assertions.push(['data + meta.request_id', res.body?.data !== undefined && typeof res.body?.meta?.request_id === 'string']);
  if (code) assertions.push([`code ${[code].flat().join('|')}`, [code].flat().includes(res.code)]);
  for (const [name, fn] of checks) {
    let ok = false;
    try { ok = expected.includes(res.status) && Boolean(fn(res.body ?? res)); } catch { ok = false; }
    assertions.push([name, ok]);
  }
  const passed = assertions.every(([, ok]) => ok);
  cases.push({
    case: id,
    routeTemplate: `${res.method} ${API}${res.route}`,
    httpStatus: res.status,
    code: res.status >= 400 ? res.code : null,
    assertions: assertions.map(([name, ok]) => ({ name, result: ok ? 'passed' : 'failed' })),
    result: passed ? 'passed' : 'failed',
  });
  console.log(`${passed ? 'PASS' : 'FAIL'} ${id} ${res.method} ${res.route} ${res.status}${res.code ? ` ${res.code}` : ''}`);
  if (!passed) {
    for (const [name, ok] of assertions) if (!ok) console.log(`   ✗ ${name}`);
    throw new CaseFailure(`Kasus ${id} gagal.`);
  }
  return res.body ?? res;
}

const check = async (id, method, route, opts, expect, assertOpts) => record(id, await http(method, route, opts), expect, assertOpts);

async function login(key) {
  const creds = actors[key];
  const res = await http('POST', '/auth/mobile-login', { body: { email: creds.email, password: creds.password, device_name: 'M08 synthetic acceptance' } });
  if (res.status !== 200 || typeof res.body?.data?.token !== 'string') throw new CaseFailure(`Login ${key} gagal (${res.status} ${res.code ?? ''}).`);
  remember(res.body.data.token);
  tokens.set(key, res.body.data.token);
  return res.body.data;
}
const as = (key, extra = {}) => ({ token: tokens.get(key), ...extra });

async function file(kind) {
  const [name, type] = MEDIA[kind];
  return new Blob([await fs.readFile(path.join(args.media, name))], { type });
}

async function complaintForm({ judul, anon, photos = [] }) {
  const form = new FormData();
  form.set('kategori_id', categoryId);
  form.set('judul', judul);
  form.set('deskripsi', 'Deskripsi sintetis uji M08 staging; bukan laporan warga.');
  form.set('sembunyikan_identitas', anon ? '1' : '0');
  for (const kind of photos) form.append('foto[]', await file(kind), MEDIA[kind][0]);
  return form;
}

/** Ada nilai identitas pelapor (ID akun, email, nama) di body warga lain? */
function leaks(text, who) {
  return [who.id, who.email, who.nama].filter(Boolean).some(v => text.includes(v));
}

let categoryId;
const run = crypto.randomBytes(3).toString('hex').toUpperCase();
const ids = {};

async function main() {
  try {
    args = parseArgs(process.argv.slice(2));
  } catch (e) {
    console.error(e.message);
    return 3;
  }
  const admin = JSON.parse(await fs.readFile(args.adminFixture, 'utf8'));
  const m07 = JSON.parse(await fs.readFile(args.actors, 'utf8'));
  if (admin.origin !== ORIGIN || m07.origin !== ORIGIN) { console.error('Fixture bukan untuk staging.'); return 3; }
  actors.admin = admin.accounts.admin;
  actors.a = m07.actors[m07.ids.primaryWinner];
  actors.b = m07.actors[m07.ids.primaryLoser];
  actors.p = m07.actors.a2;
  for (const c of Object.values(actors)) remember(c.email, c.password);
  for (const kind of Object.keys(MEDIA)) await fs.access(path.join(args.media, MEDIA[kind][0]));
  console.log(`Target ${ORIGIN}${API}; SHA ${args.sha}; run Sintetis M08 ${run}; fixture ada (nilai tidak ditampilkan).`);
  if (!args.execute) { console.log('Mode rencana: tambahkan --execute setelah SHA deployment diverifikasi.'); return 0; }

  let exit = 0;
  try {
    await check('C00-api-info', 'GET', '', {}, 200, { checks: [['api_version v1', b => b.data.api_version === 'v1']] });
    for (const k of ['admin', 'a', 'b', 'p']) ids[k] = (await login(k)).akun;
    record('C01-actors', { method: 'POST', route: '/auth/mobile-login', status: 200, body: { data: {}, meta: { request_id: 'x' } } }, 200, {
      checks: [['admin pengurus, a/b aktif, p menunggu', () => ids.admin.peran === 'pengurus' && ids.a.status === 'aktif' && ids.b.status === 'aktif' && ids.p.status === 'menunggu']],
    });
    for (const k of ['a', 'b']) remember(ids[k].id, ids[k].nama);

    // Akun menunggu ditolak sebelum handler.
    await check('C02-pending-feed-denied', 'GET', '/complaints', as('p'), 403);
    await check('C03-pending-create-denied', 'POST', '/complaints', as('p', { body: { judul: 'x' } }), 403);

    const cats = await check('C04-categories', 'GET', '/complaint-categories', as('a'), 200, { checks: [['kategori aktif', b => b.data.length > 0]] });
    categoryId = cats.data[0].id;

    // Warga A membuat pengaduan anonim dengan dua foto (JPEG ber-EXIF sintetis + PNG).
    const created = await check('C05-create-anonymous-with-photos', 'POST', '/complaints', as('a', { body: await complaintForm({ judul: `Saluran tersumbat Sintetis M08 ${run}`, anon: true, photos: ['exif', 'png'] }) }), 201, {
      checks: [
        ['status diajukan versi 1', b => b.data.status === 'diajukan' && b.data.versi === 1],
        ['2 foto diproses/siap, url hanya bila siap', b => b.data.foto.length === 2 && b.data.foto.every(f => ['diproses', 'siap'].includes(f.status) && (f.status === 'siap') === (f.url !== null))],
      ],
    });
    ids.complaint = created.data.id;
    const initialStates = created.data.foto.map(f => f.status).join(',');

    // Worker-media mencapai siap.
    let detail;
    const started = Date.now();
    for (;;) {
      detail = (await http('GET', '/complaints/{id}', as('a', { params: { id: ids.complaint } }))).body.data;
      if (detail.foto.every(f => f.status !== 'diproses') || Date.now() - started > 180_000) break;
      await sleep(3000);
    }
    const readyMs = Date.now() - started;
    record('C06-worker-media-ready', { method: 'GET', route: '/complaints/{id}', status: 200, body: { data: detail, meta: { request_id: 'x' } } }, 200, {
      checks: [['semua foto siap ≤180 dtk', b => b.data.foto.every(f => f.status === 'siap')], ['tepi terpanjang ≤1600', b => b.data.foto.every(f => Math.max(f.lebar, f.tinggi) <= 1600)]],
    });

    // Warga lain: tanpa identitas, foto dapat diunduh, metadata hilang.
    const other = await http('GET', '/complaints/{id}', as('b', { params: { id: ids.complaint } }));
    record('C07-other-resident-detail-anonymous', other, 200, { checks: [['pelapor null', b => b.data.pelapor === null], ['tanpa ID/email/nama pelapor', () => !leaks(other.raw, ids.a)], ['2 foto siap', b => b.data.foto.length === 2]] });
    const photoUrl = other.body.data.foto[0].url;
    const m = /^\/api\/v1(\/complaints\/[0-9a-f-]{36}\/photos\/([0-9a-f-]{36}))$/.exec(photoUrl);
    if (!m) throw new CaseFailure('URL foto tidak berbentuk path API ber-policy.');
    ids.photo = m[2];
    const dl = await http('GET', '/complaints/{id}/photos/{photo}', as('b', { params: { id: ids.complaint, photo: ids.photo }, binary: true }));
    record('C08-photo-download-stripped', dl, 200, {
      checks: [
        ['image/jpeg + nosniff', r => r.headers['content-type'] === 'image/jpeg' && r.headers['x-content-type-options'] === 'nosniff'],
        ['private cache', r => r.headers['cache-control'].includes('private')],
        ['marker EXIF/GPS sintetis hilang', r => !r.bytes.includes(EXIF_MARKER) && !r.bytes.includes('Exif')],
        ['JPEG valid', r => r.bytes[0] === 0xff && r.bytes[1] === 0xd8],
        ['nama berkas tanpa identitas', r => !leaks(r.headers['content-disposition'], ids.a) && !/sintetis-exif/.test(r.headers['content-disposition'])],
        ['URL tanpa identitas', () => !leaks(photoUrl, ids.a)],
      ],
    });
    const feed = await http('GET', '/complaints', as('b', { query: { per_page: 50 } }));
    record('C09-feed-anonymous', feed, 200, { checks: [['laporan ada tanpa identitas', b => b.data.some(c => c.id === ids.complaint && c.pelapor === null)], ['tanpa ID/email/nama pelapor', () => !leaks(feed.raw, ids.a)]] });

    // Validasi upload: tidak ada laporan baru dari percobaan yang ditolak.
    const before = (await http('GET', '/complaints', as('a', { query: { milik_saya: 1, per_page: 100 } }))).body.meta.total;
    const rejects = [['C10-spoof-php-415', 'spoof', 415, 'UNSUPPORTED_MEDIA_TYPE'], ['C11-svg-415', 'svg', 415, 'UNSUPPORTED_MEDIA_TYPE'], ['C12-gif-415', 'gif', 415, 'UNSUPPORTED_MEDIA_TYPE'],
      ['C13-over-50mp-413', 'dims', 413, 'PAYLOAD_TOO_LARGE'], ['C14-over-12mb-rejected', 'big', [413, 422], ['PAYLOAD_TOO_LARGE', 'VALIDATION_FAILED']]];
    for (const [id, kind, status, code] of rejects) {
      await check(id, 'POST', '/complaints', as('a', { body: await complaintForm({ judul: `Tolak ${kind} Sintetis M08 ${run}`, anon: false, photos: [kind] }) }), status, { code });
    }
    await check('C15-no-partial-writes', 'GET', '/complaints', as('a', { query: { milik_saya: 1, per_page: 100 } }), 200, { checks: [['jumlah laporan A tetap', b => b.meta.total === before]] });

    // Kepemilikan dan referensi parent asing.
    const own = await check('C16-other-creates-own', 'POST', '/complaints', as('b', { body: await complaintForm({ judul: `Laporan warga lain Sintetis M08 ${run}`, anon: false, photos: ['webp'] }) }), 201);
    ids.otherComplaint = own.data.id;
    await check('C17-other-cannot-edit', 'PATCH', '/complaints/{id}', as('b', { params: { id: ids.complaint }, body: { versi: 1, judul: 'Diambil alih' } }), 403, { code: 'COMPLAINT_LOCKED' });
    await check('C18-foreign-parent-photo-404', 'GET', '/complaints/{id}/photos/{photo}', as('b', { params: { id: ids.otherComplaint, photo: ids.photo } }), 404);

    // Edit pemilik + versi basi.
    await check('C19-owner-edit', 'PATCH', '/complaints/{id}', as('a', { params: { id: ids.complaint }, body: { versi: 1, judul: `Saluran tersumbat diperbaiki Sintetis M08 ${run}` } }), 200, { checks: [['versi 2', b => b.data.versi === 2]] });
    await check('C20-owner-edit-stale-409', 'PATCH', '/complaints/{id}', as('a', { params: { id: ids.complaint }, body: { versi: 1, judul: 'Basi' } }), 409, { code: 'VERSION_CONFLICT' });

    // Pengurus: identitas terlihat, transisi, buka ulang beralasan.
    await check('C21-admin-sees-reporter', 'GET', '/complaints/{id}', as('admin', { params: { id: ids.complaint } }), 200, { checks: [['pelapor = A', b => b.data.pelapor?.id === ids.a.id]] });
    const status = (s, v, catatan) => as('admin', { params: { id: ids.complaint }, body: { status: s, expected_version: v, ...(catatan ? { catatan } : {}) } });
    await check('C22-invalid-transition-422', 'POST', '/admin/complaints/{id}/status', status('selesai', 2), 422, { code: 'INVALID_STATUS_TRANSITION' });
    await check('C23-to-processing', 'POST', '/admin/complaints/{id}/status', status('diproses', 2, 'Dicek pengurus (sintetis)'), 200, { checks: [['diproses v3', b => b.data.status === 'diproses' && b.data.versi === 3]] });
    await check('C24-status-stale-409', 'POST', '/admin/complaints/{id}/status', status('selesai', 2), 409, { code: 'VERSION_CONFLICT' });
    await check('C25-owner-edit-locked', 'PATCH', '/complaints/{id}', as('a', { params: { id: ids.complaint }, body: { versi: 3, judul: 'Terlambat' } }), 403, { code: 'COMPLAINT_LOCKED' });
    await check('C26-to-resolved', 'POST', '/admin/complaints/{id}/status', status('selesai', 3), 200, { checks: [['selesai v4', b => b.data.status === 'selesai']] });
    await check('C27-reopen-needs-reason', 'POST', '/admin/complaints/{id}/status', status('diproses', 4), 422, { code: 'REOPEN_REASON_REQUIRED' });
    await check('C28-reopen-with-reason', 'POST', '/admin/complaints/{id}/status', status('diproses', 4, 'Dibuka ulang: genangan muncul lagi (sintetis)'), 200, { checks: [['diproses v5', b => b.data.status === 'diproses' && b.data.versi === 5]] });

    const hist = await http('GET', '/complaints/{id}/history', as('b', { params: { id: ids.complaint } }));
    record('C29-history-safe-for-others', hist, 200, {
      checks: [
        ['4 entri berurutan', b => b.data.map(h => h.status_ke).join() === 'diajukan,diproses,selesai,diproses'],
        ['pelapor "Warga", tindakan "Pengurus RT"', b => b.data[0].aktor.nama === 'Warga' && b.data.slice(1).every(h => h.aktor.nama === 'Pengurus RT')],
        ['catatan buka ulang ada', b => b.data.at(-1).catatan?.startsWith('Dibuka ulang')],
        ['tanpa identitas pelapor', () => !leaks(hist.raw, ids.a)],
      ],
    });

    // Inbox pelapor dari outbox (dispatcher tiap menit).
    let inbox = [];
    const t0 = Date.now();
    for (;;) {
      inbox = (await http('GET', '/notifications', as('a', { query: { per_page: 100 } }))).body.data.filter(n => n.referensi?.id === ids.complaint);
      if (inbox.length >= 3 || Date.now() - t0 > 240_000) break;
      await sleep(10_000);
    }
    const inboxMs = Date.now() - t0;
    record('C30-owner-inbox', { method: 'GET', route: '/notifications', status: 200, body: { data: inbox, meta: { request_id: 'x' } } }, 200, {
      checks: [
        ['3 notifikasi status (tanpa duplikat)', b => b.data.length === 3],
        ['jenis pengaduan', b => b.data.every(n => n.jenis === 'pengaduan')],
        ['tiap transisi disebut (Diproses, Selesai, Diproses)', b => b.data.map(n => /berstatus (\w+)\./.exec(n.isi)?.[1]).sort().join() === 'Diproses,Diproses,Selesai'],
        ['tanpa identitas', b => !leaks(JSON.stringify(b.data), ids.a)],
      ],
    });
    const otherInbox = await http('GET', '/notifications', as('b', { query: { per_page: 100 } }));
    record('C31-no-inbox-for-others', otherInbox, 200, { checks: [['warga lain tidak menerima status laporan A', b => !b.data.some(n => n.referensi?.id === ids.complaint)]] });

    // Arsip mempertahankan histori.
    const archive = id => as('admin', { params: { id }, body: { alasan: 'Arsip uji sintetis M08' } });
    await check('C32-archive', 'POST', '/admin/complaints/{id}/archive', archive(ids.otherComplaint), 204);
    await check('C33-archive-again-409', 'POST', '/admin/complaints/{id}/archive', archive(ids.otherComplaint), 409, { code: 'ALREADY_ARCHIVED' });
    await check('C34-archived-status-409', 'POST', '/admin/complaints/{id}/status', as('admin', { params: { id: ids.otherComplaint }, body: { status: 'diproses', expected_version: 1 } }), 409, { code: 'ALREADY_ARCHIVED' });
    await check('C35-archived-hidden-from-owner', 'GET', '/complaints/{id}', as('b', { params: { id: ids.otherComplaint } }), 404);
    await check('C36-archived-history-kept', 'GET', '/complaints/{id}/history', as('admin', { params: { id: ids.otherComplaint } }), 200, { checks: [['riwayat diajukan tetap', b => b.data.length === 1]] });
    await check('C37-admin-archive-filter', 'GET', '/complaints', as('admin', { query: { arsip: 1, per_page: 100 } }), 200, { checks: [['arsip tampil untuk pengurus', b => b.data.some(c => c.id === ids.otherComplaint && c.archived_at)]] });

    // Akun nonaktif: token lama ditolak, lalu diaktifkan kembali.
    await check('C38-deactivate-other', 'PATCH', '/admin/accounts/{id}', as('admin', { params: { id: ids.b.id }, body: { status: 'nonaktif', alasan: 'Uji sintetis M08 akun nonaktif' } }), 200);
    await check('C39-inactive-token-denied', 'GET', '/complaints', as('b'), [401, 403]);
    await check('C40-reactivate-other', 'PATCH', '/admin/accounts/{id}', as('admin', { params: { id: ids.b.id }, body: { status: 'aktif', alasan: 'Uji sintetis M08 selesai' } }), 200);
    tokens.delete('b');
    await login('b');
    await check('C41-reactivated-reads', 'GET', '/complaints', as('b'), 200);

    console.log(`Info: foto awal ${initialStates}; siap ${Math.round(readyMs / 1000)} dtk; inbox ${Math.round(inboxMs / 1000)} dtk.`);
    cases.push({ case: 'timing', initialPhotoStates: initialStates, readySeconds: Math.round(readyMs / 1000), inboxSeconds: Math.round(inboxMs / 1000) });
  } catch (e) {
    exit = 1;
    console.error(`BERHENTI: ${e instanceof CaseFailure ? e.message : `${e.name}: ${e.message}`}`);
  } finally {
    for (const k of tokens.keys()) await http('POST', '/auth/logout', { token: tokens.get(k) }).catch(() => {});
  }

  const real = cases.filter(c => c.result);
  const summary = {
    schema: 'rt05-m08-live-summary/v1',
    scope: 'synthetic staging only',
    origin: ORIGIN,
    sourceSha: args.sha,
    run: `Sintetis M08 ${run}`,
    finishedAt: new Date().toISOString(),
    status: exit === 0 && real.every(c => c.result === 'passed') ? 'passed' : 'failed',
    cases: real.length,
    assertions: real.reduce((n, c) => n + c.assertions.length, 0),
    details: cases,
  };
  const text = JSON.stringify(summary, null, 2);
  const scan = text.replaceAll(args.sha, '');
  if ([...secrets].some(s => scan.includes(s)) || /@|Bearer /.test(text)) {
    console.error('Ringkasan publik memuat nilai rahasia; tidak ditulis.');
    return 1;
  }
  await fs.mkdir(args.out, { recursive: true });
  await fs.writeFile(path.join(args.out, SUMMARY_FILE), text);
  if (ids.complaint) {
    await fs.writeFile(path.join(args.out, ANDROID_FILE), JSON.stringify({ run, complaintId: ids.complaint, archivedId: ids.otherComplaint }, null, 2), { mode: 0o600 });
  }
  console.log(`${summary.status.toUpperCase()}: ${summary.cases} kasus, ${summary.assertions} assertion. Ringkasan: ${path.join(args.out, SUMMARY_FILE)}`);
  return exit;
}

process.exitCode = await main();
