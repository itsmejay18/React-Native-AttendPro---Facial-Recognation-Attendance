import { FaceDetector, FilesetResolver } from '@mediapipe/tasks-vision';

const scanner = document.querySelector('[data-recognition-scanner]');

if (scanner) {
    const video = scanner.querySelector('[data-camera-video]');
    const overlay = scanner.querySelector('[data-camera-overlay]');
    const overlayContext = overlay.getContext('2d');
    const viewport = scanner.querySelector('[data-camera-viewport]');
    const guideFrame = viewport.querySelector('.attendpro-camera-frame');
    const placeholder = scanner.querySelector('[data-camera-placeholder]');
    const state = scanner.querySelector('.attendpro-camera-state');
    const stateLabel = scanner.querySelector('[data-camera-state-label]');
    const status = scanner.querySelector('[data-scanner-status]');
    const statusTitle = scanner.querySelector('[data-scanner-status-title]');
    const statusDetail = scanner.querySelector('[data-scanner-status-detail]');
    const startButton = scanner.querySelector('[data-camera-start]');
    const captureButton = scanner.querySelector('[data-camera-capture]');
    const stopButton = scanner.querySelector('[data-camera-stop]');
    const attendanceSession = document.querySelector('[data-attendance-session]');
    const capturePreviewWrap = document.querySelector('[data-capture-preview-wrap]');
    const capturePreview = document.querySelector('[data-capture-preview]');
    const captureEmpty = document.querySelector('[data-capture-empty]');
    const faceCount = document.querySelector('[data-result-face-count]');
    const confidence = document.querySelector('[data-result-confidence]');
    const matchConfidence = document.querySelector('[data-result-match-confidence]');
    const captureTime = document.querySelector('[data-result-time]');
    const resultIdentity = document.querySelector('[data-result-identity]');
    const resultAction = document.querySelector('[data-result-action]');
    const flash = scanner.querySelector('[data-camera-flash]');
    const enrollmentSaveButton = scanner.querySelector('[data-enrollment-save]');
    const enrollmentId = scanner.querySelector('[data-enrollment-id]');
    const enrollmentRetention = scanner.querySelector('[data-enrollment-retention]');
    const enrollmentConsent = scanner.querySelector('[data-enrollment-consent]');
    const enrollmentSamplePreview = scanner.querySelector('[data-enrollment-sample-preview]');
    const enrollmentSampleCount = scanner.querySelector('[data-enrollment-sample-count]');
    const enrollmentSampleGallery = scanner.querySelector('[data-enrollment-sample-gallery]');
    const serviceState = document.querySelector('[data-python-service-state]');
    const serviceLabel = document.querySelector('[data-python-service-label]');
    const resultModal = document.querySelector('#recognition-result-modal');
    const resultModalPanel = resultModal?.querySelector('[data-result-modal]');
    const resultModalKicker = resultModal?.querySelector('[data-result-modal-kicker]');
    const resultModalTitle = resultModal?.querySelector('[data-result-modal-title]');
    const resultModalIcon = resultModal?.querySelector('[data-result-modal-icon]');
    const resultModalMessage = resultModal?.querySelector('[data-result-modal-message]');
    const resultModalRetake = resultModal?.querySelector('[data-result-retake]');
    const resultName = resultModal?.querySelector('[data-result-name]');
    const resultRole = resultModal?.querySelector('[data-result-role]');
    const resultDepartment = resultModal?.querySelector('[data-result-department]');
    const resultProgram = resultModal?.querySelector('[data-result-program]');
    const resultYearLevel = resultModal?.querySelector('[data-result-year-level]');
    const resultPosition = resultModal?.querySelector('[data-result-position]');
    const resultEmail = resultModal?.querySelector('[data-result-email]');
    const resultPhone = resultModal?.querySelector('[data-result-phone]');
    const resultProfileStatus = resultModal?.querySelector('[data-result-profile-status]');
    const resultJoinedOn = resultModal?.querySelector('[data-result-joined-on]');
    const resultAttendanceDetail = resultModal?.querySelector('[data-result-attendance-detail]');
    const resultProfile = resultModal?.querySelector('[data-result-profile]');
    const enrollmentMode = scanner.dataset.enrollmentMode === 'true';
    const frameWindow = Math.max(3, Math.min(15, Number.parseInt(scanner.dataset.frameWindow ?? '7', 10) || 7));
    const enrollmentSamples = Math.max(5, Math.min(30, Number.parseInt(scanner.dataset.enrollmentSamples ?? '15', 10) || 15));
    const enrollmentCaptureFrames = Math.max(enrollmentSamples, Math.min(30, Number.parseInt(scanner.dataset.enrollmentCaptureFrames ?? '20', 10) || 20));
    const landmarkPreviewUrl = scanner.dataset.landmarkPreviewUrl;

    let detector;
    let detectorPromise;
    let mediaStream;
    let animationFrame;
    let latestDetections = [];
    let lastVideoTime = -1;
    let lastDetectionAt = 0;
    let scanning = false;
    let capturedBlob;
    let pythonReady = false;
    let processing = false;
    let useInsightFaceLandmarks = false;
    let landmarkRequestPending = false;
    let enrollmentSamplePreviewUrls = [];
    let pendingEnrollmentFrames = [];

    const updateActionButtons = () => {
        captureButton.disabled = processing
            || !scanning
            || !video.videoWidth
            || !pythonReady
            || (!enrollmentMode && !attendanceSession?.value)
            || (enrollmentMode && !enrollmentConsent?.checked);
        if (enrollmentSaveButton) {
            enrollmentSaveButton.disabled = processing
                || !scanning
                || !video.videoWidth
                || !pythonReady
                || !enrollmentConsent?.checked
                || pendingEnrollmentFrames.length < enrollmentSamples;
        }
    };

    const setScannerStatus = (title, detail, statusState = 'idle') => {
        status.dataset.state = statusState;
        statusTitle.textContent = title;
        statusDetail.textContent = detail;
    };

    const setCameraState = (label, cameraState) => {
        state.dataset.cameraState = cameraState;
        stateLabel.textContent = label;
    };

    const titleCase = (value = '') => value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());

    const displayValue = (value) => value || 'Not provided';

    const displayDate = (value) => {
        if (!value) return 'Not provided';

        const date = new Date(`${value}T00:00:00`);
        return Number.isNaN(date.getTime())
            ? value
            : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date);
    };

    const displayDateTime = (value) => {
        if (!value) return 'Not recorded';

        const date = new Date(value);
        return Number.isNaN(date.getTime())
            ? value
            : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
    };

    const setResultModalState = (modalState, kicker, title, icon, message) => {
        if (!resultModalPanel) return;

        resultModalPanel.dataset.resultState = modalState;
        resultModalKicker.textContent = kicker;
        resultModalTitle.textContent = title;
        resultModalIcon.innerHTML = `<i class="ph ${icon}" aria-hidden="true"></i>`;
        resultModalMessage.textContent = message;
    };

    const openResultModal = () => {
        if (!resultModal) return;

        resultModal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        resultModal.querySelector('[data-modal-close]')?.focus({ preventScroll: true });
    };

    const closeResultModal = () => {
        if (!resultModal) return;

        resultModal.classList.remove('is-open');
        if (!document.querySelector('.kit-modal.is-open')) document.body.style.overflow = '';
    };

    const clearAttendanceCapture = () => {
        capturedBlob = undefined;
        if (capturePreview) capturePreview.removeAttribute('src');
        capturePreviewWrap?.classList.remove('has-capture');
        if (captureEmpty) captureEmpty.hidden = false;
        if (faceCount) faceCount.textContent = '—';
        if (confidence) confidence.textContent = '—';
        if (captureTime) captureTime.textContent = '—';
        if (matchConfidence) matchConfidence.textContent = '—';
    };

    const retakeAttendanceScan = () => {
        if (processing) return;
        closeResultModal();
        clearAttendanceCapture();
        if (scanning && mediaStream && video.videoWidth) {
            setScannerStatus('Ready for retake', 'Center one face in the guide, then scan again.', 'live');
            updateActionButtons();
            captureButton.focus({ preventScroll: true });
            scanFace();
        } else {
            setScannerStatus('Ready for retake', 'Start the camera, center one face, then scan again.', 'idle');
            updateActionButtons();
            (scanning ? captureButton : startButton).focus({ preventScroll: true });
        }
    };

    const resetResultModal = () => {
        setResultModalState(
            'processing',
            'VERIFYING CAPTURE',
            enrollmentMode ? 'Preparing facial enrollment…' : 'Matching identity…',
            'ph-circle-notch',
            'The captured image is being checked securely.',
        );

        resultRole.textContent = enrollmentMode ? 'Enrollment capture' : 'Checking profile';
        resultName.textContent = enrollmentMode ? 'Face captured successfully' : 'Matching identity…';
        resultIdentity.textContent = enrollmentMode
            ? 'Close this result and complete the enrollment details below.'
            : 'Please wait while AttendPro verifies this face.';
        resultAction.textContent = enrollmentMode ? 'Ready for enrollment' : 'Processing…';
        resultAttendanceDetail.textContent = enrollmentMode
            ? 'This capture has not recorded attendance.'
            : 'Attendance information will appear after verification.';
        matchConfidence.textContent = '—';
        resultProfile.hidden = true;
    };

    const renderRecognitionResult = (result, recognition, person) => {
        const matched = result.result === 'matched' && person;
        const attendance = result.attendance ?? {};
        const action = result.action ?? result.result ?? 'processed';
        const detail = result.message || 'Attendance information will appear after verification.';

        matchConfidence.textContent = recognition.confidence === undefined
            ? '—'
            : `${Math.round(recognition.confidence * 100)}%`;

        if (!matched) {
            setResultModalState(
                'unknown',
                'NO MATCH FOUND',
                'Face not recognized',
                'ph-user-minus',
                'No enrolled campus profile matched this capture. An authorized staff member can review the event.',
            );
            resultRole.textContent = 'Unknown visitor';
            resultName.textContent = 'No matching profile';
            resultIdentity.textContent = 'Ask an authorized staff member for assistance or enroll this person first.';
            resultAction.textContent = 'Attendance not recorded';
            resultAttendanceDetail.textContent = 'This scan was saved for authorized review.';
            resultProfile.hidden = true;
            return;
        }

        const personType = person.type ?? person.person_type ?? 'person';
        const isStudent = personType === 'student';
        const isEmployee = personType === 'faculty' || personType === 'staff';

        if (!result.attendance) {
            setResultModalState(
                action === 'wrong_location' ? 'error' : 'unknown',
                action === 'wrong_location' ? 'WRONG LOCATION' : 'ATTENDANCE NOT RECORDED',
                action === 'wrong_location' ? 'Wrong attendance session' : 'Attendance not recorded',
                action === 'wrong_location' ? 'ph-map-pin' : 'ph-warning-circle',
                detail,
            );

            resultRole.textContent = titleCase(personType);
            resultName.textContent = person.full_name;
            resultIdentity.textContent = person.institution_id;
            resultDepartment.textContent = displayValue(person.department?.name);
            resultProgram.textContent = displayValue(person.program);
            resultYearLevel.textContent = displayValue(person.year_level);
            resultPosition.textContent = displayValue(person.position);
            resultEmail.textContent = displayValue(person.email);
            resultPhone.textContent = displayValue(person.phone);
            resultProfileStatus.textContent = titleCase(person.status ?? 'active');
            resultJoinedOn.textContent = displayDate(person.joined_on);
            resultProfile.querySelector('[data-profile-row="program"]').hidden = !isStudent;
            resultProfile.querySelector('[data-profile-row="year_level"]').hidden = !isStudent;
            resultProfile.querySelector('[data-profile-row="position"]').hidden = !isEmployee;
            resultProfile.hidden = false;

            resultAction.textContent = 'Attendance not recorded';
            resultAttendanceDetail.textContent = detail;
            return;
        }

        setResultModalState(
            'success',
            'IDENTITY VERIFIED',
            'Attendance recorded',
            'ph-check-circle',
            `${titleCase(action)} completed successfully for ${person.full_name}.`,
        );

        resultRole.textContent = titleCase(personType);
        resultName.textContent = person.full_name;
        resultIdentity.textContent = person.institution_id;
        resultDepartment.textContent = displayValue(person.department?.name);
        resultProgram.textContent = displayValue(person.program);
        resultYearLevel.textContent = displayValue(person.year_level);
        resultPosition.textContent = displayValue(person.position);
        resultEmail.textContent = displayValue(person.email);
        resultPhone.textContent = displayValue(person.phone);
        resultProfileStatus.textContent = titleCase(person.status ?? 'active');
        resultJoinedOn.textContent = displayDate(person.joined_on);
        resultProfile.querySelector('[data-profile-row="program"]').hidden = !isStudent;
        resultProfile.querySelector('[data-profile-row="year_level"]').hidden = !isStudent;
        resultProfile.querySelector('[data-profile-row="position"]').hidden = !isEmployee;
        resultProfile.hidden = false;

        resultAction.textContent = titleCase(action);
        resultAttendanceDetail.textContent = [
            attendance.status ? titleCase(attendance.status) : null,
            action === 'time_out' ? displayDateTime(attendance.time_out) : displayDateTime(attendance.time_in),
        ].filter(Boolean).join(' · ');
    };

    const renderRecognitionError = (message) => {
        setResultModalState(
            'error',
            'SCAN COULD NOT BE COMPLETED',
            'Recognition failed',
            'ph-warning-circle',
            message,
        );
        resultRole.textContent = 'Action required';
        resultName.textContent = 'Please try again';
        resultIdentity.textContent = 'Check the recognition server and camera, then capture a new image.';
        resultAction.textContent = 'Attendance not recorded';
        resultAttendanceDetail.textContent = message;
        resultProfile.hidden = true;
    };

    const getDetector = async () => {
        if (detector) return detector;

        if (!detectorPromise) {
            detectorPromise = (async () => {
                const vision = await FilesetResolver.forVisionTasks(scanner.dataset.wasmBase);
                detector = await FaceDetector.createFromOptions(vision, {
                    baseOptions: {
                        modelAssetPath: scanner.dataset.modelUrl,
                    },
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
            if (!box) return;
            overlayContext.strokeRect(box.originX, box.originY, box.width, box.height);
        });

        overlayContext.shadowBlur = 0;
    };

    const drawInsightFaceLandmarks = (preview) => {
        sizeOverlay();
        overlayContext.clearRect(0, 0, overlay.width, overlay.height);

        const imageWidth = Number(preview.image_width) || overlay.width;
        const imageHeight = Number(preview.image_height) || overlay.height;
        const crop = preview.guideCrop ?? { x: 0, y: 0, width: imageWidth, height: imageHeight };
        const scaleX = Number(crop.width) / imageWidth;
        const scaleY = Number(crop.height) / imageHeight;
        const faces = Array.isArray(preview.faces) ? preview.faces : [];
        const dotColors = ['#25d3f7', '#25d3f7', '#f6c344', '#ff6b57', '#ff6b57'];
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

    const reportInsightFacePreview = (preview) => {
        const count = Number(preview.face_count) || 0;
        const firstFace = preview.faces?.[0];
        faceCount.textContent = String(count);
        confidence.textContent = firstFace ? `${Math.round((Number(firstFace.detection_score) || 0) * 100)}%` : '—';
        if (processing) return;

        if (count === 1) {
            setCameraState('Face landmarks detected', 'detected');
            setScannerStatus('Face landmarks detected', enrollmentMode
                ? 'Only the center guide is eligible. Verify consent, then collect the 15 enrollment samples below.'
                : 'Only the face inside the center guide is eligible. Select Scan Face when ready.', 'success');
        } else if (count > 1) {
            setCameraState('Multiple faces', 'warning');
            setScannerStatus('More than one face detected', 'Keep only one person in view before scanning.', 'warning');
        } else {
            setCameraState('Searching', 'live');
            setScannerStatus('Looking for a face', 'Center your face in the guide while InsightFace searches.', 'live');
        }
        updateActionButtons();
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
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': scanner.dataset.csrfToken },
                credentials: 'same-origin',
                body: form,
            }));
            const preview = response.data ?? {};
            if (!scanning || preview.supported !== true) return;
            preview.guideCrop = frame.crop;
            drawInsightFaceLandmarks(preview);
            reportInsightFacePreview(preview);
        } catch (error) {
            console.warn('InsightFace landmark preview failed:', error);
            useInsightFaceLandmarks = false;
            setCameraState('Camera guidance unavailable', 'warning');
            setScannerStatus('Camera is live', 'InsightFace landmark guidance is unavailable. Python will still verify the final scan.', 'warning');
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
        const count = detections.length;
        faceCount.textContent = String(count);
        confidence.textContent = count
            ? `${Math.round((detections[0].categories[0]?.score ?? 0) * 100)}%`
            : '—';

        if (processing) return;

        if (count === 1) {
            setCameraState('Face detected', 'detected');
            setScannerStatus('Face detected', enrollmentMode
                ? 'Only the center guide is eligible. Verify consent, then collect and save the 15 samples.'
                : 'Only the face inside the center guide is eligible. Select Scan Face when ready.', 'success');
            updateActionButtons();
        } else if (count > 1) {
            setCameraState('Multiple faces', 'warning');
            setScannerStatus('More than one face detected', 'Keep only one person in view before scanning. Python will verify the capture.', 'warning');
            updateActionButtons();
        } else {
            setCameraState('Searching', 'live');
            setScannerStatus('Looking for a face', 'Center your face in the guide. You may scan when ready; Python will perform the final check.', 'live');
            updateActionButtons();
        }
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
                console.error('Face detection failed:', error);
                latestDetections = [];
                setCameraState('Manual scan', 'warning');
                setScannerStatus('Camera is live', 'Browser face guidance stopped, but you can still scan. Python will verify the image.', 'warning');
                updateActionButtons();
                return;
            }
        }

        animationFrame = requestAnimationFrame(detectionLoop);
    };

    const describeCameraError = (error) => {
        if (error.name === 'NotAllowedError' || error.name === 'SecurityError') {
            return ['Camera permission denied', 'Allow camera access in your browser settings, then try again.'];
        }

        if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
            return ['No camera found', 'Connect a camera or enable the built-in camera, then try again.'];
        }

        if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
            return ['Camera is unavailable', 'Another application may be using the camera. Close it and try again.'];
        }

        return ['Scanner could not start', 'Check the camera connection, reload the page, and try again.'];
    };

    const stopCamera = ({ updateStatus = true } = {}) => {
        scanning = false;
        cancelAnimationFrame(animationFrame);
        mediaStream?.getTracks().forEach((track) => track.stop());
        mediaStream = undefined;
        video.srcObject = null;
        latestDetections = [];
        overlayContext.clearRect(0, 0, overlay.width, overlay.height);
        viewport.classList.remove('has-landmarks');
        viewport.classList.remove('is-camera-active');
        placeholder.hidden = false;
        startButton.disabled = false;
        captureButton.disabled = true;
        stopButton.disabled = true;
        setCameraState('Camera off', 'idle');

        if (updateStatus) {
            setScannerStatus('Camera stopped', 'Select Start Camera when you are ready to scan again.', 'idle');
        }
    };

    const startCamera = async () => {
        if (!window.isSecureContext) {
            setCameraState('Secure access required', 'error');
            setScannerStatus('Camera access is blocked', 'Open this site through HTTPS or localhost to use the camera.', 'error');
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setCameraState('Unsupported', 'error');
            setScannerStatus('Camera access is not supported', 'Use a current version of Chrome, Edge, Firefox, or Safari.', 'error');
            return;
        }

        startButton.disabled = true;
        captureButton.disabled = true;
        setCameraState('Starting', 'loading');
        setScannerStatus('Preparing face detector', 'Allow camera access when your browser asks for permission.', 'loading');

        try {
            mediaStream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {
                    facingMode: 'user',
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
            });
            video.srcObject = mediaStream;
            await video.play();
            sizeOverlay();
            viewport.classList.add('is-camera-active');
            placeholder.hidden = true;
            stopButton.disabled = false;
            scanning = true;
            lastVideoTime = -1;
            lastDetectionAt = 0;
            updateActionButtons();

            try {
                if (!useInsightFaceLandmarks) await getDetector();
                setCameraState('Searching', 'live');
                setScannerStatus('Camera is live', useInsightFaceLandmarks
                    ? 'InsightFace will show face landmarks as it finds them.'
                    : 'Center one face in the guide while the scanner searches.', 'live');
                animationFrame = requestAnimationFrame(detectionLoop);
            } catch (detectorError) {
                console.error('Browser face guidance could not start:', detectorError);
                setCameraState('Manual scan', 'warning');
                setScannerStatus('Camera is live', 'Face guidance is unavailable, but you can still scan. Python will verify the image.', 'warning');
                updateActionButtons();
            }
        } catch (error) {
            console.error('Camera scanner failed to start:', error);
            mediaStream?.getTracks().forEach((track) => track.stop());
            const [title, detail] = describeCameraError(error);
            setCameraState('Unavailable', 'error');
            setScannerStatus(title, detail, 'error');
            startButton.disabled = false;
            stopButton.disabled = true;
        }
    };

    const captureFace = async () => {
        if (!video.videoWidth) {
            throw new Error('Start the camera and wait for the live video before scanning.');
        }

        const { canvas: captureCanvas } = guideCanvas(1280, true);

        capturePreview.src = captureCanvas.toDataURL('image/jpeg', 0.9);
        capturePreviewWrap.classList.add('has-capture');
        captureEmpty.hidden = true;
        captureTime.textContent = new Intl.DateTimeFormat(undefined, {
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
        }).format(new Date());

        flash.classList.remove('is-active');
        void flash.offsetWidth;
        flash.classList.add('is-active');
        capturedBlob = await new Promise((resolve, reject) => {
            captureCanvas.toBlob((blob) => {
                if (blob) resolve(blob);
                else reject(new Error('The browser could not prepare the captured image.'));
            }, 'image/jpeg', 0.9);
        });

        return capturedBlob;
    };

    const readJsonResponse = async (response) => {
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message ?? `Request failed with HTTP ${response.status}.`);
        return body;
    };

    const submitFrame = async (url, fields = {}) => {
        if (!capturedBlob) throw new Error('Capture one face before submitting it.');
        const form = new FormData();
        form.append('image', capturedBlob, 'camera-capture.jpg');
        Object.entries(fields).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') form.append(key, value);
        });

        return readJsonResponse(await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': scanner.dataset.csrfToken,
            },
            credentials: 'same-origin',
            body: form,
        }));
    };

    const submitFrames = async (url, frames, fields = {}) => {
        if (!frames.length) throw new Error('Capture camera frames before submitting them.');
        const form = new FormData();
        frames.forEach((frame, index) => form.append('images[]', frame, `camera-frame-${index + 1}.jpg`));
        Object.entries(fields).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') form.append(key, value);
        });

        return readJsonResponse(await fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': scanner.dataset.csrfToken },
            credentials: 'same-origin',
            body: form,
        }));
    };

    const captureFrames = async (count) => {
        const frames = [];
        for (let index = 0; index < count; index += 1) {
            frames.push(await captureFace());
            if (index < count - 1) await new Promise((resolve) => window.setTimeout(resolve, 160));
        }
        return frames;
    };

    const captureEnrollmentFrames = async (count) => {
        enrollmentSamplePreviewUrls.forEach((url) => URL.revokeObjectURL(url));
        enrollmentSamplePreviewUrls = [];
        enrollmentSampleGallery?.replaceChildren();
        if (enrollmentSamplePreview) enrollmentSamplePreview.hidden = false;
        if (enrollmentSampleCount) enrollmentSampleCount.textContent = `0 / ${count}`;

        const frames = [];
        for (let index = 0; index < count; index += 1) {
            setScannerStatus(
                'Collecting enrollment samples',
                `${index + 1} / ${count}: make small left, right, up, and down movements.`,
                'loading',
            );
            const frame = await captureFace();
            frames.push(frame);

            if (enrollmentSampleGallery) {
                const url = URL.createObjectURL(frame);
                enrollmentSamplePreviewUrls.push(url);
                const thumbnail = document.createElement('img');
                thumbnail.src = url;
                thumbnail.alt = `Enrollment sample ${index + 1}`;
                enrollmentSampleGallery.append(thumbnail);
            }
            if (enrollmentSampleCount) enrollmentSampleCount.textContent = `${index + 1} / ${count}`;
            if (index < count - 1) await new Promise((resolve) => window.setTimeout(resolve, 420));
        }
        return frames;
    };

    const setBusy = (button, busy, busyLabel) => {
        if (!button.dataset.label) button.dataset.label = button.innerHTML;
        button.disabled = busy;
        button.innerHTML = busy ? `<i class="ph ph-circle-notch"></i>${busyLabel}` : button.dataset.label;
        if (!busy) updateActionButtons();
    };

    const scanFace = async () => {
        if (!attendanceSession?.value) {
            setScannerStatus('Choose an attendance session', 'Select the class session, room, and time before scanning a student.', 'warning');
            attendanceSession?.focus();
            return;
        }

        processing = true;
        updateActionButtons();
        setBusy(captureButton, true, enrollmentMode ? 'Capturing…' : 'Scanning…');

        try {
            await captureFace();
            resetResultModal();
            openResultModal();

            if (enrollmentMode) {
                setResultModalState(
                    'success',
                    'CAPTURE READY',
                    'Face captured for enrollment',
                    'ph-check-circle',
                    'Close this window, verify consent, then save the facial enrollment below.',
                );
                setScannerStatus('Face captured', 'Verify consent and save the facial enrollment below.', 'success');
                updateActionButtons();
                return;
            }

            setScannerStatus('Verifying identity', `Collecting ${frameWindow} quality frames for a stable match.`, 'loading');
            const frames = await captureFrames(frameWindow);
            const response = await submitFrames(scanner.dataset.recognizeUrl, frames, {
                direction: 'auto',
                schedule_id: attendanceSession.value,
            });
            const result = response.data ?? {};
            const recognition = result.recognition ?? {};
            const person = result.person ?? recognition.candidate;
            renderRecognitionResult(result, recognition, person);

            if (result.result === 'matched' && result.attendance) {
                setScannerStatus('Attendance recorded', `${person?.full_name ?? 'Recognized person'} has been verified successfully.`, 'success');
            } else if (result.result === 'matched') {
                setScannerStatus('Attendance not recorded', result.message || 'This scan does not belong to the active room session.', 'warning');
            } else {
                setScannerStatus('Face not recognized', 'Ask authorized staff for assistance or enroll this profile first.', 'warning');
            }
            window.dispatchEvent(new Event('attendpro:recognition-recorded'));
        } catch (error) {
            setScannerStatus('Recognition failed', error.message, 'error');
            renderRecognitionError(error.message);
            openResultModal();
        } finally {
            processing = false;
            setBusy(captureButton, false, '');
        }
    };

    const collectEnrollmentSamples = async () => {
        const institutionId = enrollmentId?.value.trim();
        if (!institutionId) {
            setScannerStatus('Institution ID required', 'Enter the existing institutional ID for this captured face.', 'warning');
            enrollmentId?.focus();
            return;
        }
        if (!enrollmentConsent?.checked) {
            setScannerStatus('Consent verification required', 'Confirm documented consent before facial enrollment.', 'warning');
            return;
        }

        processing = true;
        updateActionButtons();
        setBusy(captureButton, true, 'Capturing photos…');
        pendingEnrollmentFrames = [];
        setScannerStatus('Capturing replacement photos', `Collecting 0 / ${enrollmentSamples} photos.`, 'loading');
        try {
            pendingEnrollmentFrames = await captureEnrollmentFrames(enrollmentCaptureFrames);
            setScannerStatus(
                '15 photos ready to save',
                'Review the previews, then select Save face replacement to replace the current facial profile.',
                'success',
            );
        } catch (error) {
            pendingEnrollmentFrames = [];
            setScannerStatus('Photo capture failed', error.message, 'error');
        } finally {
            processing = false;
            setBusy(captureButton, false, '');
        }
    };

    const saveEnrollment = async () => {
        const institutionId = enrollmentId?.value.trim();
        if (!institutionId) {
            setScannerStatus('Institution ID required', 'Enter the existing institutional ID for this captured face.', 'warning');
            enrollmentId?.focus();
            return;
        }
        if (!enrollmentConsent?.checked) {
            setScannerStatus('Consent verification required', 'Confirm documented consent before saving the facial enrollment.', 'warning');
            return;
        }
        if (pendingEnrollmentFrames.length < enrollmentSamples) {
            setScannerStatus('Capture 15 photos first', `Capture all ${enrollmentSamples} replacement photos before saving.`, 'warning');
            return;
        }

        processing = true;
        updateActionButtons();
        setBusy(enrollmentSaveButton, true, 'Saving replacement…');
        setScannerStatus('Saving face replacement', 'Laravel is securely replacing the current facial profile.', 'loading');
        try {
            const response = await submitFrames(scanner.dataset.enrollUrl, pendingEnrollmentFrames, {
                institution_id: institutionId,
                consent: '1',
                retention_until: enrollmentRetention?.value,
            });
            const profile = response.data?.profile;
            resultIdentity.textContent = profile?.institution_id ?? institutionId;
            resultAction.textContent = `Enrolled profile v${profile?.version ?? 1}`;
            setScannerStatus(
                enrollmentMode ? 'Face replacement saved' : 'Facial profile enrolled',
                response.message ?? 'The new facial profile is ready for recognition.',
                'success',
            );
            pendingEnrollmentFrames = [];
        } catch (error) {
            setScannerStatus('Enrollment failed', error.message, 'error');
            resultAction.textContent = 'Enrollment not saved';
        } finally {
            processing = false;
            setBusy(enrollmentSaveButton, false, '');
        }
    };

    const updateServiceState = (ready, label, detail = '', service = {}) => {
        pythonReady = ready;
        useInsightFaceLandmarks = ready && service.backend === 'insightface' && Boolean(landmarkPreviewUrl);
        serviceState?.classList.toggle('kit-dashboard-tag-green', ready);
        serviceState?.classList.toggle('kit-dashboard-tag-yellow', !ready);
        if (serviceLabel) serviceLabel.textContent = label;
        if (!ready && detail && !scanning) setScannerStatus('Python service unavailable', detail, 'warning');
        updateActionButtons();
    };

    const checkPythonService = async () => {
        try {
            const response = await readJsonResponse(await fetch(scanner.dataset.pythonStatusUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            }));
            const service = response.data ?? {};
            const ready = service.status === 'ready' && service.configured;
            updateServiceState(
                ready,
                ready ? `Python ready · ${service.profiles_loaded} profiles` : 'Python initializing',
                service.last_error ?? 'Start the Python service and verify its Laravel connection settings.',
                service,
            );
        } catch (error) {
            updateServiceState(false, 'Python offline', error.message);
        }
    };

    startButton.addEventListener('click', startCamera);
    captureButton.addEventListener('click', enrollmentMode ? collectEnrollmentSamples : scanFace);
    enrollmentSaveButton?.addEventListener('click', saveEnrollment);
    enrollmentConsent?.addEventListener('change', updateActionButtons);
    attendanceSession?.addEventListener('change', () => {
        if (!attendanceSession.value && !enrollmentMode) {
            setScannerStatus('Choose an attendance session', 'Select the class session before scanning students.', 'warning');
        } else if (!enrollmentMode && !processing) {
            setScannerStatus('Session selected', 'Start the camera, then scan students for this class session.', 'idle');
        }
        updateActionButtons();
    });
    resultModalRetake?.addEventListener('click', retakeAttendanceScan);
    stopButton.addEventListener('click', () => stopCamera());
    video.addEventListener('loadedmetadata', sizeOverlay);
    window.addEventListener('resize', sizeOverlay);
    window.addEventListener('pagehide', () => {
        stopCamera({ updateStatus: false });
        detector?.close();
        enrollmentSamplePreviewUrls.forEach((url) => URL.revokeObjectURL(url));
    });
    checkPythonService();
    const serviceInterval = window.setInterval(checkPythonService, 15000);
    window.addEventListener('pagehide', () => window.clearInterval(serviceInterval), { once: true });
}

const activityPanel = document.querySelector('[data-recognition-activity]');

if (activityPanel) {
    const badgeClass = (result) => result === 'matched' ? 'success' : 'warning';

    const renderActivity = (events) => {
        activityPanel.replaceChildren();

        if (!events.length) {
            const empty = document.createElement('p');
            empty.className = 'attendpro-empty';
            empty.textContent = 'No recognition activity yet.';
            activityPanel.append(empty);
            return;
        }

        events.forEach((event) => {
            const item = document.createElement('div');
            item.className = 'kit-list-item';
            const meta = document.createElement('div');
            meta.className = 'kit-list-meta';
            const title = document.createElement('strong');
            title.textContent = event.label;
            const detail = document.createElement('span');
            detail.textContent = `${event.terminal} · ${event.captured_human} · ${event.confidence === null ? 'No confidence' : `${event.confidence}%`}`;
            const badge = document.createElement('span');
            badge.className = `kit-badge ${badgeClass(event.result)}`;
            badge.textContent = event.result.charAt(0).toUpperCase() + event.result.slice(1);
            meta.append(title, detail);
            item.append(meta, badge);
            activityPanel.append(item);
        });
    };

    const refreshActivity = async () => {
        if (document.hidden) return;

        try {
            const response = await fetch(activityPanel.dataset.activityUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (response.ok) renderActivity((await response.json()).data ?? []);
        } catch (error) {
            console.debug('Recognition activity refresh unavailable:', error);
        }
    };

    const activityInterval = window.setInterval(refreshActivity, 5000);
    window.addEventListener('attendpro:recognition-recorded', refreshActivity);
    window.addEventListener('pagehide', () => window.clearInterval(activityInterval), { once: true });
}
