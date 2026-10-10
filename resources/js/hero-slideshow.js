export function setupHeroSlideshow(root) {
    if (!root) return;
    const slides = [...root.querySelectorAll('[data-hero-slide]')];
    const choices = [...root.querySelectorAll('[data-hero-select]')];
    const controls = root.querySelector('[data-hero-controls]');
    const toggle = root.querySelector('[data-hero-toggle]');
    const caption = root.querySelector('[data-hero-caption]');
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    let index = 0, paused = motion.matches, hovered = false, visible = true, timer;

    // Load the other photos ahead of time; keep the current photo if a file fails.
    slides.forEach(slide => { slide.querySelector('img').loading = 'eager'; });
    const updateToggle = () => {
        toggle.setAttribute('aria-label', paused ? 'Putar pergantian foto' : 'Jeda pergantian foto');
        toggle.querySelector('[data-hero-toggle-label]').textContent = paused ? 'Putar' : 'Jeda';
        toggle.querySelector('[data-hero-pause-icon]').toggleAttribute('hidden', paused);
        toggle.querySelector('[data-hero-play-icon]').toggleAttribute('hidden', !paused);
    };
    const schedule = () => {
        clearTimeout(timer);
        if (!paused && !hovered && visible && !document.hidden) {
            timer = setTimeout(() => { show((index + 1) % slides.length, true); }, 3000);
        }
    };
    let request = 0;
    const show = async (next, automatic = false) => {
        const currentRequest = ++request;
        clearTimeout(timer);
        try {
            await slides[next].querySelector('img').decode();
        } catch {
            if (currentRequest !== request) return;
            paused = true;
            updateToggle();
            return;
        }
        if (currentRequest !== request) return;
        if (automatic && (paused || hovered || !visible || document.hidden)) { schedule(); return; }
        index = next;
        slides.forEach((slide, i) => {
            slide.classList.toggle('is-active', i === index);
            slide.setAttribute('aria-hidden', String(i !== index));
            choices[i].setAttribute('aria-pressed', String(i === index));
        });
        caption.textContent = slides[index].dataset.caption;
        schedule();
    };
    const pause = () => {
        paused = true;
        ++request; // Cancel any photo still decoding.
        clearTimeout(timer);
        updateToggle();
    };
    choices.forEach((button, i) => button.addEventListener('click', () => { pause(); show(i); }));
    toggle.addEventListener('click', () => {
        if (!paused) { pause(); return; }
        paused = false;
        updateToggle();
        schedule();
    });
    // Keyboard interaction stops autoplay until the visitor explicitly presses Putar.
    root.addEventListener('focusin', event => {
        // Mouse focus on Jeda must not turn the same click into Putar.
        if (event.target !== toggle || toggle.matches(':focus-visible')) pause();
    });
    root.addEventListener('pointerenter', e => {
        if (e.pointerType !== 'mouse') return;
        hovered = true;
        clearTimeout(timer);
    });
    root.addEventListener('pointerleave', e => {
        if (e.pointerType !== 'mouse') return;
        hovered = false;
        schedule();
    });
    document.addEventListener('visibilitychange', schedule);
    motion.addEventListener('change', () => { if (motion.matches) pause(); });
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            if (visible === entry.isIntersecting) return;
            visible = entry.isIntersecting;
            schedule();
        }, { threshold: 0 }).observe(root.querySelector('.hero-arch'));
    }
    controls.hidden = false;
    updateToggle();
    schedule();
}
