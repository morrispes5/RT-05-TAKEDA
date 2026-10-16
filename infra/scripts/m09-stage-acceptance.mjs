#!/usr/bin/env node
/*
 * M09 synthetic LIVE acceptance (aspirasi) — staging only (docs/evidence/m09/REPORT.md).
 *
 * Node 22, standard fetch, tanpa dependency. Hanya memanggil
 * https://takeda-staging.morriz.tech/api/v1/*; host lain dan redirect ditolak.
 * Tanpa --execute script hanya memvalidasi argumen/fixture lalu mencetak rencana (tanpa jaringan).
 *
 *   node infra/scripts/m09-stage-acceptance.mjs --sha <40hex> \
 *     --admin-fixture C:/Users/USER/.rt05/m06-stage-check/fixtures.json \
 *     --actors C:/Users/USER/.rt05/m07-stage-check/m07-state.private.json \
 *     --out C:/Users/USER/.rt05/m09-stage-check [--execute]
 *
 * Aktor sintetis dari fixture privat M06 (admin) dan M07 (a1 utama aktif = pengirim, a3 aktif =
 * warga lain, a2 menunggu). Nilai fixture tidak pernah dicetak/ditulis ulang. Ringkasan publik:
 * {case, routeTemplate, httpStatus, code, assertions} tanpa token/kata sandi/email/ID akun.
 * Tidak ada hapus data, reset, SMTP, atau Neon langsung. Satu-satunya perubahan akun: a3
 * dinonaktifkan sementara lalu diaktifkan kembali (uji akun nonaktif).
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
const SUMMARY_FILE = 'm09-live-summary.json';
const ANDROID_FILE = 'm09-android-fixtures.private.json';

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
    else if (k === '--out') a.out = v();
    else if (k === '--execute') a.execute = true;
    else throw new UsageError(`Argumen tidak dikenal: ${k}`);
  }
  if (!/^[0-9a-f]{40}$/.test(a.sha ?? '')) throw new UsageError('--sha wajib 40 hex (SHA deployment yang diverifikasi lewat SSH).');
  for (const k of ['adminFixture', 'actors', 'out']) {
    if (!a[k] || !path.isAbsolute(a[k])) throw new UsageError(`--${k.replace(/[A-Z]/g, c => `-${c.toLowerCase()}`)} wajib path absolut.`);
  }
  const canon = p => { let r = path.resolve(p); try { r = realpathSync.native(r); } catch { /* belum ada */ } return process.platform === 'win32' ? r.toLowerCase() : r; };
  const repo = canon(REPO);
  for (const k of ['adminFixture', 'actors', 'out']) {
    const rel = path.relative(repo, canon(a[k]));
    if (rel === '' || (!rel.startsWith('..') && !path.isAbsolute(rel))) throw new UsageError('Fixture dan output privat harus di luar repository Git.');
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

/** Satu request; 429 diulang sesuai Retry-After (≤ 5 menit). */
async function http(method, route, { params, query, token, body } = {}) {
  const url = buildUrl(route, params, query);
  for (let attempt = 1; ; attempt++) {
    const response = await fetch(url, {
      method,
      redirect: 'manual',
      signal: AbortSignal.timeout(120_000),
      headers: {
        Accept: 'application/json',
        'User-Agent': 'rt05-m09-synthetic-acceptance',
        ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
    if (response.status >= 300 && response.status < 400) throw new Error(`${method} ${route}: redirect ditolak.`);
    if (response.status === 429 && attempt <= 3) {
      const wait = Number(response.headers.get('retry-after')) || 60;
      if (wait > 300) throw new Error(`${method} ${route}: Retry-After ${wait}s terlalu lama.`);
      console.log(`  rate limit ${route}: tunggu ${wait + 1}s`);
      await sleep((wait + 1) * 1000);
      continue;
    }
    const text = await response.text();
    let json = null;
    if (text) { try { json = JSON.parse(text); } catch { json = null; } }
    return { method, route, status: response.status, body: json, code: json?.error?.code ?? null, raw: text };
  }
}

function record(id, res, expect, { code, checks = [] } = {}) {
  const expected = Array.isArray(expect) ? expect : [expect];
  const assertions = [[`status ${expected.join('|')}`, expected.includes(res.status)]];
  if (res.status >= 400) assertions.push(['error.code + request_id', typeof res.body?.error?.code === 'string' && typeof res.body?.error?.request_id === 'string']);
  else if (res.status !== 204) assertions.push(['data + meta.request_id', res.body?.data !== undefined && typeof res.body?.meta?.request_id === 'string']);
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
  const res = await http('POST', '/auth/mobile-login', { body: { email: creds.email, password: creds.password, device_name: 'M09 synthetic acceptance' } });
  if (res.status !== 200 || typeof res.body?.data?.token !== 'string') throw new CaseFailure(`Login ${key} gagal (${res.status} ${res.code ?? ''}).`);
  remember(res.body.data.token);
  tokens.set(key, res.body.data.token);
  return res.body.data;
}
const as = (key, extra = {}) => ({ token: tokens.get(key), ...extra });

/** Ada nilai identitas pengirim (ID akun, email, nama) di body warga lain? */
function leaks(text, who) {
  return [who.id, who.email, who.nama].filter(Boolean).some(v => text.includes(v));
}

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
  console.log(`Target ${ORIGIN}${API}; SHA ${args.sha}; run Sintetis M09 ${run}; fixture ada (nilai tidak ditampilkan).`);
  if (!args.execute) { console.log('Mode rencana: tambahkan --execute setelah SHA deployment diverifikasi.'); return 0; }

  let exit = 0;
  try {
    await check('A00-api-info', 'GET', '', {}, 200, { checks: [['api_version v1', b => b.data.api_version === 'v1']] });
    for (const k of ['admin', 'a', 'b', 'p']) ids[k] = (await login(k)).akun;
    record('A01-actors', { method: 'POST', route: '/auth/mobile-login', status: 200, body: { data: {}, meta: { request_id: 'x' } } }, 200, {
      checks: [['admin pengurus, a/b aktif, p menunggu', () => ids.admin.peran === 'pengurus' && ids.a.status === 'aktif' && ids.b.status === 'aktif' && ids.p.status === 'menunggu']],
    });
    for (const k of ['a', 'b']) remember(ids[k].id, ids[k].nama);

    // Akun menunggu ditolak sebelum handler.
    await check('A02-pending-feed-denied', 'GET', '/aspirations', as('p'), 403);
    await check('A03-pending-create-denied', 'POST', '/aspirations', as('p', { body: { judul: 'x', isi: 'y' } }), 403);

    // Validasi: tidak ada aspirasi parsial.
    const countOf = async key => (await http('GET', '/aspirations', as(key, { query: { milik_saya: 1, per_page: 1 } }))).body.meta.total;
    const before = await countOf('a');
    await check('A04-invalid-422', 'POST', '/aspirations', as('a', { body: { judul: '', isi: 'tanpa judul' } }), 422);
    await check('A05-title-too-long-422', 'POST', '/aspirations', as('a', { body: { judul: 'x'.repeat(151), isi: 'y' } }), 422);
    await check('A06-no-partial-writes', 'GET', '/aspirations', as('a', { query: { milik_saya: 1, per_page: 1 } }), 200, { checks: [['jumlah aspirasi A tetap', b => b.meta.total === before]] });

    // Kirim aspirasi anonim: 201 hanya sesudah semua tersimpan.
    const created = await check('A07-create-anonymous', 'POST', '/aspirations', as('a', { body: { judul: `Usul taman bermain Sintetis M09 ${run}`, isi: 'Usulan sintetis M09 staging; bukan aspirasi warga.', sembunyikan_identitas: true } }), 201, {
      checks: [
        ['diajukan versi 1, milik saya, pengirim = A', b => b.data.status === 'diajukan' && b.data.versi === 1 && b.data.milik_saya === true && b.data.pengirim?.id === ids.a.id],
        ['tanpa kategori/foto, archived_at null', b => !('kategori' in b.data) && !('foto' in b.data) && 'archived_at' in b.data && b.data.archived_at === null],
      ],
    });
    ids.aspiration = created.data.id;
    await check('A08-count-increased-by-one', 'GET', '/aspirations', as('a', { query: { milik_saya: 1, per_page: 1 } }), 200, { checks: [['jumlah +1', b => b.meta.total === before + 1]] });

    // Warga lain: tanpa identitas di detail, feed, riwayat.
    const other = await http('GET', '/aspirations/{id}', as('b', { params: { id: ids.aspiration } }));
    record('A09-other-resident-detail-anonymous', other, 200, { checks: [['pengirim null, bukan milik saya', b => b.data.pengirim === null && b.data.milik_saya === false], ['tanpa ID/email/nama pengirim', () => !leaks(other.raw, ids.a)], ['archived_at null untuk warga', b => b.data.archived_at === null]] });
    const feed = await http('GET', '/aspirations', as('b', { query: { per_page: 100 } }));
    record('A10-feed-anonymous', feed, 200, { checks: [['aspirasi ada tanpa pengirim', b => b.data.some(c => c.id === ids.aspiration && c.pengirim === null)], ['tanpa ID/email/nama pengirim', () => !leaks(feed.raw, ids.a)]] });

    // Kepemilikan dan edit pemilik + versi basi.
    await check('A11-other-cannot-edit', 'PATCH', '/aspirations/{id}', as('b', { params: { id: ids.aspiration }, body: { versi: 1, judul: 'Diambil alih' } }), 403, { code: 'ASPIRATION_LOCKED' });
    await check('A12-owner-edit', 'PATCH', '/aspirations/{id}', as('a', { params: { id: ids.aspiration }, body: { versi: 1, judul: `Usul taman bermain diperbaiki Sintetis M09 ${run}` } }), 200, { checks: [['versi 2', b => b.data.versi === 2]] });
    await check('A13-owner-edit-stale-409', 'PATCH', '/aspirations/{id}', as('a', { params: { id: ids.aspiration }, body: { versi: 1, judul: 'Basi' } }), 409, { code: 'VERSION_CONFLICT' });

    // Pengurus: identitas terlihat, hak, transisi, buka ulang beralasan.
    await check('A14-admin-sees-sender', 'GET', '/aspirations/{id}', as('admin', { params: { id: ids.aspiration } }), 200, { checks: [['pengirim = A', b => b.data.pengirim?.id === ids.a.id]] });
    await check('A15-resident-cannot-change-status', 'POST', '/admin/aspirations/{id}/status', as('a', { params: { id: ids.aspiration }, body: { status: 'ditinjau', expected_version: 2 } }), 403);
    const status = (s, v, catatan) => as('admin', { params: { id: ids.aspiration }, body: { status: s, expected_version: v, ...(catatan ? { catatan } : {}) } });
    await check('A16-invalid-transition-422', 'POST', '/admin/aspirations/{id}/status', status('selesai', 2), 422, { code: 'INVALID_STATUS_TRANSITION' });
    await check('A17-complaint-status-name-422', 'POST', '/admin/aspirations/{id}/status', status('diproses', 2), 422);
    await check('A18-to-reviewed', 'POST', '/admin/aspirations/{id}/status', status('ditinjau', 2, 'Dibahas rapat RT (sintetis)'), 200, { checks: [['ditinjau v3', b => b.data.status === 'ditinjau' && b.data.versi === 3]] });
    await check('A19-status-stale-409', 'POST', '/admin/aspirations/{id}/status', status('selesai', 2), 409, { code: 'VERSION_CONFLICT' });
    await check('A20-owner-edit-locked', 'PATCH', '/aspirations/{id}', as('a', { params: { id: ids.aspiration }, body: { versi: 3, judul: 'Terlambat' } }), 403, { code: 'ASPIRATION_LOCKED' });
    await check('A21-to-completed', 'POST', '/admin/aspirations/{id}/status', status('selesai', 3), 200, { checks: [['selesai v4', b => b.data.status === 'selesai']] });
    await check('A22-reopen-needs-reason', 'POST', '/admin/aspirations/{id}/status', status('ditinjau', 4), 422, { code: 'REOPEN_REASON_REQUIRED' });
    await check('A23-reopen-with-reason', 'POST', '/admin/aspirations/{id}/status', status('ditinjau', 4, 'Dibuka ulang: usulan lanjutan (sintetis)'), 200, { checks: [['ditinjau v5', b => b.data.status === 'ditinjau' && b.data.versi === 5]] });

    const hist = await http('GET', '/aspirations/{id}/history', as('b', { params: { id: ids.aspiration } }));
    record('A24-history-safe-for-others', hist, 200, {
      checks: [
        ['4 entri berurutan', b => b.data.map(h => h.status_ke).join() === 'diajukan,ditinjau,selesai,ditinjau'],
        ['pengirim "Warga", tindakan "Pengurus RT"', b => b.data[0].aktor.nama === 'Warga' && b.data.slice(1).every(h => h.aktor.nama === 'Pengurus RT')],
        ['catatan buka ulang ada', b => b.data.at(-1).catatan?.startsWith('Dibuka ulang')],
        ['tanpa identitas pengirim', () => !leaks(hist.raw, ids.a)],
      ],
    });

    // Inbox pengirim dari outbox (dispatcher tiap menit).
    let inbox = [];
    const t0 = Date.now();
    for (;;) {
      inbox = (await http('GET', '/notifications', as('a', { query: { per_page: 100 } }))).body.data.filter(n => n.referensi?.id === ids.aspiration);
      if (inbox.length >= 3 || Date.now() - t0 > 240_000) break;
      await sleep(10_000);
    }
    const inboxMs = Date.now() - t0;
    record('A25-owner-inbox', { method: 'GET', route: '/notifications', status: 200, body: { data: inbox, meta: { request_id: 'x' } } }, 200, {
      checks: [
        ['3 notifikasi status (tanpa duplikat)', b => b.data.length === 3],
        ['jenis aspirasi', b => b.data.every(n => n.jenis === 'aspirasi')],
        ['tiap transisi disebut (Ditinjau, Selesai, Ditinjau)', b => b.data.map(n => /berstatus (\w+)\./.exec(n.isi)?.[1]).sort().join() === 'Ditinjau,Ditinjau,Selesai'],
        ['tanpa identitas', b => !leaks(JSON.stringify(b.data), ids.a)],
      ],
    });
    const otherInbox = await http('GET', '/notifications', as('b', { query: { per_page: 100 } }));
    record('A26-no-inbox-for-others', otherInbox, 200, { checks: [['warga lain tidak menerima notifikasi aspirasi A', b => !b.data.some(n => n.referensi?.id === ids.aspiration)]] });
    const adminInbox = await http('GET', '/notifications', as('admin', { query: { per_page: 100 } }));
    record('A27-admin-notified-once-without-identity', adminInbox, 200, {
      checks: [
        ['satu "Aspirasi baru" untuk aspirasi ini', b => b.data.filter(n => n.referensi?.id === ids.aspiration && n.judul === 'Aspirasi baru').length === 1],
        ['teks tanpa identitas pengirim', () => !leaks(JSON.stringify(adminInbox.body.data.filter(n => n.referensi?.id === ids.aspiration)), ids.a)],
      ],
    });

    // Filter dan pagination.
    await check('A28-filter-status', 'GET', '/aspirations', as('a', { query: { status: 'ditinjau', per_page: 100 } }), 200, { checks: [['hanya ditinjau dan memuat aspirasi ini', b => b.data.every(x => x.status === 'ditinjau') && b.data.some(x => x.id === ids.aspiration)]] });
    await check('A29-filter-invalid-422', 'GET', '/aspirations', as('a', { query: { status: 'diproses' } }), 422);
    await check('A30-pagination-meta', 'GET', '/aspirations', as('a', { query: { per_page: 1, page: 1 } }), 200, { checks: [['per_page 1, 1 item, total konsisten', b => b.meta.per_page === 1 && b.data.length === 1 && b.meta.last_page === b.meta.total]] });
    await check('A31-per-page-capped', 'GET', '/aspirations', as('a', { query: { per_page: 500 } }), 200, { checks: [['per_page dibatasi 100', b => b.meta.per_page === 100]] });
    await check('A32-mine-filter', 'GET', '/aspirations', as('b', { query: { milik_saya: 1, per_page: 100 } }), 200, { checks: [['aspirasi A tidak termasuk milik B', b => !b.data.some(x => x.id === ids.aspiration)]] });

    // Domain terpisah.
    await check('A33-not-a-complaint', 'GET', '/complaints/{id}', as('a', { params: { id: ids.aspiration } }), 404);

    // Arsip mempertahankan histori (aspirasi milik B, tidak anonim).
    const own = await check('A34-other-creates-own', 'POST', '/aspirations', as('b', { body: { judul: `Usul warga lain Sintetis M09 ${run}`, isi: 'Usulan sintetis kedua.', sembunyikan_identitas: false } }), 201);
    ids.archived = own.data.id;
    await check('A35-non-anonymous-visible', 'GET', '/aspirations/{id}', as('a', { params: { id: ids.archived } }), 200, { checks: [['pengirim tampil = B', b => b.data.pengirim?.id === ids.b.id]] });
    const archive = alasan => as('admin', { params: { id: ids.archived }, body: alasan === undefined ? {} : { alasan } });
    await check('A36-archive-needs-reason', 'POST', '/admin/aspirations/{id}/archive', archive(''), 422);
    await check('A37-archive', 'POST', '/admin/aspirations/{id}/archive', archive('Arsip uji sintetis M09'), 204);
    await check('A38-archive-again-409', 'POST', '/admin/aspirations/{id}/archive', archive('lagi'), 409, { code: 'ALREADY_ARCHIVED' });
    await check('A39-archived-status-409', 'POST', '/admin/aspirations/{id}/status', as('admin', { params: { id: ids.archived }, body: { status: 'ditinjau', expected_version: 1 } }), 409, { code: 'ALREADY_ARCHIVED' });
    await check('A40-archived-hidden-from-owner', 'GET', '/aspirations/{id}', as('b', { params: { id: ids.archived } }), 404);
    await check('A41-archived-history-kept', 'GET', '/aspirations/{id}/history', as('admin', { params: { id: ids.archived } }), 200, { checks: [['riwayat diajukan tetap', b => b.data.length === 1]] });
    await check('A42-admin-archive-filter', 'GET', '/aspirations', as('admin', { query: { arsip: 1, per_page: 100 } }), 200, { checks: [['arsip tampil dengan archived_at untuk pengurus', b => b.data.some(x => x.id === ids.archived && x.archived_at)]] });
    await check('A43-archived-hidden-in-feed', 'GET', '/aspirations', as('a', { query: { per_page: 100 } }), 200, { checks: [['aspirasi arsip tidak tampil bagi warga', b => !b.data.some(x => x.id === ids.archived)]] });

    // Akun nonaktif: token lama ditolak, lalu diaktifkan kembali.
    await check('A44-deactivate-other', 'PATCH', '/admin/accounts/{id}', as('admin', { params: { id: ids.b.id }, body: { status: 'nonaktif', alasan: 'Uji sintetis M09 akun nonaktif' } }), 200);
    await check('A45-inactive-token-denied', 'GET', '/aspirations', as('b'), [401, 403]);
    await check('A46-reactivate-other', 'PATCH', '/admin/accounts/{id}', as('admin', { params: { id: ids.b.id }, body: { status: 'aktif', alasan: 'Uji sintetis M09 selesai' } }), 200);
    tokens.delete('b');
    await login('b');
    await check('A47-reactivated-reads', 'GET', '/aspirations', as('b'), 200);

    console.log(`Info: inbox ${Math.round(inboxMs / 1000)} dtk.`);
    cases.push({ case: 'timing', inboxSeconds: Math.round(inboxMs / 1000) });
  } catch (e) {
    exit = 1;
    console.error(`BERHENTI: ${e instanceof CaseFailure ? e.message : `${e.name}: ${e.message}`}`);
  } finally {
    for (const k of tokens.keys()) await http('POST', '/auth/logout', { token: tokens.get(k) }).catch(() => {});
  }

  const real = cases.filter(c => c.result);
  const summary = {
    schema: 'rt05-m09-live-summary/v1',
    scope: 'synthetic staging only',
    origin: ORIGIN,
    sourceSha: args.sha,
    run: `Sintetis M09 ${run}`,
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
  if (ids.aspiration) {
    await fs.writeFile(path.join(args.out, ANDROID_FILE), JSON.stringify({ run, aspirationId: ids.aspiration, archivedId: ids.archived }, null, 2), { mode: 0o600 });
  }
  console.log(`${summary.status.toUpperCase()}: ${summary.cases} kasus, ${summary.assertions} assertion. Ringkasan: ${path.join(args.out, SUMMARY_FILE)}`);
  return exit;
}

process.exitCode = await main();
