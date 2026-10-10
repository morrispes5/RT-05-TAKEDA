import { readdir, readFile, writeFile, mkdir } from 'node:fs/promises';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { chromium } from '@playwright/test';

const base = process.argv[2] || 'https://rt05takeda.vercel.app';
assert.equal(new URL(base).protocol, 'https:');
const htmlFiles = (await readdir('preview', { recursive: true })).filter(f => f.endsWith('.html') && f !== '404.html');
const results = [];
const assets = new Set();
for (const file of htmlFiles) {
    const route = file === 'index.html' ? '/' : '/' + file.replaceAll('\\', '/').replace(/\.html$/, '');
    const response = await fetch(base + route, { redirect: 'error' });
    assert.equal(response.status, 200, route);
    assert.ok(response.headers.get('x-robots-tag')?.includes('noindex'), 'preview noindex ' + route);
    const actual = await response.text();
    const expected = await readFile('preview/' + file, 'utf8');
    assert.equal(actual.replaceAll('\r\n', '\n').trim(), expected.trim(), 'deployed HTML differs: ' + route);
    for (const match of actual.matchAll(/(?:src|href)="(\/(?:build|images)\/[^"]+)"/g)) assets.add(match[1]);
    results.push({ route, status: response.status, exactHtmlMatch: true });
}
for (const asset of assets) {
    const response = await fetch(base + asset, { redirect: 'error' });
    assert.equal(response.status, 200, asset);
    const remoteHash = createHash('sha256').update(Buffer.from(await response.arrayBuffer())).digest('hex');
    const localHash = createHash('sha256').update(await readFile('preview' + asset)).digest('hex');
    assert.equal(remoteHash, localHash, 'asset mismatch: ' + asset);
}
for (const route of ['/admin', '/artikel/tidak-ada', '/pengurus/tidak-ada']) {
    assert.equal((await fetch(base + route)).status, 404, route);
}
const browser = await chromium.launch();
const errors = [];
try {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    for (const route of ['/', '/dokumentasi', '/dokumentasi/perayaan-17-agustus', '/artikel/cara-mencuci-tangan', '/pengurus/artikel']) {
        await page.goto(base + route);
        await page.evaluate(() => document.fonts.ready);
        if (route.startsWith('/pengurus')) await page.locator('[data-cms-ready]').waitFor();
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, route);
    }
    await page.locator('[data-menu-toggle]').click();
    await page.getByRole('link', { name: 'Kembali ke website', exact: true }).click();
    await page.waitForURL(base + '/');
    await page.goto(base + '/pengurus/masuk');
    await page.locator('[data-cms-ready]').waitFor();
    assert.equal(await page.locator('input[type=password], input[type=email]').count(), 0);
    assert.deepEqual(errors, []);
} finally {
    await browser.close();
}
await mkdir('artifacts', { recursive: true });
const report = { checkedAt: new Date().toISOString(), base, routes: results.length, exactHtmlMatches: results.length, assetsMatched: assets.size, unknownRoutes: '404', mobileBrowser: 'PASS', browserErrors: errors, results };
await writeFile('artifacts/live-report.json', JSON.stringify(report, null, 2));
console.log(JSON.stringify({ ...report, results: undefined }, null, 2));
