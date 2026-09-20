(function () {
    const body = document.body;
    const sidebarOpenClass = 'kit-sidebar-open';
    const themeButtons = document.querySelectorAll('[data-theme-toggle]');
    const themeStorageKey = 'attendpro-theme';
    const storedTheme = localStorage.getItem(themeStorageKey);
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const isLightThemeLocked = body.classList.contains('attendpro-theme-light');

    if (isLightThemeLocked) {
        body.classList.remove('theme-night');
    } else if ((storedTheme ?? (prefersDark ? 'night' : 'morning')) === 'night') {
        body.classList.add('theme-night');
    }

    function syncBodyLock() {
        const hasOpenModal = document.querySelector('.kit-modal.is-open');
        const mobileSidebarOpen = body.classList.contains(sidebarOpenClass) && window.innerWidth < 768;
        body.style.overflow = hasOpenModal || mobileSidebarOpen ? 'hidden' : '';
    }

    function updateThemeButtons() {
        themeButtons.forEach((button) => {
            const icon = button.querySelector('i');
            if (!icon) {
                return;
            }

            icon.className = `ph ${body.classList.contains('theme-night') ? 'ph-moon' : 'ph-sun'}`;
        });
    }

    function applyTheme() {
        if (!isLightThemeLocked) {
            localStorage.setItem(themeStorageKey, body.classList.contains('theme-night') ? 'night' : 'morning');
        }

        updateThemeButtons();
        drawTrendChart();
    }

    themeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            body.classList.toggle('theme-night');
            applyTheme();
        });
    });

    document.querySelectorAll('[data-sidebar-open]').forEach((button) => {
        button.addEventListener('click', () => {
            body.classList.add(sidebarOpenClass);
            syncBodyLock();
        });
    });

    document.querySelectorAll('[data-sidebar-close]').forEach((button) => {
        button.addEventListener('click', () => {
            body.classList.remove(sidebarOpenClass);
            syncBodyLock();
        });
    });

    document.querySelectorAll('[data-public-menu]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.querySelector(button.getAttribute('data-public-menu'));
            if (target) {
                target.classList.toggle('is-open');
                button.setAttribute('aria-expanded', String(target.classList.contains('is-open')));
            }
        });
    });

    const userMenus = document.querySelectorAll('[data-user-menu]');

    function closeUserMenu(menu, { restoreFocus = false } = {}) {
        const toggle = menu.querySelector('[data-user-menu-toggle]');
        const panel = menu.querySelector('[data-user-menu-panel]');
        if (!toggle || !panel) return;

        panel.hidden = true;
        menu.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        if (restoreFocus) toggle.focus();
    }

    userMenus.forEach((menu) => {
        const toggle = menu.querySelector('[data-user-menu-toggle]');
        const panel = menu.querySelector('[data-user-menu-panel]');
        if (!toggle || !panel) return;

        toggle.addEventListener('click', () => {
            const willOpen = panel.hidden;
            userMenus.forEach((otherMenu) => closeUserMenu(otherMenu));
            panel.hidden = !willOpen;
            menu.classList.toggle('is-open', willOpen);
            toggle.setAttribute('aria-expanded', String(willOpen));

            if (willOpen) panel.querySelector('a, button')?.focus({ preventScroll: true });
        });
    });

    document.addEventListener('click', (event) => {
        userMenus.forEach((menu) => {
            if (!menu.contains(event.target)) closeUserMenu(menu);
        });
    });

    document.querySelectorAll('[data-modal-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = document.querySelector(button.getAttribute('data-modal-open'));
            if (target) {
                target.classList.add('is-open');
                syncBodyLock();
            }
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = button.closest('.kit-modal');
            if (modal) {
                modal.classList.remove('is-open');
                syncBodyLock();
            }
        });
    });

    document.querySelectorAll('.kit-modal').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.classList.remove('is-open');
                syncBodyLock();
            }
        });
    });

    document.querySelectorAll('[data-modal-auto-open="true"]').forEach((modal) => {
        modal.classList.add('is-open');
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const wrap = button.closest('.attendpro-password-wrap');
            const input = wrap ? wrap.querySelector('input') : null;
            if (!input) return;

            const willShow = input.type === 'password';
            input.type = willShow ? 'text' : 'password';
            button.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');
            button.setAttribute('aria-pressed', String(willShow));
            const icon = button.querySelector('i');
            if (icon) icon.className = `ph ${willShow ? 'ph-eye-slash' : 'ph-eye'}`;
        });
    });

    document.querySelectorAll('[data-tabs-scroll]').forEach((container) => {
        const active = container.querySelector('.attendpro-tab.is-active');
        if (active) active.scrollIntoView({ block: 'nearest', inline: 'center' });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.kit-modal.is-open').forEach((modal) => modal.classList.remove('is-open'));
            userMenus.forEach((menu) => closeUserMenu(menu, { restoreFocus: menu.classList.contains('is-open') }));
            body.classList.remove(sidebarOpenClass);
            syncBodyLock();
        }
    });

    const trendCanvas = document.getElementById('kitTrendChart');

    function drawTrendChart() {
        if (!trendCanvas) {
            return;
        }

        const context = trendCanvas.getContext('2d');
        const ratio = window.devicePixelRatio || 1;
        const width = trendCanvas.clientWidth || 940;
        const height = trendCanvas.clientHeight || 400;

        trendCanvas.width = width * ratio;
        trendCanvas.height = height * ratio;
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.clearRect(0, 0, width, height);

        const parseChartData = (value, fallback) => {
            try {
                return value ? JSON.parse(value) : fallback;
            } catch {
                return fallback;
            }
        };
        const labels = parseChartData(trendCanvas.dataset.labels, ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']);
        const values = parseChartData(trendCanvas.dataset.values, [86, 89, 91, 90, 93, 95]);

        const padding = { top: 22, right: 18, bottom: 42, left: 40 };
        const innerWidth = width - padding.left - padding.right;
        const innerHeight = height - padding.top - padding.bottom;
        const maxValue = Number(trendCanvas.dataset.maxValue) || 100;
        const stepCount = Number(trendCanvas.dataset.stepCount) || 4;
        const isNight = body.classList.contains('theme-night');
        const horizontalGrid = isNight ? '#24364d' : '#dbe4f0';
        const verticalGrid = isNight ? '#1b2a3c' : '#e6edf7';
        const axisText = isNight ? '#9fb0c5' : '#475569';
        const fillStart = isNight ? 'rgba(170, 164, 239, 0.24)' : 'rgba(33, 25, 107, 0.22)';
        const fillEnd = isNight ? 'rgba(170, 164, 239, 0.08)' : 'rgba(33, 25, 107, 0.10)';
        const lineColor = isNight ? '#aaa4ef' : '#21196b';
        const pointStroke = isNight ? '#aaa4ef' : '#21196b';
        const pointFill = '#10b981';

        context.strokeStyle = horizontalGrid;
        context.lineWidth = 1;

        for (let step = 0; step <= stepCount; step += 1) {
            const stepValue = (maxValue / stepCount) * step;
            const y = padding.top + innerHeight - (stepValue / maxValue) * innerHeight;
            context.beginPath();
            context.moveTo(padding.left, y);
            context.lineTo(width - padding.right, y);
            context.stroke();

            context.fillStyle = axisText;
            context.font = '14px "Public Sans"';
            context.textAlign = 'right';
            context.fillText(String(Math.round(stepValue)), padding.left - 10, y + 4);
        }

        const stepX = innerWidth / (labels.length - 1);
        const points = values.map((value, index) => {
            const x = padding.left + index * stepX;
            const y = padding.top + innerHeight - (value / maxValue) * innerHeight;
            return { x, y };
        });

        context.strokeStyle = verticalGrid;
        for (let index = 0; index < labels.length; index += 1) {
            const x = padding.left + index * stepX;
            context.beginPath();
            context.moveTo(x, padding.top);
            context.lineTo(x, padding.top + innerHeight);
            context.stroke();
        }

        function buildSmoothPath(target, fillArea) {
            target.beginPath();
            target.moveTo(points[0].x, points[0].y);

            for (let index = 0; index < points.length - 1; index += 1) {
                const current = points[index];
                const next = points[index + 1];
                const controlX = (current.x + next.x) / 2;

                target.bezierCurveTo(controlX, current.y, controlX, next.y, next.x, next.y);
            }

            if (fillArea) {
                target.lineTo(points[points.length - 1].x, padding.top + innerHeight);
                target.lineTo(points[0].x, padding.top + innerHeight);
                target.closePath();
            }
        }

        const gradient = context.createLinearGradient(0, padding.top, 0, padding.top + innerHeight);
        gradient.addColorStop(0, fillStart);
        gradient.addColorStop(1, fillEnd);

        context.fillStyle = gradient;
        buildSmoothPath(context, true);
        context.fill();

        context.strokeStyle = lineColor;
        context.lineWidth = 4;
        buildSmoothPath(context, false);
        context.stroke();

        points.forEach((point) => {
            context.beginPath();
            context.fillStyle = pointFill;
            context.strokeStyle = pointStroke;
            context.lineWidth = 2;
            context.arc(point.x, point.y, 7, 0, Math.PI * 2);
            context.fill();
            context.stroke();
        });

        context.fillStyle = axisText;
        context.font = '14px "Public Sans"';
        context.textAlign = 'center';

        labels.forEach((label, index) => {
            const x = padding.left + index * stepX;
            context.fillText(label, x, height - 14);
        });
    }

    if (trendCanvas) {
        drawTrendChart();
        window.addEventListener('resize', drawTrendChart);
    }

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) {
            body.classList.remove(sidebarOpenClass);
        }

        syncBodyLock();
    });

    applyTheme();
    syncBodyLock();
})();
