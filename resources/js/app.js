document.documentElement.classList.add('js');

// Menu mobile
const toggle = document.querySelector('[data-menu-toggle]');
const menu = document.querySelector('[data-menu]');

if (toggle && menu) {
    const setOpen = (open) => {
        menu.classList.toggle('hidden', !open);
        toggle.setAttribute('aria-expanded', String(open));
    };

    toggle.addEventListener('click', () => setOpen(menu.classList.contains('hidden')));
    menu.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
}

// Lightbox foto: <dialog> bawaan browser sudah menangani Esc dan fokus.
const dialog = document.getElementById('lightbox');

if (dialog) {
    const img = dialog.querySelector('[data-lightbox-img]');
    const caption = dialog.querySelector('[data-lightbox-caption]');
    const prev = dialog.querySelector('[data-lightbox-prev]');
    const next = dialog.querySelector('[data-lightbox-next]');
    let group = [];
    let index = 0;

    const show = (i) => {
        index = (i + group.length) % group.length;
        const item = group[index].dataset;
        img.src = item.src;
        img.alt = item.alt;
        caption.textContent = item.caption;
        prev.hidden = next.hidden = group.length < 2;
    };

    document.querySelectorAll('[data-lightbox]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            group = [...document.querySelectorAll(`[data-lightbox="${trigger.dataset.lightbox}"]`)];
            show(group.indexOf(trigger));
            dialog.showModal();
        });
    });

    prev.addEventListener('click', () => show(index - 1));
    next.addEventListener('click', () => show(index + 1));
    dialog.querySelector('[data-lightbox-close]').addEventListener('click', () => dialog.close());

    dialog.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft' && group.length > 1) show(index - 1);
        if (e.key === 'ArrowRight' && group.length > 1) show(index + 1);
    });

    // Klik di luar foto menutup lightbox.
    dialog.addEventListener('click', (e) => {
        if (e.target === dialog || e.target.tagName === 'FIGURE') dialog.close();
    });
}
