import { FaceDetector, FilesetResolver } from '@mediapipe/tasks-vision';

const registration = document.querySelector('[data-registration-enrollment]');

if (registration) {
    const modal = registration.closest('.kit-modal');
    const detailsStep = registration.querySelector('[data-registration-details-step]');
    const faceStep = registration.querySelector('[data-registration-face-step]');
    const detailsActions = registration.querySelector('[data-registration-details-actions]');
    const faceActions = registration.querySelector('[data-registration-face-actions]');
    const submitButton = registration.querySelector('[data-registration-submit]');
    const finishButton = registration.querySelector('[data-registration-finish]');
    const errorsPanel = registration.querySelector('[data-registration-errors]');
    const errorList = registration.querySelector('[data-registration-error-list]');
    const title = registration.querySelector('[data-registration-title]');
    const subtitle = registration.querySelector('[data-registration-subtitle]');
    const detailsProgress = registration.querySelector('[data-registration-progress="details"]');
    const faceProgress = registration.querySelector('[data-registration-progress="face"]');
    const personName = registration.querySelector('[data-registration-person-name]');
    const personId = registration.querySelector('[data-registration-person-id]');
    const personRole = registration.querySelector('[data-registration-person-role]');
    const serviceBadge = registration.querySelector('[data-registration-service-badge]');
    const serviceLabel = registration.querySelector('[data-registration-service-label]');
    const video = registration.querySelector('[data-registration-camera-video]');
    const overlay = registration.querySelector('[data-registration-camera-overlay]');
    const overlayContext = overlay.getContext('2d');
    const viewport = registration.querySelector('[data-registration-camera-viewport]');
    const guideFrame = viewport.querySelector('.attendpro-camera-frame');
    const placeholder = registration.querySelector('[data-registration-camera-placeholder]');
    const flash = registration.querySelector('[data-registration-camera-flash]');
    const cameraState = registration.querySelector('[data-registration-camera-state]');
    const cameraStateLabel = registration.querySelector('[data-registration-camera-state-label]');
    const status = registration.querySelector('[data-registration-status]');
    const statusTitle = registration.querySelector('[data-registration-status-title]');
    const statusDetail = registration.querySelector('[data-registration-status-detail]');
    const startButton = registration.querySelector('[data-registration-camera-start]');
    const enrollButton = registration.querySelector('[data-registration-enroll]');
    const retakeButton = registration.querySelector('[data-registration-retake]');
    const stopButton = registration.querySelector('[data-registration-camera-stop]');
    const consent = registration.querySelector('[data-registration-consent]');
    const retention = registration.querySelector('[data-registration-retention]');
    const previewWrap = registration.querySelector('[data-registration-capture-wrap]');
    const preview = registration.querySelector('[data-registration-capture]');
    const previewEmpty = registration.querySelector('[data-registration-capture-empty]');
    const successPanel = registration.querySelector('[data-registration-success]');
    const samplePreview = registration.querySelector('[data-registration-sample-preview]');
    const sampleCount = registration.querySelector('[data-registration-sample-count]');
    const sampleGallery = registration.querySelector('[data-registration-sample-gallery]');
    const enrollmentSamples = Math.max(1, Math.min(30, Number.parseInt(registration.dataset.enrollmentSamples ?? '15', 10) || 15));
    const enrollmentCaptureFrames = Math.max(enrollmentSamples, Math.min(30, Number.parseInt(registration.dataset.enrollmentCaptureFrames ?? '15', 10) || 15));
    const landmarkPreviewUrl = registration.dataset.landmarkPreviewUrl;

    let createdPerson;
    let directoryUrl = registration.dataset.directoryUrl;
    let detector;
    let detectorPromise;
    let mediaStream;
    let animationFrame;
    let latestDetections = [];
    let lastVideoTime = -1;
    let lastDetectionAt = 0;
    let scanning = false;
    let pythonReady = false;
    let processing = false;
    let enrolled = false;
    let enrollmentFailure = false;
    let enrollmentFailureMessage = '';
    let serviceInterval;
    let samplePreviewUrls = [];
    let useInsightFaceLandmarks = false;
    let landmarkRequestPending = false;
    let latestInsightFacePreview;

    const titleCase = (value = '') => value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());

    const setBusy = (button, busy, busyLabel) => {
        if (!button.dataset.originalLabel) button.dataset.originalLabel = button.innerHTML;
        button.disabled = busy;
        button.innerHTML = busy
            ? `<i class="ph ph-circle-notch" aria-hidden="true"></i>${busyLabel}`
            : button.dataset.originalLabel;
    };

    const readJsonResponse = async (response) => {
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(body.message ?? `Request failed with HTTP ${response.status}.`);
            error.validationErrors = body.errors ?? null;
            throw error;
        }

        return body;
    };

    const hideErrors = () => {
        errorsPanel.hidden = true;
        errorList.replaceChildren();
    };

    const showErrors = (error) => {
        const messages = error.validationErrors
            ? Object.values(error.validationErrors).flat()
            : [error.message];

        errorList.replaceChildren(...messages.map((message) => {
            const item = document.createElement('li');
            item.textContent = message;
            return item;
        }));
        errorsPanel.hidden = false;
        errorsPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const setStatus = (heading, detail, state = 'idle') => {
        const icons = {
            idle: 'ph-info',
            live: 'ph-scan',
            loading: 'ph-circle-notch',
            success: 'ph-check-circle',
            warning: 'ph-warning',
            error: 'ph-warning-circle',
        };

        status.dataset.state = state;
        status.querySelector('.attendpro-scanner-status-icon').innerHTML = `<i class="ph ${icons[state] ?? icons.idle}" aria-hidden="true"></i>`;
        statusTitle.textContent = heading;
        statusDetail.textContent = detail;
    };

    const setCameraState = (label, state) => {
        cameraState.dataset.cameraState = state;
        cameraStateLabel.textContent = label;
    };

    const updateButtons = () => {
        const hasCapture = previewWrap?.classList.contains('has-capture')
            || (sampleGallery && sampleGallery.childElementCount > 0)
            || enrollmentFailure;
        startButton.disabled = processing || Boolean(mediaStream) || enrolled;
        stopButton.disabled = processing || !mediaStream;
        enrollButton.disabled = processing
            || enrolled
            || !pythonReady
            || !mediaStream
            || !video.videoWidth
            || !consent.checked;
        if (retakeButton) retakeButton.disabled = processing || enrolled || !hasCapture;
        finishButton.classList.toggle('is-disabled', !enrolled);
        finishButton.setAttribute('aria-disabled', String(!enrolled));
    };

    const clearRegistrationCapture = () => {
        samplePreviewUrls.forEach((url) => URL.revokeObjectURL(url));
        samplePreviewUrls = [];
        sampleGallery?.replaceChildren();
        if (samplePreview) samplePreview.hidden = true;
        if (sampleCount) sampleCount.textContent = `0 / ${enrollmentSamples}`;
        if (preview) preview.removeAttribute('src');
        previewWrap?.classList.remove('has-capture');
        if (previewEmpty) previewEmpty.hidden = false;
        enrollmentFailure = false;
        enrollmentFailureMessage = '';
    };

    const retakeRegistrationCapture = () => {
        if (processing || enrolled) return;
        clearRegistrationCapture();
        if (mediaStream && video.videoWidth) {
            setStatus('Ready for retake', 'Center one face in the guide, then collect the enrollment samples again.', 'live');
        } else {
            setStatus('Ready for retake', 'Start the camera, center one face, then collect the enrollment samples again.', 'idle');
        }
        updateButtons();
        (mediaStream ? enrollButton : startButton).focus({ preventScroll: true });
    };

    const setServiceState = (ready, label, detail = '') => {
        pythonReady = ready;
        serviceBadge.classList.toggle('success', ready);
        serviceBadge.classList.toggle('warning', !ready);
        serviceBadge.querySelector('i').className = `ph ${ready ? 'ph-check-circle' : 'ph-warning-circle'}`;
        serviceLabel.textContent = label;

        if (!ready && detail && !scanning) {
            setStatus('Recognition server unavailable', detail, 'warning');
        }

        updateButtons();
    };

    const checkPythonService = async () => {
        try {
            const response = await fetch(registration.dataset.pythonStatusUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const body = await readJsonResponse(response);
            const service = body.data ?? {};
            const ready = service.status === 'ready' && service.configured;
            useInsightFaceLandmarks = ready && service.backend === 'insightface' && Boolean(landmarkPreviewUrl);
            setServiceState(
                ready,
                ready ? `Python ready · ${service.profiles_loaded ?? 0} profiles` : 'Python initializing',
                service.last_error ?? 'Start the recognition server from Settings, then try again.',
            );
        } catch (error) {
            setServiceState(false, 'Python offline', error.message);
        }
    };

    const getDetector = async () => {
        if (detector) return detector;

        if (!detectorPromise) {
            detectorPromise = (async () => {
                const vision = await FilesetResolver.forVisionTasks(registration.dataset.wasmBase);
                detector = await FaceDetector.createFromOptions(vision, {
                    baseOptions: { modelAssetPath: registration.dataset.modelUrl },
                    runningMode: 'VIDEO',
                    minDetectionConfidence: 0.65,
                    minSuppressionThreshold: 0.3,
                });

                return detector;
            })().catch((error) => {
                detectorPromise = undefined;
                throw error;
            });
        }

        return detectorPromise;
    };

    const sizeOverlay = () => {
        if (!video.videoWidth || !video.videoHeight) return;
        overlay.width = video.videoWidth;
        overlay.height = video.videoHeight;
    };

    const drawDetections = (detections) => {
        sizeOverlay();
        overlayContext.clearRect(0, 0, overlay.width, overlay.height);
        viewport.classList.remove('has-landmarks');
        if (!detections.length) return;

        overlayContext.lineWidth = Math.max(4, overlay.width / 260);
        overlayContext.strokeStyle = detections.length === 1 ? '#35d07f' : '#f7b731';
        overlayContext.shadowColor = 'rgba(0, 0, 0, 0.3)';
        overlayContext.shadowBlur = 8;
        detections.forEach((detection) => {
            const box = detection.boundingBox;
            if (box) overlayContext.strokeRect(box.originX, box.originY, box.width, box.height);
        });
        overlayContext.shadowBlur = 0;
    };

    const drawInsightFaceLandmarks = (previewData) => {
        sizeOverlay();
        overlayContext.clearRect(0, 0, overlay.width, overlay.height);

        const imageWidth = Number(previewData.image_width) || overlay.width;
        const imageHeight = Number(previewData.image_height) || overlay.height;
        const crop = previewData.guideCrop ?? { x: 0, y: 0, width: imageWidth, height: imageHeight };
        const scaleX = Number(crop.width) / imageWidth;
        const scaleY = Number(crop.height) / imageHeight;
        const dotColors = ['#25d3f7', '#25d3f7', '#f6c344', '#ff6b57', '#ff6b57'];
        const faces = Array.isArray(previewData.faces) ? previewData.faces : [];
        viewport.classList.toggle('has-landmarks', faces.some((face) => (face.keypoints?.length ?? 0) >= 20));

        faces.forEach((face) => {
            const points = Array.isArray(face.keypoints) ? face.keypoints : [];
            if (!points.length) return;
            const scaled = points.map((point) => ({
                x: Number(crop.x) + (Number(point.x) * scaleX),
                y: Number(crop.y) + (Number(point.y) * scaleY),
            }));
            const box = face.bounding_box ?? {};
            const boxX = Number(crop.x) + (Number(box.x) * scaleX);
            const boxY = Number(crop.y) + (Number(box.y) * scaleY);
            const boxWidth = Math.max(1, Number(box.width) * scaleX);
            const boxHeight = Math.max(1, Number(box.height) * scaleY);
            const dense = face.landmark_type === 'dense_106' || scaled.length >= 20;
            overlayContext.save();
            overlayContext.lineWidth = Math.max(1.5, overlay.width / 520);
            overlayContext.strokeStyle = 'rgba(93, 188, 255, 0.8)';
            overlayContext.shadowColor = 'rgba(0, 0, 0, 0.45)';
            overlayContext.shadowBlur = 5;
            if (!dense && scaled.length >= 5) {
                overlayContext.beginPath();
                overlayContext.moveTo(scaled[0].x, scaled[0].y);
                overlayContext.lineTo(scaled[2].x, scaled[2].y);
                overlayContext.lineTo(scaled[1].x, scaled[1].y);
                overlayContext.moveTo(scaled[2].x, scaled[2].y);
                overlayContext.lineTo(scaled[3].x, scaled[3].y);
                overlayContext.lineTo(scaled[4].x, scaled[4].y);
                overlayContext.stroke();
            }
            scaled.forEach((point, index) => {
                overlayContext.beginPath();
                if (dense) {
                    const relativeX = (point.x - boxX) / boxWidth;
                    const relativeY = (point.y - boxY) / boxHeight;
                    overlayContext.fillStyle = relativeX < 0.14 || relativeX > 0.86
                        ? '#6989ff'
                        : (relativeY < 0.43 ? '#25d3f7' : (relativeY < 0.68 ? '#9eea63' : '#ff6b57'));
                } else {
                    overlayContext.fillStyle = dotColors[index] ?? '#a78bfa';
                }
                overlayContext.arc(point.x, point.y, dense ? Math.max(1.4, overlay.width / 620) : Math.max(3.5, overlay.width / 170), 0, Math.PI * 2);
                overlayContext.fill();
            });
            overlayContext.restore();
        });
    };

    const reportInsightFacePreview = (previewData) => {
        if (processing || enrollmentFailure) return;
        const count = Number(previewData.face_count) || 0;
        if (count === 1) {
            setCameraState('Face landmarks detected', 'detected');
            setStatus(
                'Face landmarks detected',
                consent.checked ? 'InsightFace mapped the 106 facial landmark points. Collect the 15 enrollment samples when ready.' : 'Verify documented consent to enable enrollment.',
                'success',
            );
        } else if (count > 1) {
            setCameraState('Multiple faces', 'warning');
            setStatus('More than one face detected', 'Only the person being registered may appear in the frame.', 'warning');
        } else {
            setCameraState('Searching', 'live');
            setStatus('Looking for one face', 'Center the person in the guide while InsightFace searches.', 'live');
        }
        updateButtons();
    };

    const guideCrop = () => {
        const viewportRect = viewport.getBoundingClientRect();
        const frameRect = guideFrame.getBoundingClientRect();
        const displayScale = Math.max(viewportRect.width / video.videoWidth, viewportRect.height / video.videoHeight);
        const renderedWidth = video.videoWidth * displayScale;
        const renderedHeight = video.videoHeight * displayScale;
        const offsetX = (viewportRect.width - renderedWidth) / 2;
        const offsetY = (viewportRect.height - renderedHeight) / 2;
        const x = Math.max(0, Math.round(((frameRect.left - viewportRect.left) - offsetX) / displayScale));
        const y = Math.max(0, Math.round(((frameRect.top - viewportRect.top) - offsetY) / displayScale));
        const width = Math.min(video.videoWidth - x, Math.round(frameRect.width / displayScale));
        const height = Math.min(video.videoHeight - y, Math.round(frameRect.height / displayScale));
        return { x, y, width: Math.max(1, width), height: Math.max(1, height) };
    };

    const guideCanvas = (maximumDimension, mirrored = false) => {
        const crop = guideCrop();
        const scale = Math.min(1, maximumDimension / Math.max(crop.width, crop.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(crop.width * scale));
        canvas.height = Math.max(1, Math.round(crop.height * scale));
        const context = canvas.getContext('2d');
        if (mirrored) {
            context.translate(canvas.width, 0);
            context.scale(-1, 1);
        }
        context.drawImage(video, crop.x, crop.y, crop.width, crop.height, 0, 0, canvas.width, canvas.height);
        return { canvas, crop };
    };

    const previewCameraFrame = () => new Promise((resolve, reject) => {
        const { canvas, crop } = guideCanvas(640);
        canvas.toBlob((blob) => blob
            ? resolve({ blob, crop })
            : reject(new Error('Unable to prepare the center-frame preview.')), 'image/jpeg', 0.75);
    });

    const requestInsightFacePreview = async () => {
        if (!useInsightFaceLandmarks || landmarkRequestPending || !video.videoWidth) return;
        landmarkRequestPending = true;
        try {
            const frame = await previewCameraFrame();
            const form = new FormData();
            form.append('image', frame.blob, 'landmark-preview.jpg');
            const response = await readJsonResponse(await fetch(landmarkPreviewUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': registration.dataset.csrfToken },
                credentials: 'same-origin',
                body: form,
            }));
            const previewData = response.data ?? {};
            if (!scanning || previewData.supported !== true) return;
            previewData.guideCrop = frame.crop;
            latestInsightFacePreview = previewData;
            drawInsightFaceLandmarks(previewData);
            reportInsightFacePreview(previewData);
        } catch (error) {
            console.warn('InsightFace enrollment landmark preview failed:', error);
            useInsightFaceLandmarks = false;
            setCameraState('Camera guidance unavailable', 'warning');
            setStatus('Camera is live', 'InsightFace landmark guidance is unavailable. Python will still verify enrollment.', 'warning');
            try {
                await getDetector();
            } catch (detectorError) {
                console.warn('Browser face guidance fallback failed:', detectorError);
            }
        } finally {
            landmarkRequestPending = false;
        }
    };

    const reportDetections = (detections) => {
        if (processing || enrollmentFailure) return;

        if (detections.length === 1) {
            setCameraState('Face detected', 'detected');
            setStatus(
                'Face detected',
                consent.checked ? 'Hold still, then capture and enroll this face.' : 'Verify documented consent to enable capture.',
                'success',
            );
        } else if (detections.length > 1) {
            setCameraState('Multiple faces', 'warning');
            setStatus('More than one face detected', 'Only the person being registered may appear in the frame.', 'warning');
        } else {
            setCameraState('Searching', 'live');
            setStatus('Looking for one face', 'Center the person in the guide and use even lighting.', 'live');
        }

        updateButtons();
    };

    const detectionLoop = (timestamp) => {
        if (!scanning) return;

        if (useInsightFaceLandmarks &&
            video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA
            && video.currentTime !== lastVideoTime
            && timestamp - lastDetectionAt >= 450
        ) {
            lastVideoTime = video.currentTime;
            lastDetectionAt = timestamp;
            void requestInsightFacePreview();
        } else if (!useInsightFaceLandmarks && (
            video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA
            && video.currentTime !== lastVideoTime
            && timestamp - lastDetectionAt >= 100
        )) {
            lastVideoTime = video.currentTime;
            lastDetectionAt = timestamp;

            try {
                latestDetections = detector.detectForVideo(video, timestamp).detections;
                drawDetections(latestDetections);
                reportDetections(latestDetections);
            } catch (error) {
                console.error('Registration face detection failed:', error);
                latestDetections = [];
                setCameraState('Manual capture', 'warning');
                setStatus('Camera is live', 'Browser face guidance stopped, but you can still enroll. Python will verify the image.', 'warning');
                updateButtons();
                return;
            }
        }

        animationFrame = requestAnimationFrame(detectionLoop);
    };

    const describeCameraError = (error) => {
        if (error.name === 'NotAllowedError' || error.name === 'SecurityError') {
            return ['Camera permission denied', 'Allow camera access in the browser, then try again.'];
        }
        if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
            return ['No camera found', 'Connect or enable a camera, then try again.'];
        }
        if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
            return ['Camera is unavailable', 'Close other applications using the camera, then try again.'];
        }

        return ['Camera could not start', 'Use HTTPS or localhost and check the camera connection.'];
    };

    const stopCamera = ({ updateStatus = true } = {}) => {
        scanning = false;
        cancelAnimationFrame(animationFrame);
        mediaStream?.getTracks().forEach((track) => track.stop());
        mediaStream = undefined;
        video.srcObject = null;
        latestDetections = [];
        latestInsightFacePreview = undefined;
        overlayContext.clearRect(0, 0, overlay.width, overlay.height);
        viewport.classList.remove('has-landmarks');
        viewport.classList.remove('is-camera-active');
        placeholder.hidden = false;
        setCameraState('Camera off', 'idle');
        if (updateStatus && !enrolled) setStatus('Camera stopped', 'Start the camera when you are ready to enroll the face.', 'idle');
        updateButtons();
    };

    const startCamera = async () => {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            setStatus('Secure camera access required', 'Open AttendPro through localhost or HTTPS in a current browser.', 'error');
            return;
        }

        processing = true;
        setCameraState('Starting', 'loading');
        setStatus('Preparing the camera', 'Allow camera access when the browser asks.', 'loading');
        updateButtons();

        try {
            mediaStream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
            });
            video.srcObject = mediaStream;
            await video.play();
            sizeOverlay();
            viewport.classList.add('is-camera-active');
            placeholder.hidden = true;
            scanning = true;
            lastVideoTime = -1;
            lastDetectionAt = 0;

            try {
                if (!useInsightFaceLandmarks) await getDetector();
                setCameraState('Searching', 'live');
                setStatus('Camera is live', useInsightFaceLandmarks
                    ? 'InsightFace will show face landmarks as it finds them.'
                    : 'Center exactly one face inside the guide.', 'live');
                animationFrame = requestAnimationFrame(detectionLoop);
            } catch (detectorError) {
                console.error('Browser face guidance could not start:', detectorError);
                setCameraState('Manual capture', 'warning');
                setStatus('Camera is live', 'Face guidance is unavailable, but you can still enroll. Python will verify the image.', 'warning');
            }
        } catch (error) {
            console.error('Registration camera failed to start:', error);
            mediaStream?.getTracks().forEach((track) => track.stop());
            mediaStream = undefined;
            const [heading, detail] = describeCameraError(error);
            setCameraState('Unavailable', 'error');
            setStatus(heading, detail, 'error');
        } finally {
            processing = false;
            updateButtons();
        }
    };

    const captureFace = async () => {
        if (!video.videoWidth) {
            throw new Error('Start the camera and wait for the live video before capture.');
        }

        // Keep enough resolution for Python's 120px face-size quality check.
        // The local launcher explicitly permits the 15-photo batch (64 MB).
        const { canvas } = guideCanvas(1280, true);

        preview.src = canvas.toDataURL('image/jpeg', 0.9);
        previewWrap.classList.add('has-capture');
        previewEmpty.hidden = true;
        flash.classList.remove('is-active');
        void flash.offsetWidth;
        flash.classList.add('is-active');

        return new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (blob) resolve(blob);
                else reject(new Error('The browser could not prepare the captured image.'));
            }, 'image/jpeg', 0.78);
        });
    };

    const captureEnrollmentFrames = async () => {
        samplePreviewUrls.forEach((url) => URL.revokeObjectURL(url));
        samplePreviewUrls = [];
        sampleGallery.replaceChildren();
        samplePreview.hidden = false;
        sampleCount.textContent = `0 / ${enrollmentSamples}`;
        const frames = [];
        for (let index = 0; index < enrollmentCaptureFrames; index += 1) {
            setStatus(
                'Collecting enrollment samples',
                `${index + 1} / ${enrollmentCaptureFrames}: slowly vary your face angle while staying in even light.`,
                'loading',
            );
            const frame = await captureFace();
            frames.push(frame);
            const previewUrl = URL.createObjectURL(frame);
            samplePreviewUrls.push(previewUrl);
            const thumbnail = document.createElement('img');
            thumbnail.src = previewUrl;
            thumbnail.alt = `Temporary enrollment sample ${index + 1}`;
            sampleGallery.append(thumbnail);
            sampleCount.textContent = `${Math.min(index + 1, enrollmentSamples)} / ${enrollmentSamples}`;
            if (index < enrollmentCaptureFrames - 1) await new Promise((resolve) => window.setTimeout(resolve, 420));
        }
        return frames;
    };

    const enrollFace = async () => {
        if (!createdPerson || !consent.checked) {
            setStatus('Consent verification required', 'Confirm documented consent before enrolling the face.', 'warning');
            return;
        }

        enrollmentFailure = false;
        enrollmentFailureMessage = '';
        processing = true;
        setBusy(enrollButton, true, 'Enrolling…');
        setStatus('Creating facial profile', `Collecting ${enrollmentSamples} high-quality face samples.`, 'loading');
        updateButtons();

        try {
            const images = await captureEnrollmentFrames();
            const payload = new FormData();
            images.forEach((image, index) => payload.append('images[]', image, `registration-face-${index + 1}.jpg`));
            payload.append('institution_id', createdPerson.institution_id);
            payload.append('consent', '1');
            if (retention.value) payload.append('retention_until', retention.value);

            const response = await fetch(registration.dataset.enrollUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': registration.querySelector('[name="_token"]').value,
                },
                credentials: 'same-origin',
                body: payload,
            });
            const body = await readJsonResponse(response);
            enrolled = true;
            finishButton.href = directoryUrl || registration.dataset.directoryUrl;
            successPanel.hidden = false;
            setServiceState(true, 'Face profile synchronized');
            setStatus('Face enrolled and account activated', body.message ?? 'This person can now sign in and use facial attendance.', 'success');
            stopCamera({ updateStatus: false });
        } catch (error) {
            enrollmentFailure = true;
            enrollmentFailureMessage = error.message;
            setStatus('Facial enrollment failed', error.message, 'error');
        } finally {
            processing = false;
            setBusy(enrollButton, false, '');
            updateButtons();
        }
    };

    const showFaceStep = (person) => {
        createdPerson = person;
        detailsStep.hidden = true;
        detailsActions.hidden = true;
        faceStep.hidden = false;
        faceActions.hidden = false;
        detailsProgress.classList.remove('is-active');
        detailsProgress.classList.add('is-complete');
        faceProgress.classList.add('is-active');
        title.textContent = 'Activate account with face enrollment';
        subtitle.textContent = 'Step 2 of 2 · Scan one clear face to activate login and attendance.';
        personName.textContent = person.full_name;
        personId.textContent = person.institution_id;
        personRole.textContent = titleCase(person.type);
        checkPythonService();
        serviceInterval = window.setInterval(checkPythonService, 15000);
        faceStep.scrollIntoView({ block: 'start' });
    };

    registration.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (createdPerson || !registration.reportValidity()) return;

        hideErrors();
        processing = true;
        setBusy(submitButton, true, 'Creating account…');

        try {
            const response = await fetch(registration.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                body: new FormData(registration),
            });
            const body = await readJsonResponse(response);
            directoryUrl = body.data?.redirect_url ?? directoryUrl;
            showFaceStep(body.data.person);
        } catch (error) {
            showErrors(error);
        } finally {
            processing = false;
            setBusy(submitButton, false, '');
        }
    });

    startButton.addEventListener('click', startCamera);
    stopButton.addEventListener('click', () => stopCamera());
    enrollButton.addEventListener('click', enrollFace);
    retakeButton?.addEventListener('click', retakeRegistrationCapture);
    finishButton.addEventListener('click', (event) => {
        if (!enrolled) {
            event.preventDefault();
            if (enrollmentFailure) {
                setStatus('Facial enrollment failed', enrollmentFailureMessage, 'error');
                return;
            }
            setStatus(
                'Finish registration is waiting for enrollment',
                'Keep the face centered and wait for “Face enrolled and account activated” before finishing.',
                'warning',
            );
        }
    });
    consent.addEventListener('change', () => {
        if (useInsightFaceLandmarks && latestInsightFacePreview && !processing) reportInsightFacePreview(latestInsightFacePreview);
        else if (latestDetections.length === 1 && !processing) reportDetections(latestDetections);
        updateButtons();
    });
    video.addEventListener('loadedmetadata', sizeOverlay);
    window.addEventListener('resize', sizeOverlay);
    const leaveRegistration = () => {
        stopCamera({ updateStatus: false });
        if (serviceInterval) window.clearInterval(serviceInterval);
        if (createdPerson) window.location.assign(directoryUrl);
    };

    registration.querySelectorAll('[data-registration-close]').forEach((button) => {
        button.addEventListener('click', leaveRegistration);
    });
    modal.addEventListener('click', (event) => {
        if (event.target === modal) leaveRegistration();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) leaveRegistration();
    }, { capture: true });
    window.addEventListener('pagehide', () => {
        stopCamera({ updateStatus: false });
        if (serviceInterval) window.clearInterval(serviceInterval);
        samplePreviewUrls.forEach((url) => URL.revokeObjectURL(url));
        detector?.close();
    }, { once: true });

    updateButtons();
}
