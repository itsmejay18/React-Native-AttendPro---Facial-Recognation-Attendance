const setupLoadingOverlay = () => {
    const body = document.body;
    const overlay = document.querySelector('.attendpro-loading-overlay');

    if (!overlay || body.dataset.loadingOverlayReady === 'true') {
        return;
    }

    body.dataset.loadingOverlayReady = 'true';

    const minimumDuration = 1000;
    const loadingDeadlineKey = 'attendpro-loading-visible-until';
    let safetyTimer;
    let minimumTimer;

    const storedDeadline = () => {
        try {
            return Number.parseInt(sessionStorage.getItem(loadingDeadlineKey) || '0', 10) || 0;
        } catch {
            return 0;
        }
    };

    const storeDeadline = (deadline) => {
        try {
            sessionStorage.setItem(loadingDeadlineKey, String(deadline));
        } catch {
            // Strict privacy settings must not prevent normal navigation.
        }
    };

    const clearDeadline = () => {
        try {
            sessionStorage.removeItem(loadingDeadlineKey);
        } catch {
            // No cleanup is required when session storage is unavailable.
        }
    };

    const reset = () => {
        window.clearTimeout(safetyTimer);
        window.clearTimeout(minimumTimer);
        safetyTimer = undefined;
        minimumTimer = undefined;
        clearDeadline();
        body.classList.remove('attendpro-is-loading');
        body.removeAttribute('aria-busy');
        overlay.setAttribute('aria-hidden', 'true');
    };

    const show = () => {
        if (body.classList.contains('attendpro-is-loading')) {
            return;
        }

        window.clearTimeout(safetyTimer);
        storeDeadline(Date.now() + minimumDuration);
        body.classList.add('attendpro-is-loading');
        body.setAttribute('aria-busy', 'true');
        overlay.setAttribute('aria-hidden', 'false');
        safetyTimer = window.setTimeout(reset, 12000);
    };

    const resumeMinimumDisplay = () => {
        const remaining = storedDeadline() - Date.now();

        if (remaining <= 0) {
            reset();
            return;
        }

        window.clearTimeout(safetyTimer);
        window.clearTimeout(minimumTimer);
        body.classList.add('attendpro-is-loading');
        body.setAttribute('aria-busy', 'true');
        overlay.setAttribute('aria-hidden', 'false');
        minimumTimer = window.setTimeout(reset, remaining);
    };

    const urlFor = (value) => {
        try {
            return new URL(value, window.location.href);
        } catch {
            return null;
        }
    };

    const isExcluded = (element) => Boolean(element?.closest?.('[data-no-loading-overlay]'));
    const isModifiedClick = (event) => event.button !== 0
        || event.metaKey
        || event.ctrlKey
        || event.shiftKey
        || event.altKey;

    const eligibleLink = (link) => {
        if (!(link instanceof HTMLAnchorElement) || !link.href || isExcluded(link) || link.hasAttribute('download')) {
            return false;
        }

        const target = (link.getAttribute('target') || '').toLowerCase();
        if (target && target !== '_self') {
            return false;
        }

        const url = urlFor(link.href);
        if (!url || url.origin !== window.location.origin || !['http:', 'https:'].includes(url.protocol)) {
            return false;
        }

        const sameDocument = url.pathname === window.location.pathname
            && url.search === window.location.search;

        return !sameDocument;
    };

    const eligibleForm = (form) => {
        if (!(form instanceof HTMLFormElement) || isExcluded(form)) {
            return false;
        }

        const target = (form.getAttribute('target') || '').toLowerCase();
        return (!target || target === '_self') && form.getAttribute('method')?.toLowerCase() !== 'dialog';
    };

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || isModifiedClick(event)) {
            return;
        }

        const link = event.target.closest?.('a[href]');
        if (eligibleLink(link)) {
            window.setTimeout(() => {
                if (!event.defaultPrevented) show();
            }, 0);
            return;
        }

        const trigger = event.target.closest?.('[data-loading-overlay="true"]');
        if (trigger && !isExcluded(trigger)) {
            window.setTimeout(() => {
                if (!event.defaultPrevented) show();
            }, 0);
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;

        window.setTimeout(() => {
            if (!event.defaultPrevented && eligibleForm(form)) show();
        }, 0);
    });

    window.addEventListener('beforeunload', show);
    window.addEventListener('pageshow', resumeMinimumDisplay);
    window.addEventListener('load', resumeMinimumDisplay);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) resumeMinimumDisplay();
    });

    resumeMinimumDisplay();
};

setupLoadingOverlay();
