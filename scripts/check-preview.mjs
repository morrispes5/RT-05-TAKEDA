import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { mkdir, readdir, writeFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
import { previewServer } from './serve-preview.mjs';

const server = previewServer();
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = 'http://127.0.0.1:' + server.address().port;
const htmlFiles = (await readdir('preview', { recursive: true })).filter(f => f.endsWith('.html') && f !== '404.html');
const routes = htmlFiles.map(f => f === 'index.html' ? '/' : '/' + f.replaceAll('\\', '/').replace(/\.html$/, ''));
await mkdir('artifacts/screenshots', { recursive: true });
const browser = await chromium.launch();
const errors = [];
const results = [];
const links = new Set();
try {
    const context = await browser.newContext();
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    page.on('response', response => { if (response.status() >= 400) errors.push(response.status() + ' ' + response.url()); });
    for (const width of [360, 390, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const route of routes) {
            await page.goto(base + route);
            await page.evaluate(() => document.fonts.ready);
            assert.equal(await page.locator('h1').count(), 1, route);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
            assert.equal(overflow, false, route + ' overflows at ' + width);
            const localLinks = await page.locator('a[href]').evaluateAll(nodes => nodes.map(n => n.getAttribute('href')).filter(h => h.startsWith('/')));
            localLinks.forEach(link => links.add(link));
            if (width === 390 || width === 1440) {
                const a11y = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
                assert.deepEqual(a11y.violations.map(v => ({ id: v.id, impact: v.impact, targets: v.nodes.map(n => n.target) })), [], 'a11y ' + route + ' at ' + width);
            }
            // Force offscreen images to load, then validate every image.
            await page.locator('img').evaluateAll(images => images.forEach(image => image.loading = 'eager'));
            await page.waitForFunction(() => [...document.images].every(img => img.complete));
            assert.deepEqual(await page.locator('img:not([data-lightbox-img])').evaluateAll(images => images.filter(i => !i.naturalWidth || !i.hasAttribute('alt')).map(i => i.src)), [], route + ' images');
            if (['/', '/profil', '/dokumentasi', '/artikel', '/artikel/cara-mencuci-tangan', '/pengurus', '/pengurus/iuran'].includes(route)) {
                await page.screenshot({ path: 'artifacts/screenshots/' + (route === '/' ? 'home' : route.slice(1).replaceAll('/', '-')) + '-' + width + '.png', fullPage: true });
            }
            results.push({ route, width, passed: true });
        }
    }
    for (const link of links) {
        const [route, hash] = link.split('#');
        const response = await fetch(base + route);
        assert.equal(response.status, 200, link);
        if (hash) assert.ok((await response.text()).includes('id="' + hash + '"'), 'missing fragment: ' + link);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    for (const route of ['/', '/pengurus', '/pengurus/iuran']) {
        await page.goto(base + route);
        const toggle = page.locator('[data-menu-toggle]');
        await toggle.click();
        assert.equal(await toggle.getAttribute('aria-expanded'), 'true');
        await page.keyboard.press('Escape');
        assert.equal(await toggle.getAttribute('aria-expanded'), 'false');
        assert.equal(await toggle.evaluate(el => el === document.activeElement), true);
        await toggle.click();
        await page.locator('[data-menu] a').last().click();
        assert.equal(await page.locator('[data-menu-toggle]').getAttribute('aria-expanded'), 'false');
    }
    await page.goto(base + '/dokumentasi');
    await page.getByRole('button', { name: 'Ruang bermain', exact: true }).click();
    assert.equal(await page.locator('[data-filter-item="photos"]:visible').count(), 3);
    const opener = page.locator('[data-lightbox]:visible').first();
    await opener.click();
    assert.equal(await page.locator('dialog').isVisible(), true);
    const first = await page.locator('[data-lightbox-img]').getAttribute('src');
    await page.getByRole('button', { name: 'Foto berikutnya', exact: true }).click();
    assert.notEqual(await page.locator('[data-lightbox-img]').getAttribute('src'), first);
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('dialog').isVisible(), false);
    assert.equal(await opener.evaluate(el => el === document.activeElement), true);
    await page.goto(base + '/artikel');
    await page.getByRole('button', { name: 'Lingkungan', exact: true }).click();
    assert.equal(await page.locator('[data-filter-item="articles"]:visible').count(), 2);
    await page.emulateMedia({ reducedMotion: 'reduce' });
    assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).scrollBehavior), 'auto');
    assert.deepEqual(errors, [], 'browser errors');
    for (const route of ['/admin', '/pengurus/tidak-ada', '/artikel/tidak-ada']) assert.equal((await fetch(base + route)).status, 404);
    const report = { routes: routes.length, responsiveCases: results.length, a11yCases: routes.length * 2, internalLinks: links.size, browserErrors: errors, interactions: 'PASS', results };
    await writeFile('artifacts/qa-report.json', JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ ...report, results: undefined }, null, 2));
} finally {
    await browser.close();
    server.close();
}
