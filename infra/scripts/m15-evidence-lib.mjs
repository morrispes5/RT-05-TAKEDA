// Penilaian bukti M15 tanpa jaringan (dipakai m15-load-check.mjs dan m15-notify-latency.mjs, diuji
// m15-evidence-lib.test.mjs). Klaim "0 error" hanya sah bila SEMUA hasil sesuai status yang diharapkan.
export const GATE = { p95Ms: 1000, notifyMs: 60000 };

export const pct = (xs, q) => {
    const s = [...xs].sort((a, b) => a - b);
    return s.length ? Math.round(s[Math.min(s.length - 1, Math.ceil(q * s.length) - 1)]) : null;
};

// Kegagalan jaringan (0), pembatasan (429), dan 5xx tidak pernah dianggap sesuai harapan.
const transportFailure = status => status === 0 || status === 429 || status >= 500;

/**
 * Status yang diharapkan per skenario. Kunci `"<peran> <label>"` mengalahkan `"<label>"`; bawaan [200].
 * Contoh: { 'admin GET /household': [404] } hanya setelah penyebab 404 diperiksa dan dijelaskan.
 */
export function expectedStatuses(expect, role, label) {
    return expect[`${role} ${label}`] ?? expect[label] ?? [200];
}

export function isExpected(result, expect = {}) {
    return !transportFailure(result.status) && expectedStatuses(expect, result.role, result.label).includes(result.status);
}

export function summarize(results, seconds, expect = {}) {
    const rows = results.map(r => ({ ...r, ok: isExpected(r, expect) }));
    const byLabel = {};
    for (const r of rows) (byLabel[r.label] ||= []).push(r);
    const endpoints = Object.entries(byLabel).sort(([a], [b]) => a.localeCompare(b)).map(([label, rs]) => {
        const good = rs.filter(r => r.ok);
        const statuses = {};
        for (const r of rs) {
            const k = `${r.role} ${r.status}`;
            statuses[k] = (statuses[k] || 0) + 1;
        }
        return {
            label, n: rs.length, ok: good.length, unexpected: rs.length - good.length, statuses,
            // Latensi hanya dari permintaan yang sesuai harapan; respons gagal tidak boleh memperbaiki/merusak P95.
            p50: pct(good.map(r => r.ms), 0.5), p95: pct(good.map(r => r.ms), 0.95), max: good.length ? Math.round(Math.max(...good.map(r => r.ms))) : null,
        };
    });
    const good = rows.filter(r => r.ok);
    return {
        endpoints,
        total: { requests: rows.length, ok: good.length, unexpected: rows.length - good.length, rps: +(rows.length / seconds).toFixed(1), p50: pct(good.map(r => r.ms), 0.5), p95: pct(good.map(r => r.ms), 0.95) },
        statuses: rows.reduce((m, r) => ({ ...m, [r.status]: (m[r.status] || 0) + 1 }), {}),
    };
}

/** Daftar pelanggaran gate beban; kosong = lulus. */
export function loadFailures(summary, gate = GATE) {
    const out = [];
    if (summary.total.requests === 0) out.push('tidak ada permintaan yang berjalan');
    for (const e of summary.endpoints) {
        if (e.unexpected > 0) out.push(`${e.label}: ${e.unexpected}/${e.n} respons di luar harapan (${JSON.stringify(e.statuses)})`);
        if (e.ok === 0) out.push(`${e.label}: tidak ada respons sukses, P95 tidak dapat dinilai`);
        else if (e.p95 > gate.p95Ms) out.push(`${e.label}: P95 ${e.p95} ms > ${gate.p95Ms} ms`);
    }
    return out;
}

/** Pelanggaran gate latensi notifikasi; kosong = lulus. */
export function notifyFailures({ createdStatus, id, inboxSeconds }, gate = GATE) {
    const out = [];
    if (!(createdStatus >= 200 && createdStatus < 300) || !id) out.push(`pembuatan pengumuman gagal (status ${createdStatus}, id ${id ?? 'kosong'})`);
    else if (inboxSeconds === null) out.push('notifikasi tidak sampai ke inbox dalam batas waktu polling');
    else if (inboxSeconds * 1000 > gate.notifyMs) out.push(`notifikasi sampai ${inboxSeconds} dtk > ${gate.notifyMs / 1000} dtk`);
    return out;
}
