// Pemakaian (dari website/): node --require <preload-dns> scripts/cms-ui-live.mjs [folder-hasil]
// UAT M15 putaran 2 (20 Okt 2026): uji UI CMS mode server di staging lewat Chromium. Akun admin sintetis dari fixture
// privat %USERPROFILE%/.rt05/m06-stage-check/fixtures.json (di luar Git, tidak dicetak). Artikel uji diarsipkan di akhir.
// Alur: login → tulis + foto sampul asli → terbit → tampil publik (foto, bukan SVG) → edit artikel terbit → pesan "tekan Terbitkan" → terbit ulang →
// versi baru publik → arsipkan → hilang dari daftar dan website → tampil di "Lihat arsip" (hanya baca).
// RT05_RESOLVE_V6=<IPv6 VPS> memetakan host staging untuk Chromium bila IPv4 ISP ke VPS bermasalah (lihat docs/HANDOFF.md).
import { chromium } from '@playwright/test';
import fs from 'node:fs';
const ORIGIN = 'https://takeda-staging.morriz.tech';
const coverPhoto = process.env.USERPROFILE + '/.rt05/m14-stage-check/uji-sintetis.jpg';
const admin = JSON.parse(fs.readFileSync(process.env.USERPROFILE + '/.rt05/m06-stage-check/fixtures.json', 'utf8')).accounts.admin;
const out = process.argv[2] || 'artifacts/cms-ui-live';
fs.mkdirSync(out, { recursive: true });
const run = Math.random().toString(16).slice(2, 8);
const title = 'Uji UAT sintetis ' + run;
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
    const shot = name => page.screenshot({ path: out + '/cms-' + name + '.png', fullPage: true });
    const confirmDialog = label => page.getByRole('dialog').getByRole('button', { name: label, exact: true }).click();
    const publicHas = async (text, seconds = 75) => {
        const pub = await browser.newContext(); const p = await pub.newPage();
        for (let t = 0; t <= seconds; t += 5) {
            await p.goto(ORIGIN + '/artikel?_=' + Date.now());
            if ((await p.textContent('body')).includes(text)) { await pub.close(); return t; }
            await new Promise(r => setTimeout(r, 5000));
        }
        await pub.close(); return -1;
    };

    await page.goto(ORIGIN + '/pengurus/artikel'); await ready();
    rec('C01 belum masuk diarahkan ke login', page.url().includes('/pengurus/masuk'));
    await page.locator('input[name=email]').fill(admin.email);
    await page.locator('input[name=password]').fill(admin.password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.waitForURL(/\/pengurus\/artikel/); await ready();
    rec('C02 login pengurus kembali ke daftar artikel', true);
    rec('C03 tombol Lihat arsip ada', await page.getByRole('link', { name: 'Lihat arsip' }).isVisible());

    await page.getByRole('link', { name: 'Tulis artikel' }).click(); await ready();
    await page.getByLabel('Judul artikel').fill(title);
    await page.getByLabel('Ringkasan').fill('Ringkasan awal ' + run + ' untuk uji.');
    await page.getByLabel('Pembuka').fill('Paragraf pembuka artikel uji sintetis.');
    await page.getByLabel('Subjudul bagian 1').fill('Bagian uji');
    await page.getByLabel('Isi bagian 1').fill('Isi bagian uji sintetis untuk UAT.');
    await page.locator('.cms-field input[type=file]').first().setInputFiles(coverPhoto);
    await page.getByLabel('Deskripsi foto sampul').fill('Foto uji sintetis kotak warna');
    await page.getByRole('button', { name: 'Simpan draf', exact: true }).click();
    await page.getByText('Draf tersimpan di server').waitFor({ timeout: 20000 });
    rec('C04 simpan draf baru', true);
    await page.getByRole('button', { name: 'Terbitkan', exact: true }).click(); await confirmDialog('Terbitkan');
    await page.getByText('Terbit di website. Halaman publik diperbarui').waitFor({ timeout: 20000 });
    await shot('terbit');
    let t = await publicHas('Ringkasan awal ' + run);
    rec('C05 artikel terbit tampil di website publik', t >= 0, { detik: t });
    const listing = await (await fetch(ORIGIN + '/artikel?_=' + Date.now())).text();
    rec('C05b foto sampul asli tampil di website (bukan SVG)', listing.includes('aria-label="Baca ' + title + '"><img class="article-cover" src="/api/v1/public/media/'));

    // Ganti isi artikel yang sudah terbit (temuan UAT E).
    await page.getByLabel('Ringkasan').fill('Ringkasan diganti ' + run + ' setelah terbit.');
    await page.getByRole('button', { name: 'Simpan draf', exact: true }).click();
    const msg = page.getByText('Website masih menampilkan versi lama sampai Anda menekan Terbitkan');
    await msg.waitFor({ timeout: 20000 });
    rec('C06 simpan perubahan artikel terbit memberi petunjuk Terbitkan', true);
    await shot('perubahan-tersimpan');
    t = await publicHas('Ringkasan diganti ' + run, 10);
    rec('C07 sebelum Terbitkan, website masih versi lama', t < 0);
    await page.goto(ORIGIN + '/pengurus/artikel'); await ready();
    const row = page.locator('.cms-row, li, article').filter({ hasText: title }).first();
    rec('C08 daftar menandai ada perubahan belum terbit', (await row.textContent()).includes('ada perubahan belum terbit'));
    await row.getByRole('link', { name: 'Edit' }).click(); await ready();
    rec('C09 editor memuat ringkasan yang diganti', (await page.getByLabel('Ringkasan').inputValue()).includes('diganti ' + run));
    await page.getByRole('button', { name: 'Terbitkan', exact: true }).click(); await confirmDialog('Terbitkan');
    await page.getByText('Terbit di website. Halaman publik diperbarui').waitFor({ timeout: 20000 });
    t = await publicHas('Ringkasan diganti ' + run);
    rec('C10 setelah Terbitkan, website menampilkan versi baru', t >= 0, { detik: t });

    // Arsip.
    await page.goto(ORIGIN + '/pengurus/artikel'); await ready();
    await page.locator('.cms-row, li, article').filter({ hasText: title }).first().getByRole('button', { name: 'Arsipkan' }).click();
    await confirmDialog('Arsipkan');
    await page.waitForTimeout(2000);
    rec('C11 artikel hilang dari daftar aktif', (await page.locator('.cms-row, li, article').filter({ hasText: title }).count()) === 0);
    await page.getByRole('link', { name: 'Lihat arsip' }).click(); await ready();
    const arsipRow = page.locator('.cms-row, li, article').filter({ hasText: title }).first();
    rec('C12 artikel muncul di Arsip artikel, berlabel Diarsipkan', (await arsipRow.textContent()).includes('Diarsipkan'));
    rec('C13 arsip hanya baca (tanpa Edit/Arsipkan)', (await arsipRow.getByRole('link', { name: 'Edit' }).count()) === 0 && (await arsipRow.getByRole('button', { name: 'Arsipkan' }).count()) === 0);
    await shot('arsip');
    await arsipRow.getByRole('link', { name: 'Lihat' }).click(); await ready();
    rec('C14 isi arsip dapat dibaca', (await page.textContent('body')).includes('diganti ' + run));
    let gone = -1;
    for (let w = 0; w <= 60; w += 5) { const r = await fetch(ORIGIN + '/artikel?_=' + Date.now()).catch(() => null); if (r && !(await r.text()).includes(title)) { gone = w; break; } await new Promise(res => setTimeout(res, 5000)); }
    rec('C15 arsip hilang dari website', gone >= 0, { detik: gone });
    // Pulihkan (ADR34, UAT M15 20 Okt): kembali sebagai draf, belum tampil di website; lalu diarsipkan lagi agar staging bersih.
    await page.goto(ORIGIN + '/pengurus/artikel?arsip=1'); await ready();
    const pulih = page.locator('.cms-row, li, article').filter({ hasText: title }).first();
    rec('C16 arsip punya tombol Pulihkan', (await pulih.getByRole('button', { name: 'Pulihkan' }).count()) === 1);
    await pulih.getByRole('button', { name: 'Pulihkan' }).click();
    await confirmDialog('Pulihkan');
    await page.waitForTimeout(2000);
    rec('C17 setelah dipulihkan hilang dari arsip', (await page.locator('.cms-row, li, article').filter({ hasText: title }).count()) === 0);
    await page.goto(ORIGIN + '/pengurus/artikel'); await ready();
    const aktif = page.locator('.cms-row, li, article').filter({ hasText: title }).first();
    rec('C18 muncul di daftar aktif sebagai draf, belum terbit', (await aktif.textContent()).includes('Draf, belum terbit'));
    await shot('dipulihkan');
    rec('C19 dipulihkan tidak langsung tampil di website', (await publicHas(title, 20)) === -1);
    await aktif.getByRole('button', { name: 'Arsipkan' }).click();
    await confirmDialog('Arsipkan');
    await page.waitForTimeout(2000);
    rec('C20 tanpa error JavaScript', errors.length === 0, { errors });
} catch (e) {
    rec('ERROR', false, { message: e.message.split('\n')[0] });
} finally {
    await browser.close();
    fs.writeFileSync(out + '/cms-ui-staging.json', JSON.stringify({ run: new Date().toISOString(), cases }, null, 2));
    if (cases.some(c => c.result !== 'PASS')) process.exitCode = 1;
}
