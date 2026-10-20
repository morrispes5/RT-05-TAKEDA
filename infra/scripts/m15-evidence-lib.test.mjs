// Pemakaian: node --test infra/scripts/m15-evidence-lib.test.mjs (tanpa jaringan, tanpa fixture).
import test from 'node:test';
import assert from 'node:assert/strict';
import { summarize, loadFailures, notifyFailures, isExpected } from './m15-evidence-lib.mjs';

const r = (label, status, ms = 100, role = 'a1') => ({ label, status, ms, role });

test('200, 401, 403, 404, 429, 500, dan kegagalan jaringan: hanya status yang diharapkan yang sukses', () => {
    for (const [status, ok] of [[200, true], [401, false], [403, false], [404, false], [429, false], [500, false], [0, false]]) {
        assert.equal(isExpected(r('GET /x', status)), ok, `status ${status}`);
    }
});

test('404 yang dijelaskan boleh diharapkan per peran, tetapi tidak untuk peran lain', () => {
    const expect = { 'admin GET /household': [404] };
    assert.equal(isExpected(r('GET /household', 404, 10, 'admin'), expect), true);
    assert.equal(isExpected(r('GET /household', 404, 10, 'a1'), expect), false);
});

test('404 diam-diam tidak lagi menghasilkan "0 error"', () => {
    const s = summarize([r('GET /x', 200), r('GET /x', 404), r('GET /y', 200)], 10);
    assert.equal(s.total.unexpected, 1);
    assert.equal(s.endpoints.find(e => e.label === 'GET /x').unexpected, 1);
    assert.match(loadFailures(s).join('\n'), /GET \/x: 1\/2 respons di luar harapan/);
});

test('P95 hanya dari respons sukses: respons gagal yang cepat atau lambat tidak mengubahnya', () => {
    const results = [...Array.from({ length: 20 }, () => r('GET /x', 200, 400)), r('GET /x', 500, 5), r('GET /x', 404, 9000)];
    const s = summarize(results, 10);
    assert.equal(s.endpoints[0].p95, 400);
    assert.equal(s.total.p95, 400);
});

test('P95 di atas gate dan endpoint tanpa respons sukses sama-sama gagal', () => {
    assert.match(loadFailures(summarize([r('GET /lambat', 200, 1500)], 10)).join('\n'), /P95 1500 ms > 1000 ms/);
    assert.match(loadFailures(summarize([r('GET /mati', 401)], 10)).join('\n'), /tidak ada respons sukses/);
    assert.deepEqual(loadFailures(summarize([r('GET /ok', 200, 300)], 10)), []);
});

test('tanpa permintaan sama sekali bukan lulus', () => {
    assert.match(loadFailures(summarize([], 10)).join('\n'), /tidak ada permintaan/);
});

test('latensi notifikasi: gagal membuat, tidak sampai, terlambat; lulus bila ≤60 dtk', () => {
    assert.match(notifyFailures({ createdStatus: 500, id: undefined, inboxSeconds: null })[0], /pembuatan pengumuman gagal/);
    assert.match(notifyFailures({ createdStatus: 201, id: 'a', inboxSeconds: null })[0], /tidak sampai/);
    assert.match(notifyFailures({ createdStatus: 201, id: 'a', inboxSeconds: 61.2 })[0], /> 60 dtk/);
    assert.deepEqual(notifyFailures({ createdStatus: 201, id: 'a', inboxSeconds: 12.5 }), []);
});
