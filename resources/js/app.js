import { setupHeroSlideshow } from './hero-slideshow';

document.documentElement.classList.add('js');

const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;

// Hapus sisa animasi pembuka setelah selesai agar transform inline (parallax) bebas bekerja.
document.querySelectorAll('.hero-copy > p, .hero-cta, .hero-visual, .hero-badge, .hero-arc-line path').forEach(el =>
    el.addEventListener('animationend', () => { el.style.animation = 'none'; }, { once: true }));

// Parallax pointer pada foto dan badge hero.
const scene = document.querySelector('[data-parallax-scene]');
if (scene && !reduceMotion && matchMedia('(pointer: fine)').matches) {
    const hero = scene.closest('.home-hero');
    const layers = [...scene.querySelectorAll('[data-parallax]')];
    let tx = 0, ty = 0, cx = 0, cy = 0, raf = null;
    const tick = () => {
        cx += (tx - cx) * 0.08;
        cy += (ty - cy) * 0.08;
        layers.forEach(layer => {
            const depth = +layer.dataset.parallax;
            layer.style.transform = `translate3d(${(-cx * depth).toFixed(2)}px, ${(-cy * depth).toFixed(2)}px, 0)`;
        });
        raf = (Math.abs(tx - cx) > 0.001 || Math.abs(ty - cy) > 0.001) ? requestAnimationFrame(tick) : null;
    };
    hero.addEventListener('pointermove', e => {
        const r = hero.getBoundingClientRect();
        tx = (e.clientX - r.left) / r.width - 0.5;
        ty = (e.clientY - r.top) / r.height - 0.5;
        if (!raf) raf = requestAnimationFrame(tick);
    });
    hero.addEventListener('pointerleave', () => {
        tx = 0; ty = 0;
        if (!raf) raf = requestAnimationFrame(tick);
    });
}

setupHeroSlideshow(document.querySelector('[data-hero-slideshow]'));

// Scroll reveal sekali jalan untuk section beranda.
const revealEls = document.querySelectorAll('[data-reveal]');
if (revealEls.length && 'IntersectionObserver' in window && !reduceMotion) {
    revealEls.forEach(el => el.classList.add('reveal-init'));
    const io = new IntersectionObserver(entries => entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('reveal-in');
            io.unobserve(entry.target);
        }
    }), { threshold: 0.12 });
    revealEls.forEach(el => io.observe(el));
}


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
    const search = group === 'articles' ? document.querySelector('[data-article-search]') : null;
    let category = 'all';
    const update = () => {
        const term = (search?.value || '').toLocaleLowerCase('id').trim();
        items.forEach(item => {
            const matches = !term || item.querySelector('h3')?.textContent.toLocaleLowerCase('id').includes(term);
            item.hidden = (category !== 'all' && item.dataset.category !== category) || !matches;
        });
        const count = items.filter(item => !item.hidden).length;
        document.querySelector('[data-filter-status="' + group + '"]').textContent = count + (group === 'photos' ? ' foto ditampilkan' : ' artikel ditampilkan');
        if (search) document.querySelector('[data-search-empty]').hidden = count > 0;
    };
    search?.addEventListener('input', update);
    bar.addEventListener('click', e => {
        const button = e.target.closest('[data-filter]');
        if (!button) return;
        bar.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', String(b === button)));
        category = button.dataset.filter; update();
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
        dialog.querySelector('[data-lightbox-count]').textContent = (index + 1) + ' / ' + group.length;
        dialog.querySelector('[data-lightbox-prev]').disabled = group.length < 2;
        dialog.querySelector('[data-lightbox-next]').disabled = group.length < 2;
    };
    document.querySelectorAll('[data-lightbox]').forEach(trigger => trigger.addEventListener('click', () => {
        opener = trigger;
        group = [...document.querySelectorAll('[data-lightbox]')].filter(button => button.dataset.lightbox === trigger.dataset.lightbox && button.getClientRects().length);
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
const tabs = [...document.querySelectorAll('[data-document-tab]')];
if (tabs.length) {
    const select = (tab, focus = false) => {
        tabs.forEach(button => {
            const active = button === tab;
            button.setAttribute('aria-selected', String(active)); button.tabIndex = active ? 0 : -1;
            document.getElementById(button.dataset.documentTab).hidden = !active;
        });
        if (focus) tab.focus();
    };
    const applyHash = () => {
        const target = location.hash ? document.getElementById(location.hash.slice(1)) : null;
        select(tabs.find(tab => tab.dataset.documentTab === (target?.closest('#lingkungan') ? 'lingkungan' : 'kegiatan')));
        if (target) target.scrollIntoView({ behavior: 'instant' });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => select(tab));
        tab.addEventListener('keydown', event => {
            if (['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                event.preventDefault();
                select(tabs[event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length], true);
            }
        });
    });
    applyHash(); window.addEventListener('hashchange', applyHash);
}
if (document.querySelector('[data-cms]')) import('./cms-preview.js').catch(() => {
    document.querySelector('[data-cms-content]').textContent = 'Editor belum dapat dimuat. Muat ulang halaman untuk mencoba lagi.';
});
