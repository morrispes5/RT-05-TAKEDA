// Pemakaian (dari website/): node --require <preload-dns> scripts/cms-profile-live.mjs [folder-hasil]
// ADR35 (21 Okt 2026): uji editor Profil RT05 di CMS web staging lewat Chromium. Akun admin sintetis dari fixture privat
// %USERPROFILE%/.rt05/m06-stage-check/fixtures.json (di luar Git, tidak dicetak). Pengurus uji yang ditambahkan dihapus lagi
// di akhir sehingga profil staging kembali seperti semula.
// Alur: login → /pengurus/profil → tambah pengurus uji → Simpan dan terbitkan → tampil di /profil → hapus → hilang dari /profil.
// RT05_RESOLVE_V6=<IPv6 VPS> memetakan host staging untuk Chromium bila IPv4 ISP ke VPS bermasalah (docs/HANDOFF.md).
import { chromium } from '@playwright/test';
import fs from 'node:fs';

const ORIGIN = 'https://takeda-staging.morriz.tech';
const admin = JSON.parse(fs.readFileSync(process.env.USERPROFILE + '/.rt05/m06-stage-check/fixtures.json', 'utf8')).accounts.admin;
const out = process.argv[2] || 'artifacts/cms-profile-live';
fs.mkdirSync(out, { recursive: true });
const run = Math.random().toString(16).slice(2, 8);
const nama = 'Pengurus Uji Sintetis ' + run;
const cases = [];
const rec = (name, ok, detail = {}) => { cases.push({ name, result: ok ? 'PASS' : 'FAIL', ...detail }); console.log(ok ? 'PASS' : 'FAIL', name, JSON.stringify(detail)); };
const v6 = process.env.RT05_RESOLVE_V6;
const browser = await chromium.launch({ args: v6 ? ['--host-resolver-rules=MAP takeda-staging.morriz.tech [' + v6 + ']'] : [] });
const errors = [];
try {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    page.on('pageerror', e => errors.push(e.message));
    const ready = () => page.locator('[data-cms-ready="true"]').waitFor({ timeout: 30000 });
    const confirmDialog = label => page.getByRole('dialog').getByRole('button', { name: label, exact: true }).click();
    const publicHas = async (text, seconds = 75) => {
        const pub = await browser.newContext(); const p = await pub.newPage();
        for (let t = 0; t <= seconds; t += 5) {
            await p.goto(ORIGIN + '/profil?_=' + Date.now());
            if ((await p.textContent('body')).includes(text)) { await pub.close(); return t; }
            await new Promise(r => setTimeout(r, 5000));
        }
        await pub.close(); return -1;
    };

    await page.goto(ORIGIN + '/pengurus/profil'); await ready();
    rec('P01 belum masuk diarahkan ke login', page.url().includes('/pengurus/masuk'));
    await page.locator('input[name=email]').fill(admin.email);
    await page.locator('input[name=password]').fill(admin.password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.waitForURL(u => !String(u).includes('/pengurus/masuk'), { timeout: 30000 });
    const openEditor = async () => { await page.goto(ORIGIN + '/pengurus/profil'); await ready(); await page.getByRole('button', { name: 'Tambah pengurus' }).waitFor({ timeout: 30000 }); };
    await openEditor();
    rec('P02 editor Profil RT05 terbuka', (await page.textContent('body')).includes('Tentang Taman Kedaung'));
    const before = await page.locator('.cms-sections > .cms-panel').count();
    rec('P03 daftar pengurus terisi', before >= 1, { jumlah: before });

    await page.getByRole('button', { name: 'Tambah pengurus' }).click();
    const last = page.locator('.cms-sections > .cms-panel').last();
    await last.getByLabel(/Jabatan/).fill('Pengurus uji');
    await last.getByLabel(/^Nama/).fill(nama);
    await page.getByRole('button', { name: 'Simpan dan terbitkan' }).click();
    await confirmDialog('Terbitkan');
    await page.waitForTimeout(2500);
    rec('P04 terbit tanpa galat', (await page.locator('.cms-error:visible').count()) === 0);
    const shown = await publicHas(nama);
    rec('P05 pengurus baru tampil di /profil', shown >= 0, { detik: shown });

    await openEditor();
    const rows = page.locator('.cms-sections > .cms-panel');
    const n = await rows.count();
    let removed = false;
    for (let i = 0; i < n; i++) {
        const row = rows.nth(i);
        if ((await row.locator('input').nth(1).inputValue()) === nama) { await row.getByRole('button', { name: /Hapus/ }).click(); removed = true; break; }
    }
    rec('P06 baris uji ditemukan untuk dihapus', removed);
    await page.getByRole('button', { name: 'Simpan dan terbitkan' }).click();
    await confirmDialog('Terbitkan');
    await page.waitForTimeout(2500);
    let gone = -1;
    for (let w = 0; w <= 75; w += 5) { const r = await fetch(ORIGIN + '/profil?_=' + Date.now()).catch(() => null); if (r && !(await r.text()).includes(nama)) { gone = w; break; } await new Promise(res => setTimeout(res, 5000)); }
    rec('P07 pengurus uji hilang lagi dari /profil', gone >= 0, { detik: gone });
    rec('P08 jumlah pengurus kembali seperti semula', (await (async () => { await openEditor(); return page.locator('.cms-sections > .cms-panel').count(); })()) === before);
    rec('P09 tanpa error JavaScript', errors.length === 0, { errors });
} catch (e) {
    rec('ERROR', false, { message: e.message.split('\n')[0] });
} finally {
    await browser.close();
    fs.writeFileSync(out + '/cms-profile-staging.json', JSON.stringify({ run: new Date().toISOString(), cases }, null, 2));
    if (cases.some(c => c.result !== 'PASS')) process.exitCode = 1;
}
