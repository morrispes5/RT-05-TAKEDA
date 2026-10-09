document.documentElement.classList.add('js');

const toggle = document.querySelector('[data-menu-toggle]');
const menu = document.querySelector('[data-menu]');
if (toggle && menu) {
    const setOpen = (open) => {
        menu.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', String(open));
    };
    toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
    menu.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            toggle.focus();
        }
    });
    document.addEventListener('click', e => {
        if (!menu.contains(e.target) && !toggle.contains(e.target)) setOpen(false);
    });
    matchMedia('(min-width: 1025px)').addEventListener('change', () => setOpen(false));
}
document.querySelectorAll('[data-filters]').forEach(bar => {
    const group = bar.dataset.filters;
    const items = [...document.querySelectorAll('[data-filter-item="' + group + '"]')];
    bar.addEventListener('click', e => {
        const button = e.target.closest('[data-filter]');
        if (!button) return;
        bar.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', String(b === button)));
        items.forEach(item => item.hidden = button.dataset.filter !== 'all' && item.dataset.category !== button.dataset.filter);
        const count = items.filter(item => !item.hidden).length;
        document.querySelector('[data-filter-status="' + group + '"]').textContent = count + (group === 'photos' ? ' foto ditampilkan' : ' artikel ditampilkan');
    });
});
const dialog = document.getElementById('lightbox');
if (dialog) {
    const img = dialog.querySelector('[data-lightbox-img]');
    const caption = dialog.querySelector('[data-lightbox-caption]');
    let group = [], index = 0, opener;
    const show = i => {
        index = (i + group.length) % group.length;
        const item = group[index].dataset;
        img.src = item.src;
        img.alt = item.alt;
        caption.textContent = item.caption;
    };
    document.querySelectorAll('[data-lightbox]').forEach(trigger => trigger.addEventListener('click', () => {
        opener = trigger;
        group = [...document.querySelectorAll('[data-lightbox]')].filter(button => !button.closest('figure').hidden);
        show(group.indexOf(trigger));
        dialog.showModal();
        document.body.classList.add('dialog-open');
    }));
    dialog.querySelector('[data-lightbox-prev]').addEventListener('click', () => show(index - 1));
    dialog.querySelector('[data-lightbox-next]').addEventListener('click', () => show(index + 1));
    dialog.querySelector('[data-lightbox-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('keydown', e => {
        if (e.key === 'ArrowLeft') show(index - 1);
        if (e.key === 'ArrowRight') show(index + 1);
    });
    dialog.addEventListener('click', e => { if (e.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => {
        document.body.classList.remove('dialog-open');
        opener?.focus();
    });
}
