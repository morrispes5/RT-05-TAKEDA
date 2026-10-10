export function setupHeroSlideshow(root) {
    if (!root) return;
    const slides = [...root.querySelectorAll('[data-hero-slide]')];
    const choices = [...root.querySelectorAll('[data-hero-select]')];
    const controls = root.querySelector('[data-hero-controls]');
    const caption = root.querySelector('[data-hero-caption]');
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    let index = 0, visible = true, timer;
    const failed = new Set();

    // Load the other photos ahead of time; keep the current photo if a file fails.
    slides.forEach(slide => { slide.querySelector('img').loading = 'eager'; });
    const schedule = () => {
        clearTimeout(timer);
        if (!motion.matches && visible && !document.hidden) {
            const next = Array.from({ length: slides.length - 1 }, (_, i) => (index + i + 1) % slides.length).find(i => !failed.has(i));
            if (next !== undefined) timer = setTimeout(() => { show(next, true); }, 3000);
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
            failed.add(next);
            schedule();
            return;
        }
        if (currentRequest !== request) return;
        if (automatic && (motion.matches || !visible || document.hidden)) { schedule(); return; }
        failed.delete(next);
        index = next;
        slides.forEach((slide, i) => {
            slide.classList.toggle('is-active', i === index);
            slide.setAttribute('aria-hidden', String(i !== index));
            choices[i].setAttribute('aria-pressed', String(i === index));
        });
        caption.textContent = slides[index].dataset.caption;
        schedule();
    };
    // Selecting a photo restarts its 3-second interval without stopping autoplay.
    choices.forEach((button, i) => button.addEventListener('click', () => { show(i); }));
    document.addEventListener('visibilitychange', schedule);
    motion.addEventListener('change', schedule);
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            if (visible === entry.isIntersecting) return;
            visible = entry.isIntersecting;
            schedule();
        }, { threshold: 0 }).observe(root.querySelector('.hero-arch'));
    }
    controls.hidden = false;
    schedule();
}
