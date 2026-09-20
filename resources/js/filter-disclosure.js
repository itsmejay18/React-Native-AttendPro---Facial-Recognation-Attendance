const selector = '[data-filter-disclosure]';

const setOpen = (disclosure, open) => {
    const trigger = disclosure.querySelector('[data-filter-trigger]');
    const panel = disclosure.querySelector('[data-filter-panel]');

    if (!trigger || !panel) return;

    trigger.setAttribute('aria-expanded', String(open));
    panel.hidden = !open;
    disclosure.classList.toggle('is-open', open);
};

const closeAllExcept = (current) => {
    document.querySelectorAll(selector).forEach((disclosure) => {
        if (disclosure !== current) setOpen(disclosure, false);
    });
};

const initialise = () => {
    document.querySelectorAll(selector).forEach((disclosure) => {
        const trigger = disclosure.querySelector('[data-filter-trigger]');
        const panel = disclosure.querySelector('[data-filter-panel]');

        if (!trigger || !panel) return;

        const shouldOpen = disclosure.dataset.filterOpen === 'true';
        setOpen(disclosure, shouldOpen);

        trigger.addEventListener('click', () => {
            const open = trigger.getAttribute('aria-expanded') !== 'true';
            closeAllExcept(disclosure);
            setOpen(disclosure, open);
        });
    });

    document.addEventListener('click', (event) => {
        document.querySelectorAll(selector).forEach((disclosure) => {
            if (!disclosure.contains(event.target)) setOpen(disclosure, false);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll(selector).forEach((disclosure) => setOpen(disclosure, false));
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialise, { once: true });
} else {
    initialise();
}
