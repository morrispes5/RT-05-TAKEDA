import { expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import assert from 'node:assert/strict';

export async function checkContentPreview(browser, base) {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    const errors = [], writes = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => { if (!['GET', 'HEAD'].includes(request.method())) writes.push(request.url()); });
    const visit = async path => { await page.goto(base + path); await page.locator('[data-cms-ready]').waitFor(); };
    const saved = () => expect(page.locator('.cms-save-status')).toHaveText('Draf tersimpan di perangkat ini.');
    const showPreview = async () => {
        await page.getByRole('button', { name: 'Lihat pratinjau', exact: true }).click();
        await page.getByRole('dialog').getByRole('button', { name: 'Lihat pratinjau', exact: true }).click();
        await page.locator('[data-cms-ready]').waitFor();
    };
    const accessibility = async () => {
        const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
        assert.deepEqual(result.violations.map(v => ({ id: v.id, nodes: v.nodes.map(n => n.target) })), []);
    };
    try {
        await visit('/pengurus/artikel/editor');
        await page.getByRole('button', { name: 'Lihat pratinjau', exact: true }).click();
        assert.equal(await page.locator('dialog').count(), 0, 'invalid article cannot preview');
        const title = 'Bacaan percobaan <img src=x onerror=alert(1)>';
        await page.getByLabel('Judul artikel', { exact: true }).fill(title);
        await page.getByLabel('Ringkasan', { exact: true }).fill('Ringkasan lokal untuk menguji editor.');
        await page.getByLabel('Pembuka', { exact: true }).fill('Pembuka bacaan untuk warga.');
        await page.getByLabel('Subjudul bagian 1', { exact: true }).fill('Bagian pertama');
        await page.getByLabel('Isi bagian 1', { exact: true }).fill('Isi pertama tetap utuh ketika diurutkan.');
        await page.getByRole('button', { name: 'Tambah bagian', exact: true }).click();
        await page.getByLabel('Subjudul bagian 2', { exact: true }).fill('Bagian kedua');
        await page.getByLabel('Isi bagian 2', { exact: true }).fill('Isi kedua tetap utuh ketika diurutkan.');
        await page.getByRole('button', { name: 'Naikkan bagian 2', exact: true }).click();
        await expect(page.getByLabel('Subjudul bagian 1', { exact: true })).toHaveValue('Bagian kedua');
        await page.getByRole('button', { name: 'Hapus bagian 2', exact: true }).click();
        await page.locator('input[type=file]').setInputFiles('public/images/dokumentasi/taman-bermain-480.webp');
        await page.getByLabel('Deskripsi foto sampul', { exact: true }).fill('Ruang bermain di bawah pepohonan.');
        await page.getByLabel('Nama sumber bacaan (opsional)', { exact: true }).fill('Sumber contoh');
        await page.getByLabel('Tautan sumber (opsional)', { exact: true }).fill('https://example.com/bacaan');
        await page.getByRole('button', { name: 'Simpan draf', exact: true }).click(); await saved();
        const articleEditor = page.url();
        await page.reload(); await page.locator('[data-cms-ready]').waitFor();
        await expect(page.getByLabel('Judul artikel', { exact: true })).toHaveValue(title);
        await expect(page.getByLabel('Subjudul bagian 1', { exact: true })).toHaveValue('Bagian kedua');
        await expect(page.getByLabel('Deskripsi foto sampul', { exact: true })).toHaveValue('Ruang bermain di bawah pepohonan.');
        assert.ok((await page.locator('.cms-cover-box img').getAttribute('src')).startsWith('blob:'));
        await accessibility();

        // A failed write must retain input and prevent navigation; recovery uses the same draft.
        await page.evaluate(() => { window.originalPut = IDBObjectStore.prototype.put; IDBObjectStore.prototype.put = function (...args) { if (this.name === 'articles') throw new DOMException('Simulated quota', 'QuotaExceededError'); return window.originalPut.apply(this, args); }; });
        await page.locator('input[type=file]').setInputFiles('public/images/kegiatan/perayaan-17-agustus-480.webp');
        await expect(page.locator('.cms-cover-box img')).toHaveAttribute('alt', 'Pratinjau sampul');
        await page.getByLabel('Deskripsi foto sampul', { exact: true }).fill('Warga di panggung Takeda.');
        await page.getByLabel('Pembuka', { exact: true }).fill('Isian tidak hilang saat penyimpanan gagal.');
        await page.getByRole('button', { name: 'Simpan draf', exact: true }).click();
        await expect(page.locator('.cms-save-status')).toHaveText('Draf belum tersimpan.');
        await expect(page.locator('.cms-error')).toContainText('Isianmu masih ada');
        await expect(page.getByLabel('Pembuka', { exact: true })).toHaveValue('Isian tidak hilang saat penyimpanan gagal.');
        let leaveWarning = false;
        page.once('dialog', async dialog => { assert.equal(dialog.type(), 'beforeunload'); leaveWarning = true; await dialog.dismiss(); });
        await page.goto(base + '/').catch(error => { assert.ok(error.message.includes('ERR_ABORTED')); });
        assert.equal(leaveWarning, true, 'unsaved draft warns before leaving');
        assert.equal(page.url(), articleEditor, 'dismissed warning preserves the editor');
        await page.evaluate(() => { IDBObjectStore.prototype.put = window.originalPut; });
        await page.getByRole('button', { name: 'Simpan draf', exact: true }).click(); await saved();
        const mediaCount = await page.evaluate(() => new Promise((resolve, reject) => {
            const open = indexedDB.open('rt05-content-preview');
            open.onsuccess = () => { const request = open.result.transaction('media').objectStore('media').count(); request.onsuccess = () => { resolve(request.result); open.result.close(); }; request.onerror = () => reject(request.error); };
        }));
        assert.equal(mediaCount, 1, 'replaced cover media is cleaned up atomically');
        await showPreview(); await expect(page.locator('h1')).toHaveText(title);
        assert.equal(await page.locator('h1 img').count(), 0, 'user HTML is rendered as text');
        await expect(page.locator('.cms-public-preview')).toContainText('Isian tidak hilang');
        await expect(page.locator('.cms-public-preview img')).toHaveAttribute('alt', 'Warga di panggung Takeda.');
        await expect(page.locator('.cms-public-preview a')).toHaveAttribute('href', 'https://example.com/bacaan');
        await accessibility(); await page.screenshot({ path: 'artifacts/screenshots/article-local-preview-390.png', fullPage: true });

        // A new browser has no access to this origin's draft state from another context.
        const visitor = await browser.newContext();
        const publicPage = await visitor.newPage();
        await publicPage.goto(base + '/artikel'); assert.equal((await publicPage.textContent('body')).includes(title), false);
        await publicPage.goto(articleEditor); await publicPage.locator('[data-cms-ready]').waitFor();
        await expect(publicPage.getByRole('heading', { name: 'Draf tidak ditemukan.', exact: true })).toBeVisible();
        await visitor.close();

        await visit('/pengurus/dokumentasi/editor');
        await page.getByLabel('Judul album', { exact: true }).fill('Album percobaan lokal');
        await page.getByLabel('Cerita kegiatan', { exact: true }).fill('Cerita ini hanya ada di browser pengujian.');
        await page.getByRole('button', { name: 'Lihat pratinjau', exact: true }).click();
        await expect(page.locator('.cms-error')).toContainText('setidaknya satu foto');
        const upload = page.locator('input[type=file]');
        await upload.setInputFiles({ name: 'bukan-foto.svg', mimeType: 'image/svg+xml', buffer: Buffer.from('<svg></svg>') });
        await expect(page.locator('.cms-error')).toContainText('Pilih foto JPG, PNG, atau WebP');
        await upload.setInputFiles(['public/images/kegiatan/perayaan-17-agustus-480.webp', 'public/images/dokumentasi/taman-bermain-480.webp']);
        await expect(page.locator('.cms-photo-row')).toHaveCount(2);
        await page.getByLabel('Keterangan foto 1', { exact: true }).fill('Panggung Takeda');
        await page.getByLabel('Deskripsi foto 1', { exact: true }).fill('Warga di panggung merah putih.');
        await page.getByLabel('Keterangan foto 2', { exact: true }).fill('Ruang bermain');
        await page.getByLabel('Deskripsi foto 2', { exact: true }).fill('Kubah panjat dan pepohonan.');
        await page.locator('.cms-photo-row').nth(1).getByRole('button', { name: 'Jadikan sampul', exact: true }).click();
        await page.getByRole('button', { name: 'Naikkan foto 2', exact: true }).click();
        await expect(page.getByLabel('Keterangan foto 1', { exact: true })).toHaveValue('Ruang bermain');
        await expect(page.locator('.cms-photo-row').first()).toContainText('Sampul album');
        await page.getByRole('button', { name: 'Simpan draf', exact: true }).click(); await saved();
        await page.reload(); await page.locator('[data-cms-ready]').waitFor();
        await expect(page.getByLabel('Keterangan foto 1', { exact: true })).toHaveValue('Ruang bermain');
        assert.equal(await page.locator('.cms-photo-row img').evaluateAll(images => images.every(image => image.src.startsWith('blob:'))), true);
        await accessibility(); await page.screenshot({ path: 'artifacts/screenshots/album-editor-390.png', fullPage: true });
        await showPreview(); await expect(page.locator('h1')).toHaveText('Album percobaan lokal');
        await expect(page.locator('.cms-public-preview figure')).toHaveCount(2);
        await page.locator('.cms-public-preview figure').first().getByRole('button', { name: 'Perbesar foto' }).click();
        await expect(page.getByRole('dialog')).toBeVisible(); await page.keyboard.press('Escape');
        await expect(page.getByRole('dialog')).toHaveCount(0);

        await visit('/pengurus/dokumentasi');
        const row = page.locator('.cms-content-row').filter({ hasText: 'Album percobaan lokal' });
        await row.getByRole('button', { name: 'Hapus draf', exact: true }).click();
        await page.getByRole('dialog').getByRole('button', { name: 'Hapus', exact: true }).click();
        await expect(page.locator('.cms-content-row').filter({ hasText: 'Album percobaan lokal' })).toHaveCount(0);
        await visit('/pengurus'); await page.getByRole('button', { name: 'Reset pratinjau', exact: true }).click();
        await page.getByRole('dialog').getByRole('button', { name: 'Reset pratinjau', exact: true }).click();
        await expect(page.locator('.cms-count').first()).toHaveText('0 draf di perangkat ini');
        await page.goto(articleEditor); await page.locator('[data-cms-ready]').waitFor();
        await expect(page.getByRole('heading', { name: 'Draf tidak ditemukan.', exact: true })).toBeVisible();
        assert.deepEqual(errors, []); assert.deepEqual(writes, [], 'no draft/photo network writes');
        return { article: 'PASS', album: 'PASS', persistence: 'PASS', isolation: 'PASS', failedSaveRecovery: 'PASS', textSafety: 'PASS', reset: 'PASS', networkWrites: writes.length, a11yCases: 3 };
    } finally { await context.close(); }
}
