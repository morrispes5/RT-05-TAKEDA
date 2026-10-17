// Pemakaian: node m12-stage-check.mjs (fixture privat di %USERPROFILE%/.rt05, di luar Git).
// Cek live M12 staging: tanpa mencatat uang. Satu-satunya tulisan adalah audit ekspor milik pengurus sintetis.
import fs from 'node:fs';
const O = 'https://takeda-staging.morriz.tech/api/v1';
const p = process.env.USERPROFILE + '/.rt05/';
const admin = JSON.parse(fs.readFileSync(p + 'm06-stage-check/fixtures.json', 'utf8')).accounts.admin;
const warga = JSON.parse(fs.readFileSync(p + 'm07-stage-check/m07-state.private.json', 'utf8')).actors.a3;
const h = { 'Content-Type': 'application/json', Accept: 'application/json' };
const login = async c => (await (await fetch(O + '/auth/mobile-login', { method: 'POST', headers: h, body: JSON.stringify({ email: c.email, password: c.password, device_name: 'M12 live check' }) })).json()).data.token;
const req = (t, u) => fetch(O + u, { headers: { ...h, Authorization: 'Bearer ' + t } });
const out = { run: new Date().toISOString(), cases: [] };
const rec = (name, ok, detail = {}) => out.cases.push({ name, result: ok ? 'PASS' : 'FAIL', ...detail });
const ta = await login(admin), tw = await login(warga);
let r = await req(ta, '/admin/cash-transactions?per_page=100'); let b = await r.json();
rec('M01 daftar kas memuat dapat_dibalik', r.status === 200 && b.data.every(k => typeof k.dapat_dibalik === 'boolean'), { status: r.status, baris: b.data?.length, saldo: b.meta?.saldo });
rec('M02 kas iuran tidak dapat dibalik langsung', b.data.filter(k => k.sumber === 'iuran').every(k => k.dapat_dibalik === false), { kas_iuran: b.data.filter(k => k.sumber === 'iuran').length });
const saldo = b.meta?.saldo;
r = await req(ta, '/admin/exports/kas'); const csv = (await r.text()).replace(/^﻿/, '');
const lines = csv.trim().split(/\r?\n/).slice(1);
const total = lines.reduce((s, l) => s + Number(l.match(/^(?:"[^"]*"|[^,]*),(?:"[^"]*"|[^,]*),(?:"[^"]*"|[^,]*),(?:"[^"]*"|[^,]*),(-?\d+)/)?.[1] ?? NaN), 0);
rec('M03 total ekspor kas = saldo ledger', r.status === 200 && total === saldo, { status: r.status, cache: r.headers.get('cache-control'), baris: lines.length, total_ekspor: total });
for (const j of ['iuran', 'kas', 'rumah']) { r = await req(tw, '/admin/exports/' + j); rec(`M04 warga ekspor ${j} ditolak`, r.status === 403, { status: r.status }); }
r = await req(tw, '/admin/cash-transactions'); rec('M05 warga daftar kas ditolak', r.status === 403, { status: r.status });
for (const t of [ta, tw]) await fetch(O + '/auth/logout', { method: 'POST', headers: { ...h, Authorization: 'Bearer ' + t } });
out.summary = { total: out.cases.length, pass: out.cases.filter(c => c.result === 'PASS').length };
console.log(JSON.stringify(out, null, 1));
