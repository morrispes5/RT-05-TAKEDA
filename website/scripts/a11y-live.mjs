// Pemakaian (dari website/): node scripts/a11y-live.mjs [origin]
// M15: axe-core (WCAG 2.1 A/AA) pada halaman staging, termasuk CMS mode server yang tidak ada di preview statis.
// Hanya membaca halaman publik dan layar login; tidak memasukkan kredensial.
import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const origin = process.argv[2] || 'https://takeda-staging.morriz.tech';
const paths = ['/', '/profil', '/fasilitas', '/dokumentasi', '/artikel', '/kontak', '/pengurus/masuk'];
const browser = await chromium.launch();
const out = [];
for (const viewport of [{ width: 1280, height: 900 }, { width: 360, height: 780 }]) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    for (const path of paths) {
        await page.goto(origin + path, { waitUntil: 'networkidle' });
        if (path === '/pengurus/masuk') await page.waitForSelector('[data-cms-ready="true"]');
        const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
        out.push({ path, width: viewport.width, violations: violations.map(v => ({ id: v.id, impact: v.impact, nodes: v.nodes.length })) });
    }
    // Keyboard: Tab pertama ke tautan lewati-konten, form login bisa dijangkau tanpa mouse.
    await page.goto(origin + '/pengurus/masuk', { waitUntil: 'networkidle' });
    await page.waitForSelector('[data-cms-ready="true"]');
    await page.keyboard.press('Tab');
    const first = await page.evaluate(() => document.activeElement?.textContent?.trim());
    let reached = false;
    for (let i = 0; i < 25 && !reached; i++) {
        await page.keyboard.press('Tab');
        reached = await page.evaluate(() => document.activeElement?.getAttribute('type') === 'email');
    }
    out.push({ keyboard: { width: viewport.width, firstTab: first, loginEmailReachable: reached } });
    await context.close();
}
await browser.close();
console.log(JSON.stringify(out, null, 1));
