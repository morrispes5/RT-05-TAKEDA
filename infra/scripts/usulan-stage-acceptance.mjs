#!/usr/bin/env node
/*
 * Uji live sintetis usulan kegiatan (docs/USULAN_KEGIATAN.md) — hanya staging.
 *
 * Node 22, fetch/FormData/Blob bawaan, tanpa dependency dan tanpa emulator (ringan RAM).
 * Hanya memanggil https://takeda-staging.morriz.tech/api/v1/*; host lain dan redirect ditolak.
 * Tanpa --execute hanya memvalidasi argumen/fixture lalu mencetak rencana.
 *
 *   node infra/scripts/usulan-stage-acceptance.mjs --sha <40hex> \
 *     --admin-fixture C:/Users/USER/.rt05/m06-stage-check/fixtures.json \
 *     --actors C:/Users/USER/.rt05/m07-stage-check/m07-state.private.json \
 *     --out C:/Users/USER/.rt05/usulan-stage-check [--execute]
 *
 * Aktor sintetis: admin M06, a1 (pengusul), a3 (warga lain), a2 (menunggu). Dokumen dibuat di memori
 * (TXT/PDF sintetis). Agenda hasil persetujuan diarsipkan di akhir agar staging tidak menumpuk agenda uji;
 * usulan dan riwayatnya tetap (data penting tidak dihapus). Ringkasan publik tanpa token/email/ID akun.
 */
import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import { realpathSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ORIGIN = 'https://takeda-staging.morriz.tech';
const API = '/api/v1';
const REPO = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const SUMMARY_FILE = 'usulan-live-summary.json';
const IS_UUID = v => typeof v === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(v);

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
  const canon = p => { let r = path.resolve(p); try { r = realpathSync.native(r); } catch { /* belum ada */ } return process.platform === 'win32' ? r.toLowerCase() : r; };
  for (const k of ['adminFixture', 'actors', 'out']) {
    if (!a[k] || !path.isAbsolute(a[k])) throw new UsageError(`--${k} wajib path absolut.`);
    const rel = path.relative(canon(REPO), canon(a[k]));
    if (rel === '' || (!rel.startsWith('..') && !path.isAbsolute(rel))) throw new UsageError('Fixture dan output privat harus di luar repository Git.');
  }
  return a;
}

let args;
const secrets = new Set();
const tokens = new Map();
const actors = {};
const cases = [];
const ids = {};
const remember = (...vs) => vs.forEach(v => typeof v === 'string' && v.length >= 6 && secrets.add(v));
const sleep = ms => new Promise(r => setTimeout(r, ms));
const run = crypto.randomBytes(3).toString('hex').toUpperCase();

function buildUrl(route, params = {}, query) {
  const filled = route.replace(/\{(\w+)\}/g, (_, k) => {
    if (!IS_UUID(params[k])) throw new Error(`Parameter ${k} bukan UUID`);
    return params[k];
  });
  const url = new URL(filled.startsWith(API) ? filled : API + filled, ORIGIN);
  for (const [k, v] of Object.entries(query ?? {})) url.searchParams.set(k, String(v));
  if (url.origin !== ORIGIN || url.protocol !== 'https:' || !url.pathname.startsWith(`${API}`)) throw new Error('URL di luar staging API ditolak.');
  return url;
}

async function http(method, route, { params, query, token, body, raw = false } = {}) {
  const url = buildUrl(route, params, query);
  for (let attempt = 1; ; attempt++) {
    const multipart = body instanceof FormData;
    const res = await fetch(url, {
      method,
      redirect: 'manual',
      signal: AbortSignal.timeout(120_000),
      headers: {
        Accept: 'application/json',
        'User-Agent': 'rt05-usulan-synthetic-acceptance',
        ...(body !== undefined && !multipart ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body === undefined ? undefined : multipart ? body : JSON.stringify(body),
    });
    if (res.status >= 300 && res.status < 400) throw new Error(`${method} ${route}: redirect ditolak.`);
    if (res.status === 429 && attempt <= 3) {
      const wait = Number(res.headers.get('retry-after')) || 60;
      if (wait > 300) throw new Error('Retry-After terlalu lama.');
      console.log(`  rate limit ${route}: tunggu ${wait + 1}s`);
      await sleep((wait + 1) * 1000);
      continue;
    }
    const headers = Object.fromEntries(['content-type', 'content-disposition', 'x-content-type-options', 'cache-control'].map(h => [h, res.headers.get(h) ?? '']));
    const text = raw && res.ok ? '' : await res.text();
    let json = null;
    if (text) { try { json = JSON.parse(text); } catch { json = null; } }
    return { method, route, status: res.status, body: json, code: json?.error?.code ?? null, headers, raw: text };
  }
}

function record(id, res, expect, { code, checks = [] } = {}) {
  const expected = [expect].flat();
  const assertions = [[`status ${expected.join('|')}`, expected.includes(res.status)]];
  if (code) assertions.push([`code ${code}`, res.code === code]);
  for (const [name, fn] of checks) {
    let ok = false;
    try { ok = expected.includes(res.status) && Boolean(fn(res.body ?? res)); } catch { ok = false; }
    assertions.push([name, ok]);
  }
  const passed = assertions.every(([, ok]) => ok);
  // Tautan bertanda tangan tidak boleh tercatat utuh (signature adalah kredensial sementara).
  res.route = res.route.replace(/documents\/[0-9a-f-]{36}\/download\?.*$/i, 'documents/{doc}/download?expires=…&signature=…');
  cases.push({ case: id, routeTemplate: `${res.method} ${res.route}`, httpStatus: res.status, code: res.status >= 400 ? res.code : null,
    assertions: assertions.map(([name, ok]) => ({ name, result: ok ? 'passed' : 'failed' })), result: passed ? 'passed' : 'failed' });
  console.log(`${passed ? 'PASS' : 'FAIL'} ${id} ${res.method} ${res.route} ${res.status}${res.code ? ` ${res.code}` : ''}`);
  if (!passed) {
    for (const [name, ok] of assertions) if (!ok) console.log(`   ✗ ${name}`);
    throw new CaseFailure(`Kasus ${id} gagal.`);
  }
  return res.body ?? res;
}
const check = async (id, method, route, opts, expect, assertOpts) => record(id, await http(method, route, opts), expect, assertOpts);

async function login(key) {
  const c = actors[key];
  const res = await http('POST', '/auth/mobile-login', { body: { email: c.email, password: c.password, device_name: 'Usulan synthetic acceptance' } });
  if (res.status !== 200) throw new CaseFailure(`Login ${key} gagal (${res.status}).`);
  remember(res.body.data.token);
  tokens.set(key, res.body.data.token);
  return res.body.data.akun;
}
const as = (key, extra = {}) => ({ token: tokens.get(key), ...extra });

/** Waktu dinding Jakarta → ISO UTC. */
function jakarta(daysAhead, hour) {
  const d = new Date(Date.now() + 7 * 3600_000 + daysAhead * 86_400_000);
  return new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate(), hour - 7, 0)).toISOString();
}

function form(fields, docs = []) {
  const f = new FormData();
  for (const [k, v] of Object.entries(fields)) if (v !== undefined && v !== null) f.set(k, String(v));
  for (const [name, content, type] of docs) f.append('dokumen[]', new Blob([content], { type }), name);
  return f;
}

const base = () => ({
  jenis: 'olahraga',
  nama: `Badminton bersama Sintetis USULAN ${run}`,
  deskripsi: 'Kegiatan sintetis uji staging; bukan acara warga nyata. Susunan acara terlampir.',
  lokasi: 'Lapangan RT 05',
  mulai_at: jakarta(4, 16),
  selesai_at: jakarta(4, 18),
  perkiraan_peserta: 20,
  kebutuhan: 'Izin memakai lapangan (uji sintetis).',
  pernyataan: 1,
});
const TXT = ['susunan-acara.txt', 'Susunan acara sintetis:\n1. Pemanasan\n2. Pertandingan\n', 'text/plain'];
const PDF = ['proposal.pdf', '%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n', 'application/pdf'];

async function main() {
  try { args = parseArgs(process.argv.slice(2)); } catch (e) { console.error(e.message); return 3; }
  const admin = JSON.parse(await fs.readFile(args.adminFixture, 'utf8'));
  const m07 = JSON.parse(await fs.readFile(args.actors, 'utf8'));
  if (admin.origin !== ORIGIN || m07.origin !== ORIGIN) { console.error('Fixture bukan untuk staging.'); return 3; }
  actors.admin = admin.accounts.admin;
  actors.a = m07.actors[m07.ids.primaryWinner];
  actors.b = m07.actors[m07.ids.primaryLoser];
  actors.p = m07.actors.a2;
  for (const c of Object.values(actors)) remember(c.email, c.password);
  console.log(`Target ${ORIGIN}${API}; SHA ${args.sha}; run Sintetis USULAN ${run}.`);
  if (!args.execute) { console.log('Mode rencana: tambahkan --execute setelah SHA deployment diverifikasi.'); return 0; }

  let exit = 0;
  const t0 = Date.now();
  try {
    for (const k of ['admin', 'a', 'b', 'p']) ids[k] = await login(k);
    for (const k of ['a', 'b']) remember(ids[k].id, ids[k].nama);
    record('U00-actors', { method: 'POST', route: '/auth/mobile-login', status: 200, body: {} }, 200, {
      checks: [['admin pengurus, a/b aktif, p menunggu', () => ids.admin.peran === 'pengurus' && ids.a.status === 'aktif' && ids.b.status === 'aktif' && ids.p.status === 'menunggu']],
    });

    await check('U01-pending-denied', 'POST', '/activity-proposals', as('p', { body: form(base()) }), 403);
    await check('U02-statement-required', 'POST', '/activity-proposals', as('a', { body: form({ ...base(), pernyataan: 0 }) }), 422, {
      checks: [['pesan pada field pernyataan', b => Boolean(b.error.fields?.pernyataan)]],
    });
    await check('U03-too-soon', 'POST', '/activity-proposals', as('a', { body: form({ ...base(), mulai_at: new Date(Date.now() + 3 * 3600_000).toISOString(), selesai_at: new Date(Date.now() + 5 * 3600_000).toISOString() }) }), 422, {
      checks: [['pesan 24 jam', b => JSON.stringify(b.error.fields?.mulai_at ?? '').includes('24 jam')]],
    });
    await check('U04-html-as-pdf-415', 'POST', '/activity-proposals', as('a', { body: form(base(), [['berkas.pdf', '<html>bukan pdf</html>', 'application/pdf']]) }), 415);
    await check('U05-docx-415', 'POST', '/activity-proposals', as('a', { body: form(base(), [['proposal.docx', 'PK\u0003\u0004[Content_Types].xml', 'application/octet-stream']]) }), 415);

    const created = await check('U06-create-with-txt-and-pdf', 'POST', '/activity-proposals', as('a', { body: form(base(), [TXT, PDF]) }), 201, {
      checks: [
        ['diajukan v1 tanpa agenda', b => b.data.status === 'diajukan' && b.data.versi === 1 && b.data.agenda_id === null],
        ['field form tersimpan', b => b.data.jenis === 'olahraga' && b.data.perkiraan_peserta === 20 && b.data.kebutuhan],
        ['mime txt dan pdf', b => b.data.dokumen.map(d => d.mime).join() === 'text/plain,application/pdf'],
        ['URL dokumen tanpa nama berkas asli', b => b.data.dokumen.every(d => !d.url.includes('susunan-acara') && !d.url.includes('proposal.pdf'))],
      ],
    });
    ids.usulan = created.data.id;
    ids.doc = created.data.dokumen[0].id;

    await check('U07-other-resident-404', 'GET', '/activity-proposals/{id}', as('b', { params: { id: ids.usulan } }), 404);
    await check('U08-other-resident-doc-link-404', 'POST', '/activity-proposals/{id}/documents/{doc}/link', as('b', { params: { id: ids.usulan, doc: ids.doc } }), 404);
    await check('U09-not-an-agenda-yet', 'GET', '/agendas', as('b', { query: { per_page: 100 } }), 200, {
      checks: [['usulan tidak tampil sebagai agenda', b => !b.data.some(x => x.nama === base().nama)]],
    });
    await check('U10-admin-sees-proposer-and-clash-list', 'GET', '/activity-proposals/{id}', as('admin', { params: { id: ids.usulan } }), 200, {
      checks: [['pengusul = a1', b => b.data.pengusul?.id === ids.a.id], ['agenda_bentrok berupa daftar', b => Array.isArray(b.data.agenda_bentrok)]],
    });

    const status = (s, v, catatan) => as('admin', { params: { id: ids.usulan }, body: { status: s, expected_version: v, ...(catatan ? { catatan } : {}) } });
    await check('U11-revision-needs-note', 'POST', '/admin/activity-proposals/{id}/status', status('perlu_revisi', 1), 422, { code: 'DECISION_NOTE_REQUIRED' });
    await check('U12-revision', 'POST', '/admin/activity-proposals/{id}/status', status('perlu_revisi', 1, 'Tambahkan jam selesai yang jelas (sintetis).'), 200, {
      checks: [['perlu_revisi v2', b => b.data.status === 'perlu_revisi' && b.data.versi === 2]],
    });
    const edit = v => as('a', { params: { id: ids.usulan }, body: { ...base(), perkiraan_peserta: 24, pernyataan: true, versi: v } });
    await check('U13-owner-edit-stale-409', 'PATCH', '/activity-proposals/{id}', edit(1), 409, { code: 'VERSION_CONFLICT' });
    await check('U14-owner-resubmits', 'PATCH', '/activity-proposals/{id}', edit(2), 200, {
      checks: [['kembali diajukan v3', b => b.data.status === 'diajukan' && b.data.versi === 3 && b.data.perkiraan_peserta === 24]],
    });

    const link = await check('U15-admin-signed-link', 'POST', '/activity-proposals/{id}/documents/{doc}/link', as('admin', { params: { id: ids.usulan, doc: ids.doc } }), 200, {
      checks: [['URL relatif bertanda tangan', b => b.data.url.startsWith(`${API}/activity-proposals/documents/${ids.doc}/download?`) && b.data.url.includes('signature=')]],
    });
    const signed = link.data.url;
    await check('U16-signed-download-no-token', 'GET', signed, { raw: true }, 200, {
      checks: [['attachment txt, nosniff, no-store', r => r.headers['content-disposition'].includes('dokumen-1.txt') && r.headers['x-content-type-options'] === 'nosniff' && r.headers['cache-control'].includes('no-store')]],
    });
    await check('U17-tampered-signature-403', 'GET', signed.replace('signature=', 'signature=0'), {}, 403, { code: 'INVALID_SIGNATURE' });

    const approved = await check('U18-approve-creates-agenda', 'POST', '/admin/activity-proposals/{id}/status', status('disetujui', 3, 'Disetujui (sintetis).'), 200, {
      checks: [['disetujui dengan agenda_id', b => b.data.status === 'disetujui' && IS_UUID(b.data.agenda_id)]],
    });
    ids.agenda = approved.data.agenda_id;
    await check('U19-second-decision-409', 'POST', '/admin/activity-proposals/{id}/status', status('disetujui', 4), 409, { code: 'PROPOSAL_SCHEDULED' });
    await check('U20-agenda-visible-to-residents', 'GET', '/agendas', as('b', { query: { per_page: 100 } }), 200, {
      checks: [['tepat satu agenda dengan nama usulan', b => b.data.filter(x => x.nama === base().nama).length === 1 && b.data.some(x => x.id === ids.agenda)]],
    });

    // Notifikasi dari outbox (dispatcher terjadwal).
    let got = {};
    const n0 = Date.now();
    for (;;) {
      const [na, nb, nadm] = await Promise.all(['a', 'b', 'admin'].map(k => http('GET', '/notifications', as(k, { query: { per_page: 100 } }))));
      got = {
        bAgenda: nb.body.data.filter(n => n.referensi?.id === ids.agenda).length,
        aAgenda: na.body.data.filter(n => n.referensi?.id === ids.agenda).length,
        aApproved: na.body.data.filter(n => n.referensi?.id === ids.usulan && n.judul === 'Usulan kegiatan disetujui').length,
        aRevisi: na.body.data.filter(n => n.referensi?.id === ids.usulan && /Perlu revisi/.test(n.isi)).length,
        admNew: nadm.body.data.filter(n => n.referensi?.id === ids.usulan && n.judul === 'Usulan kegiatan baru').length,
        admFixed: nadm.body.data.filter(n => n.referensi?.id === ids.usulan && n.judul === 'Usulan kegiatan diperbaiki').length,
        bProposal: nb.body.data.filter(n => n.referensi?.id === ids.usulan).length,
        leak: JSON.stringify(nb.body.data).includes(ids.a.nama),
      };
      if ((got.bAgenda && got.aApproved && got.admFixed) || Date.now() - n0 > 240_000) break;
      await sleep(10_000);
    }
    const inboxSeconds = Math.round((Date.now() - n0) / 1000);
    record('U21-notifications', { method: 'GET', route: '/notifications', status: 200, body: got }, 200, {
      checks: [
        ['warga lain menerima tepat 1 notifikasi agenda', g => g.bAgenda === 1],
        ['pengusul juga menerima agenda', g => g.aAgenda === 1],
        ['pengusul: disetujui 1 dan perlu revisi 1', g => g.aApproved === 1 && g.aRevisi === 1],
        ['pengurus: usulan baru 1 dan diperbaiki 1', g => g.admNew === 1 && g.admFixed === 1],
        ['warga lain tidak menerima notifikasi usulan/identitas', g => g.bProposal === 0 && !g.leak],
      ],
    });

    // Bersihkan jadwal uji dari agenda staging; usulan dan riwayat tetap.
    await check('U22-archive-test-agenda', 'POST', '/admin/agendas/{id}/archive', as('admin', { params: { id: ids.agenda } }), 204);
    await check('U23-history-kept', 'GET', '/activity-proposals/{id}/history', as('admin', { params: { id: ids.usulan } }), 200, {
      checks: [['diajukan, perlu_revisi, diajukan, disetujui', b => b.data.map(h => h.status_ke).join() === 'diajukan,perlu_revisi,diajukan,disetujui']],
    });
    cases.push({ case: 'timing', inboxSeconds, totalSeconds: Math.round((Date.now() - t0) / 1000) });
    console.log(`Info: notifikasi ${inboxSeconds} dtk.`);
  } catch (e) {
    exit = 1;
    console.error(`BERHENTI: ${e instanceof CaseFailure ? e.message : `${e.name}: ${e.message}`}`);
  } finally {
    for (const k of tokens.keys()) await http('POST', '/auth/logout', { token: tokens.get(k) }).catch(() => {});
  }

  const real = cases.filter(c => c.result);
  const summary = {
    schema: 'rt05-usulan-live-summary/v1', scope: 'synthetic staging only', origin: ORIGIN, sourceSha: args.sha,
    run: `Sintetis USULAN ${run}`, finishedAt: new Date().toISOString(),
    status: exit === 0 && real.every(c => c.result === 'passed') ? 'passed' : 'failed',
    cases: real.length, assertions: real.reduce((n, c) => n + c.assertions.length, 0), details: cases,
  };
  const text = JSON.stringify(summary, null, 2);
  const scan = text.replaceAll(args.sha, '');
  if ([...secrets].some(s => scan.includes(s)) || /@|Bearer /.test(text)) { console.error('Ringkasan memuat nilai rahasia; tidak ditulis.'); return 1; }
  await fs.mkdir(args.out, { recursive: true });
  await fs.writeFile(path.join(args.out, SUMMARY_FILE), text);
  console.log(`${summary.status.toUpperCase()}: ${summary.cases} kasus, ${summary.assertions} assertion. Ringkasan: ${path.join(args.out, SUMMARY_FILE)}`);
  return exit;
}

process.exitCode = await main();
