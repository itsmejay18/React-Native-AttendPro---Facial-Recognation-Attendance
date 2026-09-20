from __future__ import annotations

import threading
from dataclasses import dataclass

from .errors import RecognitionServiceError
from .model_manager import ModelManager
from .quality import FaceQuality, FaceQualityValidator


MODEL_NAME = "opencv_sface_2021dec"
CAPABILITY = "opencv-sface"
METRIC = "cosine"


@dataclass(frozen=True, slots=True)
class ExtractedFace:
    embedding: list[float]
    detection_score: float
    bounding_box: tuple[int, int, int, int]
    quality: FaceQuality | None = None


class FaceEngine:
    MODEL_NAME = MODEL_NAME
    CAPABILITY = CAPABILITY
    METRIC = METRIC

    def __init__(self, models: ModelManager, detection_score: float = 0.85, minimum_face_ratio: float = 0.12, **quality_options):
        self.models = models
        self.detection_score = detection_score
        self.minimum_face_ratio = minimum_face_ratio
        self._detector = None
        self._recognizer = None
        self._lock = threading.RLock()
        defaults = {
            "min_face_width": 120, "min_face_height": 120, "min_blur_score": 45.0,
            "min_brightness": 45.0, "max_brightness": 210.0, "max_roll": 18.0,
            "max_yaw_proxy": 0.38, "max_pitch_proxy": 0.32,
        }
        defaults.update(quality_options)
        self.quality = FaceQualityValidator(min_face_ratio=minimum_face_ratio, **defaults)

    @property
    def ready(self) -> bool:
        return self._detector is not None and self._recognizer is not None

    def extract(self, image_bytes: bytes, *, enrollment: bool = False, validate_quality: bool = True) -> ExtractedFace:
        import cv2
        import numpy as np

        image = cv2.imdecode(np.frombuffer(image_bytes, dtype=np.uint8), cv2.IMREAD_COLOR)
        if image is None:
            raise RecognitionServiceError("The uploaded camera frame is not a valid image.", code="invalid_image")

        height, width = image.shape[:2]
        if width < 160 or height < 160:
            raise RecognitionServiceError("The camera frame is too small for reliable recognition.", code="image_too_small")

        with self._lock:
            self._ensure_ready(cv2)
            self._detector.setInputSize((width, height))
            _, faces = self._detector.detect(image)

            count = 0 if faces is None else len(faces)
            if count == 0:
                raise RecognitionServiceError("No face was detected in the captured frame.", code="no_face")
            if count > 1:
                raise RecognitionServiceError("More than one face was detected. Only one person may scan at a time.", code="multiple_faces")

            face = faces[0]
            # Enrollment and matching only require one detected face. The
            # comparison score—not blur, lighting, or pose quotas—decides
            # whether the face matches a saved student reference.
            quality = self.quality.validate(image, face) if validate_quality and not enrollment else None
            aligned = self._recognizer.alignCrop(image, face)
            feature = self._recognizer.feature(aligned).flatten().astype(np.float32)
            norm = float(np.linalg.norm(feature))
            if not np.isfinite(norm) or norm <= 0:
                raise RecognitionServiceError("The facial embedding could not be calculated.", code="invalid_embedding")
            feature /= norm

        x, y, box_width, box_height = (int(value) for value in face[:4])
        return ExtractedFace(
            embedding=[float(value) for value in feature],
            detection_score=float(face[-1]),
            bounding_box=(x, y, box_width, box_height),
            quality=quality,
        )

    def _ensure_ready(self, cv2) -> None:
        if self.ready:
            return

        detector_path, recognizer_path = self.models.ensure()
        try:
            self._detector = cv2.FaceDetectorYN.create(
                str(detector_path), "", (320, 320), self.detection_score, 0.3, 5000
            )
            self._recognizer = cv2.FaceRecognizerSF.create(str(recognizer_path), "")
        except Exception as exc:
            raise RecognitionServiceError(
                f"OpenCV could not initialize the recognition models: {exc}",
                code="model_initialization_failed",
                status_code=503,
            ) from exc
