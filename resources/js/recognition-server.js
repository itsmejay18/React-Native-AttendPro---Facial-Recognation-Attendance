const serverPanel = document.querySelector('[data-python-server-panel]');

if (serverPanel) {
    const stateBadge = document.querySelector('[data-python-server-state]');
    const stateLabel = document.querySelector('[data-python-server-state-label]');
    const feedback = serverPanel.querySelector('[data-python-server-feedback]');
    const models = serverPanel.querySelector('[data-python-server-models]');
    const profiles = serverPanel.querySelector('[data-python-server-profiles]');
    const startButton = serverPanel.querySelector('[data-python-server-start]');
    const refreshButton = serverPanel.querySelector('[data-python-server-refresh]');
    const backendSelect = serverPanel.querySelector('[data-python-backend-select]');
    const backendSaveButton = serverPanel.querySelector('[data-python-backend-save]');
    const backendDescription = serverPanel.querySelector('[data-python-backend-description]');
    const backendModel = serverPanel.querySelector('[data-python-backend-model]');
    const backendFeedback = serverPanel.querySelector('[data-python-backend-feedback]');

    const backendDetails = {
        sface: {
            name: 'OpenCV YuNet Face Detector + OpenCV SFace Face Recognition',
            model: 'face_detection_yunet_2023mar.onnx + face_recognition_sface_2021dec.onnx',
        },
        dlib: {
            name: 'dlib face_recognition ResNet v1 Face Recognition',
            model: 'dlib ResNet v1 (99.38% LFW) + HOG face detector',
        },
    };

    let serverStarting = false;

    const renderBackend = (backend) => {
        const selectedBackend = backendDetails[backend] ? backend : 'sface';
        const details = backendDetails[selectedBackend];

        if (backendSelect) backendSelect.value = selectedBackend;
        if (backendDescription) backendDescription.textContent = `${details.name}.`;
        if (backendModel) backendModel.textContent = details.model;

        return selectedBackend;
    };

    const readJsonResponse = async (response) => {
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message ?? 'Laravel could not reach the recognition server.');
        }

        return payload;
    };

    const renderService = (service = {}, errorMessage = '') => {
        const backend = renderBackend(service.backend ?? serverPanel.dataset.pythonSelectedBackend ?? 'sface');
        const ready = service.status === 'ready' && service.configured;
        const initializing = service.status === 'initializing' || serverStarting;
        const state = ready ? 'ready' : (initializing ? 'starting' : 'offline');

        serverPanel.dataset.state = state;
        stateBadge?.classList.toggle('kit-dashboard-tag-green', ready);
        stateBadge?.classList.toggle('kit-dashboard-tag-yellow', !ready);

        if (stateLabel) {
            stateLabel.textContent = ready
                ? 'Server running'
                : (initializing ? 'Server starting' : 'Server offline');
        }

        if (feedback) {
            feedback.textContent = ready
                ? 'Running in the background. Laravel can now enroll and recognize faces.'
                : (initializing
                    ? (service.last_error ?? 'Python is loading its models and Laravel profile cache.')
                    : (errorMessage || service.last_error || 'Server is offline. Start it here without opening a terminal.'));
        }

        if (models) {
            models.textContent = service.models_downloaded
                ? backendDetails[backend].name
                : (initializing ? 'Loading' : 'Not running');
            models.title = service.models_downloaded ? backendDetails[backend].model : models.textContent;
        }

        if (profiles) {
            profiles.textContent = Number.isInteger(service.profiles_loaded)
                ? String(service.profiles_loaded)
                : '—';
        }

        if (startButton) {
            startButton.disabled = ready || initializing;
            startButton.innerHTML = ready
                ? '<i class="ph ph-check-circle" aria-hidden="true"></i>Server running'
                : (initializing
                    ? '<i class="ph ph-circle-notch" aria-hidden="true"></i>Starting server…'
                    : '<i class="ph ph-play" aria-hidden="true"></i>Start recognition server');
        }

        if (refreshButton) refreshButton.disabled = serverStarting;
        if (backendSelect) backendSelect.disabled = serverStarting;
        if (backendSaveButton) backendSaveButton.disabled = serverStarting;

        return ready;
    };

    const checkService = async () => {
        try {
            const response = await readJsonResponse(await fetch(serverPanel.dataset.pythonStatusUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            }));

            renderService(response.data ?? {});
        } catch (error) {
            renderService({}, error.message);
        }
    };

    const startService = async () => {
        if (serverStarting) return;

        serverStarting = true;
        renderService({ status: 'initializing' });

        try {
            const response = await readJsonResponse(await fetch(serverPanel.dataset.pythonStartUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': serverPanel.dataset.csrfToken,
                },
                credentials: 'same-origin',
            }));
            const service = response.data ?? {};

            serverStarting = false;
            if (!renderService(service)) window.setTimeout(checkService, 1000);
        } catch (error) {
            serverStarting = false;
            renderService({}, error.message);
        }
    };

    const switchBackend = async () => {
        const backend = backendSelect?.value;
        if (serverStarting || !backend || !backendDetails[backend]) return;

        serverStarting = true;
        if (backendFeedback) {
            backendFeedback.textContent = 'Saving the selected backend and restarting the local Python server…';
        }
        renderService({ status: 'initializing', backend });

        try {
            const response = await readJsonResponse(await fetch(serverPanel.dataset.pythonBackendUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': serverPanel.dataset.csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ backend }),
            }));
            const service = response.data ?? {};

            serverStarting = false;
            serverPanel.dataset.pythonSelectedBackend = backend;
            if (backendFeedback) {
                backendFeedback.textContent = response.message ?? 'Recognition backend saved. The local server has restarted.';
            }
            if (!renderService(service)) window.setTimeout(checkService, 1000);
        } catch (error) {
            serverStarting = false;
            if (backendFeedback) backendFeedback.textContent = error.message;
            renderService({}, error.message);
        }
    };

    startButton?.addEventListener('click', startService);
    refreshButton?.addEventListener('click', checkService);
    backendSelect?.addEventListener('change', () => renderBackend(backendSelect.value));
    backendSaveButton?.addEventListener('click', switchBackend);
    checkService();

    const serviceInterval = window.setInterval(checkService, 15000);
    window.addEventListener('pagehide', () => window.clearInterval(serviceInterval), { once: true });
}
