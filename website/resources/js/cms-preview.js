import { PreviewContentRepository, preparePhoto } from './preview-content';

const main = document.querySelector('[data-cms]');
const root = main.querySelector('[data-cms-content]');
const actions = main.querySelector('[data-cms-actions]');
const repository = new PreviewContentRepository(JSON.parse(document.getElementById('cms-seeds').textContent));
const screen = main.dataset.cms;
const params = new URLSearchParams(location.search);
const urls = new Set();
let unsaved = false, uploading = false;

// Every user-controlled value is inserted as a text node or a DOM property, never HTML.
function node(tag, attrs = {}, ...children) {
    const element = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs)) {
        if (key.startsWith('on')) element.addEventListener(key.slice(2), value);
        else if (key === 'class') element.className = value;
        else if (['value', 'disabled', 'checked'].includes(key)) element[key] = value;
        else if (value !== null && value !== false) element.setAttribute(key, value === true ? '' : value);
    }
    children.flat(Infinity).forEach(child => { if (child !== null && child !== undefined) element.append(child); });
    return element;
}
const button = (text, run, primary = false, attrs = {}) => node('button', { type: 'button', class: primary ? 'button primary' : 'button secondary', onclick: run, ...attrs }, text);
const link = (text, href, primary = false) => node('a', { href, class: primary ? 'button primary' : 'button secondary' }, text);
const panel = (...children) => node('section', { class: 'cms-panel' }, children);
function photoSymbol() {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 32 32'); svg.setAttribute('width', '32'); svg.setAttribute('height', '32');
    const path = document.createElementNS(svg.namespaceURI, 'path');
    path.setAttribute('d', 'M5 6h22v20H5z M5 23l8-8 6 6 4-4 4 4 M22 10h.01');
    path.setAttribute('fill', 'none'); path.setAttribute('stroke', 'currentColor'); path.setAttribute('stroke-width', '2');
    path.setAttribute('stroke-linecap', 'round'); path.setAttribute('stroke-linejoin', 'round'); svg.append(path); return svg;
}
const typePath = type => type === 'articles' ? 'artikel' : 'dokumentasi';
const editorUrl = (type, id) => '/pengurus/' + typePath(type) + '/editor' + (id ? '?id=' + encodeURIComponent(id) : '');
const previewUrl = (type, id) => '/pengurus/pratinjau?jenis=' + type + '&id=' + encodeURIComponent(id);
function heading(title, subtitle) {
    main.querySelector('[data-cms-heading]').textContent = title;
    main.querySelector('[data-cms-subtitle]').textContent = subtitle;
}
function safeSource(value) {
    try { const url = new URL(value); return ['https:', 'http:'].includes(url.protocol) ? url.href : null; } catch { return null; }
}
async function photoUrl(photo) {
    if (!photo.mediaId) return photo.src;
    const blob = await repository.media(photo.mediaId);
    if (!blob) throw new Error('Foto lokal tidak tersedia. Kembali ke editor dan pilih foto kembali.');
    const url = URL.createObjectURL(blob); urls.add(url); return url;
}
function revokeUrls() { urls.forEach(url => URL.revokeObjectURL(url)); urls.clear(); }
window.addEventListener('pagehide', revokeUrls);
window.addEventListener('beforeunload', event => { if (unsaved || uploading) { event.preventDefault(); event.returnValue = ''; } });
async function confirm(title, description, label) {
    const opener = document.activeElement;
    return new Promise(resolve => {
        const dialog = node('dialog', { class: 'cms-confirm', 'aria-labelledby': 'confirm-title' },
            node('h2', { id: 'confirm-title' }, title), node('p', {}, description),
            node('div', { class: 'cms-action-row' }, button('Batal', () => dialog.close('cancel')), button(label, () => dialog.close('yes'), true)));
        dialog.addEventListener('close', () => { const yes = dialog.returnValue === 'yes'; dialog.remove(); opener?.focus(); resolve(yes); }, { once: true });
        document.body.append(dialog); dialog.showModal();
    });
}
function failure(error, retry = () => location.reload()) {
    root.replaceChildren(panel(node('h2', {}, 'Konten belum dapat dibuka.'), node('p', { role: 'alert' }, 'Penyimpanan browser tidak tersedia. Aktifkan penyimpanan situs, lalu coba lagi. ' + (error.message || '')),
        button('Coba lagi', retry, true), link('Kembali ke website', '/')));
}
async function reset() {
    if (!await confirm('Reset pratinjau?', 'Semua draf dan foto percobaan di browser ini akan dihapus. Konten website publik tetap tersedia.', 'Reset pratinjau')) return;
    try { await repository.reset(); revokeUrls(); await render(); } catch (error) { failure(error); }
}
function field(label, value, change, options = {}) {
    const { tag = 'input', hint, ...attrs } = options;
    const input = node(tag, { class: 'cms-input', 'aria-label': label, value: value || '', ...attrs, oninput: event => change(event.target.value) });
    return node('label', { class: 'cms-field' }, node('span', {}, label), input, hint ? node('small', {}, hint) : null);
}
async function overview() {
    const [articles, albums] = await Promise.all([repository.list('articles'), repository.list('albums')]);
    actions.replaceChildren(button('Reset pratinjau', reset));
    root.replaceChildren(node('div', { class: 'cms-overview' },
        panel(node('span', { class: 'cms-overview-symbol', 'aria-hidden': 'true' }, 'Aa'), node('h2', {}, 'Artikel'), node('p', {}, 'Bacaan yang membantu keseharian warga. Tulis, rapikan, lalu lihat hasilnya.'),
            node('p', { class: 'cms-count' }, articles.filter(a => a.status === 'draft').length + ' draf di perangkat ini'), link('Kelola artikel', '/pengurus/artikel', true)),
        panel(node('span', { class: 'cms-overview-symbol cms-photo-symbol', 'aria-hidden': 'true' }, photoSymbol()), node('h2', {}, 'Dokumentasi'), node('p', {}, 'Simpan cerita kegiatan dalam sebuah album. Pilih foto, keterangan, dan sampul.'),
            node('p', { class: 'cms-count' }, albums.filter(a => a.status === 'draft').length + ' draf di perangkat ini'), link('Kelola album', '/pengurus/dokumentasi', true))),
        panel(node('h2', {}, 'Mulai dari cerita yang sudah ada.'), node('p', {}, 'Coba mengedit bacaan atau album Perayaan 17 Agustus. Hasil percobaan dapat dilihat di pratinjau tanpa mengubah konten publik.'),
            node('div', { class: 'cms-action-row' }, link('Coba editor artikel', editorUrl('articles', articles[0]?.id)), link('Coba editor album', editorUrl('albums', albums[0]?.id)))));
}
async function listing(type) {
    const records = await repository.list(type);
    actions.replaceChildren(link(type === 'articles' ? 'Tulis artikel' : 'Buat album', editorUrl(type), true));
    const list = node('div', { class: 'cms-content-list' });
    const input = field('Cari ' + (type === 'articles' ? 'artikel' : 'album'), '', term => {
        let count = 0;
        list.querySelectorAll('[data-record]').forEach(row => { row.hidden = !row.dataset.search.includes(term.toLocaleLowerCase('id')); if (!row.hidden) count++; });
        status.textContent = count + ' konten ditampilkan'; empty.hidden = count > 0;
    }, { type: 'search', placeholder: 'Cari judul' });
    const status = node('p', { class: 'cms-list-status', role: 'status' }, records.length + ' konten ditampilkan');
    const empty = node('p', { class: 'cms-empty', hidden: records.length > 0 }, 'Belum ada konten yang cocok. Mulai konten baru atau gunakan kata lain.');
    for (const record of records) {
        const row = node('article', { class: 'cms-content-row', 'data-record': record.id, 'data-search': record.title.toLocaleLowerCase('id') });
        if (type === 'albums' && record.photos.length) {
            const photo = record.photos.find(p => p.id === record.coverId) || record.photos[0];
            row.append(node('img', { class: 'cms-list-cover', src: await photoUrl(photo), alt: photo.alt || 'Sampul album', width: 160, height: 112 }));
        }
        row.append(node('div', { class: 'cms-row-copy' }, node('span', { class: 'cms-record-status' }, record.status === 'draft' ? 'Draf di perangkat' : 'Contoh dari website'),
            node('h2', {}, node('a', { href: editorUrl(type, record.id) }, record.title || 'Tanpa judul')), node('p', {}, type === 'articles' ? record.category : record.photos.length + ' foto')),
            node('div', { class: 'cms-row-actions' }, link('Edit', editorUrl(type, record.id)), button(record.status === 'draft' ? 'Hapus draf' : 'Sembunyikan contoh', async () => {
                if (await confirm('Hapus dari pratinjau?', 'Perubahan hanya berlaku di browser ini. Konten publik tidak dihapus.', 'Hapus')) {
                    try { await repository.archive(type, record.id); revokeUrls(); await listing(type); } catch (error) { failure(error); }
                }
            })));
        list.append(row);
    }
    root.replaceChildren(panel(input, status, list, empty));
}
async function editor(type) {
    const id = params.get('id') || crypto.randomUUID();
    let record = params.has('id') ? await repository.get(type, id) : null;
    if (params.has('id') && !record) { root.replaceChildren(panel(node('h2', {}, 'Draf tidak ditemukan.'), node('p', {}, 'Draf mungkin sudah dihapus atau dibuat di browser lain.'), link('Kembali ke daftar', '/pengurus/' + typePath(type)))); return; }
    record = structuredClone(record || (type === 'articles' ? { id, title: '', category: 'Lingkungan', summary: '', intro: '', art: 'hands', sections: [{ title: '', body: '' }], note: 'Bacaan edukasi. Bukan pengumuman resmi RT.' } : { id, title: '', description: '', date: '', photos: [], coverId: null }));
    history.replaceState(null, '', editorUrl(type, id));
    heading(type === 'articles' ? 'Tulis sebuah bacaan.' : 'Susun cerita dalam foto.', 'Simpan draf, lalu periksa hasilnya sebelum digunakan.');
    let timer, revision = 0, savedRevision = 0, saving = Promise.resolve(), media = [];
    const status = node('p', { class: 'cms-save-status', role: 'status' }, record.status === 'draft' ? 'Draf tersimpan di perangkat ini.' : 'Perubahan belum disimpan.');
    const errors = node('p', { class: 'cms-error', role: 'alert', hidden: true });
    const form = node('form', { class: 'cms-editor-grid', onsubmit: event => event.preventDefault() });
    const editPanel = panel();
    const side = node('aside', { class: 'cms-editor-side' }, panel(node('h2', {}, 'Dari cerita ke bacaan.'), node('p', {}, type === 'articles' ? 'Gunakan judul yang jelas. Pisahkan isi menjadi bagian pendek agar nyaman dibaca di HP.' : 'Pilih foto yang paling mewakili kegiatan untuk sampul. Tambahkan keterangan agar ceritanya mudah dipahami.'), status, errors));
    function dirty() { unsaved = true; revision++; status.textContent = 'Ada perubahan. Menyimpan draf…'; clearTimeout(timer); timer = setTimeout(() => save(), 800); }
    async function save() {
        clearTimeout(timer);
        const version = revision, snapshot = structuredClone(record), files = [...media];
        saving = saving.catch(() => {}).then(async () => {
            try {
                await repository.save(type, snapshot, files);
                media = media.filter(file => !files.some(saved => saved.id === file.id));
                savedRevision = version; unsaved = savedRevision < revision;
                status.textContent = unsaved ? 'Ada perubahan baru. Menyimpan…' : 'Draf tersimpan di perangkat ini.';
                errors.hidden = true; return true;
            } catch {
                unsaved = true; status.textContent = 'Draf belum tersimpan.'; errors.hidden = false;
                errors.textContent = 'Browser tidak dapat menyimpan draf. Isianmu masih ada. Periksa ruang penyimpanan, lalu tekan Simpan draf untuk mencoba lagi.'; return false;
            }
        });
        return saving;
    }
    async function preview() {
        if (uploading) { errors.hidden = false; errors.textContent = 'Tunggu sampai foto selesai disiapkan.'; return; }
        if (!form.reportValidity()) return;
        if (type === 'albums' && !record.photos.length) { errors.hidden = false; errors.textContent = 'Tambahkan setidaknya satu foto sebelum melihat pratinjau.'; return; }
        if (!await save()) return;
        if (await confirm('Lihat hasil pratinjau?', 'Draf tersimpan di browser ini. Hasilnya tidak dipublikasikan ke website.', 'Lihat pratinjau')) location.href = previewUrl(type, id);
    }
    actions.replaceChildren(button('Simpan draf', () => save()), button('Lihat pratinjau', preview, true));
    editPanel.append(field(type === 'articles' ? 'Judul artikel' : 'Judul album', record.title, value => { record.title = value; dirty(); }, { required: true, maxlength: type === 'articles' ? 180 : 160, placeholder: type === 'articles' ? 'Apa yang ingin dibagikan?' : 'Nama kegiatan' }));
    if (type === 'articles') {
        const select = node('select', { class: 'cms-input', onchange: event => { record.category = event.target.value; dirty(); } }, ['Lingkungan', 'Kesehatan', 'Keamanan', 'Kebersamaan'].map(category => node('option', { value: category }, category)));
        if (![...select.options].some(o => o.value === record.category)) select.append(node('option', { value: record.category }, record.category));
        select.value = record.category;
        editPanel.append(node('label', { class: 'cms-field' }, node('span', {}, 'Kategori'), select),
            field('Ringkasan', record.summary, value => { record.summary = value; dirty(); }, { tag: 'textarea', required: true, maxlength: 300, rows: 3, hint: 'Kalimat singkat yang muncul di daftar bacaan.' }),
            field('Pembuka', record.intro, value => { record.intro = value; dirty(); }, { tag: 'textarea', required: true, rows: 4, maxlength: 5000 }));
        const sections = node('div', { class: 'cms-sections' });
        function moveSection(index, direction) {
            const target = index + direction;
            if (target < 0 || target >= record.sections.length) return;
            [record.sections[index], record.sections[target]] = [record.sections[target], record.sections[index]];
            drawSections(target); dirty();
        }
        function drawSections(focusIndex) {
            sections.replaceChildren(...record.sections.map((section, index) => panel(node('div', { class: 'cms-section-title' }, node('h2', {}, 'Bagian ' + (index + 1)),
                node('div', { class: 'cms-action-row' }, button('Naik', () => moveSection(index, -1), false, { disabled: index === 0, 'aria-label': 'Naikkan bagian ' + (index + 1) }), button('Turun', () => moveSection(index, 1), false, { disabled: index === record.sections.length - 1, 'aria-label': 'Turunkan bagian ' + (index + 1) }),
                    button('Hapus', () => { record.sections.splice(index, 1); drawSections(Math.max(0, index - 1)); dirty(); }, false, { disabled: record.sections.length === 1, 'aria-label': 'Hapus bagian ' + (index + 1) }))),
                field('Subjudul bagian ' + (index + 1), section.title, value => { section.title = value; dirty(); }, { required: true, maxlength: 180 }),
                field('Isi bagian ' + (index + 1), section.body, value => { section.body = value; dirty(); }, { tag: 'textarea', required: true, rows: 6, maxlength: 10000 }))));
            if (focusIndex !== undefined) sections.children[focusIndex]?.querySelector('input')?.focus();
        }
        drawSections();
        editPanel.append(node('h2', { class: 'cms-editor-label' }, 'Isi bacaan'), sections, button('Tambah bagian', () => {
            if (record.sections.length >= 30) return; record.sections.push({ title: '', body: '' }); drawSections(record.sections.length - 1); dirty();
        }), field('Nama sumber bacaan (opsional)', record.source?.label, value => { record.source = { ...record.source, label: value }; dirty(); }, { maxlength: 180 }),
        field('Tautan sumber (opsional)', record.source?.url, value => { record.source = { ...record.source, url: value }; dirty(); }, { type: 'url', pattern: 'https?://.*', placeholder: 'https://…' }));
        const coverBox = node('div', { class: 'cms-cover-box' });
        const coverInput = node('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', class: 'cms-input', onchange: async event => {
            const file = event.target.files[0]; if (!file) return;
            uploading = true; status.textContent = 'Menyiapkan sampul…';
            try { const prepared = await preparePhoto(file); media.push(prepared.media); record.cover = prepared.photo; await drawCover(); dirty(); } catch (error) { errors.hidden = false; errors.textContent = error.message; }
            finally { uploading = false; event.target.value = ''; }
        }});
        async function drawCover() {
            coverBox.replaceChildren(); if (!record.cover) return;
            const pending = media.find(m => m.id === record.cover.mediaId);
            const url = pending ? URL.createObjectURL(pending.blob) : await photoUrl(record.cover); urls.add(url);
            coverBox.append(node('img', { src: url, alt: record.cover.alt || 'Pratinjau sampul', width: record.cover.width, height: record.cover.height }),
                field('Deskripsi foto sampul', record.cover.alt, value => { record.cover.alt = value; dirty(); }, { required: true, maxlength: 300 }),
                button('Hapus sampul', () => { record.cover = null; drawCover(); dirty(); }));
        }
        editPanel.append(node('label', { class: 'cms-field' }, node('span', {}, 'Sampul (opsional)'), coverInput, node('small', {}, 'JPG, PNG, atau WebP. Maksimal 12 MB.')), coverBox); await drawCover();
    } else {
        editPanel.append(field('Cerita kegiatan', record.description, value => { record.description = value; dirty(); }, { tag: 'textarea', required: true, rows: 4, maxlength: 5000 }),
            field('Tanggal kegiatan (opsional)', record.date, value => { record.date = value; dirty(); }, { type: 'date', hint: 'Kosongkan jika tanggal belum pasti.' }));
        const photos = node('div', { class: 'cms-photo-list' });
        const progress = node('p', { role: 'status', class: 'cms-upload-status' });
        const upload = node('input', { type: 'file', multiple: true, accept: 'image/jpeg,image/png,image/webp', class: 'cms-input', onchange: async event => {
            const files = [...event.target.files]; if (!files.length) return;
            if (record.photos.length + files.length > 20) { errors.hidden = false; errors.textContent = 'Maksimal 20 foto per album. Pilih lebih sedikit foto.'; event.target.value = ''; return; }
            uploading = true; upload.disabled = true; const failures = [];
            for (let index = 0; index < files.length; index++) {
                progress.textContent = 'Menyiapkan foto ' + (index + 1) + ' dari ' + files.length + '…';
                try { const prepared = await preparePhoto(files[index]); media.push(prepared.media); record.photos.push(prepared.photo); record.coverId ||= prepared.photo.id; } catch (error) { failures.push(files[index].name + ': ' + error.message); }
            }
            uploading = false; upload.disabled = false; event.target.value = '';
            try { await drawPhotos(); dirty(); await save(); } catch (error) { failures.push(error.message); }
            progress.textContent = 'Foto selesai disiapkan di perangkat ini.';
            if (failures.length) { errors.hidden = false; errors.textContent = failures.join(' '); }
        }});
        async function drawPhotos(focusIndex) {
            photos.replaceChildren();
            if (!record.photos.length) photos.append(node('p', { class: 'cms-empty' }, 'Belum ada foto. Pilih foto kegiatan dari perangkatmu.'));
            for (const [index, photo] of record.photos.entries()) {
                const pending = media.find(m => m.id === photo.mediaId);
                const url = pending ? URL.createObjectURL(pending.blob) : await photoUrl(photo); urls.add(url);
                const move = async direction => {
                    const target = index + direction;
                    if (target < 0 || target >= record.photos.length) return;
                    [record.photos[index], record.photos[target]] = [record.photos[target], record.photos[index]]; await drawPhotos(target); dirty();
                };
                photos.append(node('section', { class: 'cms-photo-row' }, node('img', { src: url, alt: photo.alt || 'Pratinjau foto yang dipilih', width: photo.width, height: photo.height }),
                    node('div', {}, node('div', { class: 'cms-section-title' }, node('h2', {}, 'Foto ' + (index + 1)), node('span', { class: 'cms-record-status' }, photo.id === record.coverId ? 'Sampul album' : '')),
                        field('Keterangan foto ' + (index + 1), photo.caption, value => { photo.caption = value; dirty(); }, { maxlength: 500 }),
                        field('Deskripsi foto ' + (index + 1), photo.alt, value => { photo.alt = value; dirty(); }, { required: true, maxlength: 300, hint: 'Jelaskan apa yang terlihat, untuk pembaca yang tidak melihat foto.' }),
                        node('div', { class: 'cms-action-row' }, button('Jadikan sampul', async () => { record.coverId = photo.id; await drawPhotos(index); dirty(); }, false, { disabled: photo.id === record.coverId }),
                            button('Naik', () => move(-1), false, { disabled: index === 0, 'aria-label': 'Naikkan foto ' + (index + 1) }), button('Turun', () => move(1), false, { disabled: index === record.photos.length - 1, 'aria-label': 'Turunkan foto ' + (index + 1) }),
                            button('Hapus foto', async () => {
                                if (!await confirm('Hapus foto dari album?', 'Foto di perangkat asalmu tidak dihapus.', 'Hapus foto')) return;
                                record.photos.splice(index, 1); if (record.coverId === photo.id) record.coverId = record.photos[0]?.id || null; await drawPhotos(); dirty();
                            }, false, { 'aria-label': 'Hapus foto ' + (index + 1) })))));
            }
            if (focusIndex !== undefined) photos.children[focusIndex]?.querySelector('input')?.focus();
        }
        editPanel.append(node('label', { class: 'cms-upload cms-field' }, node('span', {}, 'Tambahkan foto kegiatan'), upload, node('small', {}, 'Pilih beberapa foto. JPG, PNG, atau WebP; maksimal 12 MB per foto dan 20 foto per album. Foto hanya disimpan di browser.')), progress, photos);
        await drawPhotos();
    }
    side.append(panel(node('h2', {}, 'Periksa sebelum dibagikan.'), node('p', {}, 'Pastikan keterangan sesuai foto dan gunakan konten yang boleh dipublikasikan.'), link('Kembali ke daftar', '/pengurus/' + typePath(type))));
    form.append(editPanel, side); root.replaceChildren(form);
}
async function preview() {
    const type = params.get('jenis');
    if (!['articles', 'albums'].includes(type)) { root.replaceChildren(panel(node('h2', {}, 'Pilih konten untuk dilihat.'), node('p', {}, 'Buka editor artikel atau album, lalu tekan Lihat pratinjau.'), link('Pilih artikel', '/pengurus/artikel'), link('Pilih album', '/pengurus/dokumentasi'))); return; }
    const record = await repository.get(type, params.get('id'));
    if (!record) { root.replaceChildren(panel(node('h2', {}, 'Draf tidak ditemukan.'), node('p', {}, 'Draf hanya tersedia di browser tempat membuatnya.'), link('Kembali ke daftar', '/pengurus/' + typePath(type)))); return; }
    heading(record.title, type === 'articles' ? record.summary : record.description);
    actions.replaceChildren(link('Kembali ke editor', editorUrl(type, record.id), true));
    const content = node('article', { class: 'cms-public-preview' });
    if (type === 'articles') {
        content.append(node('p', { class: 'cms-preview-meta' }, record.category + ' — ' + Math.max(1, Math.ceil((record.intro + ' ' + record.sections.map(s => s.body).join(' ')).split(/\s+/).length / 180)) + ' menit baca'));
        if (record.cover) content.append(node('img', { src: await photoUrl(record.cover), alt: record.cover.alt, class: 'cms-preview-cover' }));
        content.append(node('p', { class: 'lead' }, record.intro), record.sections.map(section => node('section', {}, node('h2', {}, section.title), node('p', {}, section.body))));
        if (record.source?.url && safeSource(record.source.url)) content.append(node('a', { href: safeSource(record.source.url), rel: 'noopener', class: 'text-link' }, record.source.label || 'Sumber bacaan'));
    } else {
        if (record.date) content.append(node('p', {}, new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(new Date(record.date + 'T12:00:00'))));
        for (const photo of record.photos) {
            const url = await photoUrl(photo);
            content.append(node('figure', {}, button('Perbesar foto', () => {
                const dialog = node('dialog', { class: 'cms-photo-dialog', 'aria-label': 'Foto diperbesar' }, button('Tutup', () => dialog.close()), node('img', { src: url, alt: photo.alt }), node('p', {}, photo.caption));
                const opener = document.activeElement;
                dialog.addEventListener('close', () => { dialog.remove(); opener?.focus(); }, { once: true }); document.body.append(dialog); dialog.showModal();
            }), node('img', { src: url, alt: photo.alt, width: photo.width, height: photo.height }), node('figcaption', {}, photo.caption)));
        }
    }
    root.replaceChildren(node('p', { class: 'cms-preview-reminder' }, 'Hasil pratinjau di perangkat ini. Belum dipublikasikan ke website.'), content);
}
async function render() {
    try {
        if (screen === 'masuk') {
            root.replaceChildren(node('div', { class: 'cms-access' }, panel(node('img', { src: '/images/logo/rt05-mark.svg', width: 100, height: 100, alt: '' }), node('h2', {}, 'Artikel dan dokumentasi, dikelola bersama.'), node('p', {}, 'Akses pengurus akan diaktifkan ketika website memiliki layanan penyimpanan online. Sekarang kamu dapat mencoba editor tanpa memasukkan email atau kata sandi.'), link('Masuk ke pratinjau', '/pengurus', true), link('Kembali ke website', '/'))));
        } else if (screen === 'ringkasan') await overview();
        else if (screen === 'pratinjau') await preview();
        else if (screen.endsWith('/editor')) await editor(screen.startsWith('artikel') ? 'articles' : 'albums');
        else await listing(screen === 'artikel' ? 'articles' : 'albums');
    } catch (error) { failure(error); }
    finally { main.dataset.cmsReady = 'true'; }
}
await render();
