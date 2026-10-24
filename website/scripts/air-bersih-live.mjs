// Uji live ADR41 (staging): menu Air Bersih KSM di Area Pengurus tersembunyi sebelum login dan tampil setelah login pengurus,
// mengarah ke aplikasi air bersih di tab baru. Akun sintetis dari fixture privat %USERPROFILE%/.rt05 (tidak dicetak).
// Pemakaian dari website/: node scripts/air-bersih-live.mjs
import { chromium } from '@playwright/test';
import fs from 'node:fs';

const ORIGIN = 'https://takeda-staging.morriz.tech';
const admin = JSON.parse(fs.readFileSync(process.env.USERPROFILE + '/.rt05/m06-stage-check/fixtures.json', 'utf8')).accounts.admin;
const results = [];
const rec = (name, ok) => results.push({ name, ok: Boolean(ok) });

const browser = await chromium.launch();
try {
    const page = await browser.newPage();
    const link = page.locator('[data-air-bersih-ksm]');
    await page.goto(ORIGIN + '/pengurus/artikel');
    await page.locator('[data-cms-ready="true"]').waitFor({ timeout: 30000 });
    rec('A01 belum masuk: menu Air Bersih tidak terlihat', !(await link.isVisible()));
    await page.locator('input[name=email]').fill(admin.email);
    await page.locator('input[name=password]').fill(admin.password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.waitForURL(/\/pengurus\/artikel/);
    await link.waitFor({ state: 'visible', timeout: 30000 }).catch(() => {});
    rec('A02 setelah masuk pengurus: menu Air Bersih terlihat', await link.isVisible());
    rec('A03 tujuan aplikasi air bersih, tab baru', (await link.getAttribute('href'))?.startsWith('https://airbersih-ksm-claude.vercel.app')
        && (await link.getAttribute('target')) === '_blank');
    const [popup] = await Promise.all([page.waitForEvent('popup', { timeout: 30000 }), link.click()]);
    await popup.waitForLoadState('domcontentloaded');
    rec('A04 tab baru membuka halaman masuk Air Bersih KSM', popup.url().includes('airbersih-ksm-claude.vercel.app')
        && /Air Bersih KSM/i.test(await popup.content()));
} finally {
    await browser.close();
}
for (const r of results) console.log(`${r.ok ? 'PASS' : 'GAGAL'} ${r.name}`);
console.log(`${results.filter(r => r.ok).length}/${results.length} PASS`);
process.exitCode = results.every(r => r.ok) ? 0 : 1;
