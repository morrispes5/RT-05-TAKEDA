// The UI depends on this asynchronous repository, not on IndexedDB internals.
// A Laravel HTTP adapter can replace it when the website moves to a PHP runtime.
const DATABASE = 'rt05-content-preview';
export class PreviewContentRepository {
    constructor(seeds) { this.seeds = seeds; this.connection = null; }
    async open() {
        if (this.connection) return this.connection;
        this.connection = await new Promise((resolve, reject) => {
            const request = indexedDB.open(DATABASE, 1);
            request.onupgradeneeded = () => {
                for (const name of ['articles', 'albums', 'media']) request.result.createObjectStore(name, { keyPath: 'id' });
            };
            request.onsuccess = () => {
                const db = request.result;
                db.onversionchange = () => { db.close(); this.connection = null; };
                resolve(db);
            };
            request.onerror = () => reject(request.error);
            request.onblocked = () => reject(new Error('Tutup tab Pengurus lain, lalu coba lagi.'));
        });
        return this.connection;
    }
    async transaction(names, mode, run) {
        const db = await this.open();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(names, mode);
            let result;
            tx.oncomplete = () => resolve(result);
            tx.onerror = tx.onabort = () => reject(tx.error || new Error('Penyimpanan browser gagal.'));
            try { run(tx, value => { result = value; }); }
            catch (error) { tx.abort(); reject(error); }
        });
    }
    async list(type) {
        const records = await this.transaction([type], 'readonly', (tx, done) => {
            const req = tx.objectStore(type).getAll(); req.onsuccess = () => done(req.result);
        });
        const merged = new Map(this.seeds[type].map(item => [item.id, structuredClone(item)]));
        records.forEach(item => merged.set(item.id, item));
        return [...merged.values()].filter(item => !item.archived).sort((a, b) => (b.updatedAt || '').localeCompare(a.updatedAt || ''));
    }
    async get(type, id) { return (await this.list(type)).find(item => item.id === id); }
    async save(type, item, media = []) {
        const value = { ...structuredClone(item), updatedAt: new Date().toISOString(), status: 'draft' };
        await this.transaction([type, 'media'], 'readwrite', (tx, done) => {
            const store = tx.objectStore(type);
            const before = store.get(value.id);
            const referenced = record => [...(record?.photos || []), record?.cover].filter(Boolean).map(photo => photo.mediaId).filter(Boolean);
            before.onsuccess = () => {
                const currentIds = new Set(referenced(value));
                referenced(before.result).filter(id => !currentIds.has(id)).forEach(id => tx.objectStore('media').delete(id));
            };
            media.forEach(file => tx.objectStore('media').put(file));
            store.put(value); done(value);
        });
        return value;
    }
    async archive(type, id) {
        // Preserve tombstones so a removed seed does not reappear. Delete its local media atomically.
        const item = await this.get(type, id);
        const ids = [...(item?.photos || []), item?.cover].filter(Boolean).map(p => p.mediaId).filter(Boolean);
        await this.transaction([type, 'media'], 'readwrite', tx => {
            ids.forEach(key => tx.objectStore('media').delete(key));
            tx.objectStore(type).put({ ...item, id, archived: true });
        });
    }
    async media(id) {
        return this.transaction(['media'], 'readonly', (tx, done) => {
            const req = tx.objectStore('media').get(id); req.onsuccess = () => done(req.result?.blob);
        });
    }
    async reset() {
        await this.transaction(['articles', 'albums', 'media'], 'readwrite', tx => {
            for (const store of ['articles', 'albums', 'media']) tx.objectStore(store).clear();
        });
    }
}

export async function preparePhoto(file) {
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) throw new Error('Pilih foto JPG, PNG, atau WebP.');
    if (file.size > 12 * 1024 * 1024) throw new Error('Ukuran foto maksimal 12 MB.');
    const bitmap = await createImageBitmap(file);
    if (bitmap.width * bitmap.height > 50_000_000) { bitmap.close(); throw new Error('Resolusi foto terlalu besar. Pilih foto di bawah 50 megapiksel.'); }
    const ratio = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * ratio); canvas.height = Math.round(bitmap.height * ratio);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height); bitmap.close();
    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', 0.82));
    if (!blob) throw new Error('Foto tidak dapat dibaca. Coba berkas lain.');
    const id = crypto.randomUUID();
    return { photo: { id, mediaId: id, alt: '', caption: '', width: canvas.width, height: canvas.height }, media: { id, blob } };
}
