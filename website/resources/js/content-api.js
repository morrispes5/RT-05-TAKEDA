// M14: adaptor HTTP CMS web ke API konten RT05 (same origin lewat gateway). Antarmukanya sama
// dengan PreviewContentRepository agar UI tidak bergantung pada sumber data. Sesi memakai cookie
// API HttpOnly + CSRF (cookie rt05_api_xsrf → header X-XSRF-TOKEN); tidak ada token di JavaScript.
const BASE = '/api/v1';
const XSRF = 'rt05_api_xsrf';

export class ApiError extends Error {
    constructor(status, body) {
        const error = body?.error || {};
        const fields = Object.values(error.fields || {}).flat().filter(Boolean);
        const message = status === 0 ? 'Koneksi ke server gagal. Periksa internet, lalu coba lagi.'
            : status === 401 ? 'Sesi pengurus berakhir. Masuk lagi untuk melanjutkan.'
            : status === 403 ? 'Akun ini tidak memiliki akses pengurus.'
            : status === 419 ? 'Sesi keamanan halaman kedaluwarsa. Muat ulang halaman, lalu coba lagi.'
            : status === 429 ? 'Terlalu banyak percobaan. Tunggu sebentar, lalu coba lagi.'
            : status >= 500 ? 'Server sedang bermasalah. Coba lagi beberapa saat lagi.'
            : [error.message, ...fields].filter(Boolean).filter((m, i, all) => all.indexOf(m) === i).join(' ') || 'Permintaan tidak dapat diproses.';
        super(message);
        this.status = status; this.code = error.code || null; this.fields = error.fields || {};
    }
}

function xsrf() {
    const match = document.cookie.split('; ').find(row => row.startsWith(XSRF + '='));
    return match ? decodeURIComponent(match.slice(XSRF.length + 1)) : null;
}

async function csrf() { await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json' } }); }

export async function request(method, path, body, { raw = false } = {}) {
    const mutation = method !== 'GET';
    if (mutation && !xsrf()) await csrf();
    const send = () => {
        const headers = { Accept: 'application/json' };
        if (mutation) headers['X-XSRF-TOKEN'] = xsrf() || '';
        if (body && !(body instanceof FormData)) headers['Content-Type'] = 'application/json';
        return fetch(BASE + path, {
            method, credentials: 'same-origin', headers, body: body instanceof FormData ? body : body ? JSON.stringify(body) : undefined,
        });
    };
    let response;
    try {
        response = await send();
        // Token CSRF kedaluwarsa (mis. tab lama): ambil token baru satu kali, lalu ulangi.
        if (response.status === 419 && mutation) { await csrf(); response = await send(); }
    } catch { throw new ApiError(0); }
    if (raw && response.ok) return response;
    const json = response.status === 204 ? null : await response.json().catch(() => null);
    if (!response.ok) throw new ApiError(response.status, json);
    return json;
}

export const session = {
    async me() { return (await request('GET', '/auth/me')).data; },
    async login(email, password) { await csrf(); return (await request('POST', '/auth/web-login', { email, password })).data; },
    async logout() { await request('POST', '/auth/logout'); },
};

export function slugify(text) {
    return (text || '').normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase()
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120).replace(/-+$/, '') || 'konten';
}

const blank = value => (typeof value === 'string' && value.trim() === '' ? null : value ?? null);

export class ApiContentRepository {
    constructor() {
        this.mode = 'server';
        this.autosave = false;
        this.alias = new Map(); // id lokal (editor baru / foto baru) → id server
        this.versions = new Map(); // id server → versi terakhir yang diketahui
        this.blobs = new Map(); // id foto → blob pratinjau
    }
    serverId(id) { return this.alias.get(id) || id; }
    fromApi(type, item) {
        const d = item.draf || {};
        this.versions.set(item.id, item.versi);
        const base = {
            id: item.id, slug: item.slug, versi: item.versi, serverStatus: item.status, pending: !!item.ada_perubahan_belum_terbit,
            publishedSlug: item.slug_terbit, status: item.status === 'draf' ? 'draft' : item.status === 'arsip' ? 'archived' : 'published', updatedAt: item.updated_at, title: d.title || '',
        };
        if (type === 'articles') {
            return { ...base, category: d.category || 'Lingkungan', art: d.art || 'hands', summary: d.summary || '', intro: d.intro || '',
                sections: d.sections?.length ? d.sections : [{ title: '', body: '' }], note: d.note || '', source: d.source || null,
                cover: d.cover_media_id ? { id: d.cover_media_id, mediaId: d.cover_media_id, alt: d.cover_alt || '' } : null };
        }
        const photos = (d.photos || []).map(p => ({ id: p.media_id, mediaId: p.media_id, alt: p.alt || '', caption: p.caption || '' }));
        return { ...base, description: d.description || '', date: d.date || '', photos, coverId: d.cover_media_id || photos[0]?.id || null };
    }
    toApi(type, item) {
        const media = id => this.serverId(id);
        if (type === 'articles') {
            const label = blank(item.source?.label), url = blank(item.source?.url);
            return { title: item.title.trim(), category: item.category, art: item.art || null, summary: item.summary.trim(), intro: item.intro.trim(),
                sections: item.sections.map(s => ({ title: s.title.trim(), body: s.body.trim() })), note: blank(item.note),
                source: label || url ? { label, url } : null,
                cover_media_id: item.cover ? media(item.cover.mediaId) : null, cover_alt: item.cover ? blank(item.cover.alt) : null };
        }
        const cover = item.photos.find(p => p.id === item.coverId);
        return { title: item.title.trim(), description: item.description.trim(), date: blank(item.date),
            photos: item.photos.map(p => ({ media_id: media(p.mediaId), alt: p.alt.trim(), caption: blank(p.caption) })),
            cover_media_id: cover ? media(cover.mediaId) : null };
    }
    /** `status: 'arsip'` (UAT M15) menampilkan konten yang diarsipkan; bawaan semua kecuali arsip. */
    async list(type, { status } = {}) {
        const records = [];
        for (let page = 1; page <= 10; page++) {
            const res = await request('GET', `/admin/${type}?per_page=100&page=${page}${status ? '&status=' + status : ''}`);
            records.push(...res.data.map(item => this.fromApi(type, item)));
            if (!res.meta?.last_page || page >= res.meta.last_page) break;
        }
        return records;
    }
    async get(type, id) {
        const sid = this.serverId(id);
        if (!/^[0-9a-f-]{36}$/i.test(sid)) return null;
        try { return this.fromApi(type, (await request('GET', `/admin/${type}/${sid}`)).data); }
        catch (error) { if (error.status === 404) return null; throw error; }
    }
    async upload(file) {
        const form = new FormData();
        form.append('foto', file.blob, 'foto.' + (file.blob.type === 'image/png' ? 'png' : file.blob.type === 'image/jpeg' ? 'jpg' : 'webp'));
        const res = await request('POST', '/admin/content-media', form);
        this.alias.set(file.id, res.data.id); this.blobs.set(file.id, file.blob); this.blobs.set(res.data.id, file.blob);
        return res.data.id;
    }
    /** Unggah foto yang belum terkirim, lalu simpan draf ke server (POST pertama, PUT dengan versi berikutnya). */
    async save(type, item, media = []) {
        for (const file of media) if (!this.alias.has(file.id)) await this.upload(file);
        const draf = this.toApi(type, item);
        const sid = this.alias.get(item.id) || (item.versi ? item.id : null);
        const res = sid
            ? await request('PUT', `/admin/${type}/${sid}`, { versi: this.versions.get(sid) || item.versi, slug: item.slug || slugify(item.title), draf })
            : await request('POST', `/admin/${type}`, { slug: slugify(item.title), draf });
        if (!sid) this.alias.set(item.id, res.data.id);
        return this.fromApi(type, res.data);
    }
    async publish(type, id) {
        const sid = this.serverId(id);
        return this.fromApi(type, (await request('POST', `/admin/${type}/${sid}/publish`, { versi: this.versions.get(sid) })).data);
    }
    async archive(type, id) {
        const sid = this.serverId(id);
        if (!this.versions.has(sid)) await this.get(type, sid);
        await request('POST', `/admin/${type}/${sid}/archive`, { versi: this.versions.get(sid) });
    }
    /** Pulihkan arsip menjadi draf (ADR34); belum tampil di website sampai diterbitkan ulang. */
    async restore(type, id) {
        const sid = this.serverId(id);
        if (!this.versions.has(sid)) await this.get(type, sid);
        await request('POST', `/admin/${type}/${sid}/restore`, { versi: this.versions.get(sid) });
    }
    async media(id) {
        if (this.blobs.has(id)) return this.blobs.get(id);
        const response = await request('GET', `/admin/content-media/${this.serverId(id)}/file`, null, { raw: true });
        const blob = await response.blob(); this.blobs.set(id, blob); return blob;
    }
    async reset() {}
}
