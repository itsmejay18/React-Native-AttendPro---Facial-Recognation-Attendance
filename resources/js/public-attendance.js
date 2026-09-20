const attendanceStatus = document.querySelector('[data-public-attendance-status]');

if (attendanceStatus) {
    const label = attendanceStatus.querySelector('[data-public-attendance-label]');
    const detail = attendanceStatus.querySelector('[data-public-attendance-detail]');

    const refresh = async () => {
        try {
            const response = await fetch(attendanceStatus.dataset.statusUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const body = await response.json().catch(() => ({}));
            const service = body.data ?? {};
            const ready = response.ok
                && service.status === 'ready'
                && service.configured
                && service.models_ready;

            attendanceStatus.dataset.state = ready ? 'ready' : 'offline';
            label.textContent = ready ? 'Attendance station ready' : 'Attendance station offline';
            detail.textContent = ready
                ? `${service.profiles_loaded ?? 0} enrolled face profiles are available for scanning.`
                : (service.last_error ?? 'Ask an administrator to start the recognition server.');
        } catch (error) {
            attendanceStatus.dataset.state = 'offline';
            label.textContent = 'Attendance station offline';
            detail.textContent = 'Ask an administrator to start the recognition server.';
        }
    };

    refresh();
    const interval = window.setInterval(refresh, 15000);
    window.addEventListener('pagehide', () => window.clearInterval(interval), { once: true });
}
